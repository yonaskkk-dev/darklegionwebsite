<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Oturum durumunu kontrol et
$loggedin = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$isAdmin = $loggedin && $_SESSION['role'] === 'admin';

// Etkinlik işlemleri
$events = [];
$message = '';
$error = '';

// JSON dosyasından etkinlikleri yükle
function loadEvents() {
    $jsonFile = 'data/events.json';
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        return json_decode($jsonData, true) ?: [];
    }
    return [];
}

// Etkinlikleri JSON dosyasına kaydet
function saveEvents($events) {
    $jsonFile = 'data/events.json';
    $jsonData = json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($jsonFile, $jsonData);
}

// Etkinlik ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action']) && $_POST['action'] === 'add_event') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $date = trim($_POST['date']);
        $time = trim($_POST['time']);
        
        if (empty($title) || empty($date)) {
            $error = 'Başlık ve tarih alanları zorunludur.';
        } else {
            $events = loadEvents();
            
            // Yeni etkinlik dizisi
            $newEvent = [
                'id' => time(), // Basit bir ID
                'title' => $title,
                'description' => $description,
                'date' => $date,
                'time' => $time,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Etkinliği ekle
            $events[] = $newEvent;
            
            // JSON'a kaydet
            if (saveEvents($events)) {
                $message = 'Etkinlik başarıyla eklendi.';
            } else {
                $error = 'Etkinlik kaydedilirken bir hata oluştu.';
            }
        }
    }
    
    // Etkinlik silme işlemi
    if (isset($_POST['action']) && $_POST['action'] === 'delete_event' && isset($_POST['event_id'])) {
        $eventId = (int)$_POST['event_id'];
        $events = loadEvents();
        
        foreach ($events as $key => $event) {
            if ($event['id'] === $eventId) {
                unset($events[$key]);
                break;
            }
        }
        
        // Diziyi yeniden indeksle
        $events = array_values($events);
        
        // JSON'a kaydet
        if (saveEvents($events)) {
            $message = 'Etkinlik başarıyla silindi.';
        } else {
            $error = 'Etkinlik silinirken bir hata oluştu.';
        }
    }
}

// Takvim ayarları
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Ay ve yıl sınırlarını kontrol et
if ($currentMonth < 1) {
    $currentMonth = 12;
    $currentYear--;
} elseif ($currentMonth > 12) {
    $currentMonth = 1;
    $currentYear++;
}

// Takvim oluşturma
$firstDayOfMonth = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$numDaysInMonth = date('t', $firstDayOfMonth);
$firstDayOfWeek = date('N', $firstDayOfMonth); // 1 (Pazartesi) ile 7 (Pazar) arası

// Tüm etkinlikleri yükle
$events = loadEvents();

// Görüntülenecek etkinlikleri filtrele (bu ay için)
$eventsForMonth = array_filter($events, function($event) use ($currentMonth, $currentYear) {
    $eventDate = explode('-', $event['date']);
    return (int)$eventDate[1] === $currentMonth && (int)$eventDate[0] === $currentYear;
});

// Türkçe ay isimleri
$aylar = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
    7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

include 'includes/header.php';
?>

