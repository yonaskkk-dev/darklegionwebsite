<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum kontrolü
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Sadece adminler ve normal üyeler anket oluşturabilir
if ($role !== 'admin' && $role !== 'uye') {
    header("Location: polls.php");
    exit;
}

// Mesaj ve hata değişkenleri
$message = '';
$error = '';

// Anket ID'sini kontrol et (düzenleme durumu için)
$edit_mode = false;
$poll_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Varsayılan değerler
$poll_data = [
    'title' => '',
    'description' => '',
    'status' => 'active',
    'allow_multiple' => 0,
    'end_date' => '',
    'options' => [
        ['id' => 0, 'option_text' => '', 'option_order' => 1],
        ['id' => 0, 'option_text' => '', 'option_order' => 2]
    ]
];

// Düzenleme modunda, mevcut anket bilgilerini getir
if ($poll_id > 0) {
    $edit_mode = true;
    
    // Anket bilgilerini al
    $poll_query = "SELECT * FROM polls WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $poll_query)) {
        mysqli_stmt_bind_param($stmt, "i", $poll_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                // Sadece kendi anketini veya admin ise herhangi bir anketi düzenleyebilir
                if ($row['user_id'] !== $current_user_id && $role !== 'admin') {
                    header("Location: polls.php");
                    exit;
                }
                
                $poll_data['title'] = $row['title'];
                $poll_data['description'] = $row['description'];
                $poll_data['status'] = $row['status'];
                $poll_data['allow_multiple'] = $row['allow_multiple'];
                $poll_data['end_date'] = $row['end_date'] ? date('Y-m-d\TH:i', strtotime($row['end_date'])) : '';
                
                // Seçenekleri al
                $options_query = "SELECT * FROM poll_options WHERE poll_id = ? ORDER BY option_order";
                if ($options_stmt = mysqli_prepare($conn, $options_query)) {
                    mysqli_stmt_bind_param($options_stmt, "i", $poll_id);
                    
                    if (mysqli_stmt_execute($options_stmt)) {
                        $options_result = mysqli_stmt_get_result($options_stmt);
                        
                        $poll_data['options'] = [];
                        while ($option = mysqli_fetch_assoc($options_result)) {
                            $poll_data['options'][] = [
                                'id' => $option['id'],
                                'option_text' => $option['option_text'],
                                'option_order' => $option['option_order']
                            ];
                        }
                    }
                }
            } else {
                header("Location: polls.php");
                exit;
            }
        }
    }
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_poll'])) {
    // Form verilerini al
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $options = isset($_POST['options']) ? $_POST['options'] : [];
    
    // Doğrulama
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Anket başlığı boş olamaz.";
    }
    
    if (count($options) < 2) {
        $errors[] = "En az iki seçenek eklemelisiniz.";
    } else {
        foreach ($options as $option) {
            if (empty(trim($option))) {
                $errors[] = "Boş seçenek olamaz.";
                break;
            }
        }
    }
    
    // Hata yoksa kaydet
    if (empty($errors)) {
        // Veritabanı işlemleri için transaction başlat
        mysqli_begin_transaction($conn);
        
        try {
            if ($edit_mode) {
                // Mevcut anketi güncelle
                $update_query = "UPDATE polls SET 
                                title = ?, 
                                description = ?, 
                                status = ?, 
                                allow_multiple = ?, 
                                end_date = ?, 
                                updated_at = NOW() 
                                WHERE id = ?";
                
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssisi", $title, $description, $status, $allow_multiple, $end_date, $poll_id);
                mysqli_stmt_execute($stmt);
                
                // Mevcut seçenekleri sil (yeniden eklenecek)
                $delete_options = "DELETE FROM poll_options WHERE poll_id = ?";
                $stmt = mysqli_prepare($conn, $delete_options);
                mysqli_stmt_bind_param($stmt, "i", $poll_id);
                mysqli_stmt_execute($stmt);
                
                // Yeni seçenekleri ekle
                $insert_option = "INSERT INTO poll_options (poll_id, option_text, option_order) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_option);
                
                $option_order = 1;
                foreach ($options as $option_text) {
                    if (!empty(trim($option_text))) {
                        mysqli_stmt_bind_param($stmt, "isi", $poll_id, $option_text, $option_order);
                        mysqli_stmt_execute($stmt);
                        $option_order++;
                    }
                }
                
                $message = "Anket başarıyla güncellendi!";
            } else {
                // Yeni anket oluştur
                $insert_query = "INSERT INTO polls (user_id, title, description, status, allow_multiple, end_date) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "isssss", $current_user_id, $title, $description, $status, $allow_multiple, $end_date);
                mysqli_stmt_execute($stmt);
                
                $new_poll_id = mysqli_insert_id($conn);
                
                // Seçenekleri ekle
                $insert_option = "INSERT INTO poll_options (poll_id, option_text, option_order) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_option);
                
                $option_order = 1;
                foreach ($options as $option_text) {
                    if (!empty(trim($option_text))) {
                        mysqli_stmt_bind_param($stmt, "isi", $new_poll_id, $option_text, $option_order);
                        mysqli_stmt_execute($stmt);
                        $option_order++;
                    }
                }
                
                $message = "Yeni anket başarıyla oluşturuldu!";
                $poll_id = $new_poll_id;
            }
            
            // İşlemleri onayla
            mysqli_commit($conn);
            
            // Detay sayfasına yönlendir
            header("Location: poll_details.php?id=$poll_id&message=" . urlencode($message));
            exit;
        } catch (Exception $e) {
            // Hata durumunda geri al
            mysqli_rollback($conn);
            $error = "Anket kaydedilirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Sayfa başlığı
$page_title = $edit_mode ? "Anket Düzenle | Dark Legion" : "Yeni Anket Oluştur | Dark Legion";

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <div class="create-poll-container">
                <div class="back-link">
                    <a href="polls.php">&laquo; Anketlere Dön</a>
                </div>
                
                <h1><?php echo $edit_mode ? 'Anketi Düzenle' : 'Yeni Anket Oluştur'; ?></h1>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form action="<?php echo $edit_mode ? "create_poll.php?id=$poll_id" : 'create_poll.php'; ?>" method="post" id="poll-form">
                    <div class="form-group">
                        <label for="title">Anket Başlığı <span class="required">*</span></label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($poll_data['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Açıklama</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($poll_data['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Durum</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo $poll_data['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="ended" <?php echo $poll_data['status'] === 'ended' ? 'selected' : ''; ?>>Sonlandırılmış</option>
                            <option value="draft" <?php echo $poll_data['status'] === 'draft' ? 'selected' : ''; ?>>Taslak</option>
                        </select>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="allow_multiple" name="allow_multiple" <?php echo $poll_data['allow_multiple'] ? 'checked' : ''; ?>>
                        <label for="allow_multiple">Çoklu seçime izin ver</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">Bitiş Tarihi (İsteğe bağlı)</label>
                        <input type="datetime-local" id="end_date" name="end_date" value="<?php echo $poll_data['end_date']; ?>">
                        <small>Anketin otomatik olarak sonlandırılacağı tarihi belirleyin. Boş bırakırsanız, anket elle sonlandırılana kadar aktif kalır.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Seçenekler <span class="required">*</span></label>
                        <div id="options-container">
                            <?php foreach ($poll_data['options'] as $i => $option): ?>
                            <div class="option-item">
                                <input type="text" name="options[]" value="<?php echo htmlspecialchars($option['option_text']); ?>" placeholder="Seçenek <?php echo $i + 1; ?>" <?php echo $i < 2 ? 'required' : ''; ?>>
                                <button type="button" class="remove-option" <?php echo count($poll_data['options']) <= 2 ? 'disabled' : ''; ?>>✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" id="add-option" class="btn secondary-btn">+ Seçenek Ekle</button>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_poll" class="btn">
                            <?php echo $edit_mode ? 'Anketi Güncelle' : 'Anketi Oluştur'; ?>
                        </button>
                        
                        <a href="polls.php" class="btn cancel-btn">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Anket Oluşturma Sayfası Stilleri */
.create-poll-container {
    max-width: 700px;
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

.create-poll-container h1 {
    margin-bottom: 20px;
    color: var(--text-color);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-color);
    font-weight: bold;
}

.form-group input[type="text"],
.form-group input[type="datetime-local"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--text-color);
}

.form-group textarea {
    resize: vertical;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin: 0;
}

.checkbox-group label {
    margin: 0;
}

.required {
    color: #F44336;
}

small {
    display: block;
    margin-top: 5px;
    color: var(--text-secondary);
    font-size: 12px;
}

#options-container {
    margin-bottom: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.option-item {
    display: flex;
    gap: 10px;
}

.remove-option {
    width: 30px;
    height: 30px;
    background-color: rgba(244, 67, 54, 0.1);
    color: #F44336;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: background-color 0.3s;
}

.remove-option:hover:not([disabled]) {
    background-color: rgba(244, 67, 54, 0.2);
}

.remove-option[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    transition: background-color 0.3s;
}

.secondary-btn {
    background-color: rgba(0, 0, 0, 0.1);
    color: var(--text-color);
}

.secondary-btn:hover {
    background-color: rgba(0, 0, 0, 0.2);
}

.form-actions {
    display: flex;
    justify-content: flex-start;
    gap: 15px;
    margin-top: 30px;
}

.form-actions .btn {
    background-color: var(--accent-color);
    color: white;
}

.form-actions .btn:hover {
    background-color: #5E35B1;
}

.cancel-btn {
    background-color: #424242 !important;
}

.cancel-btn:hover {
    background-color: #616161 !important;
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

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('poll-form');
    const optionsContainer = document.getElementById('options-container');
    const addOptionBtn = document.getElementById('add-option');
    
    // Seçenek ekleme
    addOptionBtn.addEventListener('click', function() {
        const optionCount = optionsContainer.children.length + 1;
        const optionItem = document.createElement('div');
        optionItem.className = 'option-item';
        
        optionItem.innerHTML = `
            <input type="text" name="options[]" placeholder="Seçenek ${optionCount}" required>
            <button type="button" class="remove-option">✕</button>
        `;
        
        optionsContainer.appendChild(optionItem);
        
        // Tüm kaldırma butonlarını etkinleştir (en az 2 seçenek olmalı)
        updateRemoveButtons();
        
        // Yeni eklenen input'a odaklan
        const newInput = optionItem.querySelector('input');
        newInput.focus();
    });
    
    // Seçenek silme (delegasyon ile)
    optionsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-option')) {
            e.target.parentElement.remove();
            updateRemoveButtons();
        }
    });
    
    // Kaldırma butonlarını güncelle (en az 2 seçenek gerekli)
    function updateRemoveButtons() {
        const removeButtons = document.querySelectorAll('.remove-option');
        
        if (optionsContainer.children.length <= 2) {
            removeButtons.forEach(button => {
                button.disabled = true;
            });
        } else {
            removeButtons.forEach(button => {
                button.disabled = false;
            });
        }
    }
    
    // Form gönderilmeden önce doğrulama
    form.addEventListener('submit', function(e) {
        const options = document.querySelectorAll('input[name="options[]"]');
        const filledOptions = Array.from(options).filter(input => input.value.trim() !== '');
        
        if (filledOptions.length < 2) {
            e.preventDefault();
            alert('En az iki seçenek eklemelisiniz.');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>