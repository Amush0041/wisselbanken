-- =====================================================
-- LIVE SERVER MIGRATION SCRIPT
-- Pallet Address Refactoring
-- =====================================================

-- Step 1: Create the new pallet_addresses table
CREATE TABLE `pallet_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `address1` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `state_id` bigint(20) unsigned NOT NULL,
  `postcode` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pallet_addresses_user_id_foreign` (`user_id`),
  KEY `pallet_addresses_state_id_foreign` (`state_id`),
  CONSTRAINT `pallet_addresses_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `state_taxes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pallet_addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Migrate existing address data from palletes table to pallet_addresses table
-- This will create address records for existing pallet items that have address information
INSERT INTO `pallet_addresses` (`user_id`, `address1`, `city`, `state_id`, `postcode`, `created_at`, `updated_at`)
SELECT DISTINCT 
    p.user_id,
    p.address1,
    p.city,
    p.state_id,
    p.postcode,
    NOW(),
    NOW()
FROM `palletes` p
WHERE p.address1 IS NOT NULL 
  AND p.address1 != ''
  AND p.city IS NOT NULL 
  AND p.city != ''
  AND p.state_id IS NOT NULL
  AND p.postcode IS NOT NULL 
  AND p.postcode != '';

-- Step 3: Add pallet_address_id column to palletes table
ALTER TABLE `palletes` ADD COLUMN `pallet_address_id` bigint(20) unsigned NULL AFTER `saved_list_id`;

-- Step 4: Update palletes table to link existing items to their addresses
UPDATE `palletes` p
INNER JOIN `pallet_addresses` pa ON 
    p.user_id = pa.user_id 
    AND p.address1 = pa.address1 
    AND p.city = pa.city 
    AND p.state_id = pa.state_id 
    AND p.postcode = pa.postcode
SET p.pallet_address_id = pa.id
WHERE p.address1 IS NOT NULL 
  AND p.address1 != ''
  AND p.city IS NOT NULL 
  AND p.city != ''
  AND p.state_id IS NOT NULL
  AND p.postcode IS NOT NULL 
  AND p.postcode != '';

-- Step 5: Add foreign key constraint for pallet_address_id
ALTER TABLE `palletes` 
ADD CONSTRAINT `palletes_pallet_address_id_foreign` 
FOREIGN KEY (`pallet_address_id`) REFERENCES `pallet_addresses` (`id`) ON DELETE SET NULL;

-- Step 6: Drop foreign key constraint for state_id (if it exists)
-- Note: This might fail if the constraint doesn't exist, which is fine
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'palletes' 
    AND COLUMN_NAME = 'state_id' 
    AND CONSTRAINT_NAME != 'PRIMARY'
    LIMIT 1
);

SET @sql = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE `palletes` DROP FOREIGN KEY `', @constraint_name, '`'), 
    'SELECT "No state_id foreign key constraint found" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 7: Remove address-related columns from palletes table
ALTER TABLE `palletes` 
DROP COLUMN `first_name`,
DROP COLUMN `last_name`,
DROP COLUMN `email`,
DROP COLUMN `phone`,
DROP COLUMN `company`,
DROP COLUMN `address1`,
DROP COLUMN `address2`,
DROP COLUMN `city`,
DROP COLUMN `state_id`,
DROP COLUMN `postcode`,
DROP COLUMN `country`;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check if pallet_addresses table was created successfully
SELECT COUNT(*) as pallet_addresses_count FROM `pallet_addresses`;

-- Check if pallet_address_id column was added to palletes table
SELECT COLUMN_NAME 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'palletes' 
AND COLUMN_NAME = 'pallet_address_id';

-- Check if address columns were removed from palletes table
SELECT COLUMN_NAME 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'palletes' 
AND COLUMN_NAME IN ('address1', 'city', 'state_id', 'postcode');

-- Check foreign key constraints
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'palletes' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- =====================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================
/*
-- To rollback, run these commands in reverse order:

-- 1. Add back address columns to palletes table
ALTER TABLE `palletes` 
ADD COLUMN `first_name` varchar(255) NULL AFTER `quantity`,
ADD COLUMN `last_name` varchar(255) NULL AFTER `first_name`,
ADD COLUMN `email` varchar(255) NULL AFTER `last_name`,
ADD COLUMN `phone` varchar(255) NULL AFTER `email`,
ADD COLUMN `company` varchar(255) NULL AFTER `phone`,
ADD COLUMN `address1` varchar(255) NULL AFTER `company`,
ADD COLUMN `address2` varchar(255) NULL AFTER `address1`,
ADD COLUMN `city` varchar(255) NULL AFTER `address2`,
ADD COLUMN `state_id` bigint(20) unsigned NULL AFTER `city`,
ADD COLUMN `postcode` varchar(255) NULL AFTER `state_id`,
ADD COLUMN `country` varchar(255) NULL AFTER `postcode`;

-- 2. Restore address data from pallet_addresses table
UPDATE `palletes` p
INNER JOIN `pallet_addresses` pa ON p.pallet_address_id = pa.id
SET 
    p.address1 = pa.address1,
    p.city = pa.city,
    p.state_id = pa.state_id,
    p.postcode = pa.postcode;

-- 3. Drop pallet_address_id column
ALTER TABLE `palletes` DROP FOREIGN KEY `palletes_pallet_address_id_foreign`;
ALTER TABLE `palletes` DROP COLUMN `pallet_address_id`;

-- 4. Add back state_id foreign key
ALTER TABLE `palletes` 
ADD CONSTRAINT `palletes_state_id_foreign` 
FOREIGN KEY (`state_id`) REFERENCES `state_taxes` (`id`) ON DELETE SET NULL;

-- 5. Drop pallet_addresses table
DROP TABLE `pallet_addresses`;
*/ 