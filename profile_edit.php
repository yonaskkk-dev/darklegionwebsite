<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
yetkiKontrol();

// Kullanıcı bilgilerini al
$user_id = $_SESSION['id'];
$message = '';
$error = '';

// Kullanıcı verilerini veritabanından al
$sql = "SELECT * FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user_data = mysqli_fetch_assoc($result)) {
            // Mevcut bilgileri değişkenlere atayalım
            $profile_url = $user_data['profile_url'] ?? '';
            $bio = $user_data['bio'] ?? '';
            $interests = $user_data['interests'] ?? '';
            $location = $user_data['location'] ?? '';
            $social_links = json_decode($user_data['social_links'] ?? '{}', true) ?: [];
            $current_avatar = $user_data['avatar'] ?? '';
            $current_cover_image = $user_data['cover_image'] ?? '';
            $current_cover_video = $user_data['cover_video'] ?? '';
            $current_background_music = $user_data['background_music'] ?? '';
        } else {
            $error = "Kullanıcı bilgileri alınamadı.";
        }
    } else {
        $error = "Sorgu çalıştırılırken bir hata oluştu.";
    }
    
    mysqli_stmt_close($stmt);
}

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Temel profil bilgileri
    $profile_url = trim($_POST['profile_url'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $interests = trim($_POST['interests'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    // Sosyal medya linkleri
    $social_links = [
        'facebook' => trim($_POST['facebook'] ?? ''),
        'twitter' => trim($_POST['twitter'] ?? ''),
        'instagram' => trim($_POST['instagram'] ?? ''),
        'youtube' => trim($_POST['youtube'] ?? ''),
        'discord' => trim($_POST['discord'] ?? ''),
        'twitch' => trim($_POST['twitch'] ?? ''),
        'github' => trim($_POST['github'] ?? ''),
        'website' => trim($_POST['website'] ?? '')
    ];
    
    // Profil URL kontrolü
    $profile_url_err = '';
    if (!empty($profile_url)) {
        // Sadece harf, rakam ve alt çizgi içerebilir
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $profile_url)) {
            $profile_url_err = "Profil URL'si sadece harf, rakam ve alt çizgi (_) içerebilir.";
        } else {
            // Başka birinin aynı URL'yi kullanıp kullanmadığını kontrol et
            $check_sql = "SELECT id FROM users WHERE profile_url = ? AND id != ?";
            if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
                mysqli_stmt_bind_param($check_stmt, "si", $profile_url, $user_id);
                
                if (mysqli_stmt_execute($check_stmt)) {
                    mysqli_stmt_store_result($check_stmt);
                    
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $profile_url_err = "Bu profil URL'si zaten kullanılıyor. Lütfen başka bir URL seçin.";
                    }
                }
                
                mysqli_stmt_close($check_stmt);
            }
        }
    }
    
    // Dosya yükleme fonksiyonu
    function uploadFile($file, $target_dir, $allowed_types) {
        if ($file['error'] == UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'NO_FILE'];
        }
        
        $target_dir = 'uploads/' . $target_dir . '/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Dosya türü kontrolü
        if (!in_array($file_extension, $allowed_types)) {
            return ['success' => false, 'error' => 'Dosya türü desteklenmiyor. Desteklenen türler: ' . implode(', ', $allowed_types)];
        }
        
        // Dosya boyutu kontrolü (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Dosya boyutu çok büyük. Maksimum 10MB olabilir.'];
        }
        
        // Benzersiz dosya adı
        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Dosyayı yükle
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return ['success' => true, 'file_path' => $target_file];
        } else {
            return ['success' => false, 'error' => 'Dosya yüklenirken bir hata oluştu.'];
        }
    }
    
    // Profil resmi yükleme işlemi
    $avatar = $current_avatar;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] != UPLOAD_ERR_NO_FILE) {
        $avatar_result = uploadFile($_FILES['avatar'], 'profiles', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        
        if ($avatar_result['success']) {
            $avatar = $avatar_result['file_path'];
            
            // Eski dosyayı sil (varsa)
            if (!empty($current_avatar) && file_exists($current_avatar) && $current_avatar != $avatar) {
                unlink($current_avatar);
            }
        } else if ($avatar_result['error'] !== 'NO_FILE') {
            $error = "Avatar yükleme hatası: " . $avatar_result['error'];
        }
    }
    
    // Kapak resmi yükleme işlemi
    $cover_image = $current_cover_image;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $cover_result = uploadFile($_FILES['cover_image'], 'profiles', ['jpg', 'jpeg', 'png', 'webp']);
        
        if ($cover_result['success']) {
            $cover_image = $cover_result['file_path'];
            
            // Eski dosyayı sil (varsa)
            if (!empty($current_cover_image) && file_exists($current_cover_image) && $current_cover_image != $cover_image) {
                unlink($current_cover_image);
            }
        } else if ($cover_result['error'] !== 'NO_FILE') {
            $error = "Kapak resmi yükleme hatası: " . $cover_result['error'];
        }
    }
    
    // Kapak videosu yükleme işlemi
    $cover_video = $current_cover_video;
    if (isset($_FILES['cover_video']) && $_FILES['cover_video']['error'] != UPLOAD_ERR_NO_FILE) {
        $video_result = uploadFile($_FILES['cover_video'], 'profiles', ['mp4', 'webm', 'ogg']);
        
        if ($video_result['success']) {
            $cover_video = $video_result['file_path'];
            
            // Eski dosyayı sil (varsa)
            if (!empty($current_cover_video) && file_exists($current_cover_video) && $current_cover_video != $cover_video) {
                unlink($current_cover_video);
            }
        } else if ($video_result['error'] !== 'NO_FILE') {
            $error = "Kapak videosu yükleme hatası: " . $video_result['error'];
        }
    }
    
    // Arka plan müziği yükleme işlemi
    $background_music = $current_background_music;
    if (isset($_FILES['background_music']) && $_FILES['background_music']['error'] != UPLOAD_ERR_NO_FILE) {
        $music_result = uploadFile($_FILES['background_music'], 'profiles', ['mp3', 'ogg', 'wav']);
        
        if ($music_result['success']) {
            $background_music = $music_result['file_path'];
            
            // Eski dosyayı sil (varsa)
            if (!empty($current_background_music) && file_exists($current_background_music) && $current_background_music != $background_music) {
                unlink($current_background_music);
            }
        } else if ($music_result['error'] !== 'NO_FILE') {
            $error = "Arka plan müziği yükleme hatası: " . $music_result['error'];
        }
    }
    
    // Profil bilgilerini güncelle
    if (empty($error) && empty($profile_url_err)) {
        $update_sql = "UPDATE users SET 
                      profile_url = ?, 
                      avatar = ?, 
                      cover_image = ?, 
                      cover_video = ?, 
                      background_music = ?, 
                      bio = ?, 
                      interests = ?, 
                      location = ?, 
                      social_links = ? 
                    WHERE id = ?";
        
        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
            $social_links_json = json_encode($social_links);
            
            mysqli_stmt_bind_param(
                $update_stmt, 
                "sssssssssi", 
                $profile_url, 
                $avatar, 
                $cover_image, 
                $cover_video, 
                $background_music, 
                $bio, 
                $interests, 
                $location, 
                $social_links_json, 
                $user_id
            );
            
            if (mysqli_stmt_execute($update_stmt)) {
                $message = "Profil bilgileriniz başarıyla güncellendi.";
            } else {
                $error = "Profil güncellenirken bir hata oluştu: " . mysqli_error($conn);
            }
            
            mysqli_stmt_close($update_stmt);
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Profil Düzenle</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="profile-edit-container">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    
                    <div class="profile-form-section">
                        <h2>Temel Bilgiler</h2>
                        
                        <div class="form-group">
                            <label for="profile_url">Profil URL'si (Örn: darklegion.com/profilim)</label>
                            <div class="url-input-container">
                                <span class="url-prefix">darklegion.com/</span>
                                <input type="text" name="profile_url" id="profile_url" value="<?php echo htmlspecialchars($profile_url); ?>" placeholder="url-adresiniz">
                            </div>
                            <?php if (!empty($profile_url_err)): ?>
                            <div class="alert alert-danger"><?php echo $profile_url_err; ?></div>
                            <?php endif; ?>
                            <div class="form-hint">Sadece harf, rakam ve alt çizgi (_) kullanabilirsiniz.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">Hakkımda</label>
                            <textarea name="bio" id="bio" rows="4" maxlength="500" placeholder="Kendinizi tanıtın"><?php echo htmlspecialchars($bio); ?></textarea>
                            <div class="form-hint">Maksimum 500 karakter</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="interests">İlgi Alanları</label>
                            <textarea name="interests" id="interests" rows="2" maxlength="200" placeholder="Müzik, Oyun, Yazılım..."><?php echo htmlspecialchars($interests); ?></textarea>
                            <div class="form-hint">Virgül ile ayırarak yazabilirsiniz. Maksimum 200 karakter</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Konum</label>
                            <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($location); ?>" maxlength="100" placeholder="İstanbul, Türkiye">
                        </div>
                    </div>
                    
                    <div class="profile-form-section">
                        <h2>Medya ve Görünüm</h2>
                        
                        <div class="form-group">
                            <label for="avatar">Profil Fotoğrafı</label>
                            <input type="file" name="avatar" id="avatar" accept=".jpg,.jpeg,.png,.webp,.gif">
                            <?php if (!empty($current_avatar)): ?>
                            <div class="current-media">
                                <img src="<?php echo htmlspecialchars($current_avatar); ?>" alt="Mevcut profil fotoğrafı" class="thumbnail">
                                <span>Mevcut profil fotoğrafı</span>
                            </div>
                            <?php endif; ?>
                            <div class="form-hint">JPG, PNG, WEBP veya GIF • Maksimum 10MB</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cover_image">Kapak Fotoğrafı</label>
                            <input type="file" name="cover_image" id="cover_image" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($current_cover_image)): ?>
                            <div class="current-media">
                                <img src="<?php echo htmlspecialchars($current_cover_image); ?>" alt="Mevcut kapak fotoğrafı" class="thumbnail">
                                <span>Mevcut kapak fotoğrafı</span>
                            </div>
                            <?php endif; ?>
                            <div class="form-hint">JPG, PNG veya WEBP • Maksimum 10MB • Önerilen boyut: 1500x500px</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cover_video">Kapak Videosu (Opsiyonel - Kapak fotoğrafının yerine geçer)</label>
                            <input type="file" name="cover_video" id="cover_video" accept=".mp4,.webm,.ogg">
                            <?php if (!empty($current_cover_video)): ?>
                            <div class="current-media">
                                <video width="200" controls>
                                    <source src="<?php echo htmlspecialchars($current_cover_video); ?>" type="video/<?php echo pathinfo($current_cover_video, PATHINFO_EXTENSION); ?>">
                                    Tarayıcınız video elementini desteklemiyor.
                                </video>
                                <span>Mevcut kapak videosu</span>
                            </div>
                            <?php endif; ?>
                            <div class="form-hint">MP4, WEBM veya OGG • Maksimum 10MB • Kısa video önerilir</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="background_music">Arka Plan Müziği (Opsiyonel)</label>
                            <input type="file" name="background_music" id="background_music" accept=".mp3,.ogg,.wav">
                            <?php if (!empty($current_background_music)): ?>
                            <div class="current-media">
                                <audio controls>
                                    <source src="<?php echo htmlspecialchars($current_background_music); ?>" type="audio/<?php echo pathinfo($current_background_music, PATHINFO_EXTENSION); ?>">
                                    Tarayıcınız audio elementini desteklemiyor.
                                </audio>
                                <span>Mevcut arka plan müziği</span>
                            </div>
                            <?php endif; ?>
                            <div class="form-hint">MP3, OGG veya WAV • Maksimum 10MB</div>
                        </div>
                    </div>
                    
                    <div class="profile-form-section">
                        <h2>Sosyal Medya Bağlantıları</h2>
                        
                        <div class="social-links-grid">
                            <div class="form-group">
                                <label for="facebook">Facebook</label>
                                <input type="url" name="facebook" id="facebook" value="<?php echo htmlspecialchars($social_links['facebook'] ?? ''); ?>" placeholder="https://facebook.com/profiliniz">
                            </div>
                            
                            <div class="form-group">
                                <label for="twitter">Twitter</label>
                                <input type="url" name="twitter" id="twitter" value="<?php echo htmlspecialchars($social_links['twitter'] ?? ''); ?>" placeholder="https://twitter.com/kullaniciadiniz">
                            </div>
                            
                            <div class="form-group">
                                <label for="instagram">Instagram</label>
                                <input type="url" name="instagram" id="instagram" value="<?php echo htmlspecialchars($social_links['instagram'] ?? ''); ?>" placeholder="https://instagram.com/kullaniciadiniz">
                            </div>
                            
                            <div class="form-group">
                                <label for="youtube">YouTube</label>
                                <input type="url" name="youtube" id="youtube" value="<?php echo htmlspecialchars($social_links['youtube'] ?? ''); ?>" placeholder="https://youtube.com/c/kanaliniz">
                            </div>
                            
                            <div class="form-group">
                                <label for="discord">Discord</label>
                                <input type="text" name="discord" id="discord" value="<?php echo htmlspecialchars($social_links['discord'] ?? ''); ?>" placeholder="kullaniciadi#0000">
                            </div>
                            
                            <div class="form-group">
                                <label for="twitch">Twitch</label>
                                <input type="url" name="twitch" id="twitch" value="<?php echo htmlspecialchars($social_links['twitch'] ?? ''); ?>" placeholder="https://twitch.tv/kanaliniz">
                            </div>
                            
                            <div class="form-group">
                                <label for="github">GitHub</label>
                                <input type="url" name="github" id="github" value="<?php echo htmlspecialchars($social_links['github'] ?? ''); ?>" placeholder="https://github.com/kullaniciadiniz">
                            </div>
                            
                            <div class="form-group">
                                <label for="website">Web Sitesi</label>
                                <input type="url" name="website" id="website" value="<?php echo htmlspecialchars($social_links['website'] ?? ''); ?>" placeholder="https://websiteniz.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn">Profili Kaydet</button>
                        <a href="profile.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">Profili Görüntüle</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Profil Düzenleme Sayfası Stilleri */
.profile-edit-container {
    background-color: var(--secondary-bg);
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.profile-form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.profile-form-section h2 {
    color: var(--accent-color);
    margin-bottom: 15px;
    font-size: 20px;
}

.url-input-container {
    display: flex;
    align-items: center;
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    overflow: hidden;
}

.url-prefix {
    padding: 12px;
    background-color: rgba(0, 0, 0, 0.2);
    color: var(--text-secondary);
    border-right: 1px solid var(--border-color);
    white-space: nowrap;
}

.url-input-container input {
    flex: 1;
    border: none;
    background: none;
    padding: 12px;
    color: var(--text-color);
    width: 100%;
}

.form-hint {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.social-links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-secondary {
    background-color: var(--secondary-bg);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background-color: var(--border-color);
}

.current-media {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.current-media img.thumbnail,
.current-media video {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid var(--border-color);
}

.current-media span {
    font-size: 12px;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .social-links-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>