<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$isAdmin = $loggedin && $_SESSION['role'] === 'admin';

// Galeri işlemleri
$message = '';
$error = '';

// Galeri dizini
$gallery_dir = 'uploads/gallery/';

// Galeri fonksiyonları
function getGalleryImages() {
    global $gallery_dir;
    $images = [];
    
    if (is_dir($gallery_dir)) {
        $files = scandir($gallery_dir);
        
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($file != '.' && $file != '..' && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Açıklama dosyasını kontrol et
                $desc_file = $gallery_dir . pathinfo($file, PATHINFO_FILENAME) . '.txt';
                $description = file_exists($desc_file) ? file_get_contents($desc_file) : '';
                
                $images[] = [
                    'file' => $file,
                    'path' => $gallery_dir . $file,
                    'description' => $description,
                    'timestamp' => filemtime($gallery_dir . $file)
                ];
            }
        }
        
        // Yükleme tarihine göre sırala (en yeniden en eskiye)
        usort($images, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    }
    
    return $images;
}

// Resim yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action']) && $_POST['action'] === 'upload_image') {
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        // Dosya kontrolü
        if (!isset($_FILES['image']) || $_FILES['image']['error'] != UPLOAD_ERR_OK) {
            $error = 'Lütfen bir resim dosyası seçin.';
        } else {
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Uzantı kontrolü
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $error = 'Sadece JPG, PNG ve WEBP dosyaları yüklenebilir.';
            } 
            // Boyut kontrolü (maksimum 10MB)
            else if ($file['size'] > 10 * 1024 * 1024) {
                $error = 'Dosya boyutu çok büyük. Maksimum 10MB olabilir.';
            } else {
                // Dizini oluştur (eğer yoksa)
                if (!is_dir($gallery_dir)) {
                    mkdir($gallery_dir, 0777, true);
                }
                
                // Benzersiz dosya adı oluştur
                $newFileName = uniqid() . '_' . time() . '.' . $ext;
                $target_file = $gallery_dir . $newFileName;
                
                // Dosyayı yükle
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    // Açıklama varsa kaydet
                    if (!empty($description)) {
                        $desc_file = $gallery_dir . pathinfo($newFileName, PATHINFO_FILENAME) . '.txt';
                        file_put_contents($desc_file, $description);
                    }
                    
                    $message = 'Resim başarıyla yüklendi.';
                } else {
                    $error = 'Resim yüklenirken bir hata oluştu.';
                }
            }
        }
    }
    
    // Resim silme işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'delete_image' && isset($_POST['file'])) {
        $fileName = $_POST['file'];
        
        // Dosya adının geçerli olduğundan ve güvenli olduğundan emin ol
        if (preg_match('/^[a-zA-Z0-9_]+_\d+\.(jpg|jpeg|png|webp)$/', $fileName)) {
            $file_path = $gallery_dir . $fileName;
            $desc_file = $gallery_dir . pathinfo($fileName, PATHINFO_FILENAME) . '.txt';
            
            // Dosyayı ve açıklama dosyasını sil
            if (file_exists($file_path) && unlink($file_path)) {
                if (file_exists($desc_file)) {
                    unlink($desc_file);
                }
                $message = 'Resim başarıyla silindi.';
            } else {
                $error = 'Resim silinirken bir hata oluştu.';
            }
        } else {
            $error = 'Geçersiz dosya adı.';
        }
    }
}

// Tüm resimleri al
$images = getGalleryImages();

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Galeri</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <div class="gallery-upload-form">
                <h2>Yeni Resim Yükle</h2>
                <form action="gallery.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_image">
                    
                    <div class="form-group">
                        <label for="image">Resim Seçin (JPG, PNG, WEBP)</label>
                        <input type="file" name="image" id="image" required accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Açıklama (Opsiyonel, maksimum 100 karakter)</label>
                        <input type="text" name="description" id="description" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Yükle</button>
                    </div>
                </form>
            </div>
            <hr class="divider">
            <?php endif; ?>
            
            <div class="gallery-container">
                <?php if (empty($images)): ?>
                <div class="no-images">
                    <p>Henüz galeri boş. <?php echo $isAdmin ? 'Yukarıdaki formu kullanarak resim yükleyebilirsiniz.' : ''; ?></p>
                </div>
                <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($images as $image): ?>
                    <div class="gallery-item" data-image="<?php echo htmlspecialchars($image['path']); ?>" data-description="<?php echo htmlspecialchars($image['description']); ?>">
                        <div class="gallery-card">
                            <div class="gallery-image" style="background-image: url('<?php echo htmlspecialchars($image['path']); ?>')"></div>
                            <?php if ($isAdmin): ?>
                            <div class="gallery-admin-actions">
                                <form action="gallery.php" method="post" onsubmit="return confirm('Bu resmi silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($image['file']); ?>">
                                    <button type="submit" class="btn-icon delete-icon" title="Sil">
                                        <i class="trash-icon">🗑️</i>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($image['description'])): ?>
                            <div class="gallery-description">
                                <?php echo htmlspecialchars($image['description']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resim Görüntüleme Modalı -->
<div id="imageModal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <img id="modalImage" src="" alt="Resim">
        <div id="modalDescription" class="modal-description"></div>
    </div>
</div>

<style>
/* Galeri stilleri */
.gallery-upload-form {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.gallery-card {
    background-color: var(--secondary-bg);
    border-radius: 5px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.gallery-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.gallery-image {
    height: 200px;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    cursor: pointer;
}

.gallery-admin-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 5;
}

.btn-icon {
    background: rgba(0, 0, 0, 0.5);
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-icon:hover {
    background-color: var(--danger-color);
}

.gallery-description {
    padding: 10px;
    font-size: 14px;
    color: var(--text-color);
    background-color: rgba(0, 0, 0, 0.2);
}

.no-images {
    text-align: center;
    padding: 50px 20px;
    background-color: var(--secondary-bg);
    border-radius: 5px;
    color: var(--text-secondary);
}

/* Modal stilleri */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    overflow: auto;
    animation: fadeIn 0.3s;
}

.modal-content {
    position: relative;
    margin: auto;
    display: block;
    max-width: 80%;
    max-height: 80vh;
    margin-top: 50px;
}

.modal-close {
    position: absolute;
    top: -30px;
    right: 0;
    color: var(--text-color);
    font-size: 30px;
    font-weight: bold;
    cursor: pointer;
}

#modalImage {
    width: auto;
    max-width: 100%;
    max-height: 70vh;
    display: block;
    margin: 0 auto;
    border-radius: 5px;
}

.modal-description {
    background-color: var(--secondary-bg);
    color: var(--text-color);
    padding: 15px;
    margin-top: 15px;
    border-radius: 5px;
    text-align: center;
}

/* Responsive düzenlemeler */
@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .gallery-image {
        height: 150px;
    }
    
    .modal-content {
        width: 95%;
        max-width: 95%;
    }
}

@media (max-width: 480px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    }
    
    .gallery-image {
        height: 130px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal işlemleri
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    const modalDesc = document.getElementById('modalDescription');
    const closeBtn = document.querySelector('.modal-close');
    
    // Galeri öğelerine tıklama işlevi
    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', function() {
            modal.style.display = 'block';
            modalImg.src = this.dataset.image;
            
            if (this.dataset.description) {
                modalDesc.textContent = this.dataset.description;
                modalDesc.style.display = 'block';
            } else {
                modalDesc.style.display = 'none';
            }
        });
    });
    
    // Modalı kapat
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Dışarı tıklayınca modalı kapat
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // ESC tuşuyla modalı kapat
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>