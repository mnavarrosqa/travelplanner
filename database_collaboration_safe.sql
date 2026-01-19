-- Collaboration Feature Migration (Safe Version)
-- This version checks for existing columns before adding them
-- Run this after the main database.sql to add collaboration features

USE travelplanner;

-- Add created_by and modified_by to trips (only if they don't exist)
-- Note: If columns already exist from database.sql, these will be skipped

-- Check and add created_by to trips if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'trips' 
    AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE trips ADD COLUMN created_by INT, ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL', 
    'SELECT "Column created_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add modified_by to trips if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'trips' 
    AND COLUMN_NAME = 'modified_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE trips ADD COLUMN modified_by INT, ADD FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL', 
    'SELECT "Column modified_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add created_by and modified_by to travel_items (only if they don't exist)
-- Check and add created_by to travel_items if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'travel_items' 
    AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE travel_items ADD COLUMN created_by INT, ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL', 
    'SELECT "Column created_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add modified_by to travel_items if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'travel_items' 
    AND COLUMN_NAME = 'modified_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE travel_items ADD COLUMN modified_by INT, ADD FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL', 
    'SELECT "Column modified_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add uploaded_by to documents (only if it doesn't exist)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'documents' 
    AND COLUMN_NAME = 'uploaded_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE documents ADD COLUMN uploaded_by INT, ADD FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL', 
    'SELECT "Column uploaded_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Trip users/collaborators table
CREATE TABLE IF NOT EXISTS trip_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
    invited_by INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_trip_user (trip_id, user_id),
    INDEX idx_trip_id (trip_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invitations table
CREATE TABLE IF NOT EXISTS invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    code VARCHAR(32) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    role ENUM('editor', 'viewer') NOT NULL DEFAULT 'viewer',
    expires_at DATETIME,
    max_uses INT DEFAULT NULL,
    current_uses INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_trip_id (trip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing trips: set owner as created_by and add to trip_users
INSERT INTO trip_users (trip_id, user_id, role, joined_at)
SELECT id, user_id, 'owner', created_at
FROM trips
WHERE NOT EXISTS (
    SELECT 1 FROM trip_users WHERE trip_users.trip_id = trips.id AND trip_users.user_id = trips.user_id
)
ON DUPLICATE KEY UPDATE role = 'owner';

-- Update trips with created_by (only if column exists and is NULL)
UPDATE trips SET created_by = user_id WHERE created_by IS NULL;
