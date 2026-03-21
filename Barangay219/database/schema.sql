-- E-Barangay Information Management System
-- Database Schema for Barangay 195, Tondo, Manila
-- Created: 2025

-- Drop existing tables if they exist (for fresh installation)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `application_audit_log`;
DROP TABLE IF EXISTS `resident_applications`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `blotter_hearings`;
DROP TABLE IF EXISTS `blotters`;
DROP TABLE IF EXISTS `certificate_requests`;
DROP TABLE IF EXISTS `households`;
DROP TABLE IF EXISTS `officials`;
DROP TABLE IF EXISTS `residents`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Users table - Barangay officials and personnel
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('super_admin', 'barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman', 'resident') NOT NULL,
  `resident_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `activation_token` VARCHAR(64) DEFAULT NULL,
  `activation_expires` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Officials table - Barangay core officials listing
CREATE TABLE `officials` (
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

-- Residents table - Central entity for all residents
CREATE TABLE `residents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resident_code` VARCHAR(30) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10) DEFAULT NULL,
  `birth_date` DATE NOT NULL,
  `place_of_birth` VARCHAR(150) DEFAULT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL,
  `civil_status` ENUM('single', 'married', 'widowed', 'divorced', 'separated') DEFAULT NULL,
  `occupation` VARCHAR(100) DEFAULT NULL,
  `citizenship` VARCHAR(50) DEFAULT 'Filipino',
  `address` TEXT NOT NULL,
  `house_number` VARCHAR(30) DEFAULT NULL,
  `street` VARCHAR(100) DEFAULT NULL,
  `purok_sitio` VARCHAR(80) DEFAULT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `length_of_residency_years` INT(11) DEFAULT NULL,
  `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
  `emergency_contact_number` VARCHAR(20) DEFAULT NULL,
  `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL,
  `educational_attainment` VARCHAR(50) DEFAULT NULL,
  `employment_status` VARCHAR(50) DEFAULT NULL,
  `is_senior_citizen` TINYINT(1) DEFAULT 0,
  `is_pwd` TINYINT(1) DEFAULT 0,
  `pwd_id_number` VARCHAR(50) DEFAULT NULL,
  `is_solo_parent` TINYINT(1) DEFAULT 0,
  `solo_parent_id_number` VARCHAR(50) DEFAULT NULL,
  `is_ip_member` TINYINT(1) DEFAULT 0,
  `ip_group` VARCHAR(100) DEFAULT NULL,
  `is_4ps_beneficiary` TINYINT(1) DEFAULT 0,
  `record_status` VARCHAR(30) DEFAULT 'active',
  `remarks` TEXT DEFAULT NULL,
  `last_updated_by` INT(11) DEFAULT NULL,
  `last_updated_at` DATETIME DEFAULT NULL,
  `household_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'deceased', 'transferred') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_resident_code` (`resident_code`),
  KEY `idx_household` (`household_id`),
  KEY `idx_name` (`last_name`, `first_name`),
  KEY `idx_status` (`status`),
  FULLTEXT KEY `idx_search` (`first_name`, `middle_name`, `last_name`, `address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Households table - Family/household information
