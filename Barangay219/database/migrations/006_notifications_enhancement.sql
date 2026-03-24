-- Extends notifications for staff (user_id), deep links, and read timestamps.
-- Schema is also auto-migrated at runtime via includes/notifications-store.php.

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) DEFAULT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'info',
  `event_type` VARCHAR(80) DEFAULT NULL,
  `link_url` VARCHAR(512) DEFAULT NULL,
  `payload` TEXT DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `status` VARCHAR(30) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resident_id` (`resident_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
