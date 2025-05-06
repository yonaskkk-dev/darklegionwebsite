-- Dark Legion Web Portal - Veritabanı Kurulum Dosyası
-- Bu SQL dosyası, Dark Legion Web Portal için gerekli tüm veritabanı yapısını oluşturur.

-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS `darklegion` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `darklegion`;

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','uye','misafir') NOT NULL DEFAULT 'misafir',
  `ai_access` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dosya yüklemeleri tablosu
CREATE TABLE IF NOT EXISTS `uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `uploads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI sohbet logları tablosu
CREATE TABLE IF NOT EXISTS `ai_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ai_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek admin kullanıcısı ekle (şifre: admin123)
-- NOT: Güvenlik için bu şifreyi değiştirmeyi unutmayın!
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `created_at`) VALUES
('admin', 'admin@example.com', '$2y$10$fCOFiMnqKYykVpNfrSkOYOv8Gt9zXRJe8Qx5iLy1tPLPLtqUbOrpW', 'admin', 1, NOW());

-- Örnek normal kullanıcı ekle (şifre: user123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `created_at`) VALUES
('kullanici', 'kullanici@example.com', '$2y$10$4LYA8jGGv5M7Qt5TU/K9WuIUkvZ1bSrw1KsEGSE1lAqIjbcmBPXha', 'uye', 0, NOW());

-- JSON Dosya yapıları hakkında bilgi
-- NOT: Aşağıdaki dosyalar veritabanında değil, dosya sisteminde saklanır:
-- 
-- 1. data/events.json - Etkinlik takvimi verileri
-- 2. data/announcements.json - Duyurular
-- 3. data/links.json - Bağlantılar
--
-- Bu JSON dosyaları aşağıdaki formatta boş bir dizi ile başlamalıdır:
-- []
--
-- Ayrıca galeri resimleri için uploads/gallery/ dizini oluşturulmalıdır.

-- Kurulum tamamlandı