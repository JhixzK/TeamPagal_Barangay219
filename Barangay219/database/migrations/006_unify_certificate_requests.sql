-- Unify certificate requests and extend editable certificate snapshot fields
-- Date: 2026-03-13

CREATE TABLE IF NOT EXISTS `certificate_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) NOT NULL,
  `requested_by` INT(11) DEFAULT NULL,
  `certificate_type` VARCHAR(120) NOT NULL,
  `purpose` TEXT DEFAULT NULL,
  `status` ENUM('pending','under_review','approved','rejected','issued','cancelled') NOT NULL DEFAULT 'pending',
  `attachment` VARCHAR(255) DEFAULT NULL,
  `reference_number` VARCHAR(50) DEFAULT NULL,
  `issued_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resident` (`resident_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`certificate_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `certificate_requests`
  ADD COLUMN IF NOT EXISTS `attachment` VARCHAR(255) DEFAULT NULL AFTER `purpose`,
  ADD COLUMN IF NOT EXISTS `reference_number` VARCHAR(50) DEFAULT NULL AFTER `attachment`,
  ADD COLUMN IF NOT EXISTS `cert_name` VARCHAR(255) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `cert_address` TEXT DEFAULT NULL AFTER `cert_name`,
  ADD COLUMN IF NOT EXISTS `cert_purpose` TEXT DEFAULT NULL AFTER `cert_address`,
  ADD COLUMN IF NOT EXISTS `cert_body` TEXT DEFAULT NULL AFTER `cert_purpose`,
  ADD COLUMN IF NOT EXISTS `date_issued` DATE DEFAULT NULL AFTER `cert_body`,
  ADD COLUMN IF NOT EXISTS `control_number` VARCHAR(50) DEFAULT NULL AFTER `date_issued`,
  ADD COLUMN IF NOT EXISTS `approved_at` DATETIME DEFAULT NULL AFTER `control_number`,
  ADD COLUMN IF NOT EXISTS `admin_id` INT(11) DEFAULT NULL AFTER `approved_at`;

ALTER TABLE `certificate_requests`
  MODIFY COLUMN `status` ENUM('pending','under_review','approved','rejected','issued','cancelled') NOT NULL DEFAULT 'pending';

UPDATE `certificate_requests`
SET `reference_number` = CONCAT('REQ-BRGY219-', YEAR(COALESCE(`created_at`, NOW())), '-', LPAD(`id`, 5, '0'))
WHERE `reference_number` IS NULL OR `reference_number` = '';

ALTER TABLE `certificate_requests`
  ADD UNIQUE KEY `uniq_reference_number` (`reference_number`);
