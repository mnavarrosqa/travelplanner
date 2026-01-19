<?php
/**
 * Update Trip API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$tripId = $_POST['trip_id'] ?? 0;
$title = trim($_POST['title'] ?? '');
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? null;
$description = $_POST['description'] ?? null;

// Validation
if (empty($tripId) || empty($title) || empty($startDate)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID, title and start date are required']);
    exit;
}

// Validate title length
if (strlen($title) > 255) {
    echo json_encode(['success' => false, 'message' => 'Title must be 255 characters or less']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid start date format']);
    exit;
}

// Validate end date if provided
if ($endDate) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid end date format']);
        exit;
    }
    if ($endDate < $startDate) {
        echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
        exit;
    }
}

try {
    $conn = getDBConnection();
    
    // Check permissions
    if (!canEditTrip($tripId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this trip']);
        exit;
    }
    
    $stmt = $conn->prepare("
        UPDATE trips 
        SET title = ?, start_date = ?, end_date = ?, description = ?, modified_by = ?
        WHERE id = ?
    ");
    $stmt->execute([$title, $startDate, $endDate ?: null, $description ?: null, $userId, $tripId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Trip updated successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in update_trip.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

