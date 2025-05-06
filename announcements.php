<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$isAdmin = $loggedin && $_SESSION['role'] === 'admin';

// Duyuru işlemleri
$announcements = [];
$message = '';
$error = '';

// JSON dosyasından duyuruları yükle
function loadAnnouncements() {
    $jsonFile = 'data/announcements.json';
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        return json_decode($jsonData, true) ?: [];
    }
    return [];
}

// Duyuruları JSON dosyasına kaydet
function saveAnnouncements($announcements) {
    $jsonFile = 'data/announcements.json';
    $jsonData = json_encode($announcements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($jsonFile, $jsonData);
}

// Duyuru ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action']) && $_POST['action'] === 'add_announcement') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $isPinned = isset($_POST['is_pinned']) ? true : false;
        
        if (empty($title) || empty($content)) {
            $error = 'Başlık ve içerik alanları zorunludur.';
        } else {
            $announcements = loadAnnouncements();
            
            // Yeni duyuru dizisi
            $newAnnouncement = [
                'id' => time(), // Basit bir ID
                'title' => $title,
                'content' => $content,
                'is_pinned' => $isPinned,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Sabitleme işlemi - eğer yeni duyuru sabitlenecekse, diğer sabitlenmiş duyuruları kaldır
            if ($isPinned) {
                foreach ($announcements as $key => $announcement) {
                    $announcements[$key]['is_pinned'] = false;
                }
            }
            
            // Duyuruyu ekle
            $announcements[] = $newAnnouncement;
            
            // JSON'a kaydet
            if (saveAnnouncements($announcements)) {
                $message = 'Duyuru başarıyla eklendi.';
            } else {
                $error = 'Duyuru kaydedilirken bir hata oluştu.';
            }
        }
    }
    
    // Duyuru silme işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'delete_announcement' && isset($_POST['announcement_id'])) {
        $announcementId = (int)$_POST['announcement_id'];
        $announcements = loadAnnouncements();
        
        foreach ($announcements as $key => $announcement) {
            if ($announcement['id'] === $announcementId) {
                unset($announcements[$key]);
                break;
            }
        }
        
        // Diziyi yeniden indeksle
        $announcements = array_values($announcements);
        
        // JSON'a kaydet
        if (saveAnnouncements($announcements)) {
            $message = 'Duyuru başarıyla silindi.';
        } else {
            $error = 'Duyuru silinirken bir hata oluştu.';
        }
    }
    
    // Duyuru sabitleme/sabitlemeyi kaldırma işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_pin' && isset($_POST['announcement_id'])) {
        $announcementId = (int)$_POST['announcement_id'];
        $announcements = loadAnnouncements();
        $isPinning = false;
        
        foreach ($announcements as $key => $announcement) {
            // Eğer ilgili duyuru ise, sabitleme durumunu değiştir
            if ($announcement['id'] === $announcementId) {
                $isPinning = !$announcement['is_pinned'];
                $announcements[$key]['is_pinned'] = $isPinning;
            } 
            // Eğer yeni bir duyuru sabitleniyorsa ve bu başka bir duyuru ise, sabitlemeyi kaldır
            else if ($isPinning && $announcement['is_pinned']) {
                $announcements[$key]['is_pinned'] = false;
            }
        }
        
        // JSON'a kaydet
        if (saveAnnouncements($announcements)) {
            $message = $isPinning ? 'Duyuru başarıyla sabitlendi.' : 'Duyurunun sabitlemesi kaldırıldı.';
        } else {
            $error = 'Duyuru güncellenirken bir hata oluştu.';
        }
    }
}

// Tüm duyuruları yükle
$announcements = loadAnnouncements();

