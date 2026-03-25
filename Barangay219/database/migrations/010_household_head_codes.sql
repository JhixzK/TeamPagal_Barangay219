-- Household codes for approval/assignment workflow
-- household_id_code: HH-XXXXXX (6 digits)
-- family_head_code:  HC-XXXXX  (5 digits; legacy FH-XXXXX may exist)
-- Generated when officials assign/approve the head of family.

ALTER TABLE households
    ADD COLUMN IF NOT EXISTS household_id_code VARCHAR(10) NULL AFTER family_code,
    ADD COLUMN IF NOT EXISTS family_head_code VARCHAR(9) NULL AFTER household_id_code;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'households'
      AND index_name = 'ux_households_household_id_code'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE UNIQUE INDEX ux_households_household_id_code ON households (household_id_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'households'
      AND index_name = 'ux_households_family_head_code'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE UNIQUE INDEX ux_households_family_head_code ON households (family_head_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

