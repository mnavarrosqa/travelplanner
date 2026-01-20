<?php
/**
 * Add hotel-specific fields to travel_items table
 */

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDBConnection();
    
    // Check if hotel_data column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM travel_items LIKE 'hotel_data'");
    $hotelDataExists = $stmt->rowCount() > 0;
    
    if (!$hotelDataExists) {
        // Add hotel_data JSON column
        $conn->exec("ALTER TABLE travel_items ADD COLUMN hotel_data JSON NULL AFTER notes");
        echo "✓ Added 'hotel_data' JSON column to travel_items table\n";
    } else {
        echo "✓ 'hotel_data' column already exists\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
