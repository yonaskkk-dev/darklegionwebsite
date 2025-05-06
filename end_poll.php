<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum kontrolü
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['id'];
$role = $_SESSION['role'];

// Sadece POST istekleri işlenecek
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['end_poll'])) {
    header("Location: polls.php");
    exit;
}

// Anket ID'sini kontrol et
$poll_id = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : 0;

if ($poll_id <= 0) {
    $_SESSION['error'] = "Geçersiz anket ID.";
    header("Location: polls.php");
    exit;
}

// Anket bilgilerini getir
$poll_query = "SELECT * FROM polls WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $poll_query)) {
    mysqli_stmt_bind_param($stmt, "i", $poll_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Sadece kendi anketini veya admin ise herhangi bir anketi sonlandırabilir
            if ($row['user_id'] !== $current_user_id && $role !== 'admin') {
                $_SESSION['error'] = "Bu anketi sonlandırma yetkiniz yok.";
                header("Location: polls.php");
                exit;
            }
            
            // Anket zaten sonlandırılmış mı kontrol et
            if ($row['status'] === 'ended') {
                $_SESSION['message'] = "Anket zaten sonlandırılmış.";
                header("Location: poll_details.php?id=$poll_id");
                exit;
            }
            
            // Anketi sonlandır
            $update_query = "UPDATE polls SET status = 'ended', updated_at = NOW() WHERE id = ?";
            if ($update_stmt = mysqli_prepare($conn, $update_query)) {
                mysqli_stmt_bind_param($update_stmt, "i", $poll_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $_SESSION['message'] = "Anket başarıyla sonlandırıldı.";
                } else {
                    $_SESSION['error'] = "Anket sonlandırılırken bir hata oluştu: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($update_stmt);
            }
        } else {
            $_SESSION['error'] = "Anket bulunamadı.";
        }
    } else {
        $_SESSION['error'] = "Veritabanı sorgusu çalıştırılırken bir hata oluştu: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error'] = "Veritabanı sorgusu hazırlanırken bir hata oluştu: " . mysqli_error($conn);
}

// Anket detay sayfasına yönlendir
header("Location: poll_details.php?id=$poll_id");
exit;
?>