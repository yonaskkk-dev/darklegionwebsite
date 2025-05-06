document.addEventListener('DOMContentLoaded', function() {
    // Oyun değişkenleri
    const canvas = document.getElementById('game-canvas');
    const ctx = canvas.getContext('2d');
    const scoreDisplay = document.getElementById('score');
    const highScoreDisplay = document.getElementById('high-score');
    const gameOverScreen = document.getElementById('game-over');
    const finalScoreDisplay = document.getElementById('final-score');
    const newGameBtn = document.getElementById('new-game');
    const saveScoreBtn = document.getElementById('save-score');
    
    // Oyun sabitleri
    const GRID_SIZE = 20;
    const GAME_SPEED = 125; // Milisaniye (daha düşük = daha hızlı)
    
    // Oyun durumu
    let snake = [];
    let food = {};
    let dx = GRID_SIZE; // X yönündeki hareket
    let dy = 0; // Y yönündeki hareket
    let score = 0;
    let highScore = parseInt(localStorage.getItem('snakeHighScore')) || 0;
    let gameRunning = false;
    let gameInterval;
    let lastRenderTime = 0;
    let lastDirection = 'right';
    let isPaused = false;
    
    // Canvas boyutlarını ayarla
    canvas.width = 400;
    canvas.height = 400;
    
    // Yüksek skoru göster
    highScoreDisplay.textContent = highScore;
    
    // Oyunu başlat
    function startGame() {
        // Yılanı sıfırla
        snake = [
            {x: 100, y: 200},
            {x: 80, y: 200},
            {x: 60, y: 200},
        ];
        
        // Hareketi sıfırla
        dx = GRID_SIZE;
        dy = 0;
        lastDirection = 'right';
        
        // Skoru sıfırla
        score = 0;
        scoreDisplay.textContent = score;
        
        // Yiyecek oluştur
        createFood();
        
        // Oyun döngüsünü başlat
        if (gameInterval) clearInterval(gameInterval);
        gameInterval = setInterval(gameLoop, GAME_SPEED);
        gameRunning = true;
        lastRenderTime = performance.now();
        
        // Game over ekranını kapat
        gameOverScreen.style.display = 'none';
    }
    
    // Yiyecek oluştur
    function createFood() {
        food = {
            x: Math.floor(Math.random() * (canvas.width / GRID_SIZE)) * GRID_SIZE,
            y: Math.floor(Math.random() * (canvas.height / GRID_SIZE)) * GRID_SIZE
        };
        
        // Yiyeceğin yılanın üzerine gelmemesini sağla
        for (let i = 0; i < snake.length; i++) {
            if (snake[i].x === food.x && snake[i].y === food.y) {
                createFood();
                return;
            }
        }
    }
    
    // Ana oyun döngüsü
    function gameLoop() {
        if (isPaused) return;
        
        // Yılanı hareket ettir
        const head = {x: snake[0].x + dx, y: snake[0].y + dy};
        snake.unshift(head);
        
        // Collision kontrolü yap
        if (checkCollision()) {
            gameOver();
            return;
        }
        
        // Yiyecek yeme kontrolü
        if (head.x === food.x && head.y === food.y) {
            // Skoru arttır
            score += 10;
            scoreDisplay.textContent = score;
            
            // Yeni yiyecek oluştur
            createFood();
        } else {
            // Yiyecek yemediyse kuyruğu kısalt
            snake.pop();
        }
        
        // Ekranı temizle ve çiz
        clearCanvas();
        drawSnake();
        drawFood();
    }
    
    // Çarpışma kontrolü
    function checkCollision() {
        const head = snake[0];
        
        // Duvarlara çarpma kontrolü
        if (head.x < 0 || head.x >= canvas.width || head.y < 0 || head.y >= canvas.height) {
            return true;
        }
        
        // Kendine çarpma kontrolü
        for (let i = 1; i < snake.length; i++) {
            if (head.x === snake[i].x && head.y === snake[i].y) {
                return true;
            }
        }
        
        return false;
    }
    
    // Canvas'ı temizle
    function clearCanvas() {
        ctx.fillStyle = '#262626';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Izgara çiz
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 0.5;
        
        for (let x = 0; x < canvas.width; x += GRID_SIZE) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, canvas.height);
            ctx.stroke();
        }
        
        for (let y = 0; y < canvas.height; y += GRID_SIZE) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(canvas.width, y);
            ctx.stroke();
        }
    }
    
    // Yılanı çiz
    function drawSnake() {
        snake.forEach((segment, index) => {
            // Yılanın başı için farklı renk kullan
            if (index === 0) {
                ctx.fillStyle = '#5E35B1'; // Mor
            } else {
                // Vücudun kısmına göre rengi değiştir (gradient efekti)
                const colorValue = Math.floor(255 - (150 * index / snake.length));
                ctx.fillStyle = `rgb(94, 53, ${colorValue + 100})`;
            }
            
            ctx.fillRect(segment.x, segment.y, GRID_SIZE, GRID_SIZE);
            
            // Kenarları çiz
            ctx.strokeStyle = '#7E57C2';
            ctx.lineWidth = 1;
            ctx.strokeRect(segment.x, segment.y, GRID_SIZE, GRID_SIZE);
            
            // Baş için göz çiz
            if (index === 0) {
                // Sol göz
                ctx.fillStyle = 'white';
                ctx.beginPath();
                ctx.arc(segment.x + 5, segment.y + 7, 2, 0, Math.PI * 2);
                ctx.fill();
                
                // Sağ göz
                ctx.beginPath();
                ctx.arc(segment.x + 15, segment.y + 7, 2, 0, Math.PI * 2);
                ctx.fill();
            }
        });
    }
    
    // Yiyeceği çiz
    function drawFood() {
        ctx.fillStyle = '#FF5252'; // Kırmızı elma
        ctx.beginPath();
        ctx.arc(food.x + GRID_SIZE/2, food.y + GRID_SIZE/2, GRID_SIZE/2, 0, Math.PI * 2);
        ctx.fill();
        
        // Yaprak ekle
        ctx.fillStyle = '#4CAF50';
        ctx.beginPath();
        ctx.ellipse(food.x + GRID_SIZE/2 + 3, food.y + 2, 4, 2, Math.PI / 4, 0, Math.PI * 2);
        ctx.fill();
    }
    
    // Oyun sonu
    function gameOver() {
        gameRunning = false;
        clearInterval(gameInterval);
        
        // Yüksek skoru güncelle
        if (score > highScore) {
            highScore = score;
            localStorage.setItem('snakeHighScore', highScore);
            highScoreDisplay.textContent = highScore;
        }
        
        // Game over ekranını göster
        gameOverScreen.style.display = 'flex';
        finalScoreDisplay.textContent = score;
    }
    
    // Klavye kontrolü
    document.addEventListener('keydown', function(e) {
        if (!gameRunning && !isPaused && e.key === ' ') {
            startGame();
            return;
        }
        
        if (e.key === 'p' || e.key === 'P') {
            isPaused = !isPaused;
            return;
        }
        
        if (isPaused) return;
        
        switch (e.key) {
            case 'ArrowUp':
            case 'w':
            case 'W':
                if (lastDirection !== 'down') {
                    dx = 0;
                    dy = -GRID_SIZE;
                    lastDirection = 'up';
                }
                break;
            case 'ArrowDown':
            case 's':
            case 'S':
                if (lastDirection !== 'up') {
                    dx = 0;
                    dy = GRID_SIZE;
                    lastDirection = 'down';
                }
                break;
            case 'ArrowLeft':
            case 'a':
            case 'A':
                if (lastDirection !== 'right') {
                    dx = -GRID_SIZE;
                    dy = 0;
                    lastDirection = 'left';
                }
                break;
            case 'ArrowRight':
            case 'd':
            case 'D':
                if (lastDirection !== 'left') {
                    dx = GRID_SIZE;
                    dy = 0;
                    lastDirection = 'right';
                }
                break;
        }
    });
    
    // Dokunmatik ekran kontrolü için
    let touchStartX = 0;
    let touchStartY = 0;
    
    canvas.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        e.preventDefault();
    }, false);
    
    canvas.addEventListener('touchmove', function(e) {
        if (!gameRunning || isPaused) return;
        
        e.preventDefault();
        
        const touchEndX = e.touches[0].clientX;
        const touchEndY = e.touches[0].clientY;
        
        const dx = touchEndX - touchStartX;
        const dy = touchEndY - touchStartY;
        
        // Yatay mı, dikey mi daha büyük hareket var?
        if (Math.abs(dx) > Math.abs(dy)) {
            // Yatay hareket
            if (dx > 0 && lastDirection !== 'left') {
                // Sağa
                dx = GRID_SIZE;
                dy = 0;
                lastDirection = 'right';
            } else if (dx < 0 && lastDirection !== 'right') {
                // Sola
                dx = -GRID_SIZE;
                dy = 0;
                lastDirection = 'left';
            }
        } else {
            // Dikey hareket
            if (dy > 0 && lastDirection !== 'up') {
                // Aşağı
                dx = 0;
                dy = GRID_SIZE;
                lastDirection = 'down';
            } else if (dy < 0 && lastDirection !== 'down') {
                // Yukarı
                dx = 0;
                dy = -GRID_SIZE;
                lastDirection = 'up';
            }
        }
        
        touchStartX = touchEndX;
        touchStartY = touchEndY;
    }, false);
    
    // Skor kaydet butonuna tıklama olayı
    saveScoreBtn.addEventListener('click', function() {
        if (typeof saveScore === 'function') {
            saveScore(score);
        } else {
            alert("Skor kaydetme sistemine ulaşılamıyor. Lütfen daha sonra tekrar deneyin.");
        }
    });
    
    // Yeni oyun butonuna tıklama olayı
    newGameBtn.addEventListener('click', startGame);
    
    // İlk ekranı hazırla
    clearCanvas();
    
    // Talimatları göster
    ctx.fillStyle = 'white';
    ctx.font = '18px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('Yılan Oyununa Hoş Geldiniz!', canvas.width/2, 160);
    ctx.font = '14px Arial';
    ctx.fillText('Başlamak için BOŞLUK tuşuna basın', canvas.width/2, 190);
    ctx.fillText('Yön tuşları veya W,A,S,D ile kontrol edin', canvas.width/2, 220);
    ctx.fillText('P tuşu ile oyunu duraklatın', canvas.width/2, 250);
});

// Skoru kaydetme fonksiyonu
function saveScore(score) {
    // Ajax isteği ile skoru sunucuya gönder
    const gameId = document.getElementById('game-id').value;
    const formData = new FormData();
    formData.append('game_id', gameId);
    formData.append('score', score);
    formData.append('action', 'save_score');
    
    fetch('games/save_score.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Skorunuz başarıyla kaydedildi!');
            
            // Rozet alındıysa bildir
            if (data.badges && data.badges.length > 0) {
                let badgeMessage = 'Tebrikler! Yeni rozetler kazandınız:\n\n';
                data.badges.forEach(badge => {
                    badgeMessage += `${badge.icon} ${badge.name} - ${badge.description}\n`;
                });
                alert(badgeMessage);
            }
            
            // Sayfa yenilenerek en yüksek skor tablosunun güncellenmesini sağla
            window.location.reload();
        } else {
            alert('Skor kaydedilemedi: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        alert('Skor kaydedilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
    });
}