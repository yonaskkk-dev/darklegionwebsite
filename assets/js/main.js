/**
 * Dark Legion Web Portal - Ana JavaScript Dosyası
 * Bu dosya portal genişletildiğinde daha fazla işlevsellik ile geliştirilebilir.
 */

// Sayfa yüklendiğinde çalışacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // Alert mesajlarını otomatik kapatma
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        if (!alert.classList.contains('alert-danger')) {
            setTimeout(function() {
                fadeOut(alert);
            }, 5000);
        }
    });

    // Dosya input alanı için güzelleştirme
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Dosya seçilmedi';
            const fileLabel = this.nextElementSibling;
            if (fileLabel && fileLabel.classList.contains('file-label')) {
                fileLabel.textContent = fileName;
            }
        });
    });

    // Paylaşım linklerini kopyalama özelliği
    setupCopyLinks();
});

// Elementi yavaşça gizleme fonksiyonu
function fadeOut(element) {
    let opacity = 1;
    const timer = setInterval(function() {
        if (opacity <= 0.1) {
            clearInterval(timer);
            element.style.display = 'none';
        }
        element.style.opacity = opacity;
        opacity -= 0.1;
    }, 50);
}

// Paylaşım linklerini kopyalama özelliği
function setupCopyLinks() {
    // Paylaşım linki alanındaki tüm linkler
    const fileUrls = document.querySelectorAll('.file-url a');
    
    fileUrls.forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (!e.ctrlKey && !e.metaKey) { // Ctrl veya Command tuşu basılı değilse
                e.preventDefault();
                const url = this.getAttribute('href');
                
                // Url'i panoya kopyala
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                // Kullanıcıya geri bildirim ver
                alert('Paylaşım linki panoya kopyalandı!');
            }
        });
    });
}

// Daha sonra eklenecek modüller için temel yapı
const DarkLegion = {
    // AI Sohbet modülü için hazırlık
    chatModule: {
        init: function() {
            console.log('Sohbet modülü başlatıldı');
        }
    },
    
    // Takvim modülü için hazırlık
    calendarModule: {
        init: function() {
            console.log('Takvim modülü başlatıldı');
        }
    },
    
    // Müzik modülü için hazırlık
    musicModule: {
        init: function() {
            console.log('Müzik modülü başlatıldı');
        }
    }
};