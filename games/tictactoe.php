<?php
// Konfigürasyon dosyasını dahil et
require_once '../config.php';

// Oturum kontrolü
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$current_user_id = $loggedin ? $_SESSION['id'] : 0;
$username = $loggedin ? $_SESSION['username'] : 'Misafir';

// Oyun bilgilerini al
$slug = 'tictactoe';
$game_info = null;

$game_query = "SELECT * FROM games WHERE slug = ?";
if ($stmt = mysqli_prepare($conn, $game_query)) {
    mysqli_stmt_bind_param($stmt, "s", $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $game_info = $row;
    } else {
        // Oyun bulunamadı, ana sayfaya yönlendir
        header("Location: ../games.php");
        exit;
    }
    
    mysqli_stmt_close($stmt);
}

// En yüksek skorları getir
$top_scores_query = "SELECT u.username, gs.score, gs.created_at
                    FROM game_scores gs
                    JOIN users u ON gs.user_id = u.id
                    WHERE gs.game_id = ?
                    ORDER BY gs.score DESC
                    LIMIT 5";

$top_scores = [];
if ($stmt = mysqli_prepare($conn, $top_scores_query)) {
    mysqli_stmt_bind_param($stmt, "i", $game_info['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $top_scores[] = $row;
    }
    
    mysqli_stmt_close($stmt);
}

// Kullanıcının en yüksek skorunu getir
$user_highscore = 0;
if ($loggedin) {
    $user_score_query = "SELECT MAX(score) as highscore
                        FROM game_scores
                        WHERE game_id = ? AND user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $user_score_query)) {
        mysqli_stmt_bind_param($stmt, "ii", $game_info['id'], $current_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $user_highscore = $row['highscore'] ?: 0;
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Kullanıcının kazandığı rozetleri getir
$user_badges = [];
if ($loggedin) {
    $badges_query = "SELECT gb.* 
                    FROM game_badges gb
                    JOIN user_game_badges ugb ON gb.id = ugb.badge_id
                    WHERE gb.game_id = ? AND ugb.user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $badges_query)) {
        mysqli_stmt_bind_param($stmt, "ii", $game_info['id'], $current_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $user_badges[] = $row;
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Oyun oynama sayacını artır
if ($loggedin) {
    $update_play_count = "INSERT INTO game_scores (game_id, user_id, score, time_played)
                         VALUES (?, ?, 0, 0)
                         ON DUPLICATE KEY UPDATE play_count = play_count + 1";
    
    if ($stmt = mysqli_prepare($conn, $update_play_count)) {
        mysqli_stmt_bind_param($stmt, "ii", $game_info['id'], $current_user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Sayfa başlığı
$page_title = $game_info['name'] . " | Dark Legion";

// HTML içeriği
ob_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .game-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background-color: var(--secondary-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .game-title h1 {
            margin: 0;
            color: var(--accent-color);
        }
        
        .game-score {
            display: flex;
            gap: 20px;
        }
        
        .score-display {
            text-align: center;
        }
        
        .score-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .score-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-color);
        }
        
        #game-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-gap: 10px;
            margin: 0 auto;
            max-width: 300px;
            background-color: var(--border-color);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .cell {
            width: 90px;
            height: 90px;
            background-color: var(--secondary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .cell:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .cell-x {
            color: #E91E63; /* Pembe */
        }
        
        .cell-o {
            color: #2196F3; /* Mavi */
        }
        
        .highlight {
            background-color: rgba(76, 175, 80, 0.3); /* Yeşil vurgu */
        }
        
        .game-status {
            text-align: center;
            margin: 20px 0;
            font-size: 24px;
            font-weight: bold;
            min-height: 36px;
        }
        
        .game-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        
        .game-button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }
        
        .game-button:hover {
            background-color: #512DA8;
        }
        
        .game-button.secondary {
            background-color: #424242;
        }
        
        .game-button.secondary:hover {
            background-color: #616161;
        }
        
        .score-board {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 40px;
        }
        
        .player-score {
            text-align: center;
            padding: 10px 20px;
            background-color: var(--secondary-bg);
            border-radius: 5px;
            min-width: 100px;
        }
        
        .player-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .player-x {
            color: #E91E63; /* Pembe */
        }
        
        .player-o {
            color: #2196F3; /* Mavi */
        }
        
        .game-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        .stats-card {
            background-color: var(--secondary-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .stats-card h2 {
            color: var(--accent-color);
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .leaderboard-table th {
            text-align: left;
            padding: 8px;
            color: var(--accent-color);
        }
        
        .leaderboard-table td {
            padding: 8px;
            border-top: 1px solid var(--border-color);
        }
        
        .leaderboard-table tr:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .badge-item {
            display: flex;
            align-items: center;
            background-color: rgba(0, 0, 0, 0.1);
            padding: 10px;
            border-radius: 5px;
            gap: 10px;
        }
        
        .badge-icon {
            font-size: 24px;
        }
        
        .badge-info {
            display: flex;
            flex-direction: column;
        }
        
        .badge-name {
            font-weight: bold;
            color: var(--text-color);
        }
        
        .badge-desc {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .no-badges {
            color: var(--text-secondary);
            font-style: italic;
            padding: 20px 0;
        }
        
        .game-instructions {
            margin: 30px 0;
            background-color: var(--secondary-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .game-instructions h2 {
            color: var(--accent-color);
            margin-top: 0;
        }
        
        @media (max-width: 768px) {
            .game-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .game-stats {
                grid-template-columns: 1fr;
            }
            
            .cell {
                width: 70px;
                height: 70px;
                font-size: 36px;
            }
            
            .game-controls {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content game-page">
            <?php if ($loggedin) include '../includes/sidebar.php'; ?>
            
            <div class="content-area">
                <div class="game-container">
                    <div class="game-header">
                        <div class="game-title">
                            <h1><?php echo htmlspecialchars($game_info['name']); ?></h1>
                        </div>
                    </div>
                    
                    <div class="score-board">
                        <div class="player-score">
                            <div class="player-label player-x">Oyuncu X</div>
                            <div class="score-value" id="score-x">0</div>
                        </div>
                        
                        <div class="player-score">
                            <div class="player-label player-o">Oyuncu O</div>
                            <div class="score-value" id="score-o">0</div>
                        </div>
                    </div>
                    
                    <div class="game-status" id="status">Sıra: X</div>
                    
                    <div id="game-board">
                        <div class="cell" data-cell-index="0"></div>
                        <div class="cell" data-cell-index="1"></div>
                        <div class="cell" data-cell-index="2"></div>
                        <div class="cell" data-cell-index="3"></div>
                        <div class="cell" data-cell-index="4"></div>
                        <div class="cell" data-cell-index="5"></div>
                        <div class="cell" data-cell-index="6"></div>
                        <div class="cell" data-cell-index="7"></div>
                        <div class="cell" data-cell-index="8"></div>
                    </div>
                    
                    <div class="game-controls">
                        <button id="restart-game" class="game-button">Oyunu Sıfırla</button>
                        <button id="toggle-computer" class="game-button secondary">Bilgisayara Karşı Oyna</button>
                        <?php if ($loggedin): ?>
                        <button id="save-score" class="game-button">Skoru Kaydet</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="game-instructions">
                        <h2>Nasıl Oynanır?</h2>
                        <p><?php echo htmlspecialchars($game_info['description']); ?></p>
                        
                        <ul>
                            <li>Oyun 3x3'lük bir tabloda oynanır.</li>
                            <li>İki oyuncu sırayla X ve O işaretlerini boş karelere yerleştirir.</li>
                            <li>Üç X veya üç O'yu yatay, dikey veya çapraz olarak hizalayan ilk oyuncu kazanır.</li>
                            <li>Tüm kareler doldurulduğunda ve kazanan yoksa, oyun berabere biter.</li>
                            <li>"Bilgisayara Karşı Oyna" butonuna tıklayarak yapay zekaya karşı oynayabilirsiniz.</li>
                            <li>Skor kaydetmek için önce birkaç oyun oynayın, sonra "Skoru Kaydet" butonuna tıklayın.</li>
                        </ul>
                    </div>
                    
                    <div class="game-stats">
                        <div class="stats-card">
                            <h2>En Yüksek Skorlar</h2>
                            <?php if (empty($top_scores)): ?>
                            <p>Henüz hiç skor kaydedilmemiş. İlk skoru kaydeden sen ol!</p>
                            <?php else: ?>
                            <table class="leaderboard-table">
                                <thead>
                                    <tr>
                                        <th>Oyuncu</th>
                                        <th>Skor</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_scores as $score): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($score['username']); ?></td>
                                        <td><?php echo number_format($score['score']); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($score['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                            
                            <?php if ($loggedin): ?>
                            <div style="margin-top: 15px;">
                                <div class="score-label">Senin En Yüksek Skorun</div>
                                <div class="score-value"><?php echo number_format($user_highscore); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($loggedin): ?>
                        <div class="stats-card">
                            <h2>Rozetlerim</h2>
                            <?php if (empty($user_badges)): ?>
                            <div class="no-badges">Henüz hiç rozet kazanmadın. Rozet kazanmak için oynamaya devam et!</div>
                            <?php else: ?>
                            <div class="badge-list">
                                <?php foreach ($user_badges as $badge): ?>
                                <div class="badge-item">
                                    <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                                    <div class="badge-info">
                                        <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                                        <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <input type="hidden" id="game-id" value="<?php echo $game_info['id']; ?>">
    
    <script src="../games/js/tictactoe.js"></script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>