-- Blotter Audit Logs table for tracking status changes
CREATE TABLE IF NOT EXISTS `blotter_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `blotter_id` INT(11) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `old_value` VARCHAR(255) DEFAULT NULL,
  `new_value` VARCHAR(255) DEFAULT NULL,
  `changed_by` INT(11) NOT NULL,
  `admin_name` VARCHAR(255) DEFAULT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_blotter_id` (`blotter_id`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_action` (`action`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add respondent_id column to blotter_records if it doesn't exist
ALTER TABLE blotter_records ADD COLUMN respondent_id INT(11) DEFAULT NULL AFTER `respondent_name_raw`,
ADD KEY `idx_respondent_id` (`respondent_id`);
