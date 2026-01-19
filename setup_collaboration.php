<?php
/**
 * Collaboration Setup Script
 * Run this to add collaboration features to your database
 */

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Collaboration Setup</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo "h1{color:#4A90E2;} ul{line-height:1.8;} code{background:#f5f5f5;padding:2px 6px;border-radius:3px;}</style></head><body>";

echo "<h1>Collaboration Features Setup</h1>";

try {
    $conn = getDBConnection();
    
    // Check if collaboration tables already exist
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasTripUsers = in_array('trip_users', $tables);
    $hasInvitations = in_array('invitations', $tables);
    
    if ($hasTripUsers && $hasInvitations) {
        echo "<p style='color: orange;'><strong>Note:</strong> Collaboration tables already exist. This will add missing columns only.</p>";
    }
    
    // Read and execute collaboration SQL file
    $collabFile = __DIR__ . '/database_collaboration.sql';
    if (!file_exists($collabFile)) {
        die("<p style='color: red;'><strong>Error:</strong> database_collaboration.sql file not found!</p>");
    }
    
    $collabSql = file_get_contents($collabFile);
    
    // Remove USE statement
    $collabSql = preg_replace('/USE.*?;/i', '', $collabSql);
    
    // Split and execute statements
    $statements = array_filter(array_map('trim', explode(';', $collabSql)));
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    echo "<h2>Setting up collaboration features...</h2>";
    echo "<ul>";
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
            try {
                $conn->exec($statement);
                $successCount++;
                
                // Try to extract what was done for display
                if (preg_match('/ALTER TABLE\s+`?(\w+)`?\s+ADD COLUMN\s+`?(\w+)`?/i', $statement, $matches)) {
                    echo "<li style='color: green;'>✓ Added column <strong>" . htmlspecialchars($matches[2]) . "</strong> to table <strong>" . htmlspecialchars($matches[1]) . "</strong></li>";
                } elseif (preg_match('/CREATE TABLE/i', $statement)) {
                    if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                        echo "<li style='color: green;'>✓ Created table: <strong>" . htmlspecialchars($matches[1]) . "</strong></li>";
                    } else {
                        echo "<li style='color: green;'>✓ Executed statement</li>";
                    }
                } elseif (preg_match('/INSERT INTO/i', $statement)) {
                    echo "<li style='color: green;'>✓ Migrated existing data</li>";
                } elseif (preg_match('/UPDATE/i', $statement)) {
                    echo "<li style='color: green;'>✓ Updated existing records</li>";
                } else {
                    echo "<li style='color: green;'>✓ Executed statement</li>";
                }
            } catch (PDOException $e) {
                // Ignore errors for existing columns/tables
                if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                    strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate key name') !== false ||
                    strpos($e->getMessage(), 'Duplicate entry') !== false ||
                    strpos($e->getMessage(), '1060') !== false) {
                    $successCount++;
                    echo "<li style='color: orange;'>⚠ Already exists (skipped)</li>";
                } else {
                    $errorCount++;
                    $errorMsg = 'SQL Error: ' . $e->getMessage();
                    $errors[] = $errorMsg;
                    echo "<li style='color: red;'>✗ " . htmlspecialchars($errorMsg) . "</li>";
                }
            }
        }
    }
    
    echo "</ul>";
    
    // Verify collaboration tables/columns
    echo "<h2>Verification</h2>";
    
    // Check trip_users table
    try {
        $tripUsersCols = $conn->query("SHOW COLUMNS FROM trip_users")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>✓ <strong>trip_users</strong> table exists with columns: " . implode(', ', $tripUsersCols) . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ <strong>trip_users</strong> table missing!</p>";
    }
    
    // Check invitations table
    try {
        $invCols = $conn->query("SHOW COLUMNS FROM invitations")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>✓ <strong>invitations</strong> table exists with columns: " . implode(', ', $invCols) . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ <strong>invitations</strong> table missing!</p>";
    }
    
    // Check trips table for collaboration columns
    try {
        $tripsCols = $conn->query("SHOW COLUMNS FROM trips")->fetchAll(PDO::FETCH_COLUMN);
        $hasCreatedBy = in_array('created_by', $tripsCols);
        $hasModifiedBy = in_array('modified_by', $tripsCols);
        
        if ($hasCreatedBy && $hasModifiedBy) {
            echo "<p>✓ <strong>trips</strong> table has collaboration columns (created_by, modified_by)</p>";
        } else {
            echo "<p style='color: orange;'>⚠ <strong>trips</strong> table missing collaboration columns</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Could not check trips table</p>";
    }
    
    // Check travel_items table
    try {
        $itemsCols = $conn->query("SHOW COLUMNS FROM travel_items")->fetchAll(PDO::FETCH_COLUMN);
        $hasCreatedBy = in_array('created_by', $itemsCols);
        $hasModifiedBy = in_array('modified_by', $itemsCols);
        
        if ($hasCreatedBy && $hasModifiedBy) {
            echo "<p>✓ <strong>travel_items</strong> table has collaboration columns (created_by, modified_by)</p>";
        } else {
            echo "<p style='color: orange;'>⚠ <strong>travel_items</strong> table missing collaboration columns</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Could not check travel_items table</p>";
    }
    
    // Check documents table
    try {
        $docCols = $conn->query("SHOW COLUMNS FROM documents")->fetchAll(PDO::FETCH_COLUMN);
        $hasUploadedBy = in_array('uploaded_by', $docCols);
        
        if ($hasUploadedBy) {
            echo "<p>✓ <strong>documents</strong> table has uploaded_by column</p>";
        } else {
            echo "<p style='color: orange;'>⚠ <strong>documents</strong> table missing uploaded_by column</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Could not check documents table</p>";
    }
    
    echo "<h2>Setup Complete!</h2>";
    
    if ($errorCount > 0) {
        echo "<p style='color: red;'><strong>Errors encountered:</strong> $errorCount</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'><strong>✓ Collaboration features set up successfully!</strong></p>";
        echo "<p>You can now:</p>";
        echo "<ul>";
        echo "<li>Invite users to trips via shareable links</li>";
        echo "<li>Set user roles (owner, editor, viewer)</li>";
        echo "<li>Track who created/modified items</li>";
        echo "<li>Manage collaborators</li>";
        echo "</ul>";
    }
    
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to Application</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in <code>config/database.php</code></p>";
}

echo "</body></html>";
?>
