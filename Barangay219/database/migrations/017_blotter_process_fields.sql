-- Add processing fields for admin blotter workflow
-- Use this when the columns do not exist yet.

ALTER TABLE blotter_records
    ADD COLUMN hearing_date DATETIME NULL,
    ADD COLUMN settlement_date DATE NULL,
    ADD COLUMN dismissal_reason TEXT NULL,
    ADD COLUMN resolution_file VARCHAR(255) NULL;
