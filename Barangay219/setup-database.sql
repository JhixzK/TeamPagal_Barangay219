-- E-Barangay Information Management System
-- Database Setup Script
-- Run this after creating the database and running schema.sql

-- First, create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `barangay219_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barangay219_db`;

-- Update admin password with a proper hash
-- Password: admin123
-- This hash was generated using: password_hash('admin123', PASSWORD_DEFAULT)
-- If this doesn't work, run fix-password.php in your browser to generate a new hash

UPDATE users SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' WHERE username = 'admin';

-- If admin user doesn't exist, insert it:
INSERT INTO users (username, password, email, role, status) 
VALUES ('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin@barangay195.gov.ph', 'barangay_captain', 'active')
ON DUPLICATE KEY UPDATE password = VALUES(password);
