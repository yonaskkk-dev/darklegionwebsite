<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$isAdmin = $loggedin && $_SESSION['role'] === 'admin';

// Bağlantı işlemleri
$links = [];
$message = '';
$error = '';

// JSON dosyasından bağlantıları yükle
function loadLinks() {
    $jsonFile = 'data/links.json';
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        return json_decode($jsonData, true) ?: [];
    }
    return [];
}

// Bağlantıları JSON dosyasına kaydet
function saveLinks($links) {
    $jsonFile = 'data/links.json';
    $jsonData = json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($jsonFile, $jsonData);
}

// Bağlantı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action']) && $_POST['action'] === 'add_link') {
        $title = trim($_POST['title']);
        $url = trim($_POST['url']);
        $icon = trim($_POST['icon']);
        
        if (empty($title) || empty($url)) {
            $error = 'Başlık ve URL alanları zorunludur.';
        } else {
            // URL formatını kontrol et
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $error = 'Geçersiz URL formatı. Lütfen http:// veya https:// ile başlayan geçerli bir URL girin.';
            } else {
                $links = loadLinks();
                
                // Yeni bağlantı dizisi
                $newLink = [
                    'id' => time(), // Basit bir ID
                    'title' => $title,
                    'url' => $url,
                    'icon' => $icon,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Bağlantıyı ekle
                $links[] = $newLink;
                
                // JSON'a kaydet
                if (saveLinks($links)) {
                    $message = 'Bağlantı başarıyla eklendi.';
                } else {
                    $error = 'Bağlantı kaydedilirken bir hata oluştu.';
                }
            }
        }
    }
    
    // Bağlantı silme işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'delete_link' && isset($_POST['link_id'])) {
        $linkId = (int)$_POST['link_id'];
        $links = loadLinks();
        
        foreach ($links as $key => $link) {
            if ($link['id'] === $linkId) {
                unset($links[$key]);
                break;
            }
        }
        
        // Diziyi yeniden indeksle
        $links = array_values($links);
        
        // JSON'a kaydet
        if (saveLinks($links)) {
            $message = 'Bağlantı başarıyla silindi.';
        } else {
            $error = 'Bağlantı silinirken bir hata oluştu.';
        }
    }
}

// Tüm bağlantıları yükle
$links = loadLinks();

// Bağlantıları oluşturulma tarihine göre sırala (yeniden eskiye)
usort($links, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Platform ikonları (yaygın platformlar için)
$platformIcons = [
    'discord' => '🎮 Discord',
    'steam' => '🎲 Steam',
    'youtube' => '📺 YouTube',
    'twitch' => '🎬 Twitch',
    'instagram' => '📷 Instagram',
    'facebook' => '👍 Facebook',
    'twitter' => '🐦 Twitter',
    'github' => '💻 GitHub',
    'telegram' => '✈️ Telegram',
    'whatsapp' => '📱 WhatsApp',
    'web' => '🌐 Web',
    'email' => '📧 E-posta',
    'other' => '🔗 Diğer'
];

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Bağlantılar</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <div class="links-form">
                <h2>Yeni Bağlantı Ekle</h2>
                <form action="links.php" method="post">
                    <input type="hidden" name="action" value="add_link">
                    
                    <div class="form-group">
                        <label for="title">Başlık</label>
                        <input type="text" name="title" id="title" required placeholder="Örn: Discord Sunucumuz">
                    </div>
                    
                    <div class="form-group">
                        <label for="url">URL</label>
                        <input type="url" name="url" id="url" required placeholder="Örn: https://discord.gg/...">
                    </div>
                    
                    <div class="form-group">
                        <label for="icon">Platform</label>
                        <select name="icon" id="icon">
                            <?php foreach ($platformIcons as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Bağlantı Ekle</button>
                    </div>
                </form>
            </div>
            <hr class="divider">
            <?php endif; ?>
            
            <div class="links-container">
                <h2>Tüm Bağlantılar</h2>
                
                <?php if (empty($links)): ?>
                <div class="no-links">
                    <p>Henüz bağlantı bulunmuyor.</p>
                </div>
                <?php else: ?>
                <div class="links-list">
                    <?php foreach ($links as $link): ?>
                    <div class="link-item">
                        <div class="link-icon">
                            <?php 
                            $icon = isset($link['icon']) && array_key_exists($link['icon'], $platformIcons) 
                                ? $link['icon'] 
                                : 'other';
                            $iconText = explode(' ', $platformIcons[$icon])[0];
                            echo $iconText;
                            ?>
                        </div>
                        <div class="link-content">
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($link['title']); ?>
                            </a>
                        </div>
                        <?php if ($isAdmin): ?>
                        <div class="link-actions">
                            <form action="links.php" method="post" onsubmit="return confirm('Bu bağlantıyı silmek istediğinizden emin misiniz?');">
                                <input type="hidden" name="action" value="delete_link">
                                <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                <button type="submit" class="btn-icon delete-icon" title="Sil">
                                    <i class="trash-icon">🗑️</i>
                                </button>
                            </form>
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

<style>
/* Bağlantılar sayfası stilleri */
.links-form {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.links-container {
    margin-top: 20px;
}

.links-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.link-item {
    display: flex;
    align-items: center;
    background-color: var(--secondary-bg);
    border-radius: 5px;
    padding: 15px;
    transition: transform 0.3s ease, background-color 0.3s ease;
    position: relative;
}

.link-item:hover {
    transform: translateX(5px);
    background-color: rgba(142, 68, 173, 0.1);
}

.link-icon {
    font-size: 20px;
    margin-right: 15px;
    min-width: 35px;
    text-align: center;
}

.link-content {
    flex: 1;
}

.link-content a {
    color: var(--text-color);
    text-decoration: none;
    font-weight: bold;
    transition: color 0.3s;
}

.link-content a:hover {
    color: var(--accent-color);
}

.link-actions {
    margin-left: 10px;
}

.delete-icon {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.3s;
}

.delete-icon:hover {
    opacity: 1;
}

.no-links {
    padding: 20px;
    text-align: center;
    background-color: var(--secondary-bg);
    border-radius: 5px;
    color: var(--text-secondary);
}

/* Responsive düzenlemeler */
@media (max-width: 768px) {
    .link-item {
        padding: 12px;
    }
    
    .link-icon {
        font-size: 16px;
        margin-right: 10px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>