<?php 
// Oturum durumunu kontrol et
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$role = $loggedin ? $_SESSION['role'] : 'misafir';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Legion - Web Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container site-header">
            <a href="index.php" class="logo">Dark Legion</a>
            <ul class="navbar">
                <li><a href="index.php">Ana Sayfa</a></li>
                <?php if ($loggedin): ?>
                    <li><a href="dashboard.php">Kontrol Paneli</a></li>
                    <li><a href="upload.php">Dosya Yükle</a></li>
                    <li><a href="logout.php">Çıkış Yap</a></li>
                <?php else: ?>
                    <li><a href="login.php">Giriş Yap</a></li>
                    <li><a href="register.php">Kayıt Ol</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>
    <div class="container main-content">