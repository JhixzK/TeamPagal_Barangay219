-- E-Barangay Information Management System
-- Database Schema for Barangay 195, Tondo, Manila
-- Created: 2025

-- Drop existing tables if they exist (for fresh installation)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `blotters`;
DROP TABLE IF EXISTS `certificate_requests`;
DROP TABLE IF EXISTS `households`;
DROP TABLE IF EXISTS `residents`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Users table - Barangay officials and personnel
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman') NOT NULL,
  `resident_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Residents table - Central entity for all residents
CREATE TABLE `residents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10) DEFAULT NULL,
  `birth_date` DATE NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL,
  `civil_status` ENUM('single', 'married', 'widowed', 'divorced', 'separated') DEFAULT NULL,
  `occupation` VARCHAR(100) DEFAULT NULL,
  `citizenship` VARCHAR(50) DEFAULT 'Filipino',
  `address` TEXT NOT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `household_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'deceased', 'transferred') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
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
  `status` ENUM('pending', 'approved', 'rejected', 'issued') DEFAULT 'pending',
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

-- Complaints table - General complaints module
CREATE TABLE `complaints` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `complaint_title` VARCHAR(255) NOT NULL,
  `complainant_name` VARCHAR(255) NOT NULL,
  `respondent_name` VARCHAR(255) DEFAULT NULL,
  `complaint_type` VARCHAR(100) DEFAULT NULL,
  `narrative` TEXT NOT NULL,
  `filing_date` DATE NOT NULL,
  `status` ENUM('pending', 'under_review', 'resolved', 'dismissed') DEFAULT 'pending',
  `resolution_date` DATE DEFAULT NULL,
  `handled_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_handled_by` (`handled_by`),
  KEY `idx_status` (`status`),
  KEY `idx_filing_date` (`filing_date`)
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

ALTER TABLE `complaints`
  ADD CONSTRAINT `fk_complaints_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
