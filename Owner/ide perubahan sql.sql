perubahan
nambah

CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` TEXT NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `code_id` (`code_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`code_id`) REFERENCES `codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `comments`
ADD `parent_id` INT(11) NULL DEFAULT NULL AFTER `user_id`,
ADD `likes_count` INT(11) NOT NULL DEFAULT 0 AFTER `comment_text`,
ADD `is_edited` BOOLEAN NOT NULL DEFAULT FALSE AFTER `likes_count`,
ADD KEY `parent_id` (`parent_id`),
ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `comment_likes` (
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`comment_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `users`
ADD `uuid` VARCHAR(36) NOT NULL AFTER `id`,
ADD `role` ENUM('user', 'moderator', 'admin', 'owner') NOT NULL DEFAULT 'user' AFTER `display_name`;

UPDATE users SET uuid = UUID() WHERE uuid = '';
UPDATE users SET role = 'owner' WHERE id = 1;


well kalo gagal
cara hapus nya

