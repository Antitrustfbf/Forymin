CREATE DATABASE IF NOT EXISTS `termux_forum` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `termux_forum`;

CREATE TABLE IF NOT EXISTS `threads` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `author_name` VARCHAR(60) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `thread_id` INT UNSIGNED NOT NULL,
  `author_name` VARCHAR(60) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  CONSTRAINT `fk_posts_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE,
  INDEX (`thread_id`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;