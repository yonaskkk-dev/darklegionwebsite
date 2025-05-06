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

// URL parametrelerini al
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Aktif anketleri getir
$polls_query = "SELECT p.*, u.username, 
                (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id) as vote_count,
                (SELECT COUNT(*) FROM poll_options WHERE poll_id = p.id) as option_count
                FROM polls p
                JOIN users u ON p.user_id = u.id
                WHERE 1=1";

// Filtre durumuna göre sorguyu ayarla
if ($filter === 'active') {
    $polls_query .= " AND (p.status = 'active' AND (p.end_date IS NULL OR p.end_date > NOW()))";
} elseif ($filter === 'ended') {
    $polls_query .= " AND (p.status = 'ended' OR (p.status = 'active' AND p.end_date IS NOT NULL AND p.end_date <= NOW()))";
} elseif ($filter === 'my') {
    if (!$loggedin) {
        header("Location: login.php");
        exit;
    }
    $polls_query .= " AND p.user_id = " . $current_user_id;
} elseif ($filter === 'voted') {
    if (!$loggedin) {
        header("Location: login.php");
        exit;
    }
    $polls_query .= " AND EXISTS (SELECT 1 FROM poll_votes WHERE poll_id = p.id AND user_id = " . $current_user_id . ")";
}

// Ekleme tarihi ile yakın tarihte eklenen anketleri en üstte göster
$polls_query .= " ORDER BY p.created_at DESC LIMIT ?, ?";

$all_polls = [];
if ($stmt = mysqli_prepare($conn, $polls_query)) {
    mysqli_stmt_bind_param($stmt, "ii", $offset, $per_page);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Eğer end_date geçmişse, anketi otomatik olarak bitmiş olarak işaretle
            if ($row['status'] === 'active' && !empty($row['end_date']) && strtotime($row['end_date']) <= time()) {
                $update_status = "UPDATE polls SET status = 'ended' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_status);
                mysqli_stmt_bind_param($update_stmt, "i", $row['id']);
                mysqli_stmt_execute($update_stmt);
                $row['status'] = 'ended';
            }
            
            // Kullanıcının bu ankete oy verip vermediğini kontrol et
            $row['user_voted'] = false;
            if ($loggedin) {
                $vote_check = "SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?";
                $vote_stmt = mysqli_prepare($conn, $vote_check);
                mysqli_stmt_bind_param($vote_stmt, "ii", $row['id'], $current_user_id);
                mysqli_stmt_execute($vote_stmt);
                mysqli_stmt_store_result($vote_stmt);
                $row['user_voted'] = mysqli_stmt_num_rows($vote_stmt) > 0;
            }
            
            $all_polls[] = $row;
        }
    } else {
        $error = "Anketler yüklenirken bir hata oluştu: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

// Toplam anket sayısı
$total_polls_query = "SELECT COUNT(*) as total FROM polls p WHERE 1=1";

// Filtre durumuna göre sayaç sorgusu
if ($filter === 'active') {
    $total_polls_query .= " AND (p.status = 'active' AND (p.end_date IS NULL OR p.end_date > NOW()))";
} elseif ($filter === 'ended') {
    $total_polls_query .= " AND (p.status = 'ended' OR (p.status = 'active' AND p.end_date IS NOT NULL AND p.end_date <= NOW()))";
} elseif ($filter === 'my') {
    if ($loggedin) {
        $total_polls_query .= " AND p.user_id = " . $current_user_id;
    } else {
        $total_polls = 0;
    }
} elseif ($filter === 'voted') {
    if ($loggedin) {
        $total_polls_query .= " AND EXISTS (SELECT 1 FROM poll_votes WHERE poll_id = p.id AND user_id = " . $current_user_id . ")";
    } else {
        $total_polls = 0;
    }
}

$total_polls = 0;
if ($stmt = mysqli_prepare($conn, $total_polls_query)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $total_polls = $row['total'];
        }
    }
    mysqli_stmt_close($stmt);
}

$total_pages = ceil($total_polls / $per_page);

