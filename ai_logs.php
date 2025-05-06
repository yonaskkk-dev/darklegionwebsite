<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Admin ve AI erişim kontrolü
yetkiKontrol('admin');
aiErisimKontrol();

// Tüm AI sohbet loglarını al
$logs = array();

// Silme işlemi
if (isset($_GET['delete_log']) && !empty($_GET['delete_log'])) {
    $log_id = $_GET['delete_log'];
    
    $delete_sql = "DELETE FROM ai_logs WHERE id = ?";
    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
        mysqli_stmt_bind_param($delete_stmt, "i", $log_id);
        if (mysqli_stmt_execute($delete_stmt)) {
            $delete_success = true;
        } else {
            $delete_error = "Log silinirken bir hata oluştu.";
        }
        mysqli_stmt_close($delete_stmt);
    }
}

// Logs filtreleme
$filter_user = isset($_GET['user_id']) ? $_GET['user_id'] : null;

// Kullanıcı listesini al (filtreleme için)
$users = array();
$users_sql = "SELECT id, username FROM users WHERE ai_access = 1";
if ($users_stmt = mysqli_prepare($conn, $users_sql)) {
    mysqli_stmt_execute($users_stmt);
    $users_result = mysqli_stmt_get_result($users_stmt);
    
    while ($user_row = mysqli_fetch_assoc($users_result)) {
        $users[$user_row['id']] = $user_row['username'];
    }
    
    mysqli_stmt_close($users_stmt);
}

// Logları sorgula
$sql = "SELECT l.id, l.user_id, u.username, l.question, l.answer, l.created_at 
        FROM ai_logs l
        LEFT JOIN users u ON l.user_id = u.id";

// Filtre uygula
if ($filter_user) {
    $sql .= " WHERE l.user_id = ?";
}

$sql .= " ORDER BY l.created_at DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    if ($filter_user) {
        mysqli_stmt_bind_param($stmt, "i", $filter_user);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $logs[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-area">
    <h1>AI Sohbet Logları</h1>
    
    <?php if (isset($delete_success)): ?>
    <div class="alert alert-success">
        <p>Log başarıyla silindi.</p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger">
        <p><?php echo $delete_error; ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Filtre -->
    <div class="filter-section">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
            <div class="form-group">
                <label for="user_id">Kullanıcıya Göre Filtrele:</label>
                <select name="user_id" id="user_id" onchange="this.form.submit()">
                    <option value="">-- Tüm Kullanıcılar --</option>
                    <?php foreach ($users as $id => $username): ?>
                    <option value="<?php echo $id; ?>" <?php echo $filter_user == $id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($username); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    
    <!-- Log Listesi -->
    <?php if (empty($logs)): ?>
    <div class="alert alert-info">
        <p>Görüntülenecek log bulunamadı.</p>
    </div>
    <?php else: ?>
    <div class="logs-container">
        <?php foreach ($logs as $log): ?>
        <div class="log-entry">
            <div class="log-header">
                <div class="log-info">
                    <span class="username"><?php echo htmlspecialchars($log['username']); ?></span>
                    <span class="timestamp"><?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?></span>
                </div>
                <div class="log-actions">
                    <a href="ai_logs.php?delete_log=<?php echo $log['id']; ?>" 
                       onclick="return confirm('Bu logu silmek istediğinizden emin misiniz?');" 
                       class="btn btn-danger btn-sm">Sil</a>
                </div>
            </div>
            
            <div class="log-content">
                <div class="log-question">
                    <h3>Soru:</h3>
                    <p><?php echo nl2br(htmlspecialchars($log['question'])); ?></p>
                </div>
                <div class="log-answer">
                    <h3>Cevap:</h3>
                    <p><?php echo nl2br(htmlspecialchars($log['answer'])); ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
    .filter-section {
        margin-bottom: 20px;
        padding: 15px;
        background-color: var(--secondary-bg);
        border-radius: 5px;
    }
    
    .filter-section .form-group {
        display: flex;
        align-items: center;
    }
    
    .filter-section label {
        margin-right: 10px;
        margin-bottom: 0;
    }
    
    .filter-section select {
        max-width: 300px;
    }
    
    .logs-container {
        margin-top: 20px;
    }
    
    .log-entry {
        background-color: var(--secondary-bg);
        border-radius: 5px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .log-header {
        padding: 10px 15px;
        background-color: rgba(0, 0, 0, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .log-info {
        display: flex;
        flex-direction: column;
    }
    
    .log-info .username {
        font-weight: bold;
        color: var(--accent-color);
    }
    
    .log-info .timestamp {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .log-content {
        padding: 15px;
    }
    
    .log-question, .log-answer {
        margin-bottom: 15px;
    }
    
    .log-question h3, .log-answer h3 {
        font-size: 16px;
        margin-bottom: 5px;
    }
    
    .log-question h3 {
        color: var(--accent-color);
    }
    
    .log-answer h3 {
        color: var(--success-color);
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }
</style>

<?php include 'includes/footer.php'; ?>