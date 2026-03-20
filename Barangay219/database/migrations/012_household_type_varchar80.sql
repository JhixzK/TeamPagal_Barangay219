-- Expand household_type to store register values like "Non-Relative Household (Shared / Boarders)"
ALTER TABLE households MODIFY COLUMN household_type VARCHAR(80) NULL DEFAULT NULL;
