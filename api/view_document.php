<?php
/**
 * View Document - Serve uploaded files securely
 */

// Prevent any output before headers
ob_start();

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
    
    // Determine if file should be displayed inline or downloaded
    // Images and PDFs can be displayed inline, others should be downloaded
    $extension = strtolower(pathinfo($document['original_filename'], PATHINFO_EXTENSION));
    
    // Check if file is an image or PDF
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $isImage = in_array($extension, $imageExtensions);
    $isPdf = $extension === 'pdf';
    
    // Check MIME type
    $fileType = strtolower($document['file_type'] ?? '');
    $isImageMime = strpos($fileType, 'image/') === 0;
    $isPdfMime = $fileType === 'application/pdf' || strpos($fileType, 'pdf') !== false;
    
    // Can display inline if it's an image or PDF
    $canDisplayInline = ($isImage || $isPdf) || ($isImageMime || $isPdfMime);
    
    // Force download only if explicitly requested via ?download=1 parameter
    $forceDownload = isset($_GET['download']) && $_GET['download'] == '1';
    $disposition = $forceDownload ? 'attachment' : ($canDisplayInline ? 'inline' : 'attachment');
    
    // Sanitize filename for header - use RFC 5987 encoding for better browser compatibility
    $originalFilename = $document['original_filename'];
    $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFilename);
    
    // Set proper content type
    $contentType = $document['file_type'];
    // Fix common MIME type issues
    if (empty($contentType) || $contentType === 'application/octet-stream') {
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml'
        ];
        if (isset($mimeMap[$extension])) {
            $contentType = $mimeMap[$extension];
        }
    }
    
    // Ensure images and PDFs have correct MIME types for inline display
    if ($isImage && strpos($contentType, 'image/') !== 0) {
        $imageMimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        if (isset($imageMimeMap[$extension])) {
            $contentType = $imageMimeMap[$extension];
        }
    }
    
    if ($isPdf && $contentType !== 'application/pdf') {
        $contentType = 'application/pdf';
    }
    
    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verify file is readable
    if (!is_readable($filepath)) {
        http_response_code(500);
        header('Content-Type: text/plain');
        die('File is not readable');
    }
    
    // Get file size
    $fileSize = filesize($filepath);
    if ($fileSize === false || $fileSize === 0) {
        http_response_code(500);
        header('Content-Type: text/plain');
        die('Invalid file size');
    }
    
    // Set headers - must be before any output
    // Remove any existing headers that might interfere
    if (headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain');
        die('Headers already sent');
    }
    
    header('Content-Type: ' . $contentType);
    
    // Use RFC 5987 encoding for filename to support special characters
    $encodedFilename = rawurlencode($originalFilename);
    $dispositionHeader = $disposition . '; filename="' . $safeFilename . '"';
    if ($encodedFilename !== $safeFilename) {
        $dispositionHeader .= '; filename*=UTF-8\'\'' . $encodedFilename;
    }
    header('Content-Disposition: ' . $dispositionHeader);
    header('Content-Length: ' . $fileSize);
    header('X-Content-Type-Options: nosniff');
    
    // For images and PDFs, allow caching for better performance
    if ($canDisplayInline) {
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
    } else {
        header('Cache-Control: private, max-age=3600');
    }
    
    // Output file
    $fileHandle = @fopen($filepath, 'rb');
    if ($fileHandle === false) {
        http_response_code(500);
        header('Content-Type: text/plain');
        die('Error reading file');
    }
    
    // Stream file in chunks for better performance
    while (!feof($fileHandle)) {
        $chunk = fread($fileHandle, 8192);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    fclose($fileHandle);
    exit;
    
} catch (Exception $e) {
    // Clear any output before error
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain');
    die('Error serving file');
}

