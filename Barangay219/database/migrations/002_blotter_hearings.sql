-- Blotter Hearings Migration
-- Adds hearing history for blotter cases

USE `barangay219_db`;

CREATE TABLE IF NOT EXISTS `blotter_hearings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `blotter_id` INT(11) NOT NULL,
  `hearing_date` DATE DEFAULT NULL,
  `status` ENUM('scheduled', 'completed', 'postponed', 'cancelled') DEFAULT 'scheduled',
  `outcome` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `next_hearing_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blotter_id` (`blotter_id`),
  KEY `idx_hearing_date` (`hearing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `blotter_hearings`
  ADD CONSTRAINT `fk_blotter_hearings_blotter` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
