<?php
/**
 * Delete Document API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$documentId = $_POST['document_id'] ?? 0;

if (empty($documentId)) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get document
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();
    
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }
    
    // Check permissions - user must have edit access to the trip
    $tripId = $document['trip_id'];
    if ($tripId && !canEditTrip($tripId, $userId)) {
        // If document is attached to travel item, check that trip
        if ($document['travel_item_id']) {
            $stmt = $conn->prepare("SELECT trip_id FROM travel_items WHERE id = ?");
            $stmt->execute([$document['travel_item_id']]);
            $item = $stmt->fetch();
            if ($item && !canEditTrip($item['trip_id'], $userId)) {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this document']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this document']);
            exit;
        }
    }
    
    // Delete file (with path traversal protection)
    $filepath = UPLOAD_DIR . $document['filename'];
    $realUploadDir = realpath(UPLOAD_DIR);
    $realFilePath = realpath($filepath);
    
    if ($realFilePath && strpos($realFilePath, $realUploadDir) === 0 && file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Delete database record
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Document deleted successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in delete_document.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


