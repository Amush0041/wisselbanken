-- SQL Script to update orders table to use single name field
-- Run this on live servers to update the orders table

-- Add name column to orders table
ALTER TABLE `orders` 
ADD COLUMN `name` VARCHAR(255) NULL AFTER `order_number`;

-- Update existing records to combine first_name and last_name into name
UPDATE `orders` 
SET `name` = CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
WHERE `name` IS NULL OR `name` = '';

-- Drop first_name and last_name columns
ALTER TABLE `orders` 
DROP COLUMN `first_name`,
DROP COLUMN `last_name`;

-- Verify the changes
DESCRIBE `orders`;

-- Check existing records (optional)
SELECT id, order_number, name, email, phone, address1, city, state, postcode 
FROM `orders` 
LIMIT 5;

-- Rollback script (if needed):
-- ALTER TABLE `orders` ADD COLUMN `first_name` VARCHAR(255) NULL AFTER `order_number`;
-- ALTER TABLE `orders` ADD COLUMN `last_name` VARCHAR(255) NULL AFTER `first_name`;
-- UPDATE `orders` SET `first_name` = SUBSTRING_INDEX(`name`, ' ', 1), `last_name` = SUBSTRING_INDEX(`name`, ' ', -1) WHERE `name` IS NOT NULL;
-- ALTER TABLE `orders` DROP COLUMN `name`; 