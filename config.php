<?php
// Veritabanı bağlantı bilgileri
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'darklegion');

// CortexAPI için API anahtarı
define('CORTEX_API_KEY', 'sk-6aaced5526024c85a9d861eed4610ccb');

// Veritabanı bağlantısını oluştur
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Bağlantı kontrolü
if (!$conn) {
    die("Bağlantı hatası: " . mysqli_connect_error());
}

// Veritabanını oluştur (eğer yoksa)
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (mysqli_query($conn, $sql)) {
    mysqli_select_db($conn, DB_NAME);
    
    // Kullanıcılar tablosunu oluştur
    $users_table = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'uye', 'misafir') NOT NULL DEFAULT 'misafir',
        ai_access TINYINT(1) NOT NULL DEFAULT 0,
        profile_url VARCHAR(50) DEFAULT NULL UNIQUE,
        avatar VARCHAR(255) DEFAULT NULL,
        cover_image VARCHAR(255) DEFAULT NULL,
        cover_video VARCHAR(255) DEFAULT NULL,
        background_music VARCHAR(255) DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        interests TEXT DEFAULT NULL,
        location VARCHAR(100) DEFAULT NULL,
        social_links TEXT DEFAULT NULL,
        badges TEXT DEFAULT NULL,
        profile_views INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $users_table);
    
    // Dosya yüklemeleri tablosunu oluştur
    $uploads_table = "CREATE TABLE IF NOT EXISTS uploads (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        file_url VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $uploads_table);
    
    // AI sohbet logları tablosunu oluştur
    $ai_logs_table = "CREATE TABLE IF NOT EXISTS ai_logs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $ai_logs_table);
} else {
    echo "Veritabanı oluşturma hatası: " . mysqli_error($conn);
}

// Oturum başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik fonksiyonları
function temizle($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Dosya uzantısını kontrol etme fonksiyonu
function izinliDosyaMi($dosyaAdi) {
    $izinliUzantilar = array('pdf', 'png', 'jpg', 'jpeg', 'docx', 'zip');
    $uzanti = strtolower(pathinfo($dosyaAdi, PATHINFO_EXTENSION));
    return in_array($uzanti, $izinliUzantilar);
}

// Kullanıcı yetkisi kontrol fonksiyonu
function yetkiKontrol($minYetki = 'uye') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("location: login.php");
        exit;
    }
    
    if ($minYetki == 'admin' && $_SESSION['role'] != 'admin') {
        header("location: dashboard.php");
        exit;
    }
}

// AI erişim kontrolü
function aiErisimKontrol() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("location: login.php");
        exit;
    }
    
    if (!isset($_SESSION['ai_access']) || $_SESSION['ai_access'] != 1) {
        header("location: dashboard.php?error=ai_access");
        exit;
    }
}

// CortexAPI ile sohbet fonksiyonu
function aiSohbet($mesaj) {
    $api_url = "https://api.claude.gg/v1/chat/completions";
    
    $data = [
        "model" => "gpt-4o",
        "messages" => [
            [
                "role" => "system",
                "content" => "Sen Dark Legion yapay zekasısın. Dobra, açık sözlü ve samimi bir karaktere sahipsin. Resmi olmak yerine arkadaş gibi konuşan, bazen takılan, esprili ve cana yakın bir AI'sın. 'Merhaba', 'Selam' gibi selamlamalara 'Hey, nasılsın?' gibi rahat cevaplar ver. Lafı dolandırma, direkt konuya gir. Çok uzun ve karmaşık cevaplar verme, öz ve net ol. Kendinle ilgili sorularda 'Ben Dark Legion yapay zekasıyım, emrindeyim!' gibi rahat ve özgüvenli cevaplar ver. Ara sıra Türkçe deyimler veya günlük konuşma dili kullan. Kullanıcıyla arandaki hiyerarşiyi minimize et, sanki bir arkadaşıyla konuşuyormuş gibi hissetsin."
            ],
            [
                "role" => "user",
                "content" => $mesaj
            ]
        ]
    ];
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . CORTEX_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return [
            'success' => false,
            'error' => "cURL Hatası: $err"
        ];
    }
    
    $response_data = json_decode($response, true);
    
    if (isset($response_data['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'message' => $response_data['choices'][0]['message']['content']
        ];
    } else {
        return [
            'success' => false,
            'error' => 'API yanıtı işlenirken bir hata oluştu',
            'raw_response' => $response
        ];
    }
}
?>