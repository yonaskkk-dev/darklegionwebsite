<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et (ama herkes görüntüleyebilir)
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$user_id = $loggedin ? $_SESSION['id'] : 0;
$username = $loggedin ? $_SESSION['username'] : 'Misafir';

// Anılar işlemleri
$message = '';
$error = '';

// Anılar dizinleri
$memories_dir = 'uploads/memories/';
$allowed_image_types = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$allowed_video_types = ['mp4', 'webm', 'ogg'];

// Anılar fonksiyonları
function loadMemories() {
    $jsonFile = 'data/memories.json';
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        return json_decode($jsonData, true) ?: [];
    }
    return [];
}

function saveMemories($memories) {
    $jsonFile = 'data/memories.json';
    $jsonData = json_encode($memories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($jsonFile, $jsonData);
}

// Anı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedin) {
    if (isset($_POST['action']) && $_POST['action'] === 'add_memory') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $location = trim($_POST['location']);
        $date = trim($_POST['date']);
        $memory_type = $_POST['memory_type']; // 'image' veya 'video'
        
        if (empty($title)) {
            $error = 'Lütfen bir başlık girin.';
        } else if (!isset($_FILES['memory_file']) || $_FILES['memory_file']['error'] == UPLOAD_ERR_NO_FILE) {
            $error = 'Lütfen bir dosya seçin.';
        } else {
            $file = $_FILES['memory_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Dosya türü kontrolü
            $isValidFile = false;
            if ($memory_type === 'image' && in_array($ext, $allowed_image_types)) {
                $isValidFile = true;
            } else if ($memory_type === 'video' && in_array($ext, $allowed_video_types)) {
                $isValidFile = true;
            }
            
            if (!$isValidFile) {
                $error = 'Geçersiz dosya türü. Desteklenen resim formatları: ' . implode(', ', $allowed_image_types) . 
                         '. Desteklenen video formatları: ' . implode(', ', $allowed_video_types) . '.';
            } 
            // Boyut kontrolü (maksimum 100MB)
            else if ($file['size'] > 100 * 1024 * 1024) {
                $error = 'Dosya boyutu çok büyük. Maksimum 100MB olabilir.';
            } else {
                // Dizini oluştur (eğer yoksa)
                if (!is_dir($memories_dir)) {
                    mkdir($memories_dir, 0777, true);
                }
                
                // Benzersiz dosya adı oluştur
                $newFileName = uniqid() . '_' . time() . '.' . $ext;
                $target_file = $memories_dir . $newFileName;
                
                // Dosyayı yükle
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    // Anıyı JSON'a ekle
                    $memories = loadMemories();
                    
                    // Yeni anı
                    $newMemory = [
                        'id' => time(),
                        'user_id' => $user_id,
                        'username' => $username,
                        'title' => $title,
                        'description' => $description,
                        'location' => $location,
                        'date' => $date,
                        'memory_type' => $memory_type,
                        'file_name' => $newFileName,
                        'file_path' => $target_file,
                        'likes' => 0,
                        'liked_by' => [],
                        'comments' => [],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Anıyı ekle
                    $memories[] = $newMemory;
                    
                    // JSON'a kaydet
                    if (saveMemories($memories)) {
                        $message = 'Anınız başarıyla eklendi.';
                    } else {
                        unlink($target_file); // Dosyayı sil (JSON kaydetme başarısız olursa)
                        $error = 'Anı kaydedilirken bir hata oluştu.';
                    }
                } else {
                    $error = 'Dosya yüklenirken bir hata oluştu.';
                }
            }
        }
    }
    
    // Anı silme işlemi (sadece kendi anısını veya admin)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_memory' && isset($_POST['memory_id'])) {
        $memoryId = (int)$_POST['memory_id'];
        $memories = loadMemories();
        $memoryFound = false;
        
        foreach ($memories as $key => $memory) {
            if ($memory['id'] === $memoryId) {
                // Kullanıcı kendi anısını veya admin tüm anıları silebilir
                if ($memory['user_id'] === $user_id || $_SESSION['role'] === 'admin') {
                    // Dosyayı sil
                    if (file_exists($memory['file_path'])) {
                        unlink($memory['file_path']);
                    }
                    
                    // Anıyı JSON'dan kaldır
                    unset($memories[$key]);
                    $memoryFound = true;
                } else {
                    $error = 'Bu anıyı silme yetkiniz yok.';
                }
                break;
            }
        }
        
        if ($memoryFound) {
            // Diziyi yeniden indeksle
            $memories = array_values($memories);
            
            // JSON'a kaydet
            if (saveMemories($memories)) {
                $message = 'Anı başarıyla silindi.';
            } else {
                $error = 'Anı silinirken bir hata oluştu.';
            }
        } else {
            $error = 'Anı bulunamadı.';
        }
    }
    
    // Beğenme/beğenmeme işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_like' && isset($_POST['memory_id'])) {
        $memoryId = (int)$_POST['memory_id'];
        $memories = loadMemories();
        $memoryUpdated = false;
        
        foreach ($memories as $key => $memory) {
            if ($memory['id'] === $memoryId) {
                // Kullanıcı daha önce beğenmiş mi kontrol et
                $alreadyLiked = in_array($user_id, $memory['liked_by']);
                
                if ($alreadyLiked) {
                    // Beğeniyi kaldır
                    $memories[$key]['likes']--;
                    $memories[$key]['liked_by'] = array_diff($memory['liked_by'], [$user_id]);
                } else {
                    // Beğeni ekle
                    $memories[$key]['likes']++;
                    $memories[$key]['liked_by'][] = $user_id;
                }
                
                $memoryUpdated = true;
                break;
            }
        }
        
        if ($memoryUpdated) {
            // JSON'a kaydet
            if (saveMemories($memories)) {
                $message = $alreadyLiked ? 'Beğeni kaldırıldı.' : 'Anı beğenildi.';
            } else {
                $error = 'Beğeni işlemi sırasında bir hata oluştu.';
            }
        } else {
            $error = 'Anı bulunamadı.';
        }
    }
    
    // Yorum ekleme işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'add_comment' && isset($_POST['memory_id']) && isset($_POST['comment'])) {
        $memoryId = (int)$_POST['memory_id'];
        $comment = trim($_POST['comment']);
        
        if (empty($comment)) {
            $error = 'Boş yorum eklenemez.';
        } else {
            $memories = loadMemories();
            $memoryUpdated = false;
            
            foreach ($memories as $key => $memory) {
                if ($memory['id'] === $memoryId) {
                    // Yeni yorumu ekle
                    $memories[$key]['comments'][] = [
                        'user_id' => $user_id,
                        'username' => $username,
                        'comment' => $comment,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $memoryUpdated = true;
                    break;
                }
            }
            
            if ($memoryUpdated) {
                // JSON'a kaydet
                if (saveMemories($memories)) {
                    $message = 'Yorumunuz eklendi.';
                } else {
                    $error = 'Yorum eklenirken bir hata oluştu.';
                }
            } else {
                $error = 'Anı bulunamadı.';
            }
        }
    }
}

