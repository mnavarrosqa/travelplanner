<?php
/**
 * Remove Collaborator API
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
$removeUserId = $_POST['user_id'] ?? 0;

if (empty($tripId) || empty($removeUserId)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID and User ID are required']);
    exit;
}

// Only owner can remove collaborators
if (!isTripOwner($tripId, $userId)) {
    echo json_encode(['success' => false, 'message' => 'Only trip owner can remove collaborators']);
    exit;
}

// Cannot remove owner
if ($removeUserId == $userId) {
    echo json_encode(['success' => false, 'message' => 'Cannot remove trip owner']);
    exit;
}

try {
    removeUserFromTrip($tripId, $removeUserId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Collaborator removed successfully'
    ]);
} catch (Exception $e) {
    error_log('Error in remove_collaborator.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


