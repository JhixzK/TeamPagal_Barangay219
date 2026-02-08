-- E-Barangay Enhancement Migration
-- Run this after schema.sql / import-all.sql
-- Adds: application_ref, control_number, resident_id (complaints), activity_logs, certificate types

USE `barangay219_db`;

-- 1. Add application_ref, control_number, remarks to certificate_requests
ALTER TABLE `certificate_requests` ADD COLUMN `application_ref` VARCHAR(50) NULL UNIQUE AFTER `id`;
ALTER TABLE `certificate_requests` ADD COLUMN `control_number` VARCHAR(50) NULL AFTER `issued_date`;
ALTER TABLE `certificate_requests` ADD COLUMN `remarks` TEXT NULL AFTER `purpose`;

-- Generate application_ref for existing records
UPDATE certificate_requests SET application_ref = CONCAT('APP-', id, '-', YEAR(created_at)) 
WHERE application_ref IS NULL;

ALTER TABLE certificate_requests MODIFY application_ref VARCHAR(50) NOT NULL;

-- 2. Modify certificate_type enum to add good_moral
ALTER TABLE `certificate_requests` 
  MODIFY COLUMN `certificate_type` ENUM(
    'barangay_clearance', 
    'certificate_indigency', 
    'certificate_residency', 
    'certificate_good_moral',
    'transfer_request'
  ) NOT NULL;

-- 3. Add resident_id and remarks to complaints
ALTER TABLE `complaints` ADD COLUMN `resident_id` INT(11) NULL AFTER `complainant_name`;
ALTER TABLE `complaints` ADD COLUMN `remarks` TEXT NULL AFTER `resolution_date`;

-- 4. Add archived to announcements status
ALTER TABLE `announcements` 
  MODIFY COLUMN `status` ENUM('active', 'inactive', 'expired', 'archived') DEFAULT 'active';

-- 5. Create activity_logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `entity_id` INT(11) DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create certificates_issued table for issuance history (control numbers)
CREATE TABLE IF NOT EXISTS `certificates_issued` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `certificate_request_id` INT(11) NOT NULL,
  `control_number` VARCHAR(50) NOT NULL UNIQUE,
  `issued_to` INT(11) NOT NULL,
  `issued_by` INT(11) NOT NULL,
  `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cert_request` (`certificate_request_id`),
  KEY `idx_control_number` (`control_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
