-- Dark Legion Web Portal - Veritabanı Kurulum Dosyası v2.0
-- Bu SQL, Dark Legion Web Portal için tüm modülleri içeren tam veritabanı yapısını oluşturur.
-- Modüller: Temel sistem, Kullanıcı profilleri, Dosya yükleme, Anılar, Oyunlar ve Anketler

-- Veritabanını oluştur (yoksa)
CREATE DATABASE IF NOT EXISTS `darklegion` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `darklegion`;

-- Tabloları DROP TABLE IF EXISTS ile silmeden önce foreign key kontrolünü kapat
SET FOREIGN_KEY_CHECKS = 0;

-- Eski tabloları temizle (zaten varsa)
DROP TABLE IF EXISTS `user_game_badges`;
DROP TABLE IF EXISTS `game_badges`;
DROP TABLE IF EXISTS `game_scores`;
DROP TABLE IF EXISTS `games`;
DROP TABLE IF EXISTS `poll_comments`;
DROP TABLE IF EXISTS `poll_votes`;
DROP TABLE IF EXISTS `poll_options`;
DROP TABLE IF EXISTS `polls`;
DROP TABLE IF EXISTS `ai_logs`;
DROP TABLE IF EXISTS `memories`;
DROP TABLE IF EXISTS `uploads`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `links`;
DROP TABLE IF EXISTS `users`;

-- Foreign key kontrolünü geri aç
SET FOREIGN_KEY_CHECKS = 1;

-- Kullanıcılar tablosu
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','uye','misafir') NOT NULL DEFAULT 'misafir',
  `ai_access` tinyint(1) NOT NULL DEFAULT 0,
  `profile_url` varchar(50) DEFAULT NULL,
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
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `profile_url` (`profile_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dosya yüklemeleri tablosu
CREATE TABLE `uploads` (
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
CREATE TABLE `ai_logs` (
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
CREATE TABLE `memories` (
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
CREATE TABLE `events` (
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
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bağlantılar tablosu
CREATE TABLE `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Oyunlar tablosu
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `thumbnail` varchar(255) NOT NULL,
  `is_multiplayer` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active', 'inactive', 'coming_soon') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Oyun skorları tablosu
CREATE TABLE `game_scores` (
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

-- Oyun rozetleri tablosu
CREATE TABLE `game_badges` (
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

-- Kullanıcı rozetleri tablosu
CREATE TABLE `user_game_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `achieved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badge` (`user_id`, `badge_id`),
  KEY `badge_id` (`badge_id`),
  CONSTRAINT `user_game_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_game_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `game_badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anket sistemi tabloları
CREATE TABLE `polls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','ended','draft') NOT NULL DEFAULT 'active',
  `allow_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `polls_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `poll_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `option_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `poll_id` (`poll_id`),
  CONSTRAINT `poll_options_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `poll_id` (`poll_id`),
  KEY `option_id` (`option_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `poll_votes_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_votes_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_votes_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `poll_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `poll_id` (`poll_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `poll_comments_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek admin kullanıcısı ekle (şifre: admin123)
-- NOT: Güvenlik için bu şifreyi değiştirmeyi unutmayın!
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `profile_url`)
SELECT 'admin', 'admin@example.com', '$2y$10$fCOFiMnqKYykVpNfrSkOYOv8Gt9zXRJe8Qx5iLy1tPLPLtqUbOrpW', 'admin', 1, 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin' OR `email` = 'admin@example.com');

-- Örnek normal kullanıcı ekle (şifre: user123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `ai_access`, `profile_url`)
SELECT 'kullanici', 'kullanici@example.com', '$2y$10$4LYA8jGGv5M7Qt5TU/K9WuIUkvZ1bSrw1KsEGSE1lAqIjbcmBPXha', 'uye', 0, 'kullanici'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'kullanici' OR `email` = 'kullanici@example.com');

-- Örnek oyunları ekle
INSERT INTO `games` (`name`, `slug`, `description`, `thumbnail`, `is_multiplayer`, `status`)
SELECT 'Yılan Oyunu', 'snake', 'Klasik yılan oyunu. Yılanı yönlendirerek elmaları topla, duvarlara ve kendine çarpmamaya dikkat et!', 'games/assets/snake_thumb.png', 0, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `games` WHERE `slug` = 'snake');

INSERT INTO `games` (`name`, `slug`, `description`, `thumbnail`, `is_multiplayer`, `status`)
SELECT 'Tetris', 'tetris', 'Düşen blokları düzenleyerek tam satırlar oluştur ve puan kazan.', 'games/assets/tetris_thumb.png', 0, 'coming_soon'
WHERE NOT EXISTS (SELECT 1 FROM `games` WHERE `slug` = 'tetris');

INSERT INTO `games` (`name`, `slug`, `description`, `thumbnail`, `is_multiplayer`, `status`)
SELECT 'Hafıza Oyunu', 'memory', 'Eşleşen kartları bul. Hafızanı test et ve en yüksek puanı elde et!', 'games/assets/memory_thumb.png', 0, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `games` WHERE `slug` = 'memory');

