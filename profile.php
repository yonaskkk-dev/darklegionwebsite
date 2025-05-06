<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et (ama herkes görüntüleyebilir)
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$current_user_id = $loggedin ? $_SESSION['id'] : 0;

// Profil ID veya URL'sini al
$profile_id = null;
$profile_url = null;
$user_data = null;

// URL kontrolü (örn: darklegion.com/username)
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/'; // Web sitenizin kök dizinini ayarlayın

if (strpos($request_uri, $base_path) === 0) {
    $path = substr($request_uri, strlen($base_path));
    $path = strtok($path, '?'); // Query string'i kaldır
    
    // Root URL değilse ve '/' içermiyorsa (alt klasör olmadığından emin ol)
    if ($path && $path != 'profile.php' && strpos($path, '/') === false) {
        $profile_url = $path;
    }
}

// URL veya GET parametresi ile profil sayfasını göster
if ($profile_url) {
    // Profil URL'sine göre kullanıcı bul
    $sql = "SELECT * FROM users WHERE profile_url = ?";
    $param = $profile_url;
    $type = "s";
} else {
    // ID'ye göre kullanıcı bul (varsayılan: giriş yapmış kullanıcı)
    $profile_id = isset($_GET['id']) ? (int)$_GET['id'] : ($current_user_id ?: null);
    
    if (!$profile_id) {
        // ID yoksa ve giriş yapılmamışsa, giriş sayfasına yönlendir
        header("Location: login.php");
        exit;
    }
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $param = $profile_id;
    $type = "i";
}

