-- Migration: Add residency_start_date column
-- Phase 3: Replace residency_years/months with date picker
-- Created: 2026-03-18

-- Add residency_start_date column to resident_applications table
ALTER TABLE `resident_applications` ADD COLUMN IF NOT EXISTS `residency_start_date` DATE NULL AFTER `email`;

-- Add residency_start_date column to residents table  
ALTER TABLE `residents` ADD COLUMN IF NOT EXISTS `residency_start_date` DATE NULL AFTER `email`;

-- Add computed length_of_residency (text format for display) to both tables if not exists
-- This will be computed from the date picker value
ALTER TABLE `resident_applications` ADD COLUMN IF NOT EXISTS `length_of_residency` VARCHAR(100) NULL AFTER `residency_start_date`;

ALTER TABLE `residents` ADD COLUMN IF NOT EXISTS `length_of_residency` VARCHAR(100) NULL AFTER `residency_start_date`;
