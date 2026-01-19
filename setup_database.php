<?php
/**
 * Database Setup Script
 * Run this to create all database tables
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>Database Setup</h1>";

try {
    $conn = getDBConnection();
    
    // Check if tables already exist
    $tables = $conn->query("SHOW TABLES")->fetchAll();
    if (!empty($tables)) {
        echo "<p style='color: orange;'><strong>Warning:</strong> Tables already exist. This will attempt to create missing tables only.</p>";
    }
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        die("<p style='color: red;'><strong>Error:</strong> database.sql file not found!</p>");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove CREATE DATABASE and USE statements
    $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
    $sql = preg_replace('/USE.*?;/i', '', $sql);
    
    // Split and execute statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    echo "<h2>Creating tables...</h2>";
    echo "<ul>";
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
            try {
                $conn->exec($statement);
                $successCount++;
                
                // Try to extract table name for display
                if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                    echo "<li style='color: green;'>✓ Created table: <strong>" . htmlspecialchars($matches[1]) . "</strong></li>";
                } else {
                    echo "<li style='color: green;'>✓ Executed SQL statement</li>";
                }
            } catch (PDOException $e) {
                // Ignore "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    $successCount++;
                    if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                        echo "<li style='color: orange;'>⚠ Table already exists: <strong>" . htmlspecialchars($matches[1]) . "</strong></li>";
                    }
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
    
    // Run collaboration migration if exists
    $collabFile = __DIR__ . '/database_collaboration.sql';
    if (file_exists($collabFile)) {
        echo "<h2>Running collaboration migration...</h2>";
        echo "<ul>";
        
        $collabSql = file_get_contents($collabFile);
        $collabSql = preg_replace('/USE.*?;/i', '', $collabSql);
        $collabStatements = array_filter(array_map('trim', explode(';', $collabSql)));
        
        foreach ($collabStatements as $statement) {
            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                try {
                    $conn->exec($statement);
                    
                    // Try to extract what was done for display
                    if (preg_match('/ALTER TABLE\s+`?(\w+)`?\s+ADD COLUMN\s+`?(\w+)`?/i', $statement, $matches)) {
                        echo "<li style='color: green;'>✓ Added column <strong>" . htmlspecialchars($matches[2]) . "</strong> to table <strong>" . htmlspecialchars($matches[1]) . "</strong></li>";
                    } elseif (preg_match('/CREATE TABLE/i', $statement)) {
                        if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                            echo "<li style='color: green;'>✓ Created table: <strong>" . htmlspecialchars($matches[1]) . "</strong></li>";
                        } else {
                            echo "<li style='color: green;'>✓ Executed migration statement</li>";
                        }
                    } else {
                        echo "<li style='color: green;'>✓ Executed migration statement</li>";
                    }
                } catch (PDOException $e) {
                    // Ignore errors for existing columns/tables
                    if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                        strpos($e->getMessage(), 'already exists') !== false ||
                        strpos($e->getMessage(), 'Duplicate key name') !== false) {
                        echo "<li style='color: orange;'>⚠ Already exists (skipped)</li>";
                    } else {
                        echo "<li style='color: red;'>✗ " . htmlspecialchars($e->getMessage()) . "</li>";
                    }
                }
            }
        }
        
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠ Collaboration migration file not found. Collaboration features may not work.</p>";
    }
    
    // Verify tables were created
    $finalTables = $conn->query("SHOW TABLES")->fetchAll();
    
    echo "<h2>Setup Complete!</h2>";
    echo "<p><strong>Tables created:</strong> " . count($finalTables) . "</p>";
    echo "<ul>";
    foreach ($finalTables as $table) {
        $tableName = array_values($table)[0];
        echo "<li>" . htmlspecialchars($tableName) . "</li>";
    }
    echo "</ul>";
    
    if ($errorCount > 0) {
        echo "<p style='color: red;'><strong>Errors encountered:</strong> $errorCount</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'><strong>✓ All tables created successfully!</strong></p>";
        echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>Go to Application</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in <code>config/database.php</code></p>";
}
?>
