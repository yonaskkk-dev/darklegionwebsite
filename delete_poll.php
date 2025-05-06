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

// Sadece adminler anketleri silebilir
if ($role !== 'admin') {
    $_SESSION['error'] = "Bu işlemi yapma yetkiniz yok.";
    header("Location: polls.php");
    exit;
}

// Sadece POST istekleri işlenecek
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_poll'])) {
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

// Anketin var olup olmadığını kontrol et
$poll_query = "SELECT * FROM polls WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $poll_query)) {
    mysqli_stmt_bind_param($stmt, "i", $poll_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_fetch_assoc($result)) {
            // Transaction başlat
            mysqli_begin_transaction($conn);
            
            try {
                // Ankete ait tüm oyları sil
                $delete_votes = "DELETE FROM poll_votes WHERE poll_id = ?";
                $stmt_votes = mysqli_prepare($conn, $delete_votes);
                mysqli_stmt_bind_param($stmt_votes, "i", $poll_id);
                mysqli_stmt_execute($stmt_votes);
                
                // Ankete ait tüm yorumları sil
                $delete_comments = "DELETE FROM poll_comments WHERE poll_id = ?";
                $stmt_comments = mysqli_prepare($conn, $delete_comments);
                mysqli_stmt_bind_param($stmt_comments, "i", $poll_id);
                mysqli_stmt_execute($stmt_comments);
                
                // Ankete ait tüm seçenekleri sil
                $delete_options = "DELETE FROM poll_options WHERE poll_id = ?";
                $stmt_options = mysqli_prepare($conn, $delete_options);
                mysqli_stmt_bind_param($stmt_options, "i", $poll_id);
                mysqli_stmt_execute($stmt_options);
                
                // Anketi sil
                $delete_poll = "DELETE FROM polls WHERE id = ?";
                $stmt_poll = mysqli_prepare($conn, $delete_poll);
                mysqli_stmt_bind_param($stmt_poll, "i", $poll_id);
                mysqli_stmt_execute($stmt_poll);
                
                // İşlemi onayla
                mysqli_commit($conn);
                
                $_SESSION['message'] = "Anket ve ilgili tüm veriler başarıyla silindi.";
                header("Location: polls.php");
                exit;
            } catch (Exception $e) {
                // Hata oluşursa işlemi geri al
                mysqli_rollback($conn);
                
                $_SESSION['error'] = "Anket silinirken bir hata oluştu: " . $e->getMessage();
                header("Location: poll_details.php?id=$poll_id");
                exit;
            }
        } else {
            $_SESSION['error'] = "Anket bulunamadı.";
            header("Location: polls.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Veritabanı sorgusu çalıştırılırken bir hata oluştu: " . mysqli_error($conn);
        header("Location: polls.php");
        exit;
    }
    
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error'] = "Veritabanı sorgusu hazırlanırken bir hata oluştu: " . mysqli_error($conn);
    header("Location: polls.php");
    exit;
}
?>