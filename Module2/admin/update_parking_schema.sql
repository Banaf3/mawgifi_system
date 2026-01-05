-- Update ParkingArea table to add color and status columns
-- Run this SQL script to update your existing database
-- Copy and paste this entire script into phpMyAdmin SQL tab

USE mawgifi;

-- Check and add area_color column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'mawgifi' AND TABLE_NAME = 'ParkingArea' AND COLUMN_NAME = 'area_color';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ParkingArea ADD COLUMN area_color VARCHAR(7) DEFAULT "#a0a0a0" AFTER area_type',
    'SELECT "area_color column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add area_status column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'mawgifi' AND TABLE_NAME = 'ParkingArea' AND COLUMN_NAME = 'area_status';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ParkingArea ADD COLUMN area_status ENUM("available", "occupied", "temporarily_closed", "under_maintenance") DEFAULT "available" AFTER area_type',
    'SELECT "area_status column already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing areas with default colors
UPDATE ParkingArea SET area_color = '#667eea' WHERE (area_name LIKE '%A%' OR area_name LIKE '%a%') AND area_color = '#a0a0a0' LIMIT 1;
UPDATE ParkingArea SET area_color = '#764ba2' WHERE (area_name LIKE '%B%' OR area_name LIKE '%b%') AND area_color = '#a0a0a0' LIMIT 1;
UPDATE ParkingArea SET area_color = '#48bb78' WHERE (area_name LIKE '%C%' OR area_name LIKE '%c%') AND area_color = '#a0a0a0' LIMIT 1;
UPDATE ParkingArea SET area_color = '#ed8936' WHERE (area_name LIKE '%D%' OR area_name LIKE '%d%') AND area_color = '#a0a0a0' LIMIT 1;
UPDATE ParkingArea SET area_color = '#e53e3e' WHERE (area_name LIKE '%E%' OR area_name LIKE '%e%') AND area_color = '#a0a0a0' LIMIT 1;

-- Set default status for all areas
UPDATE ParkingArea SET area_status = 'available' WHERE area_status IS NULL OR area_status = '';

SELECT 'Schema update completed successfully! You can now use colors and status.' AS Result;
