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

// Handle multiple files
$files = [];
if (isset($_FILES['file'])) {
    // Check if single file or multiple files
    if (is_array($_FILES['file']['name'])) {
        // Multiple files
        $fileCount = count($_FILES['file']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
                $files[] = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'size' => $_FILES['file']['size'][$i],
                    'error' => $_FILES['file']['error'][$i]
                ];
            }
        }
    } else {
        // Single file (backward compatibility)
        if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $files[] = $_FILES['file'];
        }
    }
}

if (empty($files)) {
    echo json_encode(['success' => false, 'message' => 'No files selected or file upload error']);
    exit;
}

// Validate file extension (more secure than MIME type alone)
$allowedExtensions = [
    // Images
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    // Documents
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'ppt', 'pptx',
    // Other
    'json', 'xml'
];

// Validate all files before processing
$allowedMimeTypes = ALLOWED_FILE_TYPES;
$allowedMimeTypes[] = 'application/octet-stream'; // Some systems use this for unknown types
$strictMimeCheck = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

foreach ($files as $file) {
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type: ' . htmlspecialchars($file['name']) . '. Allowed: Images, PDF, Word, Excel, PowerPoint, Text, CSV, JSON, XML']);
        exit;
    }
    
    // Validate MIME type for strict file types
    if (in_array($extension, $strictMimeCheck)) {
        if (!in_array($file['type'], $allowedMimeTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type: ' . htmlspecialchars($file['name'])]);
            exit;
        }
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'message' => 'File too large: ' . htmlspecialchars($file['name']) . '. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB']);
        exit;
    }
    
    // Additional security: check actual file content for images
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid image file: ' . htmlspecialchars($file['name'])]);
            exit;
        }
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

// Process all files
$uploadedFiles = [];
$failedFiles = [];
$conn = getDBConnection();

foreach ($files as $file) {
    // Generate unique filename (sanitize extension to prevent path traversal)
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extension = preg_replace('/[^a-z0-9]/', '', $extension); // Remove any non-alphanumeric chars
    if (!in_array($extension, $allowedExtensions)) {
        $failedFiles[] = ['name' => $file['name'], 'error' => 'Invalid file extension'];
        continue;
    }
    
    $filename = uniqid() . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Prevent path traversal
    $filepath = realpath(dirname($filepath)) . '/' . basename($filename);
    if (strpos($filepath, realpath($uploadDir)) !== 0) {
        $failedFiles[] = ['name' => $file['name'], 'error' => 'Invalid file path'];
        continue;
    }
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $failedFiles[] = ['name' => $file['name'], 'error' => 'Failed to save file'];
        continue;
    }
    
    try {
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
        
        $uploadedFiles[] = [
            'document_id' => $conn->lastInsertId(),
            'filename' => $filename,
            'original_filename' => $file['name']
        ];
    } catch (PDOException $e) {
        // Delete file if DB insert fails
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        error_log('Database error in upload_document.php: ' . $e->getMessage());
        $failedFiles[] = ['name' => $file['name'], 'error' => 'Database error'];
    }
}

// Return results
if (empty($uploadedFiles)) {
    echo json_encode([
        'success' => false,
        'message' => 'No files were uploaded successfully',
        'failed' => $failedFiles
    ]);
} else {
    $message = count($uploadedFiles) . ' file' . (count($uploadedFiles) > 1 ? 's' : '') . ' uploaded successfully';
    if (!empty($failedFiles)) {
        $message .= '. ' . count($failedFiles) . ' file' . (count($failedFiles) > 1 ? 's' : '') . ' failed';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'uploaded' => $uploadedFiles,
        'failed' => $failedFiles
    ]);
}
?>

