-- Trip Destination and Travel Type Fields Migration
-- Adds destination and travel type columns to trips table
-- Note: This migration is idempotent - it checks for column existence before adding

SET @dbname = DATABASE();
SET @tablename = 'trips';

-- Travel Type Field (vacations, work, family, business, leisure, etc.)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'travel_type') > 0,
  'SELECT 1',
  'ALTER TABLE trips ADD COLUMN travel_type VARCHAR(50) NULL COMMENT ''Type of travel: vacations, work, family, business, leisure, etc.'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Multiple Destinations Flag
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'is_multiple_destinations') > 0,
  'SELECT 1',
  'ALTER TABLE trips ADD COLUMN is_multiple_destinations TINYINT(1) DEFAULT 0 COMMENT ''Whether trip has multiple destinations'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Destinations (JSON format to store multiple destinations)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'destinations') > 0,
  'SELECT 1',
  'ALTER TABLE trips ADD COLUMN destinations JSON NULL COMMENT ''JSON array of destination objects with name, country, city, etc.'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Trip Cover Image (URL)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'cover_image') > 0,
  'SELECT 1',
  'ALTER TABLE trips ADD COLUMN cover_image VARCHAR(2048) NULL COMMENT ''Cover image URL for trip header/cards'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for travel_type for filtering
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND INDEX_NAME = 'idx_travel_type') > 0,
  'SELECT 1',
  'ALTER TABLE trips ADD INDEX idx_travel_type (travel_type)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
