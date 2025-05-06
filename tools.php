<?php
// Konfigürasyon dosyasını dahil et
require_once 'config.php';

// Sadece giriş yapmış kullanıcılar erişebilir
yetkiKontrol();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-area">
    <h1>Araçlar</h1>
    
    <div class="tools-container">
        <!-- Araç seçim sekmeleri -->
        <div class="tools-tabs">
            <button class="tab-button active" data-target="team-generator">Rastgele Takım Oluşturucu</button>
            <button class="tab-button" data-target="wheel-of-fortune">Şans Çarkı</button>
        </div>
        
        <!-- Takım Oluşturucu -->
        <div class="tool-panel active" id="team-generator">
            <h2>Rastgele Takım Oluşturucu</h2>
            <div class="tool-content">
                <div class="form-group">
                    <label for="player-list">Oyuncu İsimleri (Her satıra bir isim yazınız)</label>
                    <textarea id="player-list" rows="10" placeholder="Ali&#10;Veli&#10;Ayşe&#10;Fatma&#10;Mehmet"></textarea>
                </div>
                <div class="form-group">
                    <label for="team-count">Takım Sayısı</label>
                    <select id="team-count">
                        <option value="2">2 Takım</option>
                        <option value="3">3 Takım</option>
                        <option value="4">4 Takım</option>
                        <option value="5">5 Takım</option>
                        <option value="6">6 Takım</option>
                    </select>
                </div>
                <div class="form-group">
                    <button id="create-teams" class="btn">Takımları Oluştur</button>
                    <button id="shuffle-teams" class="btn" style="display:none;">Tekrar Karıştır</button>
                </div>
                <div id="teams-result" class="result-container"></div>
            </div>
        </div>
        
        <!-- Şans Çarkı -->
        <div class="tool-panel" id="wheel-of-fortune">
            <h2>Şans Çarkı</h2>
            <div class="tool-content">
                <div class="wheel-container">
                    <div class="wheel" id="fortune-wheel">
                        <!-- Çark dilimleri JS ile oluşturulacak -->
                    </div>
                    <div class="wheel-pointer"></div>
                </div>
                
                <div class="wheel-form">
                    <div class="form-group">
                        <label for="wheel-options">Seçenekler (Her satıra bir seçenek yazınız)</label>
                        <textarea id="wheel-options" rows="6" placeholder="Seçenek 1&#10;Seçenek 2&#10;Seçenek 3&#10;Seçenek 4"></textarea>
                    </div>
                    <div class="form-group">
                        <button id="spin-wheel" class="btn">Çarkı Çevir</button>
                    </div>
                </div>
                
                <div id="wheel-result" class="result-container">
                    <h3>Sonuç</h3>
                    <div id="selected-option"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Araçlar Sayfası Stilleri */
.tools-container {
    background-color: var(--secondary-bg);
    border-radius: 5px;
    padding: 20px;
    margin-top: 20px;
}

.tools-tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.tab-button {
    background: none;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    color: var(--text-color);
    font-size: 16px;
    font-weight: bold;
    transition: all 0.3s;
    border-bottom: 3px solid transparent;
}

.tab-button:hover {
    color: var(--accent-color);
}

.tab-button.active {
    color: var(--accent-color);
    border-bottom: 3px solid var(--accent-color);
}

.tool-panel {
    display: none;
}

.tool-panel.active {
    display: block;
}

.tool-content {
    padding: 15px 0;
}

.result-container {
    margin-top: 30px;
    padding: 20px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 5px;
    display: none;
}

/* Takım Oluşturucu Stilleri */
.team-list {
    margin-bottom: 20px;
    padding: 15px;
    background-color: rgba(142, 68, 173, 0.1);
    border-radius: 5px;
}

.team-list h3 {
    color: var(--accent-color);
    margin-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 5px;
}

.team-members {
    padding-left: 20px;
}

.team-member {
    padding: 5px 0;
}

