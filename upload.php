<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
yetkiKontrol();

// Değişkenleri tanımla
$upload_err = $upload_success = "";
$upload_link = "";

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Dosya seçilmiş mi kontrol et
    if (!isset($_FILES["file"]) || $_FILES["file"]["error"] == UPLOAD_ERR_NO_FILE) {
        $upload_err = "Lütfen bir dosya seçin.";
    } else {
        $file = $_FILES["file"];
        
        // Dosya boyutunu kontrol et (50MB)
        $max_size = 50 * 1024 * 1024; // 50MB (byte cinsinden)
        if ($file["size"] > $max_size) {
            $upload_err = "Dosya boyutu çok büyük. En fazla 50MB olabilir.";
        } 
        // Dosya türünü kontrol et
        else if (!izinliDosyaMi($file["name"])) {
            $upload_err = "Sadece PDF, PNG, JPG, JPEG, DOCX ve ZIP dosyaları yüklenebilir.";
        } else {
            // Dosya yükleme işlemi
            $target_dir = "uploads/";
            $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            $unique_file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $unique_file_name;
            
            // Dosyayı taşı
            if (move_uploaded_file($file["tmp_name"], $target_file)) {
                // Veritabanına dosya bilgilerini ekle
                $original_filename = temizle($file["name"]);
                $file_url = "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "s" : "") . 
                           "://" . $_SERVER['HTTP_HOST'] . 
                           dirname($_SERVER['PHP_SELF']) . "/" . $target_file;
                
                $sql = "INSERT INTO uploads (user_id, filename, file_url) VALUES (?, ?, ?)";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "iss", $_SESSION["id"], $unique_file_name, $file_url);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $upload_success = "Dosya başarıyla yüklendi.";
                        $upload_link = $file_url;
                    } else {
                        $upload_err = "Dosya yüklenirken bir hata oluştu: " . mysqli_error($conn);
                        // Yüklenen dosyayı sil (veritabanına ekleyemedik)
                        unlink($target_file);
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            } else {
                $upload_err = "Dosya yüklenirken bir hata oluştu.";
            }
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-area">
    <h1>Dosya Yükleme</h1>
    
    <?php if(!empty($upload_err)): ?>
    <div class="alert alert-danger">
        <p><?php echo $upload_err; ?></p>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($upload_success)): ?>
    <div class="alert alert-success">
        <p><?php echo $upload_success; ?></p>
        <p><strong>Paylaşım Linki:</strong> <a href="<?php echo $upload_link; ?>" target="_blank"><?php echo $upload_link; ?></a></p>
        <p>Bu link ile dosyanızı paylaşabilirsiniz.</p>
    </div>
    <?php endif; ?>
    
    <div class="upload-form">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Dosya Seçin</label>
                <input type="file" name="file" class="form-control">
                <div class="info">
                    <p>İzin verilen formatlar: PDF, PNG, JPG, JPEG, DOCX, ZIP</p>
                    <p>Maksimum dosya boyutu: 50MB</p>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Dosyayı Yükle</button>
            </div>
        </form>
    </div>
    
    <div class="alert alert-info">
        <p>Yüklenen tüm dosyaları görmek için <a href="dashboard.php">Kontrol Paneli</a>'ne gidin.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>