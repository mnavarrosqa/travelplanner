<?php
/**
 * Accept Invitation API
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
$code = $_POST['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Invitation code is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get invitation
    $stmt = $conn->prepare("
        SELECT i.*, t.id as trip_id, t.title as trip_title
        FROM invitations i
        INNER JOIN trips t ON i.trip_id = t.id
        WHERE i.code = ?
    ");
    $stmt->execute([$code]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => 'Invitation not found']);
        exit;
    }
    
    // Check if expired
    if ($invitation['expires_at'] && strtotime($invitation['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This invitation has expired']);
        exit;
    }
    
    // Check if max uses reached
    if ($invitation['max_uses'] && $invitation['current_uses'] >= $invitation['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'This invitation has reached its maximum number of uses']);
        exit;
    }
    
    // Check if user is already the owner
    if ($invitation['trip_id'] && isTripOwner($invitation['trip_id'], $userId)) {
        echo json_encode(['success' => false, 'message' => 'You are already the owner of this trip']);
        exit;
    }
    
    // Add user to trip
    $success = addUserToTrip($invitation['trip_id'], $userId, $invitation['role'], $invitation['created_by']);
    
    if ($success) {
        // Increment use count
        $stmt = $conn->prepare("
            UPDATE invitations 
            SET current_uses = current_uses + 1 
            WHERE id = ?
        ");
        $stmt->execute([$invitation['id']]);
        
        echo json_encode([
            'success' => true,
            'trip_id' => $invitation['trip_id'],
            'message' => 'Invitation accepted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept invitation']);
    }
    
} catch (PDOException $e) {
    error_log('Database error in accept_invitation.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


