-- Align residents.status with user-style workflow: active, inactive, suspended, deceased, transferred
ALTER TABLE `residents`
  MODIFY COLUMN `status` ENUM('active', 'inactive', 'suspended', 'deceased', 'transferred') DEFAULT 'active';
