-- Dark Legion Web Portal - Veritabanı Kurulum Dosyası v3
-- Bu SQL dosyası, Dark Legion Web Portal için gerekli tüm veritabanı yapısını oluşturur.
-- Tüm modüller için gerekli tablolar dahildir (Temel sistem, Profil, Anılar, Oyunlar).

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

-- Oyunlar tablosu (yeni)
CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL UNIQUE,
  `description` text NOT NULL,
  `thumbnail` varchar(255) NOT NULL,
  `is_multiplayer` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active', 'inactive', 'coming_soon') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Oyun skorları tablosu (yeni)
CREATE TABLE IF NOT EXISTS `game_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `level` int(11) DEFAULT 1,
  `time_played` int(11) DEFAULT 0,
  `play_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `game_scores_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `game_scores_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Oyun rozetleri tablosu (yeni)
CREATE TABLE IF NOT EXISTS `game_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `icon` varchar(255) NOT NULL,
  `requirement` varchar(255) NOT NULL,
  `requirement_value` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `game_badges_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kullanıcı rozetleri tablosu (yeni)
CREATE TABLE IF NOT EXISTS `user_game_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `achieved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badge` (`user_id`, `badge_id`),
  KEY `user_id` (`user_id`),
  KEY `badge_id` (`badge_id`),
  CONSTRAINT `user_game_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_game_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `game_badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek admin kullanıcısı ekle (şifre: admin123)
-- NOT: Güvenlik için bu şifreyi değiştirmeyi unutmayın!
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `profile_url`, `created_at`) VALUES
('admin', 'admin@example.com', '$2y$10$fCOFiMnqKYykVpNfrSkOYOv8Gt9zXRJe8Qx5iLy1tPLPLtqUbOrpW', 'admin', 1, 'admin', NOW());

-- Örnek normal kullanıcı ekle (şifre: user123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `profile_url`, `created_at`) VALUES
('kullanici', 'kullanici@example.com', '$2y$10$4LYA8jGGv5M7Qt5TU/K9WuIUkvZ1bSrw1KsEGSE1lAqIjbcmBPXha', 'uye', 0, 'kullanici', NOW());

-- Örnek oyunları ekle
INSERT INTO `games` (`name`, `slug`, `description`, `thumbnail`, `is_multiplayer`, `status`) VALUES
('Yılan Oyunu', 'snake', 'Klasik yılan oyunu. Yılanı yönlendirerek elmaları topla, duvarlara ve kendine çarpmamaya dikkat et!', 'games/assets/snake_thumb.png', 0, 'active'),
('Tetris', 'tetris', 'Düşen blokları düzenleyerek tam satırlar oluştur ve puan kazan.', 'games/assets/tetris_thumb.png', 0, 'coming_soon'),
('Hafıza Oyunu', 'memory', 'Eşleşen kartları bul. Hafızanı test et ve en yüksek puanı elde et!', 'games/assets/memory_thumb.png', 0, 'active'),
('XOX', 'tictactoe', 'İki kişilik XOX oyunu. Arkadaşınla oyna ve kazanmak için stratejik hamlelerde bulun.', 'games/assets/tictactoe_thumb.png', 1, 'active');

-- Yılan oyunu için rozet örnekleri
SET @snake_game_id = (SELECT id FROM games WHERE slug = 'snake' LIMIT 1);

INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`) VALUES
(@snake_game_id, 'Çırak Yılancı', 'İlk oyununu oyna', '🐍', 'play_count', 1),
(@snake_game_id, 'Yılan Avcısı', '500 puan üzerinde skor yap', '🏆', 'min_score', 500),
(@snake_game_id, 'Yılan Efendisi', '1000 puan üzerinde skor yap', '👑', 'min_score', 1000);

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
-- games/               - Oyun dosyaları için
-- games/assets/        - Oyun görselleri için
-- games/js/            - Oyun JavaScript dosyaları için

-- Kurulum tamamlandı