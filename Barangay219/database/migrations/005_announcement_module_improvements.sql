-- E-Barangay Information Management System
-- Announcement Module Improvements Migration
-- Date: 2026-03-12

-- Step 1: Modify status column to include new ENUM values (keep old ones for compatibility)
ALTER TABLE `announcements` MODIFY COLUMN `status` ENUM('draft', 'published', 'active', 'inactive', 'expired') DEFAULT 'published';

-- Step 2: Add new columns after content
ALTER TABLE `announcements` 
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) DEFAULT 'General' AFTER `content`;
  
ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `priority` ENUM('normal', 'urgent') DEFAULT 'normal' AFTER `category`;

ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `is_pinned` BOOLEAN DEFAULT 0 AFTER `priority`;

ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `views` INT DEFAULT 0 AFTER `is_pinned`;

-- Step 3: Add created_by column (replaces posted_by)
ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `created_by` INT(11) DEFAULT NULL AFTER `expiration_date`;

-- Step 4: Copy data from old column to new column where needed
UPDATE `announcements` SET `created_by` = `posted_by` WHERE `created_by` IS NULL AND `posted_by` IS NOT NULL;

-- Step 5: Update status values to new schema
UPDATE `announcements` SET `status` = 'published' WHERE `status` = 'active';
UPDATE `announcements` SET `status` = 'draft' WHERE `status` = 'inactive';
UPDATE `announcements` SET `status` = 'draft' WHERE `status` = 'expired';

-- Step 6: Add indexes
ALTER TABLE `announcements` 
  ADD KEY IF NOT EXISTS `idx_priority` (`priority`),
  ADD KEY IF NOT EXISTS `idx_is_pinned` (`is_pinned`),
  ADD KEY IF NOT EXISTS `idx_category` (`category`),
  ADD KEY IF NOT EXISTS `idx_created_by` (`created_by`);

-- Step 7: Add foreign key constraint for created_by
ALTER TABLE `announcements`
  DROP FOREIGN KEY IF EXISTS `fk_announcements_created_by`;

ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