// Kullanıcı verilerini sorgula
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, $type, $param);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user_data = mysqli_fetch_assoc($result)) {
            // Profile view sayısını artır (kendi profilini görüntülemiyorsa)
            if ($current_user_id != $user_data['id']) {
                $update_views = "UPDATE users SET profile_views = profile_views + 1 WHERE id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_views)) {
                    mysqli_stmt_bind_param($update_stmt, "i", $user_data['id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
            }
        } else {
            // Kullanıcı bulunamadı
            header("Location: index.php");
            exit;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Sosyal medya linkleri
$social_links = json_decode($user_data['social_links'] ?? '{}', true) ?: [];

// Kullanıcı aktivitelerini yükle (son 10 etkinlik)
$activities = [];

// Anılar
$memories_sql = "SELECT * FROM memories WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
if ($mem_stmt = mysqli_prepare($conn, $memories_sql)) {
    mysqli_stmt_bind_param($mem_stmt, "i", $user_data['id']);
    
    if (mysqli_stmt_execute($mem_stmt)) {
        $mem_result = mysqli_stmt_get_result($mem_stmt);
        
        while ($memory = mysqli_fetch_assoc($mem_result)) {
            $activities[] = [
                'type' => 'memory',
                'data' => $memory,
                'date' => $memory['created_at']
            ];
        }
    }
    
    mysqli_stmt_close($mem_stmt);
}

// Yüklenen dosyalar
$uploads_sql = "SELECT * FROM uploads WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 5";
if ($up_stmt = mysqli_prepare($conn, $uploads_sql)) {
    mysqli_stmt_bind_param($up_stmt, "i", $user_data['id']);
    
    if (mysqli_stmt_execute($up_stmt)) {
        $up_result = mysqli_stmt_get_result($up_stmt);
        
        while ($upload = mysqli_fetch_assoc($up_result)) {
            $activities[] = [
                'type' => 'upload',
                'data' => $upload,
                'date' => $upload['uploaded_at']
            ];
        }
    }
    
    mysqli_stmt_close($up_stmt);
}

// Aktiviteleri tarihe göre sırala
usort($activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Sadece son 10 aktiviteyi göster
$activities = array_slice($activities, 0, 10);

// Rozet ve ödüller (örnek rozet sistemi)
$badges = [];

// Kayıt tarihine göre rozet
$register_days = floor((time() - strtotime($user_data['created_at'])) / (60 * 60 * 24));
if ($register_days > 365) {
    $badges[] = ['name' => 'Veteran', 'icon' => '🏆', 'description' => '1 yıldan uzun süredir üye'];
} elseif ($register_days > 180) {
    $badges[] = ['name' => 'Düzenli Üye', 'icon' => '🥈', 'description' => '6 aydan uzun süredir üye'];
} elseif ($register_days > 30) {
    $badges[] = ['name' => 'Yeni Üye', 'icon' => '🥉', 'description' => '1 aydan uzun süredir üye'];
}

// Profil görüntülenme sayısına göre rozet
$views = $user_data['profile_views'];
if ($views > 1000) {
    $badges[] = ['name' => 'Popüler', 'icon' => '⭐', 'description' => '1000+ profil görüntülenmesi'];
} elseif ($views > 500) {
    $badges[] = ['name' => 'Tanınmış', 'icon' => '✨', 'description' => '500+ profil görüntülenmesi'];
} elseif ($views > 100) {
    $badges[] = ['name' => 'İlgi Çekici', 'icon' => '🌟', 'description' => '100+ profil görüntülenmesi'];
}

// Kullanıcı bilgileri
$username = htmlspecialchars($user_data['username']);
$bio = htmlspecialchars($user_data['bio'] ?? '');
$interests = htmlspecialchars($user_data['interests'] ?? '');
$location = htmlspecialchars($user_data['location'] ?? '');
$profile_views = $user_data['profile_views'];
$avatar = $user_data['avatar'] ?: 'assets/images/default_avatar.png';
$cover_image = $user_data['cover_image'] ?: 'assets/images/default_cover.jpg';
$cover_video = $user_data['cover_video'] ?: '';
$background_music = $user_data['background_music'] ?: '';
$created_at = date('d.m.Y', strtotime($user_data['created_at']));

// Sayfa başlığı
$page_title = $username . " | Dark Legion";

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <div class="profile-container">
                <!-- Kapak Fotoğrafı/Videosu -->
                <div class="profile-cover">
                    <?php if (!empty($cover_video)): ?>
                    <video autoplay muted loop id="cover-video">
                        <source src="<?php echo htmlspecialchars($cover_video); ?>" type="video/<?php echo pathinfo($cover_video, PATHINFO_EXTENSION); ?>">
                        Tarayıcınız video elementini desteklemiyor.
                    </video>
                    <?php elseif (!empty($cover_image)): ?>
                    <img src="<?php echo htmlspecialchars($cover_image); ?>" alt="Kapak fotoğrafı">
                    <?php else: ?>
                    <div class="default-cover"></div>
                    <?php endif; ?>
                    
                    <!-- Arka plan müziği -->
                    <?php if (!empty($background_music)): ?>
                    <div class="music-player">
                        <audio id="background-music" loop>
                            <source src="<?php echo htmlspecialchars($background_music); ?>" type="audio/<?php echo pathinfo($background_music, PATHINFO_EXTENSION); ?>">
                            Tarayıcınız audio elementini desteklemiyor.
                        </audio>
                        <button id="toggle-music" class="music-toggle" title="Müziği Aç/Kapat">🎵</button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Profil Üst Bilgileri -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo $username; ?>">
                    </div>
                    
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo $username; ?></h1>
                        
                        <?php if (!empty($location)): ?>
                        <div class="profile-location">📍 <?php echo $location; ?></div>
                        <?php endif; ?>
                        
                        <div class="profile-meta">
                            <span>👁️ <?php echo $profile_views; ?> görüntülenme</span>
                            <span>📅 <?php echo $created_at; ?> tarihinde katıldı</span>
                            
                            <?php if (!empty($user_data['profile_url'])): ?>
                            <span>🔗 <a href="/<?php echo htmlspecialchars($user_data['profile_url']); ?>">darklegion.com/<?php echo htmlspecialchars($user_data['profile_url']); ?></a></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($current_user_id == $user_data['id']): ?>
                        <div class="profile-actions">
                            <a href="profile_edit.php" class="btn">Profili Düzenle</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Rozet ve Ödüller -->
                <?php if (!empty($badges)): ?>
                <div class="profile-badges">
                    <h2>Rozetler</h2>
                    <div class="badges-container">
                        <?php foreach ($badges as $badge): ?>
                        <div class="badge-item" title="<?php echo htmlspecialchars($badge['description']); ?>">
                            <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Profil Detayları -->
                <div class="profile-details">
                    <!-- Hakkımda -->
                    <?php if (!empty($bio)): ?>
                    <div class="profile-section">
                        <h2>Hakkımda</h2>
                        <div class="profile-bio">
                            <?php echo nl2br($bio); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- İlgi Alanları -->
                    <?php if (!empty($interests)): ?>
                    <div class="profile-section">
                        <h2>İlgi Alanları</h2>
                        <div class="profile-interests">
                            <?php
                            $interest_array = explode(',', $interests);
                            foreach ($interest_array as $interest) {
                                echo '<span class="interest-tag">' . htmlspecialchars(trim($interest)) . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Sosyal Medya Bağlantıları -->
                    <?php if (!empty($social_links) && count(array_filter($social_links)) > 0): ?>
                    <div class="profile-section">
                        <h2>Sosyal Medya</h2>
                        <div class="social-links">
                            <?php if (!empty($social_links['facebook'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['facebook']); ?>" target="_blank" class="social-link" title="Facebook">
                                <span class="social-icon">👍</span>
                                <span class="social-name">Facebook</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['twitter'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['twitter']); ?>" target="_blank" class="social-link" title="Twitter">
                                <span class="social-icon">🐦</span>
                                <span class="social-name">Twitter</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['instagram']); ?>" target="_blank" class="social-link" title="Instagram">
                                <span class="social-icon">📷</span>
                                <span class="social-name">Instagram</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['youtube'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['youtube']); ?>" target="_blank" class="social-link" title="YouTube">
                                <span class="social-icon">📺</span>
                                <span class="social-name">YouTube</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['discord'])): ?>
                            <a href="#" class="social-link" title="Discord: <?php echo htmlspecialchars($social_links['discord']); ?>">
                                <span class="social-icon">🎮</span>
                                <span class="social-name">Discord</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['twitch'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['twitch']); ?>" target="_blank" class="social-link" title="Twitch">
                                <span class="social-icon">🎬</span>
                                <span class="social-name">Twitch</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['github'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['github']); ?>" target="_blank" class="social-link" title="GitHub">
                                <span class="social-icon">💻</span>
                                <span class="social-name">GitHub</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($social_links['website'])): ?>
                            <a href="<?php echo htmlspecialchars($social_links['website']); ?>" target="_blank" class="social-link" title="Web Sitesi">
                                <span class="social-icon">🌐</span>
                                <span class="social-name">Web Sitesi</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Aktivite Akışı -->
                <div class="profile-activities">
                    <h2>Son Aktiviteler</h2>
                    
                    <?php if (empty($activities)): ?>
                    <div class="no-activities">
                        <p>Henüz aktivite yok.</p>
                    </div>
                    <?php else: ?>
                    <div class="activities-timeline">
                        <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <?php if ($activity['type'] == 'memory'): ?>
                            <?php $memory = $activity['data']; ?>
                            <div class="activity-icon memory-icon">📸</div>
                            <div class="activity-content">
                                <div class="activity-header">
                                    <span class="activity-title">"<?php echo htmlspecialchars($memory['title']); ?>" adlı bir anı paylaştı</span>
                                    <span class="activity-date"><?php echo date('d.m.Y H:i', strtotime($memory['created_at'])); ?></span>
                                </div>
                                <div class="activity-preview">
                                    <?php if ($memory['memory_type'] == 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($memory['file_path']); ?>" alt="<?php echo htmlspecialchars($memory['title']); ?>" class="activity-image">
                                    <?php else: ?>
                                    <div class="video-placeholder">🎬 Video Anısı</div>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-actions">
                                    <a href="memories.php#memory-<?php echo $memory['id']; ?>" class="activity-link">Anıyı Görüntüle</a>
                                </div>
                            </div>
                            <?php elseif ($activity['type'] == 'upload'): ?>
                            <?php $upload = $activity['data']; ?>
                            <div class="activity-icon upload-icon">📁</div>
                            <div class="activity-content">
                                <div class="activity-header">
                                    <span class="activity-title">"<?php echo htmlspecialchars($upload['filename']); ?>" dosyasını yükledi</span>
                                    <span class="activity-date"><?php echo date('d.m.Y H:i', strtotime($upload['uploaded_at'])); ?></span>
                                </div>
                                <div class="activity-actions">
                                    <a href="<?php echo htmlspecialchars($upload['file_url']); ?>" target="_blank" class="activity-link">Dosyayı İndir</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Profil Sayfası Stilleri */
.profile-container {
    position: relative;
    background-color: var(--secondary-bg);
    border-radius: 8px;
    overflow: hidden;
    margin-top: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

/* Kapak Alanı */
.profile-cover {
    position: relative;
    height: 300px;
    overflow: hidden;
}

.profile-cover img, 
.profile-cover video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-cover {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #8e44ad, #3498db);
}

.music-player {
    position: absolute;
    bottom: 10px;
    right: 10px;
    z-index: 5;
}

.music-toggle {
    background-color: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.music-toggle:hover {
    background-color: var(--accent-color);
}

.music-toggle.playing {
    background-color: var(--accent-color);
}

/* Profil Üst Bilgileri */
.profile-header {
    display: flex;
    align-items: flex-end;
    padding: 0 20px;
    margin-top: -80px;
    position: relative;
    z-index: 2;
}

.profile-avatar {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    overflow: hidden;
    border: 5px solid var(--secondary-bg);
    margin-right: 20px;
    position: relative;
    z-index: 3;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-info {
    flex: 1;
    padding: 20px 0;
}

.profile-name {
    color: var(--text-color);
    margin: 0 0 5px;
    font-size: 24px;
}

.profile-location {
    color: var(--text-secondary);
    margin-bottom: 10px;
    font-size: 16px;
}

.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    color: var(--text-secondary);
    font-size: 14px;
}

.profile-actions {
    margin-top: 15px;
}

/* Rozet ve Ödüller */
.profile-badges {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.profile-badges h2 {
    font-size: 18px;
    margin-bottom: 15px;
    color: var(--accent-color);
}

.badges-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.badge-item {
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 5px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: transform 0.3s;
}

.badge-item:hover {
    transform: translateY(-3px);
}

.badge-icon {
    font-size: 24px;
}

.badge-name {
    font-weight: bold;
    color: var(--text-color);
}

/* Profil Detayları */
.profile-details {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.profile-section {
    margin-bottom: 25px;
}

.profile-section h2 {
    font-size: 18px;
    margin-bottom: 10px;
    color: var(--accent-color);
}

.profile-bio {
    line-height: 1.5;
    color: var(--text-color);
}

.profile-interests {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.interest-tag {
    background-color: rgba(142, 68, 173, 0.2);
    color: var(--text-color);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 14px;
}

/* Sosyal Medya Linkleri */
.social-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.social-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 5px;
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.3s;
}

.social-link:hover {
    background-color: var(--accent-color);
    transform: translateY(-3px);
}

.social-icon {
    font-size: 18px;
}

/* Aktivite Akışı */
.profile-activities {
    padding: 20px;
}

.profile-activities h2 {
    font-size: 18px;
    margin-bottom: 15px;
    color: var(--accent-color);
}

.activities-timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.activity-item {
    display: flex;
    gap: 15px;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 15px;
    transition: transform 0.3s;
}

.activity-item:hover {
    transform: translateY(-3px);
    background-color: rgba(0, 0, 0, 0.2);
}

.activity-icon {
    font-size: 24px;
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 50%;
}

.activity-content {
    flex: 1;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.activity-title {
    font-weight: bold;
    color: var(--text-color);
}

.activity-date {
    font-size: 12px;
    color: var(--text-secondary);
}

.activity-preview {
    margin-bottom: 10px;
}

.activity-image {
    max-width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 5px;
}

.video-placeholder {
    width: 100%;
    height: 150px;
    background-color: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    color: var(--text-secondary);
}

.activity-actions {
    margin-top: 10px;
}

.activity-link {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: bold;
}

.activity-link:hover {
    text-decoration: underline;
}

.no-activities {
    padding: 20px;
    background-color: rgba(0, 0, 0, 0.1);
    text-align: center;
    border-radius: 5px;
    color: var(--text-secondary);
}

/* Responsive */
@media (max-width: 768px) {
    .profile-cover {
        height: 200px;
    }
    
    .profile-header {
        flex-direction: column;
        align-items: center;
        margin-top: -50px;
    }
    
    .profile-avatar {
        margin-right: 0;
        margin-bottom: 20px;
    }
    
    .profile-info {
        text-align: center;
    }
    
    .profile-meta {
        justify-content: center;
    }
    
    .badges-container,
    .social-links {
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Müzik kontrolü
    const backgroundMusic = document.getElementById('background-music');
    const toggleMusicBtn = document.getElementById('toggle-music');
    
    if (backgroundMusic && toggleMusicBtn) {
        toggleMusicBtn.addEventListener('click', function() {
            if (backgroundMusic.paused) {
                backgroundMusic.play();
                toggleMusicBtn.classList.add('playing');
                toggleMusicBtn.textContent = '🔊';
            } else {
                backgroundMusic.pause();
                toggleMusicBtn.classList.remove('playing');
                toggleMusicBtn.textContent = '🎵';
            }
        });
    }
    
    // Aktivite resimlerine tıklama
    const activityImages = document.querySelectorAll('.activity-image');
    activityImages.forEach(img => {
        img.addEventListener('click', function() {
            const modal = document.createElement('div');
            modal.className = 'image-modal';
            
            const modalContent = document.createElement('div');
            modalContent.className = 'image-modal-content';
            
            const modalImage = document.createElement('img');
            modalImage.src = this.src;
            
            const closeBtn = document.createElement('span');
            closeBtn.className = 'modal-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.addEventListener('click', function() {
                document.body.removeChild(modal);
            });
            
            modalContent.appendChild(closeBtn);
            modalContent.appendChild(modalImage);
            modal.appendChild(modalContent);
            
            document.body.appendChild(modal);
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                }
            });
        });
    });
    
    // Stil ekle
    const style = document.createElement('style');
    style.textContent = `
        .image-modal {
            display: flex;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            align-items: center;
            justify-content: center;
        }
        
        .image-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }
        
        .image-modal-content img {
            max-width: 100%;
            max-height: 80vh;
            display: block;
        }
        
        .modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include 'includes/footer.php'; ?>