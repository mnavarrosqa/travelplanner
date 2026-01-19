-- Collaboration Feature Migration
-- IMPORTANT: The main database.sql already includes ALL collaboration features!
-- You only need to run this if you imported an OLD version of database.sql
-- 
-- If you just imported the current database.sql, you can skip this file entirely.
-- All collaboration tables and columns are already in database.sql:
--   - trip_users table
--   - invitations table  
--   - created_by/modified_by columns in trips and travel_items
--   - uploaded_by column in documents

USE travelplanner;

-- This file is kept for backward compatibility only
-- If you're getting "duplicate column" errors, it means you already have everything!
-- Just skip this file and use the application - collaboration is already set up.

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
ON DUPLICATE KEY UPDATE role = 'owner';

-- Update trips with created_by
UPDATE trips SET created_by = user_id WHERE created_by IS NULL;


