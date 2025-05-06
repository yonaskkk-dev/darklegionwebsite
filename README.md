# Dark Legion Web Portal

Dark Legion, modüler yapıda hazırlanmış, PHP tabanlı ve tamamen Türkçe web portal projesidir. Koyu tema tasarımı, kullanıcı dostu arayüzü ve genişletilebilir mimarisi ile öne çıkar.

## Özellikler

- **Kullanıcı Yönetimi**
  - Kayıt, giriş ve rol sistemi (admin, üye, misafir)
  - Güvenli parola depolama (password_hash ile)
  - Oturum yönetimi

- **Dosya Yükleme Sistemi**
  - Dosya yükleme ve paylaşım linki oluşturma
  - Güvenli dosya türü kontrolü
  - Admin silme yetkisi

- **AI Sohbet**
  - CortexAPI entegrasyonu
  - Yetkilendirilmiş kullanıcılar için erişim
  - Sohbet geçmişi ve admin log görüntüleme

- **Etkinlik Takvimi**
  - Aylık takvim görünümü
  - Admin etkinlik ekleme/silme
  - Etkinlik detay görüntüleme

- **Duyuru Sistemi**
  - Admin duyuru ekleme/silme/sabitleme
  - Kronolojik sıralama

- **Etkileşimli Araçlar**
  - Rastgele Takım Oluşturucu
  - Şans Çarkı

- **Galeri**
  - Resim yükleme ve görüntüleme
  - Açıklama ekleme
  - Modal görüntüleme

- **Bağlantılar**
  - Platform ikonları ile bağlantı yönetimi
  - Sosyal medya ve özel linkler

## Kurulum

### Gereksinimler
- PHP 7.4 veya üzeri
- MySQL veritabanı
- Web sunucusu (Apache, Nginx vb.)

### Adımlar

1. Tüm dosyaları web sunucunuza yükleyin.

2. `config.php` dosyasında veritabanı bağlantı bilgilerini güncelleyin:
   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'veritabani_kullanici_adi');
   define('DB_PASSWORD', 'veritabani_sifresi');
   define('DB_NAME', 'darklegion');
   ```

3. AI sohbet özelliği için CortexAPI anahtarını güncelleyin:
   ```php
   define('CORTEX_API_KEY', 'api_key_buraya_gelecek');
   ```

4. SQL dosyasını veritabanında çalıştırın:
   ```
   mysql -u kullanici_adi -p veritabani_adi < darklegion_setup.sql
   ```

5. Dizin izinlerini ayarlayın:
   ```
   chmod 755 uploads/
   chmod 755 uploads/gallery/
   chmod 755 data/
   ```

## Varsayılan Kullanıcılar

Kurulumdan sonra aşağıdaki kullanıcılarla giriş yapabilirsiniz:

- **Admin Kullanıcı**
  - E-posta: admin@example.com
  - Şifre: admin123
  - Tüm yetkilere sahiptir (AI erişimi dahil)

- **Normal Kullanıcı**
  - E-posta: kullanici@example.com
  - Şifre: user123
  - Standart üye yetkileri

> **Önemli:** Kurulumdan sonra güvenlik için varsayılan şifreleri değiştirmeyi unutmayın!

## Dizin Yapısı

```
darklegion/
├── assets/                  # Statik dosyalar
│   ├── css/                 # CSS dosyaları
│   └── js/                  # JavaScript dosyaları
├── data/                    # JSON veri dosyaları
│   ├── announcements.json   # Duyurular
│   ├── events.json          # Etkinlikler
│   └── links.json           # Bağlantılar
├── includes/                # PHP dahil dosyaları
│   ├── header.php           # Sayfa üst kısmı
│   ├── footer.php           # Sayfa alt kısmı
│   └── sidebar.php          # Kenar çubuğu
├── uploads/                 # Yükleme dizinleri
│   └── gallery/             # Galeri resimleri
├── config.php               # Konfigürasyon
├── index.php                # Ana sayfa
├── login.php                # Giriş sayfası
├── register.php             # Kayıt sayfası
├── dashboard.php            # Kontrol paneli
└── ... (diğer sayfalar)
```

## Genişletme

Portal modüler bir yapıda tasarlanmıştır ve kolayca yeni özellikler eklenebilir. Yeni bir modül eklemek için:

1. İlgili PHP sayfasını oluşturun
2. Gerekirse JSON veri dosyası ekleyin
3. Sidebar menüsüne bağlantı ekleyin
4. Gerekli CSS stillerini assets/css/style.css dosyasına ekleyin

## Güvenlik Notları

- Sunucuya yükledikten sonra tüm varsayılan şifreleri değiştirin
- config.php dosyasının güvenliğini sağlayın
- Düzenli olarak yedek alın

## Lisans

Bu proje MIT lisansı altında dağıtılmaktadır. Daha fazla bilgi için LICENSE dosyasına bakın.