<div class="container">
    <div class="main-content">
        <?php if ($loggedin) include 'includes/sidebar.php'; ?>
        
        <div class="content-area">
            <h1>Etkinlik Takvimi</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="calendar-container">
                <div class="calendar-header">
                    <a href="?month=<?php echo $currentMonth - 1; ?>&year=<?php echo $currentYear; ?>" class="month-nav">&laquo; Önceki Ay</a>
                    <h2><?php echo $aylar[$currentMonth] . ' ' . $currentYear; ?></h2>
                    <a href="?month=<?php echo $currentMonth + 1; ?>&year=<?php echo $currentYear; ?>" class="month-nav">Sonraki Ay &raquo;</a>
                </div>
                
                <div class="calendar">
                    <div class="calendar-weekdays">
                        <div>Pt</div>
                        <div>Sa</div>
                        <div>Ça</div>
                        <div>Pe</div>
                        <div>Cu</div>
                        <div>Ct</div>
                        <div>Pa</div>
                    </div>
                    
                    <div class="calendar-days">
                        <?php
                        // Önceki ayın son günlerini doldur (takvimin boş hücrelerini doldurmak için)
                        for ($i = 1; $i < $firstDayOfWeek; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }
                        
                        // Ayın günlerini doldur
                        for ($day = 1; $day <= $numDaysInMonth; $day++) {
                            $date = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                            $hasEvents = false;
                            $dayEvents = [];
                            
                            // Bu gün için etkinlik var mı kontrol et
                            foreach ($eventsForMonth as $event) {
                                if ($event['date'] === $date) {
                                    $hasEvents = true;
                                    $dayEvents[] = $event;
                                }
                            }
                            
                            // Günleri göster
                            echo '<div class="calendar-day' . ($hasEvents ? ' has-events' : '') . '" data-date="' . $date . '">';
                            echo '<div class="day-number">' . $day . '</div>';
                            
                            // Etkinlik göstergesi
                            if ($hasEvents) {
                                echo '<div class="event-indicator"></div>';
                            }
                            
                            echo '</div>';
                        }
                        
                        // Bir sonraki ayın ilk günlerini doldur (eğer gerekirse)
                        $remainingCells = 7 - (($firstDayOfWeek - 1 + $numDaysInMonth) % 7);
                        if ($remainingCells < 7) {
                            for ($i = 0; $i < $remainingCells; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <div class="event-details" id="eventDetails">
                    <h3>Seçilen Günün Etkinlikleri</h3>
                    <p class="selected-date" id="selectedDate">Lütfen bir tarih seçin</p>
                    <div id="eventsList"></div>
                    
                    <?php if ($isAdmin): ?>
                    <div class="event-form" id="eventForm" style="display: none;">
                        <h3>Yeni Etkinlik Ekle</h3>
                        <form action="event-calendar.php" method="post">
                            <input type="hidden" name="action" value="add_event">
                            <input type="hidden" name="date" id="formDate">
                            
                            <div class="form-group">
                                <label for="title">Başlık</label>
                                <input type="text" name="title" id="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="time">Saat (İsteğe Bağlı)</label>
                                <input type="time" name="time" id="time">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Açıklama</label>
                                <textarea name="description" id="description" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn">Kaydet</button>
                                <button type="button" class="btn btn-cancel" id="cancelButton">İptal</button>
                            </div>
                        </form>
                    </div>
                    
                    <button type="button" class="btn" id="addEventButton" style="display: none;">Etkinlik Ekle</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Takvim stilleri */
.calendar-container {
    display: flex;
    flex-wrap: wrap;
    margin-top: 20px;
}

.calendar {
    flex: 1;
    min-width: 320px;
    background-color: var(--secondary-bg);
    border-radius: 5px;
    padding: 15px;
    margin-right: 20px;
    margin-bottom: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-header h2 {
    margin: 0;
    color: var(--accent-color);
}

.month-nav {
    color: var(--text-color);
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 3px;
    background-color: rgba(0, 0, 0, 0.2);
    transition: background-color 0.3s;
}

.month-nav:hover {
    background-color: var(--accent-color);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    font-weight: bold;
    margin-bottom: 10px;
}

.calendar-weekdays div {
    padding: 10px;
    color: var(--accent-color);
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-gap: 5px;
}

.calendar-day {
    height: 60px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
    padding: 5px;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
}

.calendar-day:hover {
    background-color: rgba(142, 68, 173, 0.2);
}

.calendar-day.empty {
    background-color: transparent;
    cursor: default;
}

.calendar-day.selected {
    background-color: rgba(142, 68, 173, 0.5);
}

.day-number {
    font-size: 14px;
    font-weight: bold;
}

.has-events {
    border: 1px solid var(--accent-color);
}

.event-indicator {
    width: 8px;
    height: 8px;
    background-color: var(--accent-color);
    border-radius: 50%;
    position: absolute;
    bottom: 5px;
    right: 5px;
}

.event-details {
    flex: 1;
    min-width: 300px;
    background-color: var(--secondary-bg);
    border-radius: 5px;
    padding: 15px;
}

.selected-date {
    margin-bottom: 20px;
    font-weight: bold;
    color: var(--accent-color);
}

.event-item {
    margin-bottom: 15px;
    padding: 10px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
    position: relative;
}

.event-title {
    font-weight: bold;
    margin-bottom: 5px;
    color: var(--accent-color);
}

.event-time {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.event-description {
    margin-top: 5px;
    font-size: 14px;
}

.no-events {
    font-style: italic;
    color: var(--text-secondary);
}

.event-form {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.btn-cancel {
    background-color: var(--danger-color);
}

.btn-delete {
    background-color: var(--danger-color);
    font-size: 12px;
    padding: 5px 10px;
    position: absolute;
    top: 10px;
    right: 10px;
}

@media (max-width: 768px) {
    .calendar-container {
        flex-direction: column;
    }
    
    .calendar {
        margin-right: 0;
    }
    
    .calendar-day {
        height: 40px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Takvim değişkenleri
    const calendarDays = document.querySelectorAll('.calendar-day:not(.empty)');
    const eventsList = document.getElementById('eventsList');
    const selectedDateElement = document.getElementById('selectedDate');
    const eventForm = document.getElementById('eventForm');
    const formDateInput = document.getElementById('formDate');
    const addEventButton = document.getElementById('addEventButton');
    const cancelButton = document.getElementById('cancelButton');
    
    // Türkçe ay ve gün isimleri
    const aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    const gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    
    // Etkinlik verileri
    const events = <?php echo json_encode($events); ?>;
    let selectedDate = null;
    
    // Admin kontrolü
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    
    // Tarih formatlama fonksiyonu
    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = date.getDate();
        const month = aylar[date.getMonth()];
        const year = date.getFullYear();
        const dayName = gunler[date.getDay()];
        
        return `${day} ${month} ${year}, ${dayName}`;
    }
    
    // Takvim günlerine tıklama işlevi
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            // Önceki seçimi temizle
            calendarDays.forEach(d => d.classList.remove('selected'));
            
            // Yeni seçimi işaretle
            this.classList.add('selected');
            
            // Seçilen tarihi al
            selectedDate = this.dataset.date;
            
            // Etkinlik formunu sıfırla
            if (eventForm) {
                eventForm.style.display = 'none';
                formDateInput.value = selectedDate;
            }
            
            // Etkinlik ekle butonunu göster (admin için)
            if (addEventButton && isAdmin) {
                addEventButton.style.display = 'block';
            }
            
            // Seçilen tarihi göster
            selectedDateElement.textContent = formatDate(selectedDate);
            
            // Bu tarih için etkinlikleri göster
            displayEventsForDate(selectedDate);
        });
    });
    
    // Etkinlik ekle butonuna tıklama
    if (addEventButton) {
        addEventButton.addEventListener('click', function() {
            eventForm.style.display = 'block';
            this.style.display = 'none';
        });
    }
    
    // İptal butonuna tıklama
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            eventForm.style.display = 'none';
            addEventButton.style.display = 'block';
        });
    }
    
    // Belirli bir tarih için etkinlikleri göster
    function displayEventsForDate(date) {
        // Etkinlik listesini temizle
        eventsList.innerHTML = '';
        
        // Bu tarih için etkinlikleri filtrele
        const dayEvents = events.filter(event => event.date === date);
        
        if (dayEvents.length === 0) {
            eventsList.innerHTML = '<p class="no-events">Bu tarihte etkinlik bulunmuyor.</p>';
            return;
        }
        
        // Etkinlikleri listeye ekle
        dayEvents.forEach(event => {
            const eventElement = document.createElement('div');
            eventElement.className = 'event-item';
            
            // Etkinlik başlığı
            const titleElement = document.createElement('div');
            titleElement.className = 'event-title';
            titleElement.textContent = event.title;
            eventElement.appendChild(titleElement);
            
            // Etkinlik saati (varsa)
            if (event.time) {
                const timeElement = document.createElement('div');
                timeElement.className = 'event-time';
                timeElement.textContent = `Saat: ${event.time}`;
                eventElement.appendChild(timeElement);
            }
            
            // Etkinlik açıklaması (varsa)
            if (event.description) {
                const descElement = document.createElement('div');
                descElement.className = 'event-description';
                descElement.textContent = event.description;
                eventElement.appendChild(descElement);
            }
            
            // Admin için silme butonu
            if (isAdmin) {
                const deleteButton = document.createElement('button');
                deleteButton.className = 'btn btn-delete';
                deleteButton.textContent = 'Sil';
                deleteButton.addEventListener('click', function() {
                    if (confirm('Bu etkinliği silmek istediğinizden emin misiniz?')) {
                        deleteEvent(event.id);
                    }
                });
                eventElement.appendChild(deleteButton);
            }
            
            eventsList.appendChild(eventElement);
        });
    }
    
    // Etkinlik silme (form gönderimi)
    function deleteEvent(eventId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'event-calendar.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_event';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'event_id';
        idInput.value = eventId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
    
    // Sayfa yüklendiğinde bugünün tarihini seçili yap
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    const todayCell = document.querySelector(`.calendar-day[data-date="${todayString}"]`);
    
    if (todayCell) {
        todayCell.click();
    }
});
</script>

<?php include 'includes/footer.php'; ?>