// Sayfa başlığı
$page_title = "Anketler | Dark Legion";

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <div class="polls-header">
                <h1>Anketler</h1>
                <?php if ($loggedin && ($role === 'admin' || $role === 'uye')): ?>
                <a href="create_poll.php" class="btn create-poll-btn">Yeni Anket Oluştur</a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="polls-filter">
                <a href="polls.php?filter=active" class="filter-link <?php echo $filter === 'active' ? 'active' : ''; ?>">Aktif Anketler</a>
                <a href="polls.php?filter=ended" class="filter-link <?php echo $filter === 'ended' ? 'active' : ''; ?>">Sonuçlanan Anketler</a>
                <?php if ($loggedin): ?>
                <a href="polls.php?filter=voted" class="filter-link <?php echo $filter === 'voted' ? 'active' : ''; ?>">Oy Verdiğim Anketler</a>
                <a href="polls.php?filter=my" class="filter-link <?php echo $filter === 'my' ? 'active' : ''; ?>">Anketlerim</a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($all_polls)): ?>
            <div class="no-polls">
                <p>Bu kriterlere uyan anket bulunamadı.</p>
                <?php if ($filter === 'my' && $loggedin): ?>
                <p>Hemen yeni bir anket oluşturabilirsiniz!</p>
                <a href="create_poll.php" class="btn">Anket Oluştur</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="polls-list">
                <?php foreach ($all_polls as $poll): ?>
                <div class="poll-card">
                    <div class="poll-status <?php echo $poll['status'] === 'active' ? 'active' : 'ended'; ?>">
                        <?php echo $poll['status'] === 'active' ? 'Aktif' : 'Sonuçlandı'; ?>
                    </div>
                    
                    <div class="poll-header">
                        <h2 class="poll-title">
                            <a href="poll_details.php?id=<?php echo $poll['id']; ?>"><?php echo htmlspecialchars($poll['title']); ?></a>
                        </h2>
                        <div class="poll-meta">
                            <span class="poll-author">Oluşturan: <a href="profile.php?id=<?php echo $poll['user_id']; ?>"><?php echo htmlspecialchars($poll['username']); ?></a></span>
                            <span class="poll-date">Tarih: <?php echo date('d.m.Y', strtotime($poll['created_at'])); ?></span>
                            <?php if (!empty($poll['end_date'])): ?>
                            <span class="poll-end-date">Bitiş: <?php echo date('d.m.Y', strtotime($poll['end_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($poll['description'])): ?>
                    <div class="poll-description"><?php echo nl2br(htmlspecialchars($poll['description'])); ?></div>
                    <?php endif; ?>
                    
                    <div class="poll-stats">
                        <div class="poll-stat">
                            <span class="stat-value"><?php echo $poll['vote_count']; ?></span>
                            <span class="stat-label">Oy</span>
                        </div>
                        <div class="poll-stat">
                            <span class="stat-value"><?php echo $poll['option_count']; ?></span>
                            <span class="stat-label">Seçenek</span>
                        </div>
                        <?php if ($poll['allow_multiple']): ?>
                        <div class="poll-stat">
                            <span class="stat-value">Çoklu</span>
                            <span class="stat-label">Seçim</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="poll-actions">
                        <?php if ($poll['status'] === 'active'): ?>
                            <?php if (!$poll['user_voted']): ?>
                            <a href="poll_details.php?id=<?php echo $poll['id']; ?>" class="btn">Oy Ver</a>
                            <?php else: ?>
                            <a href="poll_details.php?id=<?php echo $poll['id']; ?>" class="btn">Sonuçları Gör</a>
                            <?php endif; ?>
                        <?php else: ?>
                        <a href="poll_details.php?id=<?php echo $poll['id']; ?>" class="btn">Sonuçları Gör</a>
                        <?php endif; ?>
                        
                        <?php if ($loggedin && ($poll['user_id'] === $current_user_id || $role === 'admin')): ?>
                        <a href="create_poll.php?id=<?php echo $poll['id']; ?>" class="btn edit-btn">Düzenle</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="polls.php?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>" class="pagination-link">&laquo; Önceki</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                <a href="polls.php?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="polls.php?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>" class="pagination-link">Sonraki &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Anketler Sayfası Stilleri */
.polls-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.create-poll-btn {
    background-color: var(--accent-color);
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s;
}

.create-poll-btn:hover {
    background-color: #5E35B1;
}

.polls-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 25px;
    background-color: var(--secondary-bg);
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.filter-link {
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    color: var(--text-color);
    background-color: rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.filter-link:hover {
    background-color: rgba(0, 0, 0, 0.2);
}

.filter-link.active {
    background-color: var(--accent-color);
    color: white;
}

.polls-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.poll-card {
    background-color: var(--secondary-bg);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    position: relative;
    transition: transform 0.3s;
}

.poll-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.poll-status {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
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

.poll-header {
    margin-bottom: 10px;
}

.poll-title {
    margin: 0 0 5px;
    font-size: 20px;
}

.poll-title a {
    color: var(--text-color);
    text-decoration: none;
}

.poll-title a:hover {
    color: var(--accent-color);
}

.poll-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.poll-author a {
    color: var(--accent-color);
    text-decoration: none;
}

.poll-description {
    margin-bottom: 15px;
    color: var(--text-color);
    line-height: 1.5;
}

.poll-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.poll-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    background-color: rgba(0, 0, 0, 0.1);
    padding: 8px 15px;
    border-radius: 4px;
    min-width: 60px;
}

.stat-value {
    font-weight: bold;
    font-size: 16px;
    color: var(--accent-color);
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
}

.poll-actions {
    display: flex;
    gap: 10px;
}

.poll-actions .btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    transition: background-color 0.3s;
    background-color: var(--accent-color);
    color: white;
}

.poll-actions .btn:hover {
    background-color: #5E35B1;
}

.poll-actions .edit-btn {
    background-color: #424242;
}

.poll-actions .edit-btn:hover {
    background-color: #616161;
}

.no-polls {
    background-color: var(--secondary-bg);
    padding: 30px;
    text-align: center;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.no-polls p {
    margin-bottom: 15px;
    color: var(--text-secondary);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.pagination-link {
    padding: 8px 12px;
    background-color: var(--secondary-bg);
    border-radius: 4px;
    text-decoration: none;
    color: var(--text-color);
    transition: all 0.3s;
}

.pagination-link:hover {
    background-color: rgba(0, 0, 0, 0.2);
}

.pagination-link.active {
    background-color: var(--accent-color);
    color: white;
}

@media (max-width: 768px) {
    .polls-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .polls-filter {
        flex-direction: column;
        gap: 5px;
    }
    
    .poll-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .poll-actions {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>