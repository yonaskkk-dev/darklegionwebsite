<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et (ama herkes görüntüleyebilir)
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$current_user_id = $loggedin ? $_SESSION['id'] : 0;
$username = $loggedin ? $_SESSION['username'] : 'Misafir';
$role = $loggedin ? $_SESSION['role'] : 'misafir';

// Mesaj ve hata değişkenleri
$message = '';
$error = '';

// Anket ID'sini al
$poll_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($poll_id <= 0) {
    header("Location: polls.php");
    exit;
}

// Oy işlemini kontrol et
if (isset($_POST['vote']) && $loggedin) {
    $option_ids = isset($_POST['option_id']) ? $_POST['option_id'] : [];
    
    // Önce kullanıcının daha önce oy verip vermediğini kontrol et
    $vote_check = "SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($conn, $vote_check)) {
        mysqli_stmt_bind_param($stmt, "ii", $poll_id, $current_user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Bu ankete daha önce oy verdiniz.";
        } else {
            // Anketin çoklu seçimi destekleyip desteklemediğini kontrol et
            $poll_check = "SELECT allow_multiple, status FROM polls WHERE id = ?";
            if ($poll_stmt = mysqli_prepare($conn, $poll_check)) {
                mysqli_stmt_bind_param($poll_stmt, "i", $poll_id);
                mysqli_stmt_execute($poll_stmt);
                $poll_result = mysqli_stmt_get_result($poll_stmt);
                
                if ($poll_row = mysqli_fetch_assoc($poll_result)) {
                    $allow_multiple = $poll_row['allow_multiple'];
                    $status = $poll_row['status'];
                    
                    if ($status !== 'active') {
                        $error = "Bu anket artık aktif değil.";
                    } else {
                        // Tek seçenek istendiğinde birden fazla seçim yapılmasını önle
                        if (!$allow_multiple && count($option_ids) > 1) {
                            $error = "Bu ankette sadece bir seçeneği seçebilirsiniz.";
                        } else if (empty($option_ids)) {
                            $error = "Lütfen en az bir seçenek seçin.";
                        } else {
                            // Her bir seçim için oy ekle
                            $vote_insert = "INSERT INTO poll_votes (poll_id, option_id, user_id, ip_address) VALUES (?, ?, ?, ?)";
                            if ($vote_stmt = mysqli_prepare($conn, $vote_insert)) {
                                $ip_address = $_SERVER['REMOTE_ADDR'];
                                $success = true;
                                
                                foreach ($option_ids as $option_id) {
                                    mysqli_stmt_bind_param($vote_stmt, "iiis", $poll_id, $option_id, $current_user_id, $ip_address);
                                    if (!mysqli_stmt_execute($vote_stmt)) {
                                        $success = false;
                                        break;
                                    }
                                }
                                
                                if ($success) {
                                    $message = "Oyunuz başarıyla kaydedildi!";
                                } else {
                                    $error = "Oy verirken bir hata oluştu: " . mysqli_error($conn);
                                }
                            }
                        }
                    }
                } else {
                    $error = "Anket bulunamadı.";
                }
            }
        }
    }
}

