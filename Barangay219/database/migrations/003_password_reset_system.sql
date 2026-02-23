-- E-Barangay Information Management System
-- Password Reset System Migration
-- Created: 2026-02-23

-- Table for password reset tokens (email-based verification)
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `method` ENUM('email', 'sms') NOT NULL,
  `identifier` VARCHAR(255) NOT NULL, -- email address or phone number
  `is_used` TINYINT(1) DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_method` (`method`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for OTP (One-Time Password) - SMS-based verification
CREATE TABLE IF NOT EXISTS `password_reset_otp` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `otp_code` VARCHAR(10) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `attempt_count` INT(11) DEFAULT 0,
  `max_attempts` INT(11) DEFAULT 5,
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for rate limiting - prevent abuse
CREATE TABLE IF NOT EXISTS `password_reset_rate_limit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `action` VARCHAR(50) NOT NULL, -- 'request', 'otp_verify', 'token_verify', 'reset'
  `request_count` INT(11) DEFAULT 1,
  `last_request` DATETIME NOT NULL,
  `window_start` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_action` (`action`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for password reset attempt logs (audit trail)
CREATE TABLE IF NOT EXISTS `password_reset_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `action` VARCHAR(100) NOT NULL, -- 'request_initiated', 'otp_sent', 'token_sent', 'otp_verified', 'token_verified', 'password_reset', 'cancelled'
  `method` ENUM('email', 'sms') NOT NULL,
  `identifier` VARCHAR(255) NOT NULL, -- email or phone
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `success` TINYINT(1) DEFAULT 1,
  `details` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_method` (`method`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add password_reset_request_id to users table if needed for tracking active reset requests
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `password_reset_request_id` INT(11) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `password_reset_request_method` VARCHAR(20) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `password_reset_request_expires` DATETIME DEFAULT NULL;
