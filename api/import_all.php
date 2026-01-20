<?php
/**
 * Import All User Data - JSON Import
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$conn = getDBConnection();

try {
    // Check if file was uploaded
    if (!isset($_FILES['import_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }
    
    if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$_FILES['import_file']['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $errorMsg]);
        exit;
    }
    
    $file = $_FILES['import_file'];
    
    // Validate file type
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExt !== 'json') {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a JSON file.']);
        exit;
    }
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
        exit;
    }
    
    // Read and parse JSON
    $jsonContent = file_get_contents($file['tmp_name']);
    $importData = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON file: ' . json_last_error_msg()]);
        exit;
    }
    
    // Validate data structure
    if (!isset($importData['trips']) || !is_array($importData['trips'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid import file format. Missing trips data.']);
        exit;
    }
    
    // Auto-migrate: Ensure all required columns exist before importing
    
    // Trips table columns
    $tripsColumns = [
        'status' => "ALTER TABLE trips ADD COLUMN status VARCHAR(20) DEFAULT 'active' COMMENT 'Trip status: active, completed, archived'",
        'travel_type' => "ALTER TABLE trips ADD COLUMN travel_type VARCHAR(50) NULL COMMENT 'Type of travel: vacations, work, family, business, leisure, etc.'",
        'is_multiple_destinations' => "ALTER TABLE trips ADD COLUMN is_multiple_destinations TINYINT(1) DEFAULT 0 COMMENT 'Whether trip has multiple destinations'",
        'destinations' => "ALTER TABLE trips ADD COLUMN destinations TEXT NULL COMMENT 'JSON array of destination objects'",
        'share_token' => "ALTER TABLE trips ADD COLUMN share_token VARCHAR(64) NULL UNIQUE COMMENT 'Unique token for public sharing'",
        'is_publicly_shared' => "ALTER TABLE trips ADD COLUMN is_publicly_shared TINYINT(1) DEFAULT 0 COMMENT 'Whether trip is publicly shared via share link'",
        'created_by' => "ALTER TABLE trips ADD COLUMN created_by INT NULL COMMENT 'User who created the trip'",
        'modified_by' => "ALTER TABLE trips ADD COLUMN modified_by INT NULL COMMENT 'User who last modified the trip'"
    ];
    
    foreach ($tripsColumns as $columnName => $sql) {
        try {
            $checkStmt = $conn->query("SHOW COLUMNS FROM trips LIKE '$columnName'");
            if ($checkStmt->rowCount() == 0) {
                $conn->exec($sql);
                // Add index for share_token if it was just created
                if ($columnName === 'share_token') {
                    try {
                        $conn->exec("ALTER TABLE trips ADD INDEX idx_share_token (share_token)");
                    } catch (PDOException $e) {
                        // Index might already exist, ignore
                    }
                }
            }
        } catch (PDOException $e) {
            // Column might already exist or other error, continue
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                error_log("Warning: Could not add column $columnName to trips: " . $e->getMessage());
            }
        }
    }
    
    // Travel items table columns
    $travelItemsColumns = [
        'notes' => "ALTER TABLE travel_items ADD COLUMN notes TEXT NULL COMMENT 'Additional notes'",
        'hotel_data' => "ALTER TABLE travel_items ADD COLUMN hotel_data JSON NULL COMMENT 'Hotel-specific data in JSON format'",
        'flight_departure_scheduled' => "ALTER TABLE travel_items ADD COLUMN flight_departure_scheduled DATETIME NULL COMMENT 'Scheduled departure time'",
        'flight_departure_revised' => "ALTER TABLE travel_items ADD COLUMN flight_departure_revised DATETIME NULL COMMENT 'Revised/estimated departure time'",
        'flight_departure_runway' => "ALTER TABLE travel_items ADD COLUMN flight_departure_runway DATETIME NULL COMMENT 'Actual takeoff time'",
        'flight_arrival_scheduled' => "ALTER TABLE travel_items ADD COLUMN flight_arrival_scheduled DATETIME NULL COMMENT 'Scheduled arrival time'",
        'flight_arrival_revised' => "ALTER TABLE travel_items ADD COLUMN flight_arrival_revised DATETIME NULL COMMENT 'Revised/estimated arrival time'",
        'flight_arrival_runway' => "ALTER TABLE travel_items ADD COLUMN flight_arrival_runway DATETIME NULL COMMENT 'Actual landing time'",
        'flight_duration_minutes' => "ALTER TABLE travel_items ADD COLUMN flight_duration_minutes INT NULL COMMENT 'Flight duration in minutes'",
        'flight_departure_icao' => "ALTER TABLE travel_items ADD COLUMN flight_departure_icao VARCHAR(4) NULL COMMENT 'Departure airport ICAO code'",
        'flight_departure_country' => "ALTER TABLE travel_items ADD COLUMN flight_departure_country VARCHAR(2) NULL COMMENT 'Departure country code'",
        'flight_arrival_icao' => "ALTER TABLE travel_items ADD COLUMN flight_arrival_icao VARCHAR(4) NULL COMMENT 'Arrival airport ICAO code'",
        'flight_arrival_country' => "ALTER TABLE travel_items ADD COLUMN flight_arrival_country VARCHAR(2) NULL COMMENT 'Arrival country code'",
        'flight_aircraft_registration' => "ALTER TABLE travel_items ADD COLUMN flight_aircraft_registration VARCHAR(20) NULL COMMENT 'Aircraft registration number'",
        'flight_aircraft_icao24' => "ALTER TABLE travel_items ADD COLUMN flight_aircraft_icao24 VARCHAR(6) NULL COMMENT 'ICAO 24-bit address'",
        'flight_aircraft_age' => "ALTER TABLE travel_items ADD COLUMN flight_aircraft_age INT NULL COMMENT 'Aircraft age in years'",
        'flight_status' => "ALTER TABLE travel_items ADD COLUMN flight_status VARCHAR(50) NULL COMMENT 'Current flight status'",
        'flight_codeshare' => "ALTER TABLE travel_items ADD COLUMN flight_codeshare VARCHAR(255) NULL COMMENT 'Codeshare information'",
        'flight_airline' => "ALTER TABLE travel_items ADD COLUMN flight_airline VARCHAR(100) NULL COMMENT 'Airline name'",
        'flight_number' => "ALTER TABLE travel_items ADD COLUMN flight_number VARCHAR(20) NULL COMMENT 'Flight number'",
        'created_by' => "ALTER TABLE travel_items ADD COLUMN created_by INT NULL COMMENT 'User who created the item'",
        'modified_by' => "ALTER TABLE travel_items ADD COLUMN modified_by INT NULL COMMENT 'User who last modified the item'"
    ];
    
    foreach ($travelItemsColumns as $columnName => $sql) {
        try {
            $checkStmt = $conn->query("SHOW COLUMNS FROM travel_items LIKE '$columnName'");
            if ($checkStmt->rowCount() == 0) {
                $conn->exec($sql);
            }
        } catch (PDOException $e) {
            // Column might already exist or other error, continue
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                error_log("Warning: Could not add column $columnName to travel_items: " . $e->getMessage());
            }
        }
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    $importedTrips = 0;
    $importedItems = 0;
    $importedDocs = 0;
    $errors = [];
    
    try {
        foreach ($importData['trips'] as $tripData) {
            // Validate required fields
            if (empty($tripData['title']) || empty($tripData['start_date'])) {
                $errors[] = 'Skipped trip: Missing required fields (title or start_date)';
                continue;
            }
            
            // Build INSERT query dynamically based on available columns
            // Check which columns exist to build the correct INSERT statement
            $columns = ['user_id', 'title', 'start_date'];
            $values = [$userId, $tripData['title'], $tripData['start_date']];
            $placeholders = ['?', '?', '?'];
            
            // Optional columns - check if they exist in database
            $optionalColumns = [
                'end_date' => isset($tripData['end_date']) ? $tripData['end_date'] : null,
                'description' => isset($tripData['description']) ? $tripData['description'] : null,
                'travel_type' => isset($tripData['travel_type']) ? $tripData['travel_type'] : null,
                'is_multiple_destinations' => isset($tripData['is_multiple_destinations']) ? $tripData['is_multiple_destinations'] : 0,
                'destinations' => isset($tripData['destinations']) ? $tripData['destinations'] : null,
                'status' => isset($tripData['status']) ? $tripData['status'] : 'active',
                'created_by' => $userId,
                'modified_by' => $userId
            ];
            
            // Check each optional column and add if it exists
            foreach ($optionalColumns as $colName => $colValue) {
                try {
                    $checkStmt = $conn->query("SHOW COLUMNS FROM trips LIKE '$colName'");
                    if ($checkStmt->rowCount() > 0) {
                        $columns[] = $colName;
                        $values[] = $colValue;
                        $placeholders[] = '?';
                    }
                } catch (PDOException $e) {
                    // Column doesn't exist, skip it
                }
            }
            
            // Add created_at if it exists (use NOW() or current timestamp)
            try {
                $checkStmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'created_at'");
                if ($checkStmt->rowCount() > 0) {
                    $columns[] = 'created_at';
                    $values[] = date('Y-m-d H:i:s');
                    $placeholders[] = '?';
                }
            } catch (PDOException $e) {
                // created_at doesn't exist, skip it
            }
            
            // Build and execute INSERT query
            $columnsStr = implode(', ', $columns);
            $placeholdersStr = implode(', ', $placeholders);
            $sql = "INSERT INTO trips ($columnsStr) VALUES ($placeholdersStr)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($values);
            
            $newTripId = $conn->lastInsertId();
            $importedTrips++;
            
            // Import travel items
            if (isset($tripData['travel_items']) && is_array($tripData['travel_items'])) {
                foreach ($tripData['travel_items'] as $itemData) {
                    if (empty($itemData['type']) || empty($itemData['title']) || empty($itemData['start_datetime'])) {
                        continue; // Skip invalid items
                    }
                    
                    // Build INSERT query dynamically based on available columns
                    $columns = ['trip_id', 'type', 'title', 'start_datetime'];
                    $values = [$newTripId, $itemData['type'], $itemData['title'], $itemData['start_datetime']];
                    $placeholders = ['?', '?', '?', '?'];
                    
                    // Optional columns - check if they exist in database
                    $optionalColumns = [
                        'end_datetime' => isset($itemData['end_datetime']) ? $itemData['end_datetime'] : null,
                        'location' => isset($itemData['location']) ? $itemData['location'] : null,
                        'confirmation_number' => isset($itemData['confirmation_number']) ? $itemData['confirmation_number'] : null,
                        'description' => isset($itemData['description']) ? $itemData['description'] : null,
                        'notes' => isset($itemData['notes']) ? $itemData['notes'] : null,
                        'hotel_data' => isset($itemData['hotel_data']) ? $itemData['hotel_data'] : null,
                        'flight_departure_scheduled' => isset($itemData['flight_departure_scheduled']) ? $itemData['flight_departure_scheduled'] : null,
                        'flight_departure_revised' => isset($itemData['flight_departure_revised']) ? $itemData['flight_departure_revised'] : null,
                        'flight_departure_runway' => isset($itemData['flight_departure_runway']) ? $itemData['flight_departure_runway'] : null,
                        'flight_arrival_scheduled' => isset($itemData['flight_arrival_scheduled']) ? $itemData['flight_arrival_scheduled'] : null,
                        'flight_arrival_revised' => isset($itemData['flight_arrival_revised']) ? $itemData['flight_arrival_revised'] : null,
                        'flight_arrival_runway' => isset($itemData['flight_arrival_runway']) ? $itemData['flight_arrival_runway'] : null,
                        'flight_duration_minutes' => isset($itemData['flight_duration_minutes']) ? $itemData['flight_duration_minutes'] : null,
                        'flight_departure_icao' => isset($itemData['flight_departure_icao']) ? $itemData['flight_departure_icao'] : null,
                        'flight_departure_country' => isset($itemData['flight_departure_country']) ? $itemData['flight_departure_country'] : null,
                        'flight_arrival_icao' => isset($itemData['flight_arrival_icao']) ? $itemData['flight_arrival_icao'] : null,
                        'flight_arrival_country' => isset($itemData['flight_arrival_country']) ? $itemData['flight_arrival_country'] : null,
                        'flight_aircraft_registration' => isset($itemData['flight_aircraft_registration']) ? $itemData['flight_aircraft_registration'] : null,
                        'flight_aircraft_icao24' => isset($itemData['flight_aircraft_icao24']) ? $itemData['flight_aircraft_icao24'] : null,
                        'flight_aircraft_age' => isset($itemData['flight_aircraft_age']) ? $itemData['flight_aircraft_age'] : null,
                        'flight_status' => isset($itemData['flight_status']) ? $itemData['flight_status'] : null,
                        'flight_codeshare' => isset($itemData['flight_codeshare']) ? $itemData['flight_codeshare'] : null,
                        'flight_airline' => isset($itemData['flight_airline']) ? $itemData['flight_airline'] : null,
                        'flight_number' => isset($itemData['flight_number']) ? $itemData['flight_number'] : null,
                        'created_by' => $userId,
                        'modified_by' => $userId
                    ];
                    
                    // Check each optional column and add if it exists
                    foreach ($optionalColumns as $colName => $colValue) {
                        try {
                            $checkStmt = $conn->query("SHOW COLUMNS FROM travel_items LIKE '$colName'");
                            if ($checkStmt->rowCount() > 0) {
                                $columns[] = $colName;
                                $values[] = $colValue;
                                $placeholders[] = '?';
                            }
                        } catch (PDOException $e) {
                            // Column doesn't exist, skip it
                        }
                    }
                    
                    // Add created_at if it exists
                    try {
                        $checkStmt = $conn->query("SHOW COLUMNS FROM travel_items LIKE 'created_at'");
                        if ($checkStmt->rowCount() > 0) {
                            $columns[] = 'created_at';
                            $values[] = date('Y-m-d H:i:s');
                            $placeholders[] = '?';
                        }
                    } catch (PDOException $e) {
                        // created_at doesn't exist, skip it
                    }
                    
                    // Build and execute INSERT query
                    $columnsStr = implode(', ', $columns);
                    $placeholdersStr = implode(', ', $placeholders);
                    $sql = "INSERT INTO travel_items ($columnsStr) VALUES ($placeholdersStr)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($values);
                    
                    $importedItems++;
                }
            }
            
            // Note: Documents are not imported as they require actual file uploads
            // Only document metadata is stored, actual files would need to be re-uploaded
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Import completed successfully',
            'summary' => [
                'trips' => $importedTrips,
                'travel_items' => $importedItems,
                'documents' => $importedDocs,
            ],
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Database error in import_all.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while importing data: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error in import_all.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
