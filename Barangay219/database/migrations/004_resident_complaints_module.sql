-- E-Barangay Information Management System
-- Resident Complaints Module Migration
-- Created: 2026-03-10

CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reference_number` VARCHAR(30) DEFAULT NULL,
  `resident_id` INT(11) DEFAULT NULL,
  `complaint_title` VARCHAR(255) NOT NULL,
  `complainant_name` VARCHAR(255) NOT NULL,
  `respondent_name` VARCHAR(255) DEFAULT NULL,
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
  `respondent_address` VARCHAR(255) DEFAULT NULL,
  `respondent_barangay` VARCHAR(150) DEFAULT NULL,
  `respondent_city` VARCHAR(150) DEFAULT NULL,
  `respondent_residency` ENUM('Resident of this Barangay', 'Resident of another Barangay', 'Unknown') DEFAULT 'Unknown',
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
  UNIQUE KEY `uniq_complaints_reference_number` (`reference_number`),
  KEY `idx_complaints_resident_id` (`resident_id`),
  KEY `idx_complaints_status` (`status`),
  KEY `idx_complaints_jurisdiction_status` (`jurisdiction_status`),
  KEY `idx_complaints_filing_date` (`filing_date`),
  KEY `idx_complaints_date_submitted` (`date_submitted`),
  KEY `idx_complaints_handled_by` (`handled_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `reference_number` VARCHAR(30) DEFAULT NULL AFTER `id`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `resident_id` INT(11) DEFAULT NULL AFTER `reference_number`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL AFTER `filing_date`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) DEFAULT NULL AFTER `category`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `title`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `incident_date` DATE DEFAULT NULL AFTER `description`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `incident_time` TIME DEFAULT NULL AFTER `incident_date`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `incident_house_street` VARCHAR(255) DEFAULT NULL AFTER `incident_time`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `incident_purok` VARCHAR(100) DEFAULT NULL AFTER `incident_house_street`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `incident_landmark` VARCHAR(255) DEFAULT NULL AFTER `incident_purok`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `incident_barangay` VARCHAR(150) DEFAULT NULL AFTER `incident_landmark`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `respondent_address` VARCHAR(255) DEFAULT NULL AFTER `respondent_name`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `respondent_barangay` VARCHAR(150) DEFAULT NULL AFTER `respondent_address`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `respondent_city` VARCHAR(150) DEFAULT NULL AFTER `respondent_barangay`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `respondent_residency` ENUM('Resident of this Barangay', 'Resident of another Barangay', 'Unknown') DEFAULT 'Unknown' AFTER `respondent_city`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `evidence_file` VARCHAR(255) DEFAULT NULL AFTER `respondent_residency`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `jurisdiction_status` ENUM('Valid', 'Outside Jurisdiction') DEFAULT 'Valid' AFTER `evidence_file`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `assigned_officer` VARCHAR(255) DEFAULT NULL AFTER `status`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `resolution_notes` TEXT DEFAULT NULL AFTER `assigned_officer`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `referral_notes` TEXT DEFAULT NULL AFTER `resolution_notes`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `date_submitted` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `remarks`;

ALTER TABLE `complaints`
  MODIFY COLUMN `status` ENUM('pending', 'under_review', 'resolved', 'dismissed', 'Pending Review', 'Under Investigation', 'Scheduled for Mediation', 'Referred to Other Barangay', 'Resolved', 'Dismissed') DEFAULT 'Pending Review';

ALTER TABLE `complaints` ADD UNIQUE KEY `uniq_complaints_reference_number` (`reference_number`);
ALTER TABLE `complaints` ADD KEY `idx_complaints_resident_id` (`resident_id`);
ALTER TABLE `complaints` ADD KEY `idx_complaints_jurisdiction_status` (`jurisdiction_status`);
ALTER TABLE `complaints` ADD KEY `idx_complaints_date_submitted` (`date_submitted`);

UPDATE `complaints`
SET
  `title` = COALESCE(NULLIF(`title`, ''), `complaint_title`),
  `category` = COALESCE(NULLIF(`category`, ''), `complaint_type`),
  `description` = COALESCE(NULLIF(`description`, ''), `narrative`),
  `incident_date` = COALESCE(`incident_date`, `filing_date`),
  `incident_barangay` = COALESCE(NULLIF(`incident_barangay`, ''), 'Barangay 219'),
  `jurisdiction_status` = COALESCE(`jurisdiction_status`, 'Valid'),
  `date_submitted` = COALESCE(`date_submitted`, `created_at`),
  `reference_number` = COALESCE(`reference_number`, CONCAT('CMP-', YEAR(COALESCE(`created_at`, CURRENT_TIMESTAMP)), '-', LPAD(`id`, 4, '0')));
