-- SQL Script to add name field to pallet_addresses table
-- Run this on live servers to add the name field

-- Add name column to pallet_addresses table
ALTER TABLE `pallet_addresses` 
ADD COLUMN `name` VARCHAR(255) NULL AFTER `user_id`;

-- Verify the column was added
DESCRIBE `pallet_addresses`;

-- Check existing records (optional)
SELECT id, user_id, name, project_title, address1, city, state_id, postcode 
FROM `pallet_addresses` 
LIMIT 5;

-- Rollback script (if needed):
-- ALTER TABLE `pallet_addresses` DROP COLUMN `name`; 