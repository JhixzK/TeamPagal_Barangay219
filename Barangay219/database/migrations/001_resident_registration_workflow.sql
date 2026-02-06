-- E-Barangay Resident Registration Workflow
-- Approval-based registration, Data Privacy Act 2012 compliant
-- Run after schema.sql. For existing DB, run run-migration.php to safely add columns.

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. RESIDENT_APPLICATIONS - Pending applications (no account)
-- ============================================================
DROP TABLE IF EXISTS `application_audit_log`;
DROP TABLE IF EXISTS `resident_applications`;

CREATE TABLE `resident_applications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_ref` VARCHAR(32) NOT NULL UNIQUE COMMENT 'APP-YYYYMMDD-NNNN',

  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10) DEFAULT NULL,
  `sex` ENUM('male', 'female', 'other') NOT NULL,
  `birth_date` DATE NOT NULL,
  `place_of_birth` VARCHAR(150) DEFAULT NULL,
  `civil_status` ENUM('single', 'married', 'widowed', 'divorced', 'separated') DEFAULT NULL,
  `citizenship` VARCHAR(50) DEFAULT 'Filipino',

  `family_code` VARCHAR(50) DEFAULT NULL,
  `relationship_to_head` VARCHAR(50) DEFAULT NULL,

  `house_number` VARCHAR(50) DEFAULT NULL,
  `street` VARCHAR(150) DEFAULT NULL,
  `purok_sitio` VARCHAR(100) DEFAULT NULL,
  `barangay` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `province` VARCHAR(100) NOT NULL,
  `length_of_residency_years` INT(11) DEFAULT NULL,

  `mobile_number` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `emergency_contact_name` VARCHAR(150) NOT NULL,
  `emergency_contact_number` VARCHAR(20) NOT NULL,
  `emergency_contact_relationship` VARCHAR(50) NOT NULL,

  `educational_attainment` VARCHAR(100) DEFAULT NULL,
  `employment_status` VARCHAR(50) DEFAULT NULL,
  `occupation` VARCHAR(100) DEFAULT NULL,

  `is_senior_citizen` TINYINT(1) DEFAULT 0,
  `is_pwd` TINYINT(1) DEFAULT 0,
  `pwd_id_number` VARCHAR(50) DEFAULT NULL,
  `is_solo_parent` TINYINT(1) DEFAULT 0,
  `solo_parent_id_number` VARCHAR(50) DEFAULT NULL,
  `is_ip_member` TINYINT(1) DEFAULT 0,
  `ip_group` VARCHAR(100) DEFAULT NULL,
  `is_4ps_beneficiary` TINYINT(1) DEFAULT 0,

  `valid_id_type` VARCHAR(50) NOT NULL,
  `valid_id_number` VARCHAR(100) NOT NULL,
  `id_document_path` VARCHAR(255) DEFAULT NULL,
  `proof_of_residency_path` VARCHAR(255) DEFAULT NULL,
  `data_privacy_consent` TINYINT(1) NOT NULL DEFAULT 1,

  `record_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `remarks` TEXT DEFAULT NULL,
  `reviewed_by` INT(11) DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,

  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application_ref` (`application_ref`),
  KEY `idx_record_status` (`record_status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. APPLICATION AUDIT LOG
-- ============================================================
CREATE TABLE `application_audit_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `performed_by` INT(11) DEFAULT NULL,
  `performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `details` JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Add new columns to residents (run run-migration.php if errors)
-- ============================================================
ALTER TABLE `residents` ADD COLUMN `resident_code` VARCHAR(30) DEFAULT NULL UNIQUE COMMENT 'BR219-YYYY-NNNNN' AFTER `id`;
ALTER TABLE `residents` ADD COLUMN `place_of_birth` VARCHAR(150) DEFAULT NULL AFTER `birth_date`;
ALTER TABLE `residents` ADD COLUMN `house_number` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `street` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `purok_sitio` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `length_of_residency_years` INT(11) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `emergency_contact_name` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `emergency_contact_number` VARCHAR(20) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `educational_attainment` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `employment_status` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `is_senior_citizen` TINYINT(1) DEFAULT 0;
ALTER TABLE `residents` ADD COLUMN `is_pwd` TINYINT(1) DEFAULT 0;
ALTER TABLE `residents` ADD COLUMN `pwd_id_number` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `is_solo_parent` TINYINT(1) DEFAULT 0;
ALTER TABLE `residents` ADD COLUMN `is_ip_member` TINYINT(1) DEFAULT 0;
ALTER TABLE `residents` ADD COLUMN `is_4ps_beneficiary` TINYINT(1) DEFAULT 0;
ALTER TABLE `residents` ADD COLUMN `record_status` ENUM('active', 'inactive', 'deceased', 'transferred') DEFAULT 'active';
ALTER TABLE `residents` ADD COLUMN `remarks` TEXT DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `last_updated_by` INT(11) DEFAULT NULL;
ALTER TABLE `residents` ADD COLUMN `last_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- ============================================================
-- 4. Add activation fields to users
-- ============================================================
ALTER TABLE `users` ADD COLUMN `activation_token` VARCHAR(64) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `activation_expires` DATETIME DEFAULT NULL;

-- Add resident role for approved residents
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman', 'resident') NOT NULL;

-- Foreign keys
ALTER TABLE `resident_applications`
  ADD CONSTRAINT `fk_applications_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