// Yorum ekleme işlemini kontrol et
if (isset($_POST['add_comment']) && $loggedin) {
    $comment_text = trim($_POST['comment']);
    
    if (!empty($comment_text)) {
        $comment_insert = "INSERT INTO poll_comments (poll_id, user_id, comment) VALUES (?, ?, ?)";
        if ($comment_stmt = mysqli_prepare($conn, $comment_insert)) {
            mysqli_stmt_bind_param($comment_stmt, "iis", $poll_id, $current_user_id, $comment_text);
            
            if (mysqli_stmt_execute($comment_stmt)) {
                $message = "Yorumunuz başarıyla eklendi!";
            } else {
                $error = "Yorum eklenirken bir hata oluştu: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Yorum boş olamaz.";
    }
}

// Anket bilgilerini getir
$poll_data = null;
$poll_query = "SELECT p.*, u.username FROM polls p
              JOIN users u ON p.user_id = u.id
              WHERE p.id = ?";

if ($stmt = mysqli_prepare($conn, $poll_query)) {
    mysqli_stmt_bind_param($stmt, "i", $poll_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $poll_data = $row;
            
            // Eğer end_date geçmişse, anketi otomatik olarak bitmiş olarak işaretle
            if ($poll_data['status'] === 'active' && !empty($poll_data['end_date']) && strtotime($poll_data['end_date']) <= time()) {
                $update_status = "UPDATE polls SET status = 'ended' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_status);
                mysqli_stmt_bind_param($update_stmt, "i", $poll_id);
                mysqli_stmt_execute($update_stmt);
                $poll_data['status'] = 'ended';
            }
        } else {
            $error = "Anket bulunamadı.";
            include 'includes/header.php';
            echo "<div class='container'><div class='alert alert-danger'>$error</div><a href='polls.php' class='btn'>Anketlere Dön</a></div>";
            include 'includes/footer.php';
            exit;
        }
    } else {
        $error = "Anket yüklenirken bir hata oluştu: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

// Anket seçeneklerini getir
$options = [];
$options_query = "SELECT * FROM poll_options WHERE poll_id = ? ORDER BY option_order";

if ($stmt = mysqli_prepare($conn, $options_query)) {
    mysqli_stmt_bind_param($stmt, "i", $poll_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Her bir seçenek için oy sayısını hesapla
            $vote_count_query = "SELECT COUNT(*) as vote_count FROM poll_votes WHERE option_id = ?";
            $vote_stmt = mysqli_prepare($conn, $vote_count_query);
            mysqli_stmt_bind_param($vote_stmt, "i", $row['id']);
            mysqli_stmt_execute($vote_stmt);
            $vote_result = mysqli_stmt_get_result($vote_stmt);
            $vote_row = mysqli_fetch_assoc($vote_result);
            
            $row['vote_count'] = $vote_row['vote_count'];
            $options[] = $row;
        }
    } else {
        $error = "Anket seçenekleri yüklenirken bir hata oluştu: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

// Toplam oy sayısını hesapla
$total_votes = 0;
foreach ($options as $option) {
    $total_votes += $option['vote_count'];
}

// Kullanıcının oy verdiği seçenekleri getir
$user_votes = [];
if ($loggedin) {
    $user_votes_query = "SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $user_votes_query)) {
        mysqli_stmt_bind_param($stmt, "ii", $poll_id, $current_user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $user_votes[] = $row['option_id'];
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Anket yorumlarını getir
$comments = [];
$comments_query = "SELECT c.*, u.username FROM poll_comments c
                  JOIN users u ON c.user_id = u.id
                  WHERE c.poll_id = ?
                  ORDER BY c.created_at DESC";

if ($stmt = mysqli_prepare($conn, $comments_query)) {
    mysqli_stmt_bind_param($stmt, "i", $poll_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $comments[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Kullanıcının oy verme hakkını kontrol et
$can_vote = $loggedin && empty($user_votes) && $poll_data['status'] === 'active';

// Sayfa başlığı
$page_title = $poll_data['title'] . " | Anket | Dark Legion";

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <div class="poll-details">
                <div class="back-link">
                    <a href="polls.php">&laquo; Anketlere Dön</a>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="poll-header">
                    <h1 class="poll-title"><?php echo htmlspecialchars($poll_data['title']); ?></h1>
                    
                    <div class="poll-status <?php echo $poll_data['status'] === 'active' ? 'active' : 'ended'; ?>">
                        <?php echo $poll_data['status'] === 'active' ? 'Aktif' : 'Sonuçlandı'; ?>
                    </div>
                    
                    <div class="poll-meta">
                        <div class="poll-meta-item">
                            <span class="meta-label">Oluşturan:</span>
                            <span class="meta-value"><a href="profile.php?id=<?php echo $poll_data['user_id']; ?>"><?php echo htmlspecialchars($poll_data['username']); ?></a></span>
                        </div>
                        
                        <div class="poll-meta-item">
                            <span class="meta-label">Oluşturulma:</span>
                            <span class="meta-value"><?php echo date('d.m.Y H:i', strtotime($poll_data['created_at'])); ?></span>
                        </div>
                        
                        <?php if (!empty($poll_data['end_date'])): ?>
                        <div class="poll-meta-item">
                            <span class="meta-label">Bitiş:</span>
                            <span class="meta-value"><?php echo date('d.m.Y H:i', strtotime($poll_data['end_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="poll-meta-item">
                            <span class="meta-label">Toplam Oy:</span>
                            <span class="meta-value"><?php echo $total_votes; ?></span>
                        </div>
                        
                        <?php if ($poll_data['allow_multiple']): ?>
                        <div class="poll-meta-item">
                            <span class="meta-label">Seçim Türü:</span>
                            <span class="meta-value">Çoklu Seçim</span>
                        </div>
                        <?php else: ?>
                        <div class="poll-meta-item">
                            <span class="meta-label">Seçim Türü:</span>
                            <span class="meta-value">Tek Seçim</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($poll_data['description'])): ?>
                    <div class="poll-description">
                        <?php echo nl2br(htmlspecialchars($poll_data['description'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($can_vote): ?>
                <div class="poll-voting">
                    <h2>Oy Ver</h2>
                    <form action="poll_details.php?id=<?php echo $poll_id; ?>" method="post">
                        <div class="poll-options">
                            <?php foreach ($options as $option): ?>
                            <div class="poll-option">
                                <?php if ($poll_data['allow_multiple']): ?>
                                <input type="checkbox" name="option_id[]" id="option-<?php echo $option['id']; ?>" value="<?php echo $option['id']; ?>" class="poll-checkbox">
                                <?php else: ?>
                                <input type="radio" name="option_id[]" id="option-<?php echo $option['id']; ?>" value="<?php echo $option['id']; ?>" class="poll-radio">
                                <?php endif; ?>
                                <label for="option-<?php echo $option['id']; ?>"><?php echo htmlspecialchars($option['option_text']); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" name="vote" class="btn poll-submit">Oyumu Gönder</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="poll-results">
                    <h2>Sonuçlar</h2>
                    
                    <?php if ($total_votes === 0): ?>
                    <div class="no-votes">
                        <p>Henüz hiç oy kullanılmamış.</p>
                    </div>
                    <?php else: ?>
                    <div class="results-chart">
                        <?php foreach ($options as $option): ?>
                        <?php 
                            $percentage = $total_votes > 0 ? round(($option['vote_count'] / $total_votes) * 100) : 0;
                            $is_user_voted = in_array($option['id'], $user_votes);
                        ?>
                        <div class="result-item <?php echo $is_user_voted ? 'user-voted' : ''; ?>">
                            <div class="result-text">
                                <span class="result-option"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                <span class="result-stats">
                                    <span class="vote-count"><?php echo $option['vote_count']; ?> oy</span>
                                    <span class="vote-percentage"><?php echo $percentage; ?>%</span>
                                    <?php if ($is_user_voted): ?>
                                    <span class="user-vote-marker">✓ Senin oyun</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="result-bar-container">
                                <div class="result-bar" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="poll-comments-section">
                    <h2>Yorumlar (<?php echo count($comments); ?>)</h2>
                    
                    <?php if ($loggedin): ?>
                    <div class="comment-form">
                        <form action="poll_details.php?id=<?php echo $poll_id; ?>" method="post">
                            <div class="form-group">
                                <textarea name="comment" rows="3" placeholder="Yorumunuzu yazın..." required></textarea>
                            </div>
                            <button type="submit" name="add_comment" class="btn">Yorum Ekle</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="login-to-comment">
                        <p>Yorum yapmak için <a href="login.php">giriş yapın</a>.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($comments)): ?>
                    <div class="no-comments">
                        <p>Henüz hiç yorum yok. İlk yorumu siz yapın!</p>
                    </div>
                    <?php else: ?>
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <span class="comment-author"><a href="profile.php?id=<?php echo $comment['user_id']; ?>"><?php echo htmlspecialchars($comment['username']); ?></a></span>
                                <span class="comment-date"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($loggedin && ($poll_data['user_id'] === $current_user_id || $role === 'admin')): ?>
                <div class="poll-admin-actions">
                    <h2>Anket Yönetimi</h2>
                    <div class="admin-buttons">
                        <a href="create_poll.php?id=<?php echo $poll_id; ?>" class="btn">Anketi Düzenle</a>
                        
                        <?php if ($poll_data['status'] === 'active'): ?>
                        <form action="end_poll.php" method="post" class="inline-form">
                            <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">
                            <button type="submit" name="end_poll" class="btn danger-btn" onclick="return confirm('Anketi sonlandırmak istediğinize emin misiniz?');">Anketi Sonlandır</button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($role === 'admin'): ?>
                        <form action="delete_poll.php" method="post" class="inline-form">
                            <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">
                            <button type="submit" name="delete_poll" class="btn danger-btn" onclick="return confirm('Anketi silmek istediğinize emin misiniz? Bu işlem geri alınamaz!');">Anketi Sil</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Anket Detayları Sayfası Stilleri */
.poll-details {
    max-width: 800px;
    margin: 0 auto;
}

.back-link {
    margin-bottom: 20px;
}

.back-link a {
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.3s;
}

.back-link a:hover {
    color: var(--accent-color);
}

.poll-header {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    position: relative;
}

.poll-title {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 24px;
    padding-right: 80px; /* Poll status için yer aç */
}

.poll-status {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: bold;
}

.poll-status.active {
    background-color: rgba(76, 175, 80, 0.2);
    color: #4CAF50;
}

.poll-status.ended {
    background-color: rgba(158, 158, 158, 0.2);
    color: #9E9E9E;
}

.poll-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.poll-meta-item {
    font-size: 14px;
}

.meta-label {
    color: var(--text-secondary);
    margin-right: 5px;
}

.meta-value {
    color: var(--text-color);
    font-weight: bold;
}

.meta-value a {
    color: var(--accent-color);
    text-decoration: none;
}

.poll-description {
    margin-top: 15px;
    line-height: 1.5;
    color: var(--text-color);
}

.poll-voting {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.poll-voting h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 20px;
    color: var(--accent-color);
}

.poll-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.poll-option {
    display: flex;
    align-items: center;
    gap: 10px;
}

.poll-checkbox, .poll-radio {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.poll-option label {
    cursor: pointer;
    flex: 1;
    padding: 10px;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    transition: background-color 0.3s;
}

.poll-option label:hover {
    background-color: rgba(0, 0, 0, 0.2);
}

.poll-submit {
    background-color: var(--accent-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s;
}

.poll-submit:hover {
    background-color: #5E35B1;
}

.poll-results {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.poll-results h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 20px;
    color: var(--accent-color);
}

.no-votes {
    text-align: center;
    padding: 20px;
    color: var(--text-secondary);
    font-style: italic;
}

.results-chart {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.result-item {
    padding: 10px;
    border-radius: 4px;
    background-color: rgba(0, 0, 0, 0.05);
    transition: background-color 0.3s;
}

.result-item:hover {
    background-color: rgba(0, 0, 0, 0.1);
}

.result-item.user-voted {
    background-color: rgba(94, 53, 177, 0.1);
    border-left: 3px solid var(--accent-color);
}

.result-text {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.result-option {
    font-weight: bold;
    color: var(--text-color);
}

.result-stats {
    display: flex;
    gap: 10px;
    color: var(--text-secondary);
    font-size: 14px;
}

.vote-percentage {
    font-weight: bold;
    color: var(--accent-color);
}

.user-vote-marker {
    color: var(--accent-color);
    font-weight: bold;
}

.result-bar-container {
    height: 10px;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 5px;
    overflow: hidden;
}

.result-bar {
    height: 100%;
    background-color: var(--accent-color);
    border-radius: 5px;
    transition: width 0.5s ease-in-out;
}

.poll-comments-section {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.poll-comments-section h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 20px;
    color: var(--accent-color);
}

.comment-form {
    margin-bottom: 20px;
}

.comment-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--text-color);
    resize: vertical;
    margin-bottom: 10px;
}

.login-to-comment {
    text-align: center;
    padding: 15px;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    margin-bottom: 20px;
}

.login-to-comment a {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: bold;
}

.no-comments {
    text-align: center;
    padding: 20px;
    color: var(--text-secondary);
    font-style: italic;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.comment-item {
    padding: 15px;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.comment-author {
    font-weight: bold;
}

.comment-author a {
    color: var(--accent-color);
    text-decoration: none;
}

.comment-date {
    color: var(--text-secondary);
}

.comment-content {
    line-height: 1.5;
}

.poll-admin-actions {
    background-color: var(--secondary-bg);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.poll-admin-actions h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 20px;
    color: var(--accent-color);
}

.admin-buttons {
    display: flex;
    gap: 10px;
}

.admin-buttons .btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    background-color: var(--accent-color);
    color: white;
    transition: background-color 0.3s;
}

.admin-buttons .btn:hover {
    background-color: #5E35B1;
}

.admin-buttons .danger-btn {
    background-color: #F44336;
}

.admin-buttons .danger-btn:hover {
    background-color: #D32F2F;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: rgba(76, 175, 80, 0.2);
    color: #4CAF50;
}

.alert-danger {
    background-color: rgba(244, 67, 54, 0.2);
    color: #F44336;
}

.inline-form {
    display: inline;
}

@media (max-width: 768px) {
    .poll-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .result-text {
        flex-direction: column;
        gap: 5px;
    }
    
    .result-stats {
        flex-direction: column;
        gap: 3px;
    }
    
    .admin-buttons {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>