<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Kullanıcı zaten giriş yapmışsa, kontrol paneline yönlendir
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

// Değişkenleri tanımla ve başlangıç değerlerini ata
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Kayıt başarı mesajı
$registered = isset($_GET['registered']) && $_GET['registered'] == 1;

// Form gönderildiğinde
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // E-posta alanı boş mu kontrol et
    if(empty(trim($_POST["email"]))){
        $email_err = "Lütfen e-posta adresinizi girin.";
    } else{
        $email = trim($_POST["email"]);
    }
    
    // Şifre alanı boş mu kontrol et
    if(empty(trim($_POST["password"]))){
        $password_err = "Lütfen şifrenizi girin.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Giriş bilgilerini doğrula
    if(empty($email_err) && empty($password_err)){
        // Sorguyu hazırla
        $sql = "SELECT id, username, email, password_hash, role FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Parametreleri bağla
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            
            // Parametreleri ayarla
            $param_email = $email;
            
            // Sorguyu çalıştır
            if(mysqli_stmt_execute($stmt)){
                // Sonuçları sakla
                mysqli_stmt_store_result($stmt);
                
                // E-posta kayıtlıysa, şifreyi doğrula
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    // Sonuç değişkenlerini bağla
                    mysqli_stmt_bind_result($stmt, $id, $username, $email, $hashed_password, $role);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Şifre doğruysa, yeni oturum başlat
                            session_regenerate_id(true); // Oturum ID'sini yenile (güvenlik için)
                            
                            // Oturum değişkenlerini sakla
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;
                            
                            // AI erişim durumunu al
                            $ai_access_sql = "SELECT ai_access FROM users WHERE id = ?";
                            if ($ai_stmt = mysqli_prepare($conn, $ai_access_sql)) {
                                mysqli_stmt_bind_param($ai_stmt, "i", $id);
                                if (mysqli_stmt_execute($ai_stmt)) {
                                    mysqli_stmt_bind_result($ai_stmt, $ai_access);
                                    mysqli_stmt_fetch($ai_stmt);
                                    $_SESSION["ai_access"] = $ai_access;
                                }
                                mysqli_stmt_close($ai_stmt);
                            }
                            
                            // Kullanıcıyı kontrol paneline yönlendir
                            header("location: dashboard.php");
                        } else{
                            // Şifre yanlış
                            $login_err = "Geçersiz e-posta veya şifre.";
                        }
                    }
                } else{
                    // E-posta kayıtlı değil
                    $login_err = "Geçersiz e-posta veya şifre.";
                }
            } else{
                echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
            }

            // İfadeyi kapat
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="content-area">
    <h2>Giriş Yap</h2>
    <p>Portal'a erişmek için giriş bilgilerinizi girin.</p>

    <?php if($registered): ?>
    <div class="alert alert-success">
        <p>Kayıt işleminiz başarıyla tamamlandı! Şimdi giriş yapabilirsiniz.</p>
    </div>
    <?php endif; ?>

    <?php if(!empty($login_err)): ?>
    <div class="alert alert-danger"><?php echo $login_err; ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label>E-posta</label>
            <input type="email" name="email" value="<?php echo $email; ?>" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>">
            <?php if(!empty($email_err)): ?>
            <div class="alert alert-danger"><?php echo $email_err; ?></div>
            <?php endif; ?>
        </div>    
        <div class="form-group">
            <label>Şifre</label>
            <input type="password" name="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
            <?php if(!empty($password_err)): ?>
            <div class="alert alert-danger"><?php echo $password_err; ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <button type="submit" class="btn">Giriş Yap</button>
        </div>
        <p>Henüz bir hesabınız yok mu? <a href="register.php">Şimdi Kayıt Olun</a>.</p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>