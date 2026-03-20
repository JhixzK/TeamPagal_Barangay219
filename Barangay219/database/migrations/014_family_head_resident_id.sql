-- Store which head a member joined under (by family head code)
-- Ensures correct head/display when multiple heads share same household
ALTER TABLE residents ADD COLUMN IF NOT EXISTS family_head_resident_id INT(11) NULL AFTER household_role;
