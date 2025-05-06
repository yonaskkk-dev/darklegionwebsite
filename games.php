<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum kontrolü
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$current_user_id = $loggedin ? $_SESSION['id'] : 0;
$username = $loggedin ? $_SESSION['username'] : 'Misafir';

// Oyunlar tablosu yoksa oluştur
$games_table = "CREATE TABLE IF NOT EXISTS games (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    thumbnail VARCHAR(255) NOT NULL,
    is_multiplayer TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'coming_soon') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Skorlar tablosu yoksa oluştur
$scores_table = "CREATE TABLE IF NOT EXISTS game_scores (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    game_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    score INT(11) NOT NULL,
    level INT(11) DEFAULT 1,
    time_played INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Rozetler tablosu yoksa oluştur
$badges_table = "CREATE TABLE IF NOT EXISTS game_badges (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    game_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(255) NOT NULL,
    requirement VARCHAR(255) NOT NULL,
    requirement_value INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
)";

// Kullanıcı rozetleri tablosu yoksa oluştur
$user_badges_table = "CREATE TABLE IF NOT EXISTS user_game_badges (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    badge_id INT(11) NOT NULL,
    achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES game_badges(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, badge_id)
)";

// Tabloları oluştur
if ($conn) {
    mysqli_query($conn, $games_table);
    mysqli_query($conn, $scores_table);
    mysqli_query($conn, $badges_table);
    mysqli_query($conn, $user_badges_table);
}

// Örnek oyunları ekle (eğer yoksa)
$existing_games = mysqli_query($conn, "SELECT COUNT(*) AS count FROM games");
$row = mysqli_fetch_assoc($existing_games);

