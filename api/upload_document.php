<?php
/**
 * Upload Document API
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
$tripId = $_POST['trip_id'] ?? 0;
$travelItemId = $_POST['travel_item_id'] ?? null;
$uploadDir = UPLOAD_DIR;

// Ensure upload directory exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$file = $_FILES['file'];

// Validate file extension (more secure than MIME type alone)
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: images (JPEG, PNG, GIF, WebP) and PDF']);
    exit;
}

// Validate MIME type
if (!in_array($file['type'], ALLOWED_FILE_TYPES)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: images (JPEG, PNG, GIF, WebP) and PDF']);
    exit;
}

// Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB']);
    exit;
}

// Additional security: check actual file content for images
if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid image file']);
        exit;
    }
}

// Check permissions if trip_id provided
if ($tripId) {
    if (!canEditTrip($tripId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to upload documents to this trip']);
        exit;
    }
}

// Check permissions if travel_item_id provided
if ($travelItemId) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT trip_id FROM travel_items WHERE id = ?");
        $stmt->execute([$travelItemId]);
        $item = $stmt->fetch();
        if ($item && !canEditTrip($item['trip_id'], $userId)) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to upload documents to this item']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Generate unique filename (sanitize extension to prevent path traversal)
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$extension = preg_replace('/[^a-z0-9]/', '', $extension); // Remove any non-alphanumeric chars
if (!in_array($extension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
    exit;
}

$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Prevent path traversal
$filepath = realpath(dirname($filepath)) . '/' . basename($filename);
if (strpos($filepath, realpath($uploadDir)) !== 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid file path']);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO documents 
        (travel_item_id, trip_id, filename, original_filename, file_type, file_size, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $travelItemId ?: null,
        $tripId ?: null,
        $filename,
        $file['name'],
        $file['type'],
        $file['size'],
        $userId
    ]);
    
    echo json_encode([
        'success' => true,
        'document_id' => $conn->lastInsertId(),
        'filename' => $filename,
        'original_filename' => $file['name'],
        'message' => 'Document uploaded successfully'
    ]);
} catch (PDOException $e) {
    // Delete file if DB insert fails
    if (isset($filepath) && file_exists($filepath)) {
        @unlink($filepath);
    }
    error_log('Database error in upload_document.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

