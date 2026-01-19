<?php
/**
 * Delete Trip API
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

if (empty($tripId)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Only owner can delete
    if (!isTripOwner($tripId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'Only trip owner can delete the trip']);
        exit;
    }
    
    // Delete trip (cascade will handle travel_items and documents)
    $stmt = $conn->prepare("DELETE FROM trips WHERE id = ?");
    $stmt->execute([$tripId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Trip deleted successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in delete_trip.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

