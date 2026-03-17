-- Link approved resident record back to application
-- Enables assigning approved residents into households by officials.

ALTER TABLE resident_applications
    ADD COLUMN IF NOT EXISTS approved_resident_id INT(11) NULL AFTER reviewed_at;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'resident_applications'
      AND index_name = 'idx_resident_applications_approved_resident_id'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_resident_applications_approved_resident_id ON resident_applications (approved_resident_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