// Duyuruları sırala: Önce sabitlenmiş, sonra tarih (yeniden eskiye)
usort($announcements, function($a, $b) {
    // Önce sabitlenmiş olanları kontrol et
    if ($a['is_pinned'] && !$b['is_pinned']) return -1;
    if (!$a['is_pinned'] && $b['is_pinned']) return 1;
    
    // Sabitlenmişse veya her ikisi de sabitlenmemişse, tarihe göre sırala
    return strtotime($b['created_at']) - strtotime($a['created_at']); // Yeniden eskiye
});

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Duyurular</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <div class="announcement-form">
                <h2>Yeni Duyuru Ekle</h2>
                <form action="announcements.php" method="post">
                    <input type="hidden" name="action" value="add_announcement">
                    
                    <div class="form-group">
                        <label for="title">Başlık</label>
                        <input type="text" name="title" id="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">İçerik</label>
                        <textarea name="content" id="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_pinned" id="is_pinned">
                            Yukarı Sabitle
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Duyuru Ekle</button>
                    </div>
                </form>
            </div>
            <hr class="divider">
            <?php endif; ?>
            
            <div class="announcements-container">
                <h2>Tüm Duyurular</h2>
                
                <?php if (empty($announcements)): ?>
                <div class="no-announcements">
                    <p>Henüz duyuru bulunmuyor.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item <?php echo $announcement['is_pinned'] ? 'pinned' : ''; ?>">
                        <?php if ($announcement['is_pinned']): ?>
                        <div class="pinned-indicator">📌 Sabitlenmiş</div>
                        <?php endif; ?>
                        
                        <div class="announcement-header">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="announcement-date">
                                <?php echo date('d.m.Y H:i', strtotime($announcement['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                        
                        <?php if ($isAdmin): ?>
                        <div class="announcement-actions">
                            <form action="announcements.php" method="post" class="inline-form">
                                <input type="hidden" name="action" value="toggle_pin">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="btn btn-small <?php echo $announcement['is_pinned'] ? 'btn-warning' : 'btn-info'; ?>">
                                    <?php echo $announcement['is_pinned'] ? 'Sabitlemeyi Kaldır' : 'Yukarı Sabitle'; ?>
                                </button>
                            </form>
                            
                            <form action="announcements.php" method="post" class="inline-form" onsubmit="return confirm('Bu duyuruyu silmek istediğinizden emin misiniz?');">
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="btn btn-small btn-danger">Sil</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Duyurular Sayfası Stilleri */
.divider {
    margin: 30px 0;
    border: 0;
    border-top: 1px solid var(--border-color);
}

.announcement-form {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 10px;
}

.announcements-container {
    margin-top: 20px;
}

.announcement-item {
    background-color: var(--secondary-bg);
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 20px;
    position: relative;
    border-left: 3px solid var(--border-color);
}

.announcement-item.pinned {
    border-left-color: var(--warning-color);
    background-color: rgba(243, 156, 18, 0.1);
}

.pinned-indicator {
    color: var(--warning-color);
    font-weight: bold;
    margin-bottom: 10px;
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.announcement-header h3 {
    color: var(--accent-color);
    margin: 0;
}

.announcement-date {
    color: var(--text-secondary);
    font-size: 12px;
    white-space: nowrap;
    margin-left: 15px;
}

.announcement-content {
    margin-bottom: 15px;
    line-height: 1.5;
}

.announcement-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 15px;
}

.btn-small {
    font-size: 12px;
    padding: 5px 10px;
}

.btn-info {
    background-color: var(--accent-color);
}

.btn-warning {
    background-color: var(--warning-color);
}

.inline-form {
    display: inline-block;
    margin-left: 10px;
}

.no-announcements {
    padding: 20px;
    text-align: center;
    background-color: var(--secondary-bg);
    border-radius: 5px;
    color: var(--text-secondary);
}

/* Responsive düzenlemeler */
@media (max-width: 768px) {
    .announcement-header {
        flex-direction: column;
    }
    
    .announcement-date {
        margin-left: 0;
        margin-top: 5px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sabitlenmiş duyurular için animasyon
    const pinnedAnnouncements = document.querySelectorAll('.announcement-item.pinned');
    pinnedAnnouncements.forEach(announcement => {
        announcement.style.animation = 'highlight 2s ease-in-out';
    });
});
</script>

<?php include 'includes/footer.php'; ?>