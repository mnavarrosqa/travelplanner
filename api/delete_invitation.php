<?php
/**
 * Delete Invitation API
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
$invitationId = $_POST['invitation_id'] ?? 0;

if (empty($invitationId)) {
    echo json_encode(['success' => false, 'message' => 'Invitation ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get invitation and verify ownership
    $stmt = $conn->prepare("
        SELECT i.*, t.user_id as trip_owner_id
        FROM invitations i
        INNER JOIN trips t ON i.trip_id = t.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invitationId]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => 'Invitation not found']);
        exit;
    }
    
    // Only owner or creator can delete
    if ($invitation['trip_owner_id'] != $userId && $invitation['created_by'] != $userId) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM invitations WHERE id = ?");
    $stmt->execute([$invitationId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Invitation deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in delete_invitation.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


