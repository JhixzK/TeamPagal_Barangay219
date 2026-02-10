-- Resident Registration Workflow Migration
-- Adds resident applications, audit log, and required columns

USE `barangay219_db`;

-- Applications table
CREATE TABLE IF NOT EXISTS `resident_applications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_ref` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10) DEFAULT NULL,
  `sex` ENUM('male','female','other') NOT NULL,
  `birth_date` DATE NOT NULL,
  `place_of_birth` VARCHAR(150) DEFAULT NULL,
  `civil_status` VARCHAR(30) DEFAULT NULL,
  `citizenship` VARCHAR(50) DEFAULT 'Filipino',
  `family_code` VARCHAR(30) DEFAULT NULL,
  `relationship_to_head` VARCHAR(50) DEFAULT NULL,
  `house_number` VARCHAR(30) DEFAULT NULL,
  `street` VARCHAR(100) DEFAULT NULL,
  `purok_sitio` VARCHAR(80) DEFAULT NULL,
  `barangay` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `province` VARCHAR(100) DEFAULT NULL,
  `length_of_residency_years` INT(11) DEFAULT NULL,
  `mobile_number` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `emergency_contact_name` VARCHAR(100) NOT NULL,
  `emergency_contact_number` VARCHAR(20) NOT NULL,
  `emergency_contact_relationship` VARCHAR(50) NOT NULL,
  `educational_attainment` VARCHAR(50) DEFAULT NULL,
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
  `valid_id_type` VARCHAR(50) DEFAULT NULL,
  `valid_id_number` VARCHAR(100) DEFAULT NULL,
  `id_document_path` VARCHAR(255) DEFAULT NULL,
  `proof_of_residency_path` VARCHAR(255) DEFAULT NULL,
  `data_privacy_consent` TINYINT(1) NOT NULL DEFAULT 1,
  `record_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT(11) DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_application_ref` (`application_ref`),
  KEY `idx_status` (`record_status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application audit log
CREATE TABLE IF NOT EXISTS `application_audit_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `performed_by` INT(11) DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend residents table
ALTER TABLE `residents`
  ADD COLUMN `resident_code` VARCHAR(30) NULL UNIQUE AFTER `id`,
  ADD COLUMN `place_of_birth` VARCHAR(150) NULL AFTER `birth_date`,
  ADD COLUMN `house_number` VARCHAR(30) NULL AFTER `address`,
  ADD COLUMN `street` VARCHAR(100) NULL AFTER `house_number`,
  ADD COLUMN `purok_sitio` VARCHAR(80) NULL AFTER `street`,
  ADD COLUMN `email` VARCHAR(100) NULL AFTER `contact_number`,
  ADD COLUMN `length_of_residency_years` INT(11) NULL AFTER `email`,
  ADD COLUMN `emergency_contact_name` VARCHAR(100) NULL AFTER `length_of_residency_years`,
  ADD COLUMN `emergency_contact_number` VARCHAR(20) NULL AFTER `emergency_contact_name`,
  ADD COLUMN `emergency_contact_relationship` VARCHAR(50) NULL AFTER `emergency_contact_number`,
  ADD COLUMN `educational_attainment` VARCHAR(50) NULL AFTER `emergency_contact_relationship`,
  ADD COLUMN `employment_status` VARCHAR(50) NULL AFTER `educational_attainment`,
  ADD COLUMN `is_senior_citizen` TINYINT(1) DEFAULT 0 AFTER `employment_status`,
  ADD COLUMN `is_pwd` TINYINT(1) DEFAULT 0 AFTER `is_senior_citizen`,
  ADD COLUMN `pwd_id_number` VARCHAR(50) NULL AFTER `is_pwd`,
  ADD COLUMN `is_solo_parent` TINYINT(1) DEFAULT 0 AFTER `pwd_id_number`,
  ADD COLUMN `solo_parent_id_number` VARCHAR(50) NULL AFTER `is_solo_parent`,
  ADD COLUMN `is_ip_member` TINYINT(1) DEFAULT 0 AFTER `solo_parent_id_number`,
  ADD COLUMN `ip_group` VARCHAR(100) NULL AFTER `is_ip_member`,
  ADD COLUMN `is_4ps_beneficiary` TINYINT(1) DEFAULT 0 AFTER `ip_group`,
  ADD COLUMN `record_status` VARCHAR(30) DEFAULT 'active' AFTER `is_4ps_beneficiary`,
  ADD COLUMN `remarks` TEXT NULL AFTER `record_status`,
  ADD COLUMN `last_updated_by` INT(11) NULL AFTER `remarks`,
  ADD COLUMN `last_updated_at` DATETIME NULL AFTER `last_updated_by`;

-- Extend users table for activation flow
ALTER TABLE `users`
  ADD COLUMN `activation_token` VARCHAR(64) NULL AFTER `status`,
  ADD COLUMN `activation_expires` DATETIME NULL AFTER `activation_token`;

-- Ensure resident role exists for user activation
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman', 'resident') NOT NULL;
