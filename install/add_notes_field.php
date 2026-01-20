<?php
/**
 * Add notes field to travel_items table
 * Migration script to replace cost/currency with notes
 */

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDBConnection();
    
    // Check if notes column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM travel_items LIKE 'notes'");
    $notesExists = $stmt->rowCount() > 0;
    
    if (!$notesExists) {
        // Add notes column
        $conn->exec("ALTER TABLE travel_items ADD COLUMN notes TEXT NULL AFTER description");
        echo "✓ Added 'notes' column to travel_items table\n";
    } else {
        echo "✓ 'notes' column already exists\n";
    }
    
    // Note: We're keeping cost/currency columns for backward compatibility
    // but they won't be used in the UI anymore
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
