<?php
// Konfigürasyon dosyasını dahil et
require_once '../config.php';

// Sadece POST istekleri ve giriş yapmış kullanıcılar için
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Gerekli parametreleri al
$user_id = $_SESSION['id'];
$game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
$score = isset($_POST['score']) ? (int)$_POST['score'] : 0;
$level = isset($_POST['level']) ? (int)$_POST['level'] : 1;
$time_played = isset($_POST['time_played']) ? (int)$_POST['time_played'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Oyun kontrolü
if ($game_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz oyun ID']);
    exit;
}

// Oyunun var olup olmadığını kontrol et
$game_check = "SELECT * FROM games WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $game_check)) {
    mysqli_stmt_bind_param($stmt, "i", $game_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!mysqli_fetch_assoc($result)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Oyun bulunamadı']);
        exit;
    }
    
    mysqli_stmt_close($stmt);
}

// İşleme göre devam et
if ($action === 'save_score') {
    // Skoru kaydet
    $insert_score = "INSERT INTO game_scores (game_id, user_id, score, level, time_played) VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $insert_score)) {
        mysqli_stmt_bind_param($stmt, "iiiii", $game_id, $user_id, $score, $level, $time_played);
        
        if (mysqli_stmt_execute($stmt)) {
            // Başarılı
            $success = true;
            $message = 'Skor başarıyla kaydedildi';
            
            // Rozet kontrolü için yeni kazanılan rozetleri tutacak dizi
            $earned_badges = [];
            
            // Kullanıcının bu oyun için toplam skor ve oynama sayılarını hesapla
            $stats_query = "SELECT COUNT(*) as play_count, MAX(score) as max_score, SUM(time_played) as total_time
                          FROM game_scores
                          WHERE game_id = ? AND user_id = ?";
            
            if ($stats_stmt = mysqli_prepare($conn, $stats_query)) {
                mysqli_stmt_bind_param($stats_stmt, "ii", $game_id, $user_id);
                mysqli_stmt_execute($stats_stmt);
                $stats_result = mysqli_stmt_get_result($stats_stmt);
                $stats = mysqli_fetch_assoc($stats_result);
                
                $play_count = $stats['play_count'];
                $max_score = max($stats['max_score'], $score); // Yeni skor daha yüksek olabilir
                $total_time = $stats['total_time'];
                
                mysqli_stmt_close($stats_stmt);
                
                // Kullanıcının henüz kazanmadığı rozetleri al
                $badges_query = "SELECT gb.*
                                FROM game_badges gb
                                WHERE gb.game_id = ?
                                AND NOT EXISTS (
                                    SELECT 1 FROM user_game_badges ugb
                                    WHERE ugb.badge_id = gb.id AND ugb.user_id = ?
                                )";
                
                if ($badges_stmt = mysqli_prepare($conn, $badges_query)) {
                    mysqli_stmt_bind_param($badges_stmt, "ii", $game_id, $user_id);
                    mysqli_stmt_execute($badges_stmt);
                    $badges_result = mysqli_stmt_get_result($badges_stmt);
                    
                    while ($badge = mysqli_fetch_assoc($badges_result)) {
                        $requirement = $badge['requirement'];
                        $requirement_value = $badge['requirement_value'];
                        
                        $badge_earned = false;
                        
                        // Rozeti kazanma koşulunu kontrol et
                        switch ($requirement) {
                            case 'play_count':
                                $badge_earned = $play_count >= $requirement_value;
                                break;
                                
                            case 'min_score':
                                $badge_earned = $max_score >= $requirement_value;
                                break;
                                
                            case 'total_time':
                                $badge_earned = $total_time >= $requirement_value;
                                break;
                                
                            // Diğer rozet koşulları buraya eklenebilir
                        }
                        
                        // Rozeti kazandıysa kaydet
                        if ($badge_earned) {
                            $insert_badge = "INSERT INTO user_game_badges (user_id, badge_id) VALUES (?, ?)";
                            
                            if ($insert_badge_stmt = mysqli_prepare($conn, $insert_badge)) {
                                mysqli_stmt_bind_param($insert_badge_stmt, "ii", $user_id, $badge['id']);
                                
                                if (mysqli_stmt_execute($insert_badge_stmt)) {
                                    // Kazanılan rozetler listesine ekle
                                    $earned_badges[] = [
                                        'id' => $badge['id'],
                                        'name' => $badge['name'],
                                        'description' => $badge['description'],
                                        'icon' => $badge['icon']
                                    ];
                                }
                                
                                mysqli_stmt_close($insert_badge_stmt);
                            }
                        }
                    }
                    
                    mysqli_stmt_close($badges_stmt);
                }
            }
            
            // Başarılı yanıt döndür ve kazanılan rozetleri ekle
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $message,
                'badges' => $earned_badges
            ]);
            exit;
        } else {
            $success = false;
            $message = 'Veritabanı hatası: ' . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $success = false;
        $message = 'Sorgu hazırlanamadı: ' . mysqli_error($conn);
    }
} else {
    $success = false;
    $message = 'Geçersiz işlem';
}

// Hata durumunda JSON yanıt döndür
header('Content-Type: application/json');
echo json_encode(['success' => $success, 'message' => $message]);