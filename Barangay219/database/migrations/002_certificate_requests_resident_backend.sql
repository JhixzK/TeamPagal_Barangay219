-- Resident Certificate Request backend table
CREATE TABLE IF NOT EXISTS `certificate_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) NOT NULL,
  `certificate_type` VARCHAR(120) NOT NULL,
  `purpose` TEXT DEFAULT NULL,
  `reference_number` VARCHAR(50) NOT NULL,
  `status` ENUM('pending','approved','ready_for_pickup','rejected','released') NOT NULL DEFAULT 'pending',
  `attachment` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reference_number` (`reference_number`),
  KEY `idx_resident_id` (`resident_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_certificate_requests_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
