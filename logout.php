<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum değişkenlerini temizle
$_SESSION = array();

// Oturum çerezini sil
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Oturumu sonlandır
session_destroy();

// Ana sayfaya yönlendir
header("location: index.php");
exit;
?>