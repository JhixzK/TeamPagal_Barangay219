-- Complaints: canonical status workflow (VARCHAR) + one-time migration from legacy ENUM values.
-- Referred to Other Barangay -> rejected (referral_notes preserved in row).
-- Run after 004_resident_complaints_module.sql.

ALTER TABLE `complaints`
  MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending';

UPDATE `complaints` SET `status` = 'pending'
WHERE LOWER(TRIM(`status`)) IN ('pending', 'pending review');

UPDATE `complaints` SET `status` = 'in_progress'
WHERE `status` IN ('under_review', 'Under Investigation', 'Scheduled for Mediation')
   OR LOWER(TRIM(`status`)) = 'under_review';

UPDATE `complaints` SET `status` = 'resolved'
WHERE LOWER(TRIM(`status`)) IN ('resolved');

UPDATE `complaints` SET `status` = 'rejected'
WHERE LOWER(TRIM(`status`)) = 'dismissed'
   OR `status` = 'Dismissed';

UPDATE `complaints` SET `status` = 'rejected'
WHERE `status` = 'Referred to Other Barangay';

-- Any remaining unknown values default to pending for staff review
UPDATE `complaints` SET `status` = 'pending'
WHERE `status` NOT IN ('pending', 'approved', 'assigned', 'in_progress', 'resolved', 'rejected');
