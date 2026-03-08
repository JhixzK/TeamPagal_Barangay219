-- Household Module Enhancement Migration
-- This migration adds extended household information and household members table

-- Add additional fields to households table
ALTER TABLE `households` 
ADD COLUMN `house_number` VARCHAR(50) DEFAULT NULL AFTER `address`,
ADD COLUMN `street` VARCHAR(100) DEFAULT NULL AFTER `house_number`,
ADD COLUMN `purok_sitio` VARCHAR(80) DEFAULT NULL AFTER `street`,
ADD COLUMN `barangay` VARCHAR(100) DEFAULT 'Barangay 219' AFTER `purok_sitio`,
ADD COLUMN `city` VARCHAR(100) DEFAULT 'Manila' AFTER `barangay`,
ADD COLUMN `province` VARCHAR(100) DEFAULT 'Metro Manila' AFTER `city`,
ADD COLUMN `postal_code` VARCHAR(10) DEFAULT '1013' AFTER `province`,
ADD COLUMN `emergency_contact_name` VARCHAR(150) DEFAULT NULL AFTER `postal_code`,
ADD COLUMN `emergency_contact_phone` VARCHAR(20) DEFAULT NULL AFTER `emergency_contact_name`,
ADD COLUMN `special_notes` TEXT DEFAULT NULL AFTER `emergency_contact_phone`,
ADD COLUMN `number_of_adults` INT(11) DEFAULT 0 AFTER `total_members`,
ADD COLUMN `number_of_minors` INT(11) DEFAULT 0 AFTER `number_of_adults`,
ADD COLUMN `number_of_seniors` INT(11) DEFAULT 0 AFTER `number_of_minors`;

-- Create household_members table
CREATE TABLE IF NOT EXISTS `household_members` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `household_id` INT(11) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `suffix` VARCHAR(10) DEFAULT NULL,
  `relationship_to_head` VARCHAR(50) NOT NULL COMMENT 'Head, Spouse, Son, Daughter, Father, Mother, Brother, Sister, etc.',
  `date_of_birth` DATE NOT NULL,
  `age` INT(11) GENERATED ALWAYS AS (TIMESTAMPDIFF(YEAR, `date_of_birth`, CURDATE())) STORED,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `civil_status` ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT 'Single',
  `occupation` VARCHAR(100) DEFAULT NULL,
  `government_id_type` VARCHAR(50) DEFAULT NULL COMMENT 'National ID, PhilHealth, SSS, GSIS, Passport, etc.',
  `government_id_number` VARCHAR(100) DEFAULT NULL,
  `voter_status` ENUM('Registered', 'Not Registered', 'N/A') DEFAULT 'Not Registered',
  `voter_id_number` VARCHAR(50) DEFAULT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `is_head` TINYINT(1) DEFAULT 0 COMMENT 'Is this member the household head?',
  `is_senior_citizen` TINYINT(1) DEFAULT 0,
  `is_pwd` TINYINT(1) DEFAULT 0,
  `is_4ps_beneficiary` TINYINT(1) DEFAULT 0,
  `remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_household` (`household_id`),
  KEY `idx_name` (`last_name`, `first_name`),
  KEY `idx_relationship` (`relationship_to_head`),
  KEY `idx_age` (`age`),
  CONSTRAINT `fk_household_members_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better query performance
CREATE INDEX `idx_household_adults` ON `households` (`number_of_adults`);
CREATE INDEX `idx_household_minors` ON `households` (`number_of_minors`);
CREATE INDEX `idx_household_seniors` ON `households` (`number_of_seniors`);
CREATE INDEX `idx_member_dob` ON `household_members` (`date_of_birth`);
CREATE INDEX `idx_member_voter` ON `household_members` (`voter_status`);