/* Şans Çarkı Stilleri */
.wheel-container {
    position: relative;
    width: 300px;
    height: 300px;
    margin: 0 auto 30px;
}

.wheel {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background-color: var(--primary-bg);
    position: relative;
    overflow: hidden;
    transition: transform 5s cubic-bezier(0.17, 0.67, 0.83, 0.67);
    transform: rotate(0deg);
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
}

.wheel-pointer {
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 30px;
    background-color: var(--accent-color);
    clip-path: polygon(50% 100%, 0 0, 100% 0);
    z-index: 10;
}

.wheel-slice {
    position: absolute;
    width: 50%;
    height: 50%;
    transform-origin: bottom right;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: pointer;
}

.wheel-text {
    position: absolute;
    left: 30px;
    transform: rotate(90deg);
    transform-origin: left;
    margin-left: 20px;
    font-weight: bold;
    font-size: 14px;
    width: 100px;
    text-align: center;
    color: var(--text-color);
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
}

.wheel-result {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: var(--accent-color);
    margin: 20px 0;
}

.wheel-form {
    max-width: 400px;
    margin: 0 auto;
}

#selected-option {
    font-size: 1.5em;
    padding: 15px;
    text-align: center;
    color: var(--accent-color);
    font-weight: bold;
}
</style>

