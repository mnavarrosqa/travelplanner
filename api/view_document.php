<?php
/**
 * View Document - Serve uploaded files securely
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

$filename = $_GET['file'] ?? '';
$documentId = $_GET['id'] ?? 0;

if (empty($filename) && empty($documentId)) {
    http_response_code(400);
    die('Invalid request');
}

try {
    $conn = getDBConnection();
    $userId = getCurrentUserId();
    
    // Get document
    if ($documentId) {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$documentId]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE filename = ?");
        $stmt->execute([$filename]);
    }
    
    $document = $stmt->fetch();
    
    if (!$document) {
        http_response_code(404);
        die('Document not found');
    }
    
    // Check permissions - user must have access to the trip
    $tripId = $document['trip_id'];
    if ($tripId && !hasTripAccess($tripId, $userId)) {
        http_response_code(403);
        die('Access denied');
    }
    
    // If document is attached to a travel item, check that trip
    if ($document['travel_item_id']) {
        $stmt = $conn->prepare("SELECT trip_id FROM travel_items WHERE id = ?");
        $stmt->execute([$document['travel_item_id']]);
        $item = $stmt->fetch();
        if ($item && !hasTripAccess($item['trip_id'], $userId)) {
            http_response_code(403);
            die('Access denied');
        }
    }
    
    $filepath = UPLOAD_DIR . $document['filename'];
    
    // Prevent path traversal
    $realUploadDir = realpath(UPLOAD_DIR);
    $realFilePath = realpath($filepath);
    
    if ($realFilePath === false || strpos($realFilePath, $realUploadDir) !== 0) {
        http_response_code(404);
        die('File not found');
    }
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        die('File not found');
    }
    
    // Set headers for download or display
    $disposition = isset($_GET['download']) && $_GET['download'] ? 'attachment' : 'inline';
    
    // Sanitize filename for header
    $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $document['original_filename']);
    
    header('Content-Type: ' . $document['file_type']);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('X-Content-Type-Options: nosniff');
    
    readfile($filepath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error serving file');
}
?>