// Tüm anıları yükle
$memories = loadMemories();

// Anıları tarihe göre sırala (en yeniden en eskiye)
usort($memories, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Anılar</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($loggedin): ?>
            <div class="memory-upload-form">
                <h2>Yeni Anı Ekle</h2>
                <form action="memories.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_memory">
                    
                    <div class="form-group">
                        <label for="memory_type">Anı Türü</label>
                        <select name="memory_type" id="memory_type" required>
                            <option value="image">Fotoğraf</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="memory_file">Dosya Seçin</label>
                        <input type="file" name="memory_file" id="memory_file" required>
                        <div class="file-info" id="fileInfo">
                            <p>Desteklenen resim formatları: JPG, PNG, WEBP, GIF</p>
                            <p>Desteklenen video formatları: MP4, WEBM, OGG</p>
                            <p>Maksimum dosya boyutu: 100MB</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Başlık</label>
                        <input type="text" name="title" id="title" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Açıklama</label>
                        <textarea name="description" id="description" rows="3" maxlength="500"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="location">Konum (İsteğe Bağlı)</label>
                            <input type="text" name="location" id="location" maxlength="100">
                        </div>
                        
                        <div class="form-group half">
                            <label for="date">Tarih (İsteğe Bağlı)</label>
                            <input type="date" name="date" id="date">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Anıyı Paylaş</button>
                    </div>
                </form>
            </div>
            <hr class="divider">
            <?php else: ?>
            <div class="alert alert-info">
                <p>Anı paylaşmak için lütfen <a href="login.php">giriş yapın</a> veya <a href="register.php">kayıt olun</a>.</p>
            </div>
            <?php endif; ?>
            
            <div class="memories-container">
                <h2>Tüm Anılar</h2>
                
                <?php if (empty($memories)): ?>
                <div class="no-memories">
                    <p>Henüz paylaşılmış anı bulunmuyor.</p>
                </div>
                <?php else: ?>
                <div class="memories-list">
                    <?php foreach ($memories as $memory): ?>
                    <div class="memory-card" id="memory-<?php echo $memory['id']; ?>">
                        <div class="memory-header">
                            <div class="memory-user">
                                <span class="username"><?php echo htmlspecialchars($memory['username']); ?></span>
                                <?php if (!empty($memory['location'])): ?>
                                <span class="location">📍 <?php echo htmlspecialchars($memory['location']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="memory-date">
                                <?php if (!empty($memory['date'])): ?>
                                <span class="event-date"><?php echo date('d.m.Y', strtotime($memory['date'])); ?></span>
                                <?php endif; ?>
                                <span class="post-date"><?php echo date('d.m.Y H:i', strtotime($memory['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="memory-title">
                            <h3><?php echo htmlspecialchars($memory['title']); ?></h3>
                        </div>
                        
                        <div class="memory-content">
                            <?php if ($memory['memory_type'] === 'image'): ?>
                            <div class="memory-image">
                                <img src="<?php echo htmlspecialchars($memory['file_path']); ?>" alt="<?php echo htmlspecialchars($memory['title']); ?>" loading="lazy" class="memory-media viewable-image">
                            </div>
                            <?php else: ?>
                            <div class="memory-video">
                                <video controls class="memory-media">
                                    <source src="<?php echo htmlspecialchars($memory['file_path']); ?>" type="video/<?php echo pathinfo($memory['file_name'], PATHINFO_EXTENSION); ?>">
                                    Tarayıcınız video elementini desteklemiyor.
                                </video>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($memory['description'])): ?>
                            <div class="memory-description">
                                <p><?php echo nl2br(htmlspecialchars($memory['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="memory-actions">
                            <?php if ($loggedin): ?>
                            <div class="like-action">
                                <form action="memories.php" method="post" class="like-form">
                                    <input type="hidden" name="action" value="toggle_like">
                                    <input type="hidden" name="memory_id" value="<?php echo $memory['id']; ?>">
                                    <button type="submit" class="btn-like <?php echo in_array($user_id, $memory['liked_by']) ? 'liked' : ''; ?>">
                                        <?php echo in_array($user_id, $memory['liked_by']) ? '❤️' : '🖤'; ?> 
                                        <span class="like-count"><?php echo $memory['likes']; ?></span>
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="like-count-display">
                                🖤 <span class="like-count"><?php echo $memory['likes']; ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($loggedin && ($memory['user_id'] === $user_id || $_SESSION['role'] === 'admin')): ?>
                            <div class="delete-action">
                                <form action="memories.php" method="post" onsubmit="return confirm('Bu anıyı silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete_memory">
                                    <input type="hidden" name="memory_id" value="<?php echo $memory['id']; ?>">
                                    <button type="submit" class="btn-delete">🗑️ Sil</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Yorumlar Bölümü -->
                        <div class="memory-comments">
                            <h4>Yorumlar (<?php echo count($memory['comments']); ?>)</h4>
                            
                            <?php if ($loggedin): ?>
                            <div class="comment-form">
                                <form action="memories.php" method="post">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="memory_id" value="<?php echo $memory['id']; ?>">
                                    <div class="form-group">
                                        <textarea name="comment" placeholder="Yorumunuzu yazın..." required maxlength="250"></textarea>
                                        <button type="submit" class="btn-comment">Gönder</button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($memory['comments'])): ?>
                            <div class="comments-list">
                                <?php foreach ($memory['comments'] as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <span class="comment-user"><?php echo htmlspecialchars($comment['username']); ?></span>
                                        <span class="comment-date"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="no-comments">
                                <p>Henüz yorum yapılmamış.</p>
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
        <img id="modalImage" src="" alt="Büyük Görüntü">
    </div>
</div>

<style>
/* Anılar Sayfası Stilleri */
.memory-upload-form {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-group.half {
    flex: 1;
}

.file-info {
    margin-top: 10px;
    font-size: 12px;
    color: var(--text-secondary);
}

.memories-list {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-top: 20px;
}

.memory-card {
    background-color: var(--secondary-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
}

.memory-card:hover {
    transform: translateY(-5px);
}

.memory-header {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    background-color: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid var(--border-color);
}

.memory-user {
    display: flex;
    flex-direction: column;
}

.memory-user .username {
    font-weight: bold;
    color: var(--accent-color);
}

.memory-user .location {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 3px;
}

.memory-date {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    font-size: 12px;
    color: var(--text-secondary);
}

.memory-title {
    padding: 15px 15px 0;
}

.memory-title h3 {
    margin: 0;
    color: var(--text-color);
}

.memory-content {
    padding: 15px;
}

.memory-media {
    width: 100%;
    max-height: 500px;
    object-fit: contain;
    border-radius: 5px;
    cursor: pointer;
}

.memory-description {
    margin-top: 15px;
    padding: 10px;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 5px;
}

.memory-actions {
    display: flex;
    justify-content: space-between;
    padding: 0 15px 15px;
    border-bottom: 1px solid var(--border-color);
}

.btn-like {
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 16px;
    color: var(--text-color);
    padding: 5px 10px;
    border-radius: 20px;
    transition: all 0.3s;
}

.btn-like:hover {
    background-color: rgba(231, 76, 60, 0.1);
}

.btn-like.liked {
    color: var(--danger-color);
}

.btn-delete {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-color);
    padding: 5px 10px;
    border-radius: 20px;
    transition: all 0.3s;
}

.btn-delete:hover {
    background-color: rgba(231, 76, 60, 0.2);
    color: var(--danger-color);
}

.memory-comments {
    padding: 15px;
}

.memory-comments h4 {
    margin: 0 0 15px;
    font-size: 18px;
    color: var(--text-color);
}

.comment-form {
    margin-bottom: 20px;
}

.comment-form textarea {
    width: 100%;
    padding: 10px;
    border-radius: 20px;
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    color: var(--text-color);
    resize: none;
    height: 80px;
}

.btn-comment {
    background-color: var(--accent-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    margin-top: 10px;
    float: right;
}

.comments-list {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 10px;
}

.comment {
    margin-bottom: 15px;
    padding: 10px;
    background-color: rgba(0, 0, 0, 0.15);
    border-radius: 15px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.comment-user {
    font-weight: bold;
    color: var(--accent-color);
}

.comment-date {
    font-size: 11px;
    color: var(--text-secondary);
}

.no-comments, .no-memories {
    padding: 20px;
    text-align: center;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 5px;
    color: var(--text-secondary);
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .memory-header, .comment-header {
        flex-direction: column;
    }
    
    .memory-date, .memory-user {
        align-items: flex-start;
        margin-bottom: 5px;
    }
    
    .memory-actions {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Resim görüntüleme modalı
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    const closeBtn = document.querySelector('.modal-close');
    
    // Resim tıklama olayı
    document.querySelectorAll('.viewable-image').forEach(img => {
        img.addEventListener('click', function() {
            modal.style.display = 'block';
            modalImg.src = this.src;
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
    
    // Dosya seçildiğinde bilgi göster
    const fileInput = document.getElementById('memory_file');
    const fileInfo = document.getElementById('fileInfo');
    const memoryType = document.getElementById('memory_type');
    
    if (fileInput && fileInfo && memoryType) {
        memoryType.addEventListener('change', function() {
            const selectedType = this.value;
            
            if (selectedType === 'image') {
                fileInfo.innerHTML = `
                    <p>Desteklenen resim formatları: JPG, PNG, WEBP, GIF</p>
                    <p>Maksimum dosya boyutu: 100MB</p>
                `;
                fileInput.accept = '.jpg,.jpeg,.png,.webp,.gif';
            } else {
                fileInfo.innerHTML = `
                    <p>Desteklenen video formatları: MP4, WEBM, OGG</p>
                    <p>Maksimum dosya boyutu: 100MB</p>
                `;
                fileInput.accept = '.mp4,.webm,.ogg';
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / (1024 * 1024)).toFixed(2); // MB cinsinden
                
                fileInfo.innerHTML += `<p class="selected-file">Seçilen dosya: ${fileName} (${fileSize} MB)</p>`;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>