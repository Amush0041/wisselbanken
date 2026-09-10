-- SQL Script to add project_title column to pallet_addresses table
-- Run this on live servers to add the project_title field

-- Add project_title column to pallet_addresses table
ALTER TABLE `pallet_addresses` 
ADD COLUMN `project_title` VARCHAR(255) NULL AFTER `user_id`;

-- Verify the column was added
DESCRIBE `pallet_addresses`;

-- Check existing records (optional)
SELECT id, user_id, project_title, address1, city, state_id, postcode 
FROM `pallet_addresses` 
LIMIT 5;

-- Rollback script (if needed):
-- ALTER TABLE `pallet_addresses` DROP COLUMN `project_title`; 