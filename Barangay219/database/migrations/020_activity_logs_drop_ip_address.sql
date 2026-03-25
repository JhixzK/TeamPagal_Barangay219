-- Remove IP storage from staff activity audit (activity_logs only).
-- Safe to run once; ignore error if column already removed.

ALTER TABLE `activity_logs` DROP COLUMN `ip_address`;