<script>
// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // Sekme değiştirme işlevi
    const tabButtons = document.querySelectorAll('.tab-button');
    const toolPanels = document.querySelectorAll('.tool-panel');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Tüm sekmeleri pasif yap
            tabButtons.forEach(btn => btn.classList.remove('active'));
            toolPanels.forEach(panel => panel.classList.remove('active'));
            
            // Seçilen sekmeyi aktifleştir
            button.classList.add('active');
            document.getElementById(button.dataset.target).classList.add('active');
        });
    });
    
    // ----- TAKIM OLUŞTURUCU ----- //
    const playerListInput = document.getElementById('player-list');
    const teamCountSelect = document.getElementById('team-count');
    const createTeamsButton = document.getElementById('create-teams');
    const shuffleTeamsButton = document.getElementById('shuffle-teams');
    const teamsResultDiv = document.getElementById('teams-result');
    
    let players = [];
    
    createTeamsButton.addEventListener('click', function() {
        createTeams();
    });
    
    shuffleTeamsButton.addEventListener('click', function() {
        createTeams();
    });
    
    function createTeams() {
        // Oyuncu listesini al
        const playerText = playerListInput.value.trim();
        if (!playerText) {
            alert('Lütfen en az bir oyuncu ismi girin.');
            return;
        }
        
        // Oyuncu listesini satırlara böl ve boş satırları temizle
        players = playerText.split('\n')
            .map(name => name.trim())
            .filter(name => name !== '');
        
        if (players.length === 0) {
            alert('Lütfen en az bir oyuncu ismi girin.');
            return;
        }
        
        // Takım sayısını al
        const teamCount = parseInt(teamCountSelect.value);
        
        if (players.length < teamCount) {
            alert(`En az ${teamCount} oyuncu gerekiyor.`);
            return;
        }
        
        // Oyuncuları karıştır
        shuffleArray(players);
        
        // Takımları oluştur
        const teams = Array.from({ length: teamCount }, () => []);
        
        // Oyuncuları takımlara dengeli bir şekilde dağıt
        players.forEach((player, index) => {
            teams[index % teamCount].push(player);
        });
        
        // Sonuçları göster
        displayTeams(teams);
        
        // Yeniden karıştır butonunu göster
        shuffleTeamsButton.style.display = 'inline-block';
    }
    
    function displayTeams(teams) {
        teamsResultDiv.innerHTML = '';
        teamsResultDiv.style.display = 'block';
        
        teams.forEach((team, index) => {
            const teamDiv = document.createElement('div');
            teamDiv.className = 'team-list';
            
            const teamTitle = document.createElement('h3');
            teamTitle.textContent = `Takım ${index + 1}`;
            teamDiv.appendChild(teamTitle);
            
            const membersList = document.createElement('ul');
            membersList.className = 'team-members';
            
            team.forEach(player => {
                const memberItem = document.createElement('li');
                memberItem.className = 'team-member';
                memberItem.textContent = player;
                membersList.appendChild(memberItem);
            });
            
            teamDiv.appendChild(membersList);
            teamsResultDiv.appendChild(teamDiv);
        });
    }
    
    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }
    
    // ----- ŞANS ÇARKI ----- //
    const wheelElement = document.getElementById('fortune-wheel');
    const wheelOptionsInput = document.getElementById('wheel-options');
    const spinWheelButton = document.getElementById('spin-wheel');
    const wheelResultDiv = document.getElementById('wheel-result');
    const selectedOptionDiv = document.getElementById('selected-option');
    
    let wheelOptions = [];
    let spinning = false;
    let currentRotation = 0;
    
    // Renk paleti
    const colors = [
        '#8e44ad', '#9b59b6', '#2980b9', '#3498db', 
        '#1abc9c', '#16a085', '#27ae60', '#2ecc71',
        '#f1c40f', '#f39c12', '#e67e22', '#d35400'
    ];
    
    spinWheelButton.addEventListener('click', function() {
        if (spinning) return;
        
        // Seçenekleri al
        const optionsText = wheelOptionsInput.value.trim();
        if (!optionsText) {
            alert('Lütfen en az iki seçenek girin.');
            return;
        }
        
        // Seçenekleri satırlara böl ve boş satırları temizle
        wheelOptions = optionsText.split('\n')
            .map(option => option.trim())
            .filter(option => option !== '');
        
        if (wheelOptions.length < 2) {
            alert('Lütfen en az iki seçenek girin.');
            return;
        }
        
        // Çarkı oluştur
        createWheel(wheelOptions);
        
        // Çarkı çevir
        spinWheel();
    });
    
    function createWheel(options) {
        wheelElement.innerHTML = '';
        
        const sliceAngle = 360 / options.length;
        
        options.forEach((option, index) => {
            const slice = document.createElement('div');
            slice.className = 'wheel-slice';
            
            // Renk ata
            const colorIndex = index % colors.length;
            slice.style.backgroundColor = colors[colorIndex];
            
            // Dönüş açısı
            const rotateAngle = index * sliceAngle;
            slice.style.transform = `rotate(${rotateAngle}deg) skewY(${90 - sliceAngle}deg)`;
            
            // Dilim metni
            const text = document.createElement('div');
            text.className = 'wheel-text';
            text.textContent = option;
            
            slice.appendChild(text);
            wheelElement.appendChild(slice);
        });
    }
    
    function spinWheel() {
        if (spinning) return;
        
        spinning = true;
        wheelResultDiv.style.display = 'block';
        selectedOptionDiv.textContent = 'Çark dönüyor...';
        
        // Rastgele derece hesapla (5-10 tam tur + rastgele açı)
        const spinDegrees = 1800 + Math.floor(Math.random() * 1800);
        currentRotation += spinDegrees;
        
        // Dönme animasyonu
        wheelElement.style.transition = 'transform 5s cubic-bezier(0.17, 0.67, 0.83, 0.67)';
        wheelElement.style.transform = `rotate(${currentRotation}deg)`;
        
        // Animasyon bitince sonucu göster
        setTimeout(() => {
            const sliceAngle = 360 / wheelOptions.length;
            const normalizedDegree = currentRotation % 360;
            const winningIndex = Math.floor(normalizedDegree / sliceAngle);
            const reversedIndex = wheelOptions.length - 1 - winningIndex;
            const winner = wheelOptions[reversedIndex % wheelOptions.length];
            
            selectedOptionDiv.textContent = winner;
            spinning = false;
        }, 5000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>