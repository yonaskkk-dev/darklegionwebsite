<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Ana sayfa tüm kullanıcılar için erişilebilir
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$role = $loggedin ? $_SESSION['role'] : 'misafir';

include 'includes/header.php';
?>

<div class="content-area">
    <h1>Dark Legion Web Portal'a Hoş Geldiniz</h1>
    <p>Bu portal, modüler ve genişletilebilir yapısıyla ihtiyaçlarınıza uygun olarak oluşturulmuştur.</p>
    
    <div class="alert alert-success">
        <p><strong>Kullanıcı Durumu:</strong> 
        <?php
        if ($loggedin) {
            echo 'Giriş yapmış durumdasınız. <a href="dashboard.php">Kontrol Paneli</a>\'ne gidebilirsiniz.';
        } else {
            echo 'Henüz giriş yapmadınız. Portal\'ın tüm özelliklerinden yararlanmak için lütfen <a href="login.php">giriş yapın</a> veya <a href="register.php">kayıt olun</a>.';
        }
        ?>
        </p>
    </div>
    
    <h2>Portal Özellikleri</h2>
    <ul>
        <li>Güvenli Kullanıcı Yönetimi</li>
        <li>Dosya Paylaşım Sistemi</li>
        <li>Koyu Tema Arayüzü</li>
        <li>Responsive Tasarım</li>
        <li>Ve daha fazlası...</li>
    </ul>
    
    <?php if ($loggedin): ?>
    <div class="alert alert-warning">
        <p><strong>Bilgilendirme:</strong> Dosya yüklemek için <a href="upload.php">Dosya Yükle</a> sayfasını ziyaret edebilirsiniz.</p>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>