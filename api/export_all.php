<?php
/**
 * Export All User Data - JSON Export
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$format = $_GET['format'] ?? 'json'; // json or download

$conn = getDBConnection();
$userId = getCurrentUserId();

try {
    // Get all trips owned by user
    $stmt = $conn->prepare("SELECT * FROM trips WHERE user_id = ? ORDER BY start_date DESC, created_at DESC");
    $stmt->execute([$userId]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all travel items for user's trips
    $tripIds = array_column($trips, 'id');
    $items = [];
    if (!empty($tripIds)) {
        $placeholders = str_repeat('?,', count($tripIds) - 1) . '?';
        $stmt = $conn->prepare("SELECT * FROM travel_items WHERE trip_id IN ($placeholders) ORDER BY start_datetime ASC");
        $stmt->execute($tripIds);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get all documents for user's trips
    $documents = [];
    if (!empty($tripIds)) {
        $placeholders = str_repeat('?,', count($tripIds) - 1) . '?';
        $stmt = $conn->prepare("SELECT * FROM documents WHERE trip_id IN ($placeholders) ORDER BY upload_date DESC");
        $stmt->execute($tripIds);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get trip collaborators (where user is owner)
    $tripCollaborators = [];
    if (!empty($tripIds)) {
        $placeholders = str_repeat('?,', count($tripIds) - 1) . '?';
        $stmt = $conn->prepare("SELECT * FROM trip_users WHERE trip_id IN ($placeholders)");
        $stmt->execute($tripIds);
        $tripCollaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Build export data structure
    $exportData = [
        'version' => '1.0',
        'export_date' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'trips' => [],
    ];
    
    // Organize data by trips
    foreach ($trips as $trip) {
        $tripData = $trip;
        $tripData['travel_items'] = [];
        $tripData['documents'] = [];
        $tripData['collaborators'] = [];
        
        // Add travel items for this trip
        foreach ($items as $item) {
            if ($item['trip_id'] == $trip['id']) {
                $tripData['travel_items'][] = $item;
            }
        }
        
        // Add documents for this trip
        foreach ($documents as $doc) {
            if ($doc['trip_id'] == $trip['id']) {
                // Don't include file content, just metadata
                $docMeta = $doc;
                unset($docMeta['id']); // Will be regenerated on import
                $tripData['documents'][] = $docMeta;
            }
        }
        
        // Add collaborators for this trip
        foreach ($tripCollaborators as $collab) {
            if ($collab['trip_id'] == $trip['id']) {
                $collabMeta = $collab;
                unset($collabMeta['id']); // Will be regenerated on import
                $tripData['collaborators'][] = $collabMeta;
            }
        }
        
        // Remove IDs that will be regenerated on import
        unset($tripData['id']);
        unset($tripData['user_id']); // Will use importing user's ID
        
        $exportData['trips'][] = $tripData;
    }
    
    // Add summary
    $exportData['summary'] = [
        'total_trips' => count($trips),
        'total_travel_items' => count($items),
        'total_documents' => count($documents),
    ];
    
    if ($format === 'download') {
        // Download as JSON file
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="travelplanner_export_' . date('Y-m-d') . '.json"');
        header('Content-Transfer-Encoding: binary');
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } else {
        // Return as JSON response
        echo json_encode([
            'success' => true,
            'data' => $exportData,
            'message' => 'Export data ready'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (PDOException $e) {
    error_log('Database error in export_all.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while exporting data. Please try again.'
    ]);
}
?>