INSERT INTO `games` (`name`, `slug`, `description`, `thumbnail`, `is_multiplayer`, `status`)
SELECT 'XOX', 'tictactoe', 'İki kişilik XOX oyunu. Arkadaşınla oyna ve kazanmak için stratejik hamlelerde bulun.', 'games/assets/tictactoe_thumb.png', 1, 'active'
WHERE NOT EXISTS (SELECT 1 FROM `games` WHERE `slug` = 'tictactoe');

-- Yılan oyunu için örnek rozetleri ekle (varsa ekleme)
-- Önce oyun ID'sini al
SET @snake_game_id = (SELECT id FROM games WHERE slug = 'snake' LIMIT 1);

-- Eğer oyun ID'si bulunduysa rozet ekle
INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`)
SELECT @snake_game_id, 'Çırak Yılancı', 'İlk oyununu oyna', '🐍', 'play_count', 1
FROM `games` 
WHERE `slug` = 'snake' 
AND NOT EXISTS (
    SELECT 1 FROM `game_badges` 
    WHERE `game_id` = @snake_game_id AND `name` = 'Çırak Yılancı'
);

INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`)
SELECT @snake_game_id, 'Yılan Avcısı', '500 puan üzerinde skor yap', '🏆', 'min_score', 500
FROM `games` 
WHERE `slug` = 'snake' 
AND NOT EXISTS (
    SELECT 1 FROM `game_badges` 
    WHERE `game_id` = @snake_game_id AND `name` = 'Yılan Avcısı'
);

INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`)
SELECT @snake_game_id, 'Yılan Efendisi', '1000 puan üzerinde skor yap', '👑', 'min_score', 1000
FROM `games` 
WHERE `slug` = 'snake' 
AND NOT EXISTS (
    SELECT 1 FROM `game_badges` 
    WHERE `game_id` = @snake_game_id AND `name` = 'Yılan Efendisi'
);

-- XOX oyunu için örnek rozetleri ekle (varsa ekleme)
-- Önce oyun ID'sini al
SET @tictactoe_game_id = (SELECT id FROM games WHERE slug = 'tictactoe' LIMIT 1);

-- Eğer oyun ID'si bulunduysa rozet ekle
INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`)
SELECT @tictactoe_game_id, 'Çaylak', 'İlk XOX oyununu oyna', '❌', 'play_count', 1
FROM `games` 
WHERE `slug` = 'tictactoe' 
AND NOT EXISTS (
    SELECT 1 FROM `game_badges` 
    WHERE `game_id` = @tictactoe_game_id AND `name` = 'Çaylak'
);

INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`)
SELECT @tictactoe_game_id, 'Stratejist', '10 puan üzerinde skor yap', '⭕', 'min_score', 10
FROM `games` 
WHERE `slug` = 'tictactoe' 
AND NOT EXISTS (
    SELECT 1 FROM `game_badges` 
    WHERE `game_id` = @tictactoe_game_id AND `name` = 'Stratejist'
);

INSERT INTO `game_badges` (`game_id`, `name`, `description`, `icon`, `requirement`, `requirement_value`)
SELECT @tictactoe_game_id, 'XOX Ustası', '20 puan üzerinde skor yap', '🏆', 'min_score', 20
FROM `games` 
WHERE `slug` = 'tictactoe' 
AND NOT EXISTS (
    SELECT 1 FROM `game_badges` 
    WHERE `game_id` = @tictactoe_game_id AND `name` = 'XOX Ustası'
);

-- Örnek anket ekle (varsa ekleme)
INSERT INTO `polls` (`user_id`, `title`, `description`, `status`, `allow_multiple`, `end_date`)
SELECT 1, 'En sevdiğiniz programlama dili hangisi?', 'Yazılım geliştirirken tercih ettiğiniz programlama dilini seçin.', 'active', 0, DATE_ADD(NOW(), INTERVAL 30 DAY)
FROM `users`
WHERE `username` = 'admin' 
AND NOT EXISTS (SELECT 1 FROM `polls` WHERE `title` = 'En sevdiğiniz programlama dili hangisi?');

-- Örnek anket seçenekleri ekle
SET @poll_id = LAST_INSERT_ID();

INSERT INTO `poll_options` (`poll_id`, `option_text`, `option_order`)
SELECT @poll_id, 'PHP', 1
WHERE @poll_id > 0 AND NOT EXISTS (
    SELECT 1 FROM `poll_options` 
    WHERE `poll_id` = @poll_id AND `option_text` = 'PHP'
);

INSERT INTO `poll_options` (`poll_id`, `option_text`, `option_order`)
SELECT @poll_id, 'JavaScript', 2
WHERE @poll_id > 0 AND NOT EXISTS (
    SELECT 1 FROM `poll_options` 
    WHERE `poll_id` = @poll_id AND `option_text` = 'JavaScript'
);

INSERT INTO `poll_options` (`poll_id`, `option_text`, `option_order`)
SELECT @poll_id, 'Python', 3
WHERE @poll_id > 0 AND NOT EXISTS (
    SELECT 1 FROM `poll_options` 
    WHERE `poll_id` = @poll_id AND `option_text` = 'Python'
);

INSERT INTO `poll_options` (`poll_id`, `option_text`, `option_order`)
SELECT @poll_id, 'Java', 4
WHERE @poll_id > 0 AND NOT EXISTS (
    SELECT 1 FROM `poll_options` 
    WHERE `poll_id` = @poll_id AND `option_text` = 'Java'
);

INSERT INTO `poll_options` (`poll_id`, `option_text`, `option_order`)
SELECT @poll_id, 'C#', 5
WHERE @poll_id > 0 AND NOT EXISTS (
    SELECT 1 FROM `poll_options` 
    WHERE `poll_id` = @poll_id AND `option_text` = 'C#'
);

INSERT INTO `poll_options` (`poll_id`, `option_text`, `option_order`)
SELECT @poll_id, 'Diğer', 6
WHERE @poll_id > 0 AND NOT EXISTS (
    SELECT 1 FROM `poll_options` 
    WHERE `poll_id` = @poll_id AND `option_text` = 'Diğer'
);

-- NOT: Aşağıdaki dizinler oluşturulmalıdır:
-- uploads/             - Genel dosya yüklemeleri için
-- uploads/gallery/     - Galeri resimleri için
-- uploads/memories/    - Anılar (fotoğraf ve videolar) için
-- uploads/profiles/    - Profil resimleri, kapak görselleri ve müzikler için
-- games/               - Oyun dosyaları için
-- games/assets/        - Oyun görselleri için
-- games/js/            - Oyun JavaScript dosyaları için

-- Kurulum tamamlandı