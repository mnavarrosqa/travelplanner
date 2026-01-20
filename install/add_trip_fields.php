<?php
/**
 * Migration: Add Trip Destination and Travel Type Fields
 * Adds travel_type, is_multiple_destinations, and destinations columns to trips table
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();

$errors = [];
$success = [];

try {
    // Check if travel_type column exists
    $stmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'travel_type'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD COLUMN travel_type VARCHAR(50) NULL COMMENT 'Type of travel: vacations, work, family, business, leisure, etc.'");
        $success[] = 'Added travel_type column';
    } else {
        $success[] = 'travel_type column already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'travel_type: ' . $e->getMessage();
    }
}

try {
    // Check if is_multiple_destinations column exists
    $stmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'is_multiple_destinations'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD COLUMN is_multiple_destinations TINYINT(1) DEFAULT 0 COMMENT 'Whether trip has multiple destinations'");
        $success[] = 'Added is_multiple_destinations column';
    } else {
        $success[] = 'is_multiple_destinations column already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'is_multiple_destinations: ' . $e->getMessage();
    }
}

try {
    // Check if destinations column exists
    $stmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'destinations'");
    if ($stmt->rowCount() == 0) {
        // Check MySQL version for JSON support
        $version = $conn->query("SELECT VERSION()")->fetchColumn();
        $majorVersion = (int)explode('.', $version)[0];
        
        if ($majorVersion >= 5) {
            // Try JSON type (MySQL 5.7+)
            try {
                $conn->exec("ALTER TABLE trips ADD COLUMN destinations JSON NULL COMMENT 'JSON array of destination objects with name, country, city, etc.'");
                $success[] = 'Added destinations column (JSON type)';
            } catch (PDOException $e) {
                // Fallback to TEXT if JSON not supported
                $conn->exec("ALTER TABLE trips ADD COLUMN destinations TEXT NULL COMMENT 'JSON array of destination objects with name, country, city, etc.'");
                $success[] = 'Added destinations column (TEXT type - JSON not supported)';
            }
        } else {
            // Use TEXT for older MySQL versions
            $conn->exec("ALTER TABLE trips ADD COLUMN destinations TEXT NULL COMMENT 'JSON array of destination objects with name, country, city, etc.'");
            $success[] = 'Added destinations column (TEXT type)';
        }
    } else {
        $success[] = 'destinations column already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'destinations: ' . $e->getMessage();
    }
}

try {
    // Add index for travel_type if it doesn't exist
    $stmt = $conn->query("SHOW INDEX FROM trips WHERE Key_name = 'idx_travel_type'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD INDEX idx_travel_type (travel_type)");
        $success[] = 'Added idx_travel_type index';
    } else {
        $success[] = 'idx_travel_type index already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate key') === false) {
        $errors[] = 'idx_travel_type index: ' . $e->getMessage();
    }
}

// Output results
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Trip Fields - Migration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .success-item, .error-item {
            margin: 5px 0;
            padding: 5px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4A90E2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #357ABD;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Trip Fields Migration</h1>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <strong>Success:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php foreach ($success as $msg): ?>
                        <li class="success-item"><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Errors:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php foreach ($errors as $msg): ?>
                        <li class="error-item"><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <p><strong>Migration completed successfully!</strong> The trip destination and travel type fields have been added to your database.</p>
        <?php else: ?>
            <p><strong>Migration completed with errors.</strong> Please review the errors above and fix them manually if needed.</p>
        <?php endif; ?>
        
        <a href="../pages/dashboard.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>
