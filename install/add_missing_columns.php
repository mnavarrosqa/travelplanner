<?php
/**
 * Add Missing Collaboration Columns
 * Run this if you're getting "Column not found: created_by" errors
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Missing Columns</title>
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
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #4A90E2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 1rem;
        }
        .btn:hover { background: #357ABD; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Add Missing Collaboration Columns</h1>
        
        <?php
        try {
            $conn = getDBConnection();
            
            // Check which columns are missing
            $missing = [];
            
            // Check trips table
            $tripsCols = $conn->query("SHOW COLUMNS FROM trips")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('created_by', $tripsCols)) {
                $missing[] = 'trips.created_by';
            }
            if (!in_array('modified_by', $tripsCols)) {
                $missing[] = 'trips.modified_by';
            }
            
            // Check travel_items table
            $itemsCols = $conn->query("SHOW COLUMNS FROM travel_items")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('created_by', $itemsCols)) {
                $missing[] = 'travel_items.created_by';
            }
            if (!in_array('modified_by', $itemsCols)) {
                $missing[] = 'travel_items.modified_by';
            }
            
            if (empty($missing)) {
                echo '<div class="success">';
                echo '<strong>✓ All columns exist!</strong><br>';
                echo 'Your database has all required collaboration columns.';
                echo '</div>';
                echo '<p><a href="../index.php" class="btn">Go to Application</a></p>';
            } else {
                echo '<div class="info">';
                echo '<strong>Missing columns detected:</strong><br>';
                echo '<ul>';
                foreach ($missing as $col) {
                    echo '<li>' . htmlspecialchars($col) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
                
                $errors = [];
                $added = [];
                
                // Add missing columns to trips table
                if (in_array('trips.created_by', $missing)) {
                    try {
                        $conn->exec("ALTER TABLE trips ADD COLUMN created_by INT");
                        $conn->exec("ALTER TABLE trips ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
                        $added[] = 'trips.created_by';
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $errors[] = 'trips.created_by: ' . $e->getMessage();
                        }
                    }
                }
                
                if (in_array('trips.modified_by', $missing)) {
                    try {
                        $conn->exec("ALTER TABLE trips ADD COLUMN modified_by INT");
                        $conn->exec("ALTER TABLE trips ADD FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL");
                        $added[] = 'trips.modified_by';
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $errors[] = 'trips.modified_by: ' . $e->getMessage();
                        }
                    }
                }
                
                // Add missing columns to travel_items table
                if (in_array('travel_items.created_by', $missing)) {
                    try {
                        $conn->exec("ALTER TABLE travel_items ADD COLUMN created_by INT");
                        $conn->exec("ALTER TABLE travel_items ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
                        $added[] = 'travel_items.created_by';
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $errors[] = 'travel_items.created_by: ' . $e->getMessage();
                        }
                    }
                }
                
                if (in_array('travel_items.modified_by', $missing)) {
                    try {
                        $conn->exec("ALTER TABLE travel_items ADD COLUMN modified_by INT");
                        $conn->exec("ALTER TABLE travel_items ADD FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL");
                        $added[] = 'travel_items.modified_by';
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate column') === false) {
                            $errors[] = 'travel_items.modified_by: ' . $e->getMessage();
                        }
                    }
                }
                
                if (!empty($added)) {
                    echo '<div class="success">';
                    echo '<strong>✓ Successfully added columns:</strong><br>';
                    echo '<ul>';
                    foreach ($added as $col) {
                        echo '<li>' . htmlspecialchars($col) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                
                if (!empty($errors)) {
                    echo '<div class="error">';
                    echo '<strong>Errors occurred:</strong><br>';
                    echo '<ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else if (!empty($added)) {
                    echo '<p><a href="../index.php" class="btn">Go to Application</a></p>';
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
