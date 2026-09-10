-- SQL Script to update orders table to add project_title and remove country
-- Run this on live servers to update the orders table

-- Add project_title column to orders table
ALTER TABLE `orders` 
ADD COLUMN `project_title` VARCHAR(255) NULL AFTER `name`;

-- Drop country column from orders table
ALTER TABLE `orders` 
DROP COLUMN `country`;

-- Verify the changes
DESCRIBE `orders`;

-- Check existing records (optional)
SELECT id, order_number, name, project_title, email, phone, address1, city, state, postcode 
FROM `orders` 
LIMIT 5;

-- Rollback script (if needed):
-- ALTER TABLE `orders` ADD COLUMN `country` VARCHAR(255) NULL AFTER `postcode`;
-- ALTER TABLE `orders` DROP COLUMN `project_title`; 