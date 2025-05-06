<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
yetkiKontrol();

// Görünüm parametresini al
$view = isset($_GET['view']) ? $_GET['view'] : 'default';

// Dosya silme işlemi
if(isset($_GET['delete_file']) && $_SESSION['role'] === 'admin') {
    $file_id = $_GET['delete_file'];
    
    // Dosya bilgilerini veritabanından al
    $sql = "SELECT filename FROM uploads WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $file_id);
        if(mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $filename);
                if(mysqli_stmt_fetch($stmt)) {
                    $file_path = "uploads/" . $filename;
                    
                    // Dosyayı fiziksel olarak sil
                    if(file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    // Veritabanından sil
                    $delete_sql = "DELETE FROM uploads WHERE id = ?";
                    if($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                        mysqli_stmt_bind_param($delete_stmt, "i", $file_id);
                        mysqli_stmt_execute($delete_stmt);
                        mysqli_stmt_close($delete_stmt);
                        $delete_success = true;
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Kullanıcının yüklediği dosyaları al
$uploads = array();
$sql = "SELECT u.id, u.filename, u.file_url, u.uploaded_at, us.username 
        FROM uploads u 
        LEFT JOIN users us ON u.user_id = us.id";

// Sadece kullanıcının kendi dosyalarını görüntüle (admin olmadığı sürece)
if($_SESSION['role'] !== 'admin') {
    $sql .= " WHERE u.user_id = ?";
}

$sql .= " ORDER BY u.uploaded_at DESC";

if($stmt = mysqli_prepare($conn, $sql)) {
    // Admin değilse, sadece kendi dosyalarını görüntüle
    if($_SESSION['role'] !== 'admin') {
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while($row = mysqli_fetch_assoc($result)) {
            $uploads[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-area">
    <h1>Kontrol Paneli</h1>
    
    <?php if(isset($delete_success)): ?>
    <div class="alert alert-success">
        <p>Dosya başarıyla silindi.</p>
    </div>
    <?php endif; ?>
    
    <?php if(isset($_GET['error']) && $_GET['error'] == 'ai_access'): ?>
    <div class="alert alert-danger">
        <p>AI Sohbet bölümüne erişim izniniz yok. Lütfen yönetici ile iletişime geçin.</p>
    </div>
    <?php endif; ?>
    
    <?php if($view === 'users' && $_SESSION['role'] === 'admin'): ?>
    <!-- Kullanıcı Yönetimi (sadece admin) -->
    <h2>Kullanıcı Yönetimi</h2>
    <p>Bu bölüm daha sonra geliştirilecek.</p>
    
    <?php else: ?>
    <!-- Dosya Yönetimi -->
    <h2>Dosya Yönetimi</h2>
    
    <?php if(empty($uploads)): ?>
    <div class="alert alert-warning">
        <p>Henüz yüklenmiş dosya bulunmuyor. <a href="upload.php">Dosya yüklemek için tıklayın</a>.</p>
    </div>
    <?php else: ?>
    <div class="file-list">
        <table>
            <thead>
                <tr>
                    <th>Dosya Adı</th>
                    <th>Yükleyen</th>
                    <th>Yükleme Tarihi</th>
                    <th>Paylaşım Linki</th>
                    <?php if($_SESSION['role'] === 'admin'): ?>
                    <th>İşlemler</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($uploads as $file): ?>
                <tr>
                    <td><?php echo htmlspecialchars($file['filename']); ?></td>
                    <td><?php echo htmlspecialchars($file['username']); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($file['uploaded_at'])); ?></td>
                    <td class="file-url"><a href="<?php echo htmlspecialchars($file['file_url']); ?>" target="_blank">
                        <?php echo htmlspecialchars($file['file_url']); ?>
                    </a></td>
                    <?php if($_SESSION['role'] === 'admin'): ?>
                    <td>
                        <a href="dashboard.php?delete_file=<?php echo $file['id']; ?>" 
                           onclick="return confirm('Bu dosyayı silmek istediğinize emin misiniz?');" 
                           class="btn btn-danger">Sil</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="alert alert-info">
        <p>Yeni dosya yüklemek için <a href="upload.php">Dosya Yükleme</a> sayfasına gidin.</p>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>