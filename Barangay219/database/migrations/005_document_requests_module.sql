-- Document Requests module migration
-- Creates resident-facing document request tracking table

CREATE TABLE IF NOT EXISTS `document_requests` (
  `request_id` INT(11) NOT NULL AUTO_INCREMENT,
  `tracking_code` VARCHAR(40) DEFAULT NULL,
  `resident_id` INT(11) NOT NULL,
  `certificate_type` VARCHAR(120) NOT NULL,
  `purpose` VARCHAR(120) NOT NULL,
  `business_name` VARCHAR(180) DEFAULT NULL,
  `business_address` VARCHAR(255) DEFAULT NULL,
  `uploaded_files` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Submitted',
  `date_requested` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `admin_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uniq_tracking_code` (`tracking_code`),
  KEY `idx_resident_id` (`resident_id`),
  KEY `idx_status` (`status`),
  KEY `idx_certificate_type` (`certificate_type`),
  KEY `idx_date_requested` (`date_requested`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional foreign key (uncomment if residents table has matching ids and constraints are desired)
-- ALTER TABLE `document_requests`
--   ADD CONSTRAINT `fk_document_requests_resident`
--   FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`)
--   ON DELETE RESTRICT ON UPDATE CASCADE;
