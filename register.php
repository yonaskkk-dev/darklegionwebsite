<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Değişkenleri tanımla ve başlangıç değerlerini ata
$username = $email = $password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = "";

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Kullanıcı adını doğrula
    if (empty(trim($_POST["username"]))) {
        $username_err = "Lütfen bir kullanıcı adı girin.";
    } else {
        // Kullanıcı adının uzunluğunu kontrol et
        if (strlen(trim($_POST["username"])) < 3) {
            $username_err = "Kullanıcı adı en az 3 karakter olmalıdır.";
        } else {
            // Kullanıcı adının zaten var olup olmadığını kontrol et
            $sql = "SELECT id FROM users WHERE username = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $param_username);
                
                $param_username = trim($_POST["username"]);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        $username_err = "Bu kullanıcı adı zaten alınmış.";
                    } else {
                        $username = trim($_POST["username"]);
                    }
                } else {
                    echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // E-posta doğrula
    if (empty(trim($_POST["email"]))) {
        $email_err = "Lütfen bir e-posta adresi girin.";
    } else {
        // E-posta formatını kontrol et
        if (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
            $email_err = "Geçersiz e-posta formatı.";
        } else {
            // E-postanın zaten var olup olmadığını kontrol et
            $sql = "SELECT id FROM users WHERE email = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $param_email);
                
                $param_email = trim($_POST["email"]);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        $email_err = "Bu e-posta adresi zaten kayıtlı.";
                    } else {
                        $email = trim($_POST["email"]);
                    }
                } else {
                    echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Şifreyi doğrula
    if (empty(trim($_POST["password"]))) {
        $password_err = "Lütfen bir şifre girin.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Şifre en az 6 karakter olmalıdır.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Şifre onayını doğrula
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Lütfen şifreyi onaylayın.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Şifreler eşleşmiyor.";
        }
    }
    
    // Herhangi bir hata yoksa, kayıt işlemini gerçekleştir
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // INSERT sorgusunu hazırla
        $sql = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)";
         
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssss", $param_username, $param_email, $param_password, $param_role);
            
            // Parametreleri ayarla
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_role = "uye"; // Varsayılan rol
            
            // Sorguyu çalıştır
            if (mysqli_stmt_execute($stmt)) {
                // Başarılı kayıt, giriş sayfasına yönlendir
                header("location: login.php?registered=1");
            } else {
                echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="content-area">
    <h2>Kayıt Ol</h2>
    <p>Portal hesabı oluşturmak için aşağıdaki formu doldurun.</p>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label>Kullanıcı Adı</label>
            <input type="text" name="username" value="<?php echo $username; ?>" class="<?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>">
            <?php if (!empty($username_err)): ?>
            <div class="alert alert-danger"><?php echo $username_err; ?></div>
            <?php endif; ?>
        </div>    
        <div class="form-group">
            <label>E-posta</label>
            <input type="email" name="email" value="<?php echo $email; ?>" class="<?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>">
            <?php if (!empty($email_err)): ?>
            <div class="alert alert-danger"><?php echo $email_err; ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Şifre</label>
            <input type="password" name="password" class="<?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
            <?php if (!empty($password_err)): ?>
            <div class="alert alert-danger"><?php echo $password_err; ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Şifre Tekrar</label>
            <input type="password" name="confirm_password" class="<?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
            <?php if (!empty($confirm_password_err)): ?>
            <div class="alert alert-danger"><?php echo $confirm_password_err; ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <button type="submit" class="btn">Kayıt Ol</button>
        </div>
        <p>Zaten bir hesabınız var mı? <a href="login.php">Giriş Yap</a>.</p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>