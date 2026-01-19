<?php
/**
 * Check Collaboration Setup
 * Verify that all collaboration features are properly set up
 */

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Collaboration Check</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo "h1{color:#4A90E2;} .ok{color:green;} .missing{color:red;} .warning{color:orange;}";
echo "table{border-collapse:collapse;width:100%;margin:20px 0;}";
echo "th,td{border:1px solid #ddd;padding:8px;text-align:left;}";
echo "th{background:#4A90E2;color:white;}</style></head><body>";

echo "<h1>Collaboration Features Check</h1>";

try {
    $conn = getDBConnection();
    
    $allGood = true;
    $issues = [];
    
    // Check tables
    echo "<h2>Tables</h2>";
    echo "<table><tr><th>Table</th><th>Status</th></tr>";
    
    $requiredTables = ['trip_users', 'invitations'];
    foreach ($requiredTables as $table) {
        try {
            $conn->query("SELECT 1 FROM $table LIMIT 1");
            echo "<tr><td><strong>$table</strong></td><td class='ok'>✓ Exists</td></tr>";
        } catch (PDOException $e) {
            $allGood = false;
            $issues[] = "Table '$table' is missing";
            echo "<tr><td><strong>$table</strong></td><td class='missing'>✗ Missing</td></tr>";
        }
    }
    echo "</table>";
    
    // Check columns in trips table
    echo "<h2>Trips Table Columns</h2>";
    echo "<table><tr><th>Column</th><th>Status</th></tr>";
    
    try {
        $cols = $conn->query("SHOW COLUMNS FROM trips")->fetchAll(PDO::FETCH_COLUMN);
        $requiredCols = ['created_by', 'modified_by'];
        foreach ($requiredCols as $col) {
            if (in_array($col, $cols)) {
                echo "<tr><td><strong>$col</strong></td><td class='ok'>✓ Exists</td></tr>";
            } else {
                $allGood = false;
                $issues[] = "Column 'trips.$col' is missing";
                echo "<tr><td><strong>$col</strong></td><td class='missing'>✗ Missing</td></tr>";
            }
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='2' class='missing'>Error checking trips table: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    echo "</table>";
    
    // Check columns in travel_items table
    echo "<h2>Travel Items Table Columns</h2>";
    echo "<table><tr><th>Column</th><th>Status</th></tr>";
    
    try {
        $cols = $conn->query("SHOW COLUMNS FROM travel_items")->fetchAll(PDO::FETCH_COLUMN);
        $requiredCols = ['created_by', 'modified_by'];
        foreach ($requiredCols as $col) {
            if (in_array($col, $cols)) {
                echo "<tr><td><strong>$col</strong></td><td class='ok'>✓ Exists</td></tr>";
            } else {
                $allGood = false;
                $issues[] = "Column 'travel_items.$col' is missing";
                echo "<tr><td><strong>$col</strong></td><td class='missing'>✗ Missing</td></tr>";
            }
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='2' class='missing'>Error checking travel_items table: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    echo "</table>";
    
    // Check columns in documents table
    echo "<h2>Documents Table Columns</h2>";
    echo "<table><tr><th>Column</th><th>Status</th></tr>";
    
    try {
        $cols = $conn->query("SHOW COLUMNS FROM documents")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('uploaded_by', $cols)) {
            echo "<tr><td><strong>uploaded_by</strong></td><td class='ok'>✓ Exists</td></tr>";
        } else {
            $allGood = false;
            $issues[] = "Column 'documents.uploaded_by' is missing";
            echo "<tr><td><strong>uploaded_by</strong></td><td class='missing'>✗ Missing</td></tr>";
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='2' class='missing'>Error checking documents table: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    echo "</table>";
    
    // Summary
    echo "<h2>Summary</h2>";
    if ($allGood) {
        echo "<p class='ok' style='font-size:1.2em;'><strong>✓ All collaboration features are set up correctly!</strong></p>";
        echo "<p>You can now use:</p>";
        echo "<ul>";
        echo "<li>Invite users to trips via shareable links</li>";
        echo "<li>Set user roles (owner, editor, viewer)</li>";
        echo "<li>Track who created/modified items</li>";
        echo "<li>Manage collaborators</li>";
        echo "</ul>";
        echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>Go to Application</a></p>";
    } else {
        echo "<p class='missing' style='font-size:1.2em;'><strong>✗ Some collaboration features are missing:</strong></p>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li class='missing'>" . htmlspecialchars($issue) . "</li>";
        }
        echo "</ul>";
        echo "<p>Run the collaboration setup script to fix these issues:</p>";
        echo "<p><a href='setup_collaboration.php' style='display: inline-block; padding: 10px 20px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>Run Collaboration Setup</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='missing'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in <code>config/database.php</code></p>";
}

echo "</body></html>";
?>
