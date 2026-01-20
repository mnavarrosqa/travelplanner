-- Flight-Specific Fields Migration
-- Adds comprehensive flight data columns to travel_items table
-- These columns are only populated when type = 'flight'
-- Note: This migration is idempotent - it checks for column existence before adding

-- Time Fields
SET @dbname = DATABASE();
SET @tablename = 'travel_items';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_departure_scheduled') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_departure_scheduled DATETIME NULL COMMENT ''Scheduled departure time'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_departure_revised') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_departure_revised DATETIME NULL COMMENT ''Revised/estimated departure time (if delayed)'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_departure_runway') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_departure_runway DATETIME NULL COMMENT ''Actual takeoff time'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_arrival_scheduled') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_arrival_scheduled DATETIME NULL COMMENT ''Scheduled arrival time'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_arrival_revised') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_arrival_revised DATETIME NULL COMMENT ''Revised/estimated arrival time (if delayed)'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_arrival_runway') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_arrival_runway DATETIME NULL COMMENT ''Actual landing time'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_duration_minutes') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_duration_minutes INT NULL COMMENT ''Flight duration in minutes'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Airport Fields
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_departure_icao') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_departure_icao VARCHAR(4) NULL COMMENT ''Departure airport ICAO code'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_departure_country') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_departure_country VARCHAR(2) NULL COMMENT ''Departure country code'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_arrival_icao') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_arrival_icao VARCHAR(4) NULL COMMENT ''Arrival airport ICAO code'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_arrival_country') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_arrival_country VARCHAR(2) NULL COMMENT ''Arrival country code'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Aircraft Fields
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_aircraft_registration') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_aircraft_registration VARCHAR(20) NULL COMMENT ''Aircraft registration number'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_aircraft_icao24') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_aircraft_icao24 VARCHAR(6) NULL COMMENT ''ICAO 24-bit address'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_aircraft_age') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_aircraft_age INT NULL COMMENT ''Aircraft age in years'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Status Fields
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_status') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_status VARCHAR(50) NULL COMMENT ''Current flight status'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = 'flight_codeshare') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD COLUMN flight_codeshare VARCHAR(255) NULL COMMENT ''Codeshare information'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add indexes for common queries (only if they don't exist)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND INDEX_NAME = 'idx_flight_departure_icao') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD INDEX idx_flight_departure_icao (flight_departure_icao)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND INDEX_NAME = 'idx_flight_arrival_icao') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD INDEX idx_flight_arrival_icao (flight_arrival_icao)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND INDEX_NAME = 'idx_flight_status') > 0,
  'SELECT 1',
  'ALTER TABLE travel_items ADD INDEX idx_flight_status (flight_status)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
