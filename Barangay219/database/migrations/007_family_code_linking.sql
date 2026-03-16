-- Family code linking support for registration and approval flow
-- Safe to run multiple times on MySQL/MariaDB.

-- 1) Households: add family_code and unique index
ALTER TABLE households
    ADD COLUMN IF NOT EXISTS family_code VARCHAR(20) NULL AFTER address;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'households'
      AND index_name = 'ux_households_family_code'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE UNIQUE INDEX ux_households_family_code ON households (family_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Residents: keep family code and role data for approved records
ALTER TABLE residents
    ADD COLUMN IF NOT EXISTS family_code VARCHAR(20) NULL AFTER purok_sitio,
    ADD COLUMN IF NOT EXISTS relationship_to_head VARCHAR(100) NULL AFTER family_code,
    ADD COLUMN IF NOT EXISTS length_of_residency VARCHAR(100) NULL AFTER relationship_to_head,
    ADD COLUMN IF NOT EXISTS verification_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER record_status;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'residents'
      AND index_name = 'idx_residents_family_code'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_residents_family_code ON residents (family_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Resident applications: add fields used by new registration flow
ALTER TABLE resident_applications
    ADD COLUMN IF NOT EXISTS length_of_residency VARCHAR(100) NULL AFTER length_of_residency_years,
    ADD COLUMN IF NOT EXISTS verification_status ENUM('pending','verified','rejected') DEFAULT 'pending';

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'resident_applications'
      AND index_name = 'idx_resident_applications_family_code'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_resident_applications_family_code ON resident_applications (family_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
