<?php
/**
 * Add Trip API
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
$title = trim($_POST['title'] ?? '');
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? null;
$description = $_POST['description'] ?? null;

// Validation
if (empty($title) || empty($startDate)) {
    echo json_encode(['success' => false, 'message' => 'Title and start date are required']);
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
    
    $stmt = $conn->prepare("
        INSERT INTO trips (user_id, title, start_date, end_date, description, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $title, $startDate, $endDate ?: null, $description ?: null, $userId]);
    
    $tripId = $conn->lastInsertId();
    
    // Add owner to trip_users
    $stmt = $conn->prepare("
        INSERT INTO trip_users (trip_id, user_id, role) 
        VALUES (?, ?, 'owner')
        ON DUPLICATE KEY UPDATE role = 'owner'
    ");
    $stmt->execute([$tripId, $userId]);
    
    echo json_encode([
        'success' => true,
        'trip_id' => $tripId,
        'message' => 'Trip created successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in add_trip.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

