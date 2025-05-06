document.addEventListener('DOMContentLoaded', function() {
    // Oyun değişkenleri
    const board = document.getElementById('game-board');
    const cells = document.getElementsByClassName('cell');
    const statusDisplay = document.getElementById('status');
    const restartButton = document.getElementById('restart-game');
    const scoreXDisplay = document.getElementById('score-x');
    const scoreODisplay = document.getElementById('score-o');
    const saveScoreButton = document.getElementById('save-score');
    
    // Oyun durumu
    let gameActive = true;
    let currentPlayer = 'X';
    let gameState = ["", "", "", "", "", "", "", "", ""];
    let scoreX = 0;
    let scoreO = 0;
    let moveCount = 0;
    
    // Kazanan kombinasyonları
    const winningConditions = [
        [0, 1, 2],
        [3, 4, 5],
        [6, 7, 8],
        [0, 3, 6],
        [1, 4, 7],
        [2, 5, 8],
        [0, 4, 8],
        [2, 4, 6]
    ];
    
    // Durum mesajları
    const winningMessage = () => `Oyuncu ${currentPlayer} kazandı!`;
    const drawMessage = () => `Oyun berabere bitti!`;
    const currentPlayerTurn = () => `Sıra: ${currentPlayer}`;
    
    // Oyunu başlat
    initGame();
    
    // Oyunu Başlatma
    function initGame() {
        moveCount = 0;
        gameActive = true;
        currentPlayer = 'X';
        gameState = ["", "", "", "", "", "", "", "", ""];
        statusDisplay.innerHTML = currentPlayerTurn();
        
        for (let i = 0; i < cells.length; i++) {
            cells[i].innerHTML = "";
            cells[i].classList.remove("cell-x", "cell-o", "highlight");
        }
        
        addCellClickListeners();
        
        // Skor tablosunu güncelle
        scoreXDisplay.textContent = scoreX;
        scoreODisplay.textContent = scoreO;
    }
    
    // Hücrelere tıklama olayları ekle
    function addCellClickListeners() {
        for (let i = 0; i < cells.length; i++) {
            cells[i].addEventListener('click', () => cellClick(cells[i], i), { once: true });
        }
    }
    
    // Hücreye tıklama
    function cellClick(clickedCell, cellIndex) {
        if (gameState[cellIndex] !== "" || !gameActive) {
            return;
        }
        
        // Hücreyi güncelle
        gameState[cellIndex] = currentPlayer;
        clickedCell.innerHTML = currentPlayer;
        clickedCell.classList.add(`cell-${currentPlayer.toLowerCase()}`);
        moveCount++;
        
        // Oyun durumunu kontrol et
        checkResult();
    }
    
    // Sonuç kontrolü
    function checkResult() {
        let roundWon = false;
        let winLine = null;
        
        // Kazanma durumlarını kontrol et
        for (let i = 0; i < winningConditions.length; i++) {
            const [a, b, c] = winningConditions[i];
            const condition = gameState[a] && gameState[a] === gameState[b] && gameState[a] === gameState[c];
            
            if (condition) {
                roundWon = true;
                winLine = winningConditions[i];
                break;
            }
        }
        
        // Kazanan varsa
        if (roundWon) {
            statusDisplay.innerHTML = winningMessage();
            gameActive = false;
            
            // Kazanan çizgiyi vurgula
            winLine.forEach(cellIndex => {
                cells[cellIndex].classList.add('highlight');
            });
            
            // Skoru güncelle
            if (currentPlayer === 'X') {
                scoreX++;
                scoreXDisplay.textContent = scoreX;
            } else {
                scoreO++;
                scoreODisplay.textContent = scoreO;
            }
            
            return;
        }
        
        // Beraberlik durumu
        if (moveCount === 9) {
            statusDisplay.innerHTML = drawMessage();
            gameActive = false;
            return;
        }
        
        // Oyuncu değişimi
        currentPlayer = currentPlayer === 'X' ? 'O' : 'X';
        statusDisplay.innerHTML = currentPlayerTurn();
    }
    
    // Oyunu yeniden başlat
    restartButton.addEventListener('click', initGame);
    
    // Skorları kaydet
    saveScoreButton.addEventListener('click', saveScore);
    
    function saveScore() {
        // Kazanan puanı ve toplam oyun sayısını hesapla
        const totalGames = Math.floor((scoreX + scoreO) / 2);
        let score = 0;
        
        // X oyuncusu için skor hesapla (2 * kazanılan oyun)
        score = scoreX * 2;
        
        // Oyun ID'sini al
        const gameId = document.getElementById('game-id').value;
        
        // Form verisini oluştur
        const formData = new FormData();
        formData.append('game_id', gameId);
        formData.append('score', score);
        formData.append('level', totalGames);  // Level olarak toplam oyun sayısını kullan
        formData.append('action', 'save_score');
        
        // Skoru sunucuya gönder
        fetch('save_score.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Skorunuz başarıyla kaydedildi!');
                
                // Rozet kazanıldıysa bildir
                if (data.badges && data.badges.length > 0) {
                    let badgeMessage = 'Tebrikler! Yeni rozetler kazandınız:\n\n';
                    data.badges.forEach(badge => {
                        badgeMessage += `${badge.icon} ${badge.name} - ${badge.description}\n`;
                    });
                    alert(badgeMessage);
                }
                
                // Skoru sıfırla
                scoreX = 0;
                scoreO = 0;
                initGame();
                
                // Sayfa yenilenerek en yüksek skor tablosunun güncellenmesini sağla
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                alert('Skor kaydedilemedi: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Hata:', error);
            alert('Skor kaydedilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
        });
    }
    
    // Mobil dokunma desteği için
    Array.from(cells).forEach((cell, index) => {
        cell.addEventListener('touchstart', function(e) {
            e.preventDefault();
            if (gameState[index] === "" && gameActive) {
                cellClick(cell, index);
            }
        });
    });
    
    // Bilgisayara karşı oynama
    let vsComputer = false;
    const toggleComputerButton = document.getElementById('toggle-computer');
    
    if (toggleComputerButton) {
        toggleComputerButton.addEventListener('click', function() {
            vsComputer = !vsComputer;
            initGame();
            if (vsComputer) {
                this.textContent = 'İki Kişilik Moda Geç';
                statusDisplay.innerHTML = 'Bilgisayara karşı oynuyorsun - Sen X\'sin';
            } else {
                this.textContent = 'Bilgisayara Karşı Oyna';
                statusDisplay.innerHTML = currentPlayerTurn();
            }
        });
    }
    
    // Bilgisayar hamlesi
    function computerMove() {
        if (!gameActive || currentPlayer !== 'O') return;
        
        setTimeout(() => {
            // Kazanma hamlesini kontrol et
            let moveIndex = findWinningMove('O');
            
            // Kazanma hamlesi yoksa, karşı oyuncunun kazanma hamlesini engelle
            if (moveIndex === -1) {
                moveIndex = findWinningMove('X');
            }
            
            // Stratejik hamle yoksa, merkezi al
            if (moveIndex === -1 && gameState[4] === "") {
                moveIndex = 4;
            }
            
            // Hala hamle bulunamadıysa, rastgele bir boş hücre seç
            if (moveIndex === -1) {
                const emptyIndices = gameState.map((val, idx) => val === "" ? idx : -1).filter(idx => idx !== -1);
                if (emptyIndices.length > 0) {
                    moveIndex = emptyIndices[Math.floor(Math.random() * emptyIndices.length)];
                }
            }
            
            // Hamle yap
            if (moveIndex !== -1 && gameState[moveIndex] === "") {
                gameState[moveIndex] = 'O';
                cells[moveIndex].innerHTML = 'O';
                cells[moveIndex].classList.add('cell-o');
                moveCount++;
                
                // Event listener'ı kaldır
                cells[moveIndex].removeEventListener('click', cellClickHandler);
                
                // Oyun durumunu kontrol et
                checkResult();
            }
        }, 600); // Bilgisayar hamlesini geciktir
    }
    
    // Kazanma hamlesi bul
    function findWinningMove(player) {
        for (let i = 0; i < gameState.length; i++) {
            if (gameState[i] === "") {
                // Deneme hamlesi
                gameState[i] = player;
                
                // Kazanıyor mu kontrol et
                for (let j = 0; j < winningConditions.length; j++) {
                    const [a, b, c] = winningConditions[j];
                    if (gameState[a] === player && gameState[b] === player && gameState[c] === player) {
                        // Deneme hamlesini geri al
                        gameState[i] = "";
                        return i;
                    }
                }
                
                // Deneme hamlesini geri al
                gameState[i] = "";
            }
        }
        
        return -1; // Kazanma hamlesi yok
    }
    
    // Hücre tıklama event handler
    function cellClickHandler(event) {
        const cellIndex = parseInt(event.target.getAttribute('data-cell-index'));
        cellClick(event.target, cellIndex);
        
        // Bilgisayara karşı oynuyorsa ve oyun hala aktifse
        if (vsComputer && gameActive && currentPlayer === 'O') {
            computerMove();
        }
    }
});