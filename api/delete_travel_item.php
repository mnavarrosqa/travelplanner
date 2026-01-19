<?php
/**
 * Delete Travel Item API
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
$itemId = $_POST['item_id'] ?? 0;

if (empty($itemId)) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get trip_id from item
    $stmt = $conn->prepare("SELECT trip_id FROM travel_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Travel item not found']);
        exit;
    }
    
    // Check permissions
    if (!canEditTrip($item['trip_id'], $userId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this item']);
        exit;
    }
    
    // Delete item (cascade will handle documents)
    $stmt = $conn->prepare("DELETE FROM travel_items WHERE id = ?");
    $stmt->execute([$itemId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Travel item deleted successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in delete_travel_item.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

