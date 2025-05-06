-- Dark Legion Web Portal - Veritabanı Kurulum Dosyası v2
-- Bu SQL dosyası, Dark Legion Web Portal için gerekli tüm veritabanı yapısını oluşturur.
-- Tüm modüller ve özellikler için gerekli tablolar dahildir.

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
  `profile_url` varchar(50) DEFAULT NULL UNIQUE,
  `avatar` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `cover_video` varchar(255) DEFAULT NULL,
  `background_music` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `social_links` text DEFAULT NULL,
  `badges` text DEFAULT NULL,
  `profile_views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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

-- Anılar tablosu
CREATE TABLE IF NOT EXISTS `memories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `memory_type` enum('image','video') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `liked_by` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `memories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Etkinlikler tablosu
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Duyurular tablosu
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bağlantılar tablosu
CREATE TABLE IF NOT EXISTS `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek admin kullanıcısı ekle (şifre: admin123)
-- NOT: Güvenlik için bu şifreyi değiştirmeyi unutmayın!
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `profile_url`, `created_at`) VALUES
('admin', 'admin@example.com', '$2y$10$fCOFiMnqKYykVpNfrSkOYOv8Gt9zXRJe8Qx5iLy1tPLPLtqUbOrpW', 'admin', 1, 'admin', NOW());

-- Örnek normal kullanıcı ekle (şifre: user123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `profile_url`, `created_at`) VALUES
('kullanici', 'kullanici@example.com', '$2y$10$4LYA8jGGv5M7Qt5TU/K9WuIUkvZ1bSrw1KsEGSE1lAqIjbcmBPXha', 'uye', 0, 'kullanici', NOW());

-- JSON Dosya yapıları hakkında bilgi
-- NOT: Aşağıdaki dosyalar veritabanında değil, dosya sisteminde saklanır:
-- 
-- 1. data/events.json - Etkinlik takvimi verileri (veritabanı alternatifi)
-- 2. data/announcements.json - Duyurular (veritabanı alternatifi)
-- 3. data/links.json - Bağlantılar (veritabanı alternatifi)
-- 4. data/memories.json - Anılar (veritabanı alternatifi)
--
-- Bu JSON dosyaları aşağıdaki formatta boş bir dizi ile başlamalıdır:
-- []
--
-- Ayrıca aşağıdaki dizinler oluşturulmalıdır:
-- uploads/             - Genel dosya yüklemeleri için
-- uploads/gallery/     - Galeri resimleri için
-- uploads/memories/    - Anılar (fotoğraf ve videolar) için
-- uploads/profiles/    - Profil resimleri, kapak görselleri ve müzikler için

-- Kurulum tamamlandı