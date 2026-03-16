-- Ensure residents table supports ID upload verification workflow
-- Safe to run multiple times on MySQL/MariaDB.

ALTER TABLE residents
    ADD COLUMN IF NOT EXISTS id_document_path VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS verification_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER record_status,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER remarks;
