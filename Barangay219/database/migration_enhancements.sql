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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create officials table for core officials listing
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

-- 8. Create role_permissions table for module access control
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role` ENUM('super_admin', 'barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman', 'resident') NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `can_access` TINYINT(1) NOT NULL DEFAULT 0,
  `can_create` TINYINT(1) NOT NULL DEFAULT 0,
  `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_module` (`role`, `module`),
  KEY `idx_role` (`role`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default role permissions
INSERT INTO role_permissions (role, module, can_access, can_create, can_edit, can_delete) VALUES
('barangay_captain', 'dashboard', 1, 1, 1, 1),
('barangay_captain', 'applications', 1, 1, 1, 1),
('barangay_captain', 'resident_applications', 1, 1, 1, 1),
('barangay_captain', 'residents', 1, 1, 1, 1),
('barangay_captain', 'households', 1, 1, 1, 1),
('barangay_captain', 'certificates', 1, 1, 1, 1),
('barangay_captain', 'blotters', 1, 1, 1, 1),
('barangay_captain', 'complaints', 1, 1, 1, 1),
('barangay_captain', 'announcements', 1, 1, 1, 1),
('barangay_captain', 'reports', 1, 1, 1, 1),
('barangay_captain', 'users', 1, 1, 1, 1),
('barangay_captain', 'profile', 1, 1, 1, 1),
('secretary', 'dashboard', 1, 1, 1, 1),
('secretary', 'applications', 1, 1, 1, 1),
('secretary', 'resident_applications', 1, 1, 1, 1),
('secretary', 'residents', 1, 1, 1, 1),
('secretary', 'households', 1, 1, 1, 1),
('secretary', 'certificates', 1, 1, 1, 1),
('secretary', 'blotters', 1, 1, 1, 1),
('secretary', 'complaints', 1, 1, 1, 1),
('secretary', 'announcements', 1, 1, 1, 1),
('secretary', 'reports', 1, 1, 1, 1),
('treasurer', 'dashboard', 1, 1, 1, 1),
('treasurer', 'certificates', 1, 1, 1, 1),
('treasurer', 'reports', 1, 1, 1, 1),
('kagawad', 'dashboard', 1, 1, 1, 1),
('kagawad', 'blotters', 1, 1, 1, 1),
('kagawad', 'complaints', 1, 1, 1, 1),
('kagawad', 'announcements', 1, 1, 1, 1),
('sk_chairman', 'dashboard', 1, 1, 1, 1),
('sk_chairman', 'announcements', 1, 1, 1, 1),
('resident', 'dashboard', 1, 0, 0, 0),
('resident', 'announcements', 1, 0, 0, 0),
('resident', 'profile', 1, 0, 0, 0)
ON DUPLICATE KEY UPDATE can_access = VALUES(can_access), can_create = VALUES(can_create), can_edit = VALUES(can_edit), can_delete = VALUES(can_delete);
