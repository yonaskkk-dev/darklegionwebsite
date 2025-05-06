<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// AI erişim kontrolü
aiErisimKontrol();

// Kullanıcı AI sohbetlerini al
$user_id = $_SESSION['id'];
$chats = array();

$sql = "SELECT question, answer, created_at FROM ai_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $chats[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Mesaj gönderme işlemi
$chat_response = '';
$user_message = '';
$chat_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message']) && !empty($_POST['message'])) {
    $user_message = trim($_POST['message']);
    
    // AI API'ye mesajı gönder ve cevabı al
    $response = aiSohbet($user_message);
    
    if ($response['success']) {
        $chat_response = $response['message'];
        
        // Sohbeti veritabanına kaydet
        $log_sql = "INSERT INTO ai_logs (user_id, question, answer) VALUES (?, ?, ?)";
        if ($log_stmt = mysqli_prepare($conn, $log_sql)) {
            mysqli_stmt_bind_param($log_stmt, "iss", $user_id, $user_message, $chat_response);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
            
            // Sayfayı yenile (sohbet geçmişini güncellemek için)
            header("Location: ai_chat.php?success=1");
            exit;
        }
    } else {
        $chat_error = "AI yanıt verirken bir hata oluştu: " . $response['error'];
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-area">
    <h1>AI Sohbet</h1>
    
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">
        <p>Mesajınız başarıyla gönderildi.</p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($chat_error)): ?>
    <div class="alert alert-danger">
        <p><?php echo $chat_error; ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Sohbet Formu -->
    <div class="chat-form">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="message">Mesajınız</label>
                <textarea name="message" id="message" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Gönder</button>
            </div>
        </form>
    </div>
    
    <!-- Sohbet Geçmişi -->
    <div class="chat-history">
        <h2>Son Konuşmalar</h2>
        
        <?php if (empty($chats)): ?>
        <div class="alert alert-info">
            <p>Henüz hiç sohbetiniz bulunmuyor. Yukarıdaki formu kullanarak sohbete başlayabilirsiniz.</p>
        </div>
        <?php else: ?>
            <?php foreach ($chats as $chat): ?>
            <div class="chat-entry">
                <div class="chat-question">
                    <h3>Siz</h3>
                    <p><?php echo nl2br(htmlspecialchars($chat['question'])); ?></p>
                    <div class="chat-time"><?php echo date('d.m.Y H:i', strtotime($chat['created_at'])); ?></div>
                </div>
                <div class="chat-answer">
                    <h3>AI</h3>
                    <p><?php echo nl2br(htmlspecialchars($chat['answer'])); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .chat-form textarea {
        width: 100%;
        padding: 15px;
        border-radius: 5px;
        background-color: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        resize: vertical;
    }
    
    .chat-history {
        margin-top: 40px;
    }
    
    .chat-entry {
        margin-bottom: 30px;
        background-color: var(--secondary-bg);
        border-radius: 5px;
        overflow: hidden;
    }
    
    .chat-question,
    .chat-answer {
        padding: 15px;
    }
    
    .chat-question {
        border-bottom: 1px solid var(--border-color);
    }
    
    .chat-question h3,
    .chat-answer h3 {
        font-size: 16px;
        margin-bottom: 10px;
    }
    
    .chat-question h3 {
        color: var(--accent-color);
    }
    
    .chat-answer h3 {
        color: var(--success-color);
    }
    
    .chat-time {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 10px;
        text-align: right;
    }
</style>

<?php include 'includes/footer.php'; ?>