CREATE TABLE `households` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `family_head_id` INT(11) NOT NULL,
  `address` TEXT NOT NULL,
  `total_members` INT(11) DEFAULT 1,
  `registration_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_family_head` (`family_head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificate Requests table - Certificate processing
CREATE TABLE `certificate_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resident_id` INT(11) NOT NULL,
  `requested_by` INT(11) NOT NULL,
  `certificate_type` ENUM('barangay_clearance', 'certificate_indigency', 'certificate_residency', 'transfer_request') NOT NULL,
  `purpose` TEXT DEFAULT NULL,
  `purpose_details` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'ready_for_pickup', 'rejected', 'released') DEFAULT 'pending',
  `issued_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resident` (`resident_id`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`certificate_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blotters table - Incident and dispute records
CREATE TABLE `blotters` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `case_title` VARCHAR(255) NOT NULL,
  `complainant_name` VARCHAR(255) NOT NULL,
  `respondent_name` VARCHAR(255) DEFAULT NULL,
  `incident_date` DATE NOT NULL,
  `incident_location` TEXT DEFAULT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('pending', 'under_investigation', 'resolved', 'settled', 'referred') DEFAULT 'pending',
  `settlement_date` DATE DEFAULT NULL,
  `handled_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_handled_by` (`handled_by`),
  KEY `idx_status` (`status`),
  KEY `idx_incident_date` (`incident_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blotter Hearings table - Hearing history per blotter case
CREATE TABLE `blotter_hearings` (
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

-- Complaints table - General complaints module
CREATE TABLE `complaints` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reference_number` VARCHAR(30) DEFAULT NULL,
  `resident_id` INT(11) DEFAULT NULL,
  `complaint_title` VARCHAR(255) NOT NULL,
  `complainant_name` VARCHAR(255) NOT NULL,
  `respondent_name` VARCHAR(255) DEFAULT NULL,
  `respondent_address` VARCHAR(255) DEFAULT NULL,
  `respondent_barangay` VARCHAR(150) DEFAULT NULL,
  `respondent_city` VARCHAR(150) DEFAULT NULL,
  `respondent_residency` ENUM('Resident of this Barangay', 'Resident of another Barangay', 'Unknown') DEFAULT 'Unknown',
  `complaint_type` VARCHAR(100) DEFAULT NULL,
  `narrative` TEXT NOT NULL,
  `filing_date` DATE NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `incident_date` DATE DEFAULT NULL,
  `incident_time` TIME DEFAULT NULL,
  `incident_house_street` VARCHAR(255) DEFAULT NULL,
  `incident_purok` VARCHAR(100) DEFAULT NULL,
  `incident_landmark` VARCHAR(255) DEFAULT NULL,
  `incident_barangay` VARCHAR(150) DEFAULT NULL,
  `evidence_file` VARCHAR(255) DEFAULT NULL,
  `jurisdiction_status` ENUM('Valid', 'Outside Jurisdiction') DEFAULT 'Valid',
  `status` ENUM('pending', 'under_review', 'resolved', 'dismissed', 'Pending Review', 'Under Investigation', 'Scheduled for Mediation', 'Referred to Other Barangay', 'Resolved', 'Dismissed') DEFAULT 'Pending Review',
  `assigned_officer` VARCHAR(255) DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `referral_notes` TEXT DEFAULT NULL,
  `resolution_date` DATE DEFAULT NULL,
  `handled_by` INT(11) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `date_submitted` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reference_number` (`reference_number`),
  KEY `idx_resident_id` (`resident_id`),
  KEY `idx_handled_by` (`handled_by`),
  KEY `idx_status` (`status`),
  KEY `idx_jurisdiction_status` (`jurisdiction_status`),
  KEY `idx_filing_date` (`filing_date`),
  KEY `idx_date_submitted` (`date_submitted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements table - Public notices
CREATE TABLE `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `posted_by` INT(11) NOT NULL,
  `date_posted` DATE NOT NULL,
  `expiration_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posted_by` (`posted_by`),
  KEY `idx_status` (`status`),
  KEY `idx_date_posted` (`date_posted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resident Applications table
CREATE TABLE `resident_applications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_ref` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10) DEFAULT NULL,
  `sex` ENUM('male', 'female', 'other') NOT NULL,
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
  `record_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
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
CREATE TABLE `application_audit_log` (
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

-- Role permissions table - Module access control
CREATE TABLE `role_permissions` (
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

-- Foreign Key Constraints
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `residents`
  ADD CONSTRAINT `fk_residents_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_family_head` FOREIGN KEY (`family_head_id`) REFERENCES `residents` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `certificate_requests`
  ADD CONSTRAINT `fk_certificates_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_certificates_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `blotters`
  ADD CONSTRAINT `fk_blotters_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `blotter_hearings`
  ADD CONSTRAINT `fk_blotter_hearings_blotter` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `complaints`
  ADD CONSTRAINT `fk_complaints_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
