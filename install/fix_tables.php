<?php
/**
 * Quick Fix: Create Missing Database Tables
 * Run this if tables are missing after installation
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Database Tables</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #4A90E2; }
        .success { color: #2E7D32; background: #E8F5E9; padding: 1rem; border-radius: 4px; margin: 1rem 0; }
        .error { color: #C62828; background: #FFEBEE; padding: 1rem; border-radius: 4px; margin: 1rem 0; }
        .info { color: #856404; background: #FFF3CD; padding: 1rem; border-radius: 4px; margin: 1rem 0; }
        ul { margin: 0.5rem 0; padding-left: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Database Tables</h1>
        
        <?php
        try {
            $conn = getDBConnection();
            
            // Check if tables exist
            $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $requiredTables = ['users', 'trips', 'travel_items', 'documents'];
            
            $missingTables = array_diff($requiredTables, $tables);
            
            if (empty($missingTables)) {
                echo '<div class="success">';
                echo '<strong>✓ All tables exist!</strong><br>';
                echo 'Your database is properly set up.';
                echo '</div>';
                echo '<p><a href="../index.php">Go to Application</a></p>';
            } else {
                echo '<div class="info">';
                echo '<strong>Missing tables detected:</strong><br>';
                echo '<ul>';
                foreach ($missingTables as $table) {
                    echo '<li>' . htmlspecialchars($table) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
                
                // Read and execute SQL files
                $sqlFiles = [
                    __DIR__ . '/../database.sql',
                    __DIR__ . '/../database_collaboration.sql'
                ];
                
                $executed = 0;
                $errors = [];
                
                foreach ($sqlFiles as $sqlFile) {
                    if (!file_exists($sqlFile)) {
                        echo '<div class="error">SQL file not found: ' . basename($sqlFile) . '</div>';
                        continue;
                    }
                    
                    $sql = file_get_contents($sqlFile);
                    
                    // Remove CREATE DATABASE and USE statements
                    $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                    $sql = preg_replace('/USE.*?;/i', '', $sql);
                    
                    // Remove comments
                    $sql = preg_replace('/--.*$/m', '', $sql);
                    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                    
                    // Split by semicolon
                    $statements = [];
                    $currentStatement = '';
                    $lines = explode("\n", $sql);
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        $currentStatement .= $line . "\n";
                        
                        if (substr(rtrim($line), -1) === ';') {
                            $stmt = trim($currentStatement);
                            if (!empty($stmt) && strlen($stmt) > 5) {
                                $statements[] = $stmt;
                            }
                            $currentStatement = '';
                        }
                    }
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (empty($statement) || strlen($statement) < 5) continue;
                        
                        try {
                            $conn->exec($statement);
                            $executed++;
                        } catch (PDOException $e) {
                            $errorMsg = $e->getMessage();
                            if (strpos($errorMsg, 'already exists') === false &&
                                strpos($errorMsg, 'Duplicate column') === false &&
                                strpos($errorMsg, 'Duplicate key') === false &&
                                strpos($errorMsg, 'Duplicate entry') === false) {
                                $errors[] = $errorMsg;
                            }
                        }
                    }
                }
                
                if (empty($errors)) {
                    echo '<div class="success">';
                    echo '<strong>✓ Success!</strong><br>';
                    echo "Executed $executed SQL statements. Tables should now be created.";
                    echo '</div>';
                    echo '<p><a href="../index.php">Go to Application</a></p>';
                } else {
                    echo '<div class="error">';
                    echo '<strong>Errors occurred:</strong><br>';
                    echo '<ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            }
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>Database Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
            echo '<p>Please check your database configuration in <code>config/database.php</code></p>';
        }
        ?>
    </div>
</body>
</html>
