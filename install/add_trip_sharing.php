<?php
/**
 * Migration: Add Trip Public Sharing Fields
 * Adds share_token and is_publicly_shared columns to trips table
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();

$errors = [];
$success = [];

try {
    // Check if share_token column exists
    $stmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'share_token'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD COLUMN share_token VARCHAR(64) NULL UNIQUE COMMENT 'Unique token for public sharing' AFTER status");
        $success[] = 'Added share_token column';
        
        // Add index for share_token
        try {
            $conn->exec("ALTER TABLE trips ADD INDEX idx_share_token (share_token)");
            $success[] = 'Added idx_share_token index';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') === false) {
                $errors[] = 'idx_share_token index: ' . $e->getMessage();
            }
        }
    } else {
        $success[] = 'share_token column already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'share_token: ' . $e->getMessage();
    }
}

try {
    // Check if is_publicly_shared column exists
    $stmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'is_publicly_shared'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE trips ADD COLUMN is_publicly_shared TINYINT(1) DEFAULT 0 COMMENT 'Whether trip is publicly shared via share link' AFTER share_token");
        $success[] = 'Added is_publicly_shared column';
    } else {
        $success[] = 'is_publicly_shared column already exists';
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'is_publicly_shared: ' . $e->getMessage();
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
    <title>Add Trip Sharing Fields</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .success {
            color: #27AE60;
            background: #D5F4E6;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #E74C3C;
            background: #FADBD8;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            color: #4A90E2;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Trip Sharing Fields</h1>
        
        <?php if (!empty($success)): ?>
            <h2>Success:</h2>
            <?php foreach ($success as $msg): ?>
                <div class="success">✓ <?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <h2>Errors:</h2>
            <?php foreach ($errors as $msg): ?>
                <div class="error">✗ <?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <a href="../install/index.php">← Back to Install</a>
    </div>
</body>
</html>
