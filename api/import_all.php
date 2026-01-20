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
            
            // Insert trip
            $stmt = $conn->prepare("
                INSERT INTO trips (user_id, title, start_date, end_date, description, travel_type, 
                                 is_multiple_destinations, destinations, status, created_at, modified_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $userId,
                $tripData['title'],
                $tripData['start_date'],
                $tripData['end_date'] ?? null,
                $tripData['description'] ?? null,
                $tripData['travel_type'] ?? null,
                $tripData['is_multiple_destinations'] ?? 0,
                $tripData['destinations'] ?? null,
                $tripData['status'] ?? 'active',
                $userId
            ]);
            
            $newTripId = $conn->lastInsertId();
            $importedTrips++;
            
            // Import travel items
            if (isset($tripData['travel_items']) && is_array($tripData['travel_items'])) {
                foreach ($tripData['travel_items'] as $itemData) {
                    if (empty($itemData['type']) || empty($itemData['title']) || empty($itemData['start_datetime'])) {
                        continue; // Skip invalid items
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO travel_items (trip_id, type, title, start_datetime, end_datetime, 
                                                 location, confirmation_number, description, notes,
                                                 flight_departure_scheduled, flight_departure_revised, flight_departure_runway,
                                                 flight_arrival_scheduled, flight_arrival_revised, flight_arrival_runway,
                                                 flight_duration_minutes, flight_departure_icao, flight_departure_country,
                                                 flight_arrival_icao, flight_arrival_country, flight_aircraft_registration,
                                                 flight_airline, flight_number, hotel_data, created_by, modified_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $newTripId,
                        $itemData['type'],
                        $itemData['title'],
                        $itemData['start_datetime'],
                        $itemData['end_datetime'] ?? null,
                        $itemData['location'] ?? null,
                        $itemData['confirmation_number'] ?? null,
                        $itemData['description'] ?? null,
                        $itemData['notes'] ?? null,
                        $itemData['flight_departure_scheduled'] ?? null,
                        $itemData['flight_departure_revised'] ?? null,
                        $itemData['flight_departure_runway'] ?? null,
                        $itemData['flight_arrival_scheduled'] ?? null,
                        $itemData['flight_arrival_revised'] ?? null,
                        $itemData['flight_arrival_runway'] ?? null,
                        $itemData['flight_duration_minutes'] ?? null,
                        $itemData['flight_departure_icao'] ?? null,
                        $itemData['flight_departure_country'] ?? null,
                        $itemData['flight_arrival_icao'] ?? null,
                        $itemData['flight_arrival_country'] ?? null,
                        $itemData['flight_aircraft_registration'] ?? null,
                        $itemData['flight_airline'] ?? null,
                        $itemData['flight_number'] ?? null,
                        $itemData['hotel_data'] ?? null,
                        $userId,
                        $userId
                    ]);
                    
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
