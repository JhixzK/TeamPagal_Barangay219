-- Allow creating households without a head yet (empty households)
-- Safe to run multiple times on MySQL/MariaDB.

-- 1) Allow NULL head and address so a household can be created empty
ALTER TABLE households
    MODIFY COLUMN family_head_id INT(11) NULL;

ALTER TABLE households
    MODIFY COLUMN address TEXT NULL;

-- 2) Allow empty households to start with 0 members
ALTER TABLE households
    MODIFY COLUMN total_members INT(11) NULL DEFAULT 0;

UPDATE households
SET total_members = 0
WHERE total_members IS NULL;

