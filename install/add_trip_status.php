<?php
/**
 * Migration: Add Trip Status Field
 * Adds status column to trips table for tracking trip status (active, completed, archived)
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();

$errors = [];
$success = [];

try {
    // Check if status column exists
    $stmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER destinations");
        $success[] = 'Added status column';
        
        // Update existing trips to have 'active' status
        $conn->exec("UPDATE trips SET status = 'active' WHERE status IS NULL OR status = ''");
        $success[] = 'Set default status to active for existing trips';
    } else {
        $success[] = 'status column already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'status: ' . $e->getMessage();
    }
}

try {
    // Add index for status if it doesn't exist
    $stmt = $conn->query("SHOW INDEX FROM trips WHERE Key_name = 'idx_status'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD INDEX idx_status (status)");
        $success[] = 'Added idx_status index';
    } else {
        $success[] = 'idx_status index already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate key') === false) {
        $errors[] = 'idx_status index: ' . $e->getMessage();
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
    <title>Add Trip Status - Migration</title>
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
        <h1>Add Trip Status Migration</h1>
        
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
            <p><strong>Migration completed successfully!</strong> The trip status field has been added to your database.</p>
        <?php else: ?>
            <p><strong>Migration completed with errors.</strong> Please review the errors above and fix them manually if needed.</p>
        <?php endif; ?>
        
        <a href="../pages/dashboard.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>