if ($row['count'] == 0) {
    $sample_games = [
        [
            'name' => 'Yılan Oyunu',
            'slug' => 'snake',
            'description' => 'Klasik yılan oyunu. Yılanı yönlendirerek elmaları topla, duvarlara ve kendine çarpmamaya dikkat et!',
            'thumbnail' => 'games/assets/snake_thumb.png',
            'is_multiplayer' => 0,
            'status' => 'active'
        ],
        [
            'name' => 'Tetris',
            'slug' => 'tetris',
            'description' => 'Düşen blokları düzenleyerek tam satırlar oluştur ve puan kazan.',
            'thumbnail' => 'games/assets/tetris_thumb.png',
            'is_multiplayer' => 0,
            'status' => 'coming_soon'
        ],
        [
            'name' => 'Hafıza Oyunu',
            'slug' => 'memory',
            'description' => 'Eşleşen kartları bul. Hafızanı test et ve en yüksek puanı elde et!',
            'thumbnail' => 'games/assets/memory_thumb.png',
            'is_multiplayer' => 0,
            'status' => 'active'
        ],
        [
            'name' => 'XOX',
            'slug' => 'tictactoe',
            'description' => 'İki kişilik XOX oyunu. Arkadaşınla oyna ve kazanmak için stratejik hamlelerde bulun.',
            'thumbnail' => 'games/assets/tictactoe_thumb.png',
            'is_multiplayer' => 1,
            'status' => 'active'
        ]
    ];
    
    $insert_game = "INSERT INTO games (name, slug, description, thumbnail, is_multiplayer, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_game);
    
    foreach ($sample_games as $game) {
        mysqli_stmt_bind_param($stmt, "ssssss", 
            $game['name'], 
            $game['slug'], 
            $game['description'], 
            $game['thumbnail'], 
            $game['is_multiplayer'], 
            $game['status']
        );
        mysqli_stmt_execute($stmt);
        
        // Oyunun ID'sini al
        $game_id = mysqli_insert_id($conn);
        
        // Bu oyun için bazı rozetler ekle
        if ($game['slug'] == 'snake') {
            $badges = [
                [
                    'name' => 'Çırak Yılancı',
                    'description' => 'İlk oyununu oyna',
                    'icon' => '🐍',
                    'requirement' => 'play_count',
                    'requirement_value' => 1
                ],
                [
                    'name' => 'Yılan Avcısı',
                    'description' => '500 puan üzerinde skor yap',
                    'icon' => '🏆',
                    'requirement' => 'min_score',
                    'requirement_value' => 500
                ],
                [
                    'name' => 'Yılan Efendisi',
                    'description' => '1000 puan üzerinde skor yap',
                    'icon' => '👑',
                    'requirement' => 'min_score',
                    'requirement_value' => 1000
                ]
            ];
            
            $insert_badge = "INSERT INTO game_badges (game_id, name, description, icon, requirement, requirement_value) VALUES (?, ?, ?, ?, ?, ?)";
            $badge_stmt = mysqli_prepare($conn, $insert_badge);
            
            foreach ($badges as $badge) {
                mysqli_stmt_bind_param($badge_stmt, "issssi", 
                    $game_id, 
                    $badge['name'], 
                    $badge['description'], 
                    $badge['icon'], 
                    $badge['requirement'], 
                    $badge['requirement_value']
                );
                mysqli_stmt_execute($badge_stmt);
            }
            
            mysqli_stmt_close($badge_stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
}

// En son oynanan oyunları getir
$recent_games = [];
if ($loggedin) {
    $recent_query = "SELECT g.*, MAX(gs.created_at) as last_played, MAX(gs.score) as best_score
                    FROM games g
                    JOIN game_scores gs ON g.id = gs.game_id
                    WHERE gs.user_id = ?
                    GROUP BY g.id
                    ORDER BY last_played DESC
                    LIMIT 3";
    
    if ($stmt = mysqli_prepare($conn, $recent_query)) {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_games[] = $row;
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Tüm aktif oyunları getir
$games_query = "SELECT * FROM games WHERE status = 'active' ORDER BY name";
$all_games = [];

if ($result = mysqli_query($conn, $games_query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_games[] = $row;
    }
}

// Yakında gelecek oyunları getir
$coming_soon_query = "SELECT * FROM games WHERE status = 'coming_soon' ORDER BY name";
$coming_soon_games = [];

if ($result = mysqli_query($conn, $coming_soon_query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $coming_soon_games[] = $row;
    }
}

// En yüksek skorlar
$leaderboard_query = "SELECT g.name, g.slug, u.username, gs.score, gs.created_at
                     FROM game_scores gs
                     JOIN games g ON gs.game_id = g.id
                     JOIN users u ON gs.user_id = u.id
                     WHERE g.status = 'active'
                     ORDER BY gs.score DESC
                     LIMIT 10";
$leaderboard = [];

if ($result = mysqli_query($conn, $leaderboard_query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $leaderboard[] = $row;
    }
}

// Sayfaya dahil edilecek diğer dosyaları çağır
include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Oyunlar</h1>
            
            <div class="games-intro">
                <p>Dark Legion oyun portalına hoş geldiniz! Burada eğlenceli mini oyunlar oynayabilir, arkadaşlarınızla rekabet edebilir ve rozetler kazanabilirsiniz. Oynadıkça profilinize rozetler eklenecek ve liderlik tablolarında yerinizi alacaksınız.</p>
            </div>
            
            <?php if ($loggedin && !empty($recent_games)): ?>
            <div class="games-section">
                <h2>Son Oynadıklarım</h2>
                <div class="games-grid">
                    <?php foreach ($recent_games as $game): ?>
                    <div class="game-card">
                        <div class="game-thumb">
                            <img src="<?php echo htmlspecialchars($game['thumbnail']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>">
                        </div>
                        <div class="game-info">
                            <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                            <p class="game-stats">En yüksek skorun: <?php echo $game['best_score']; ?></p>
                            <p class="game-stats">Son oynanma: <?php echo date('d.m.Y H:i', strtotime($game['last_played'])); ?></p>
                            <a href="games/<?php echo htmlspecialchars($game['slug']); ?>.php" class="btn">Oyna</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="games-section">
                <h2>Tüm Oyunlar</h2>
                <div class="games-grid">
                    <?php foreach ($all_games as $game): ?>
                    <div class="game-card">
                        <div class="game-thumb">
                            <img src="<?php echo htmlspecialchars($game['thumbnail']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>">
                        </div>
                        <div class="game-info">
                            <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                            <p><?php echo htmlspecialchars($game['description']); ?></p>
                            <?php if ($game['is_multiplayer']): ?>
                            <span class="game-tag multiplayer">Çok Oyunculu</span>
                            <?php endif; ?>
                            <a href="games/<?php echo htmlspecialchars($game['slug']); ?>.php" class="btn">Oyna</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!empty($coming_soon_games)): ?>
            <div class="games-section">
                <h2>Yakında Gelecek</h2>
                <div class="games-grid">
                    <?php foreach ($coming_soon_games as $game): ?>
                    <div class="game-card coming-soon">
                        <div class="game-thumb">
                            <img src="<?php echo htmlspecialchars($game['thumbnail']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>">
                            <div class="coming-soon-overlay">Yakında</div>
                        </div>
                        <div class="game-info">
                            <h3><?php echo htmlspecialchars($game['name']); ?></h3>
                            <p><?php echo htmlspecialchars($game['description']); ?></p>
                            <?php if ($game['is_multiplayer']): ?>
                            <span class="game-tag multiplayer">Çok Oyunculu</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="games-section">
                <h2>Liderlik Tablosu</h2>
                <?php if (empty($leaderboard)): ?>
                <div class="no-scores">
                    <p>Henüz hiç skor kaydedilmemiş. İlk skoru kaydeden sen ol!</p>
                </div>
                <?php else: ?>
                <div class="leaderboard">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Sıra</th>
                                <th>Oyuncu</th>
                                <th>Oyun</th>
                                <th>Skor</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($leaderboard as $entry): ?>
                            <tr>
                                <td class="rank">
                                    <?php if ($rank <= 3): ?>
                                    <span class="rank-icon rank-<?php echo $rank; ?>"><?php echo ($rank == 1) ? '🥇' : (($rank == 2) ? '🥈' : '🥉'); ?></span>
                                    <?php else: ?>
                                    <?php echo $rank; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                <td>
                                    <a href="games/<?php echo htmlspecialchars($entry['slug']); ?>.php">
                                        <?php echo htmlspecialchars($entry['name']); ?>
                                    </a>
                                </td>
                                <td class="score"><?php echo number_format($entry['score']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($entry['created_at'])); ?></td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Oyunlar Sayfası Stilleri */
.games-intro {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.games-section {
    margin-bottom: 40px;
}

.games-section h2 {
    color: var(--accent-color);
    margin-bottom: 20px;
    font-size: 24px;
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 10px;
}

.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.game-card {
    background-color: var(--secondary-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.game-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.game-thumb {
    position: relative;
    height: 180px;
    overflow: hidden;
}

.game-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.game-info {
    padding: 20px;
}

.game-info h3 {
    color: var(--text-color);
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 20px;
}

.game-info p {
    color: var(--text-secondary);
    margin-bottom: 15px;
    font-size: 14px;
    line-height: 1.4;
}

.game-stats {
    font-size: 12px;
    color: var(--text-secondary);
    margin: 5px 0;
}

.game-tag {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-right: 5px;
    margin-bottom: 10px;
}

.game-tag.multiplayer {
    background-color: rgba(52, 152, 219, 0.3);
    color: #3498db;
}

.coming-soon-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    text-transform: uppercase;
}

.coming-soon {
    opacity: 0.8;
}

/* Liderlik Tablosu */
.leaderboard {
    background-color: var(--secondary-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}

.leaderboard-table th {
    background-color: rgba(0, 0, 0, 0.2);
    padding: 12px;
    text-align: left;
    color: var(--accent-color);
}

.leaderboard-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
}

.leaderboard-table tr:last-child td {
    border-bottom: none;
}

.leaderboard-table tr:hover {
    background-color: rgba(0, 0, 0, 0.1);
}

.leaderboard-table td.rank {
    font-weight: bold;
    text-align: center;
    width: 60px;
}

.rank-icon {
    font-size: 20px;
}

.score {
    font-weight: bold;
    text-align: right;
}

.no-scores {
    background-color: var(--secondary-bg);
    padding: 40px 20px;
    text-align: center;
    border-radius: 8px;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .games-grid {
        grid-template-columns: 1fr;
    }
    
    .leaderboard-table {
        font-size: 14px;
    }
    
    .leaderboard-table th, 
    .leaderboard-table td {
        padding: 8px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>