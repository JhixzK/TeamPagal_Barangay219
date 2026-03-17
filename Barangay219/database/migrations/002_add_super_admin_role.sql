-- Add Super Admin role (highest role)
-- Run this on existing databases that were created with the old ENUM lists.

USE `barangay219_db`;

-- Extend users.role enum to include super_admin
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('super_admin', 'barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman', 'resident') NOT NULL;

-- Extend role_permissions.role enum to include super_admin
ALTER TABLE `role_permissions`
  MODIFY COLUMN `role` ENUM('super_admin', 'barangay_captain', 'secretary', 'treasurer', 'kagawad', 'sk_chairman', 'resident') NOT NULL;

