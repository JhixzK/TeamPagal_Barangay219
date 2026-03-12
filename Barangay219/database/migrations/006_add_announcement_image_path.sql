-- Add image path support for announcements
ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(255) DEFAULT NULL AFTER `content`;