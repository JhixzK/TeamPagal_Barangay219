-- Officials module table
-- Core officials (PH standard): 1 Captain, 7 Kagawad, 1 SK Chairperson, 1 Secretary, 1 Treasurer

USE `barangay219_db`;

CREATE TABLE IF NOT EXISTS `officials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `position` ENUM('barangay_captain', 'kagawad', 'sk_chairperson', 'secretary', 'treasurer') NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `resident_id` INT(11) DEFAULT NULL,
  `term_start` DATE DEFAULT NULL,
  `term_end` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_position` (`position`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_resident_id` (`resident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

