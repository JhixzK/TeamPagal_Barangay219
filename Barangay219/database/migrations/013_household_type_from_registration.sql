-- Ensure household_type exists in resident_applications and households
-- so registration input flows through to household management.

-- resident_applications: store household type from registration (required for heads)
SET @ra_col = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'resident_applications' AND column_name = 'household_type');
SET @ra_sql = IF(@ra_col = 0, 'ALTER TABLE resident_applications ADD COLUMN household_type VARCHAR(80) NULL DEFAULT NULL', 'SELECT 1');
PREPARE ra_stmt FROM @ra_sql;
EXECUTE ra_stmt;
DEALLOCATE PREPARE ra_stmt;

-- households: store household type (Family Household, Single Inhabitant, etc.)
SET @hh_col = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'households' AND column_name = 'household_type');
SET @hh_sql = IF(@hh_col = 0, 'ALTER TABLE households ADD COLUMN household_type VARCHAR(80) NULL DEFAULT NULL', 'SELECT 1');
PREPARE hh_stmt FROM @hh_sql;
EXECUTE hh_stmt;
DEALLOCATE PREPARE hh_stmt;
