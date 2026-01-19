<?php
/**
 * Add Travel Item API
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
$type = $_POST['type'] ?? '';
$title = trim($_POST['title'] ?? '');
$startDatetime = $_POST['start_datetime'] ?? '';
$endDatetime = $_POST['end_datetime'] ?? null;
$location = $_POST['location'] ?? null;
$confirmationNumber = $_POST['confirmation_number'] ?? null;
$description = $_POST['description'] ?? null;
$cost = $_POST['cost'] ?? null;
$currency = $_POST['currency'] ?? 'USD';

// Validation
if (empty($tripId) || empty($type) || empty($title) || empty($startDatetime)) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit;
}

// Validate type
$allowedTypes = ['flight', 'train', 'bus', 'hotel', 'car_rental', 'activity', 'other'];
if (!in_array($type, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid travel item type']);
    exit;
}

// Validate title length
if (strlen($title) > 255) {
    echo json_encode(['success' => false, 'message' => 'Title must be 255 characters or less']);
    exit;
}

// Validate datetime format
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $startDatetime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid start datetime format']);
    exit;
}

// Validate end datetime if provided
if ($endDatetime) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $endDatetime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid end datetime format']);
        exit;
    }
    if ($endDatetime < $startDatetime) {
        echo json_encode(['success' => false, 'message' => 'End datetime must be after start datetime']);
        exit;
    }
}

// Validate cost if provided
if ($cost !== null && $cost !== '') {
    $cost = filter_var($cost, FILTER_VALIDATE_FLOAT);
    if ($cost === false || $cost < 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cost value']);
        exit;
    }
}

// Validate currency
$allowedCurrencies = ['USD', 'EUR', 'GBP', 'JPY'];
if (!in_array($currency, $allowedCurrencies)) {
    $currency = 'USD';
}

try {
    $conn = getDBConnection();
    
    // Check permissions
    if (!canEditTrip($tripId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to add items to this trip']);
        exit;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO travel_items 
        (trip_id, type, title, description, start_datetime, end_datetime, location, confirmation_number, cost, currency, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tripId, $type, $title, $description ?: null, 
        $startDatetime, $endDatetime ?: null, 
        $location ?: null, $confirmationNumber ?: null,
        $cost ?: null, $currency, $userId
    ]);
    
    $itemId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'item_id' => $itemId,
        'message' => 'Travel item added successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in add_travel_item.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

