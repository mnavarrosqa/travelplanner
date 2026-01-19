<?php
/**
 * Get Invitations for Trip API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$tripId = $_GET['trip_id'] ?? 0;

if (empty($tripId)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID is required']);
    exit;
}

// Only owner can view invitations
if (!isTripOwner($tripId, $userId)) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Detect base path
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname(dirname($scriptName));
    if (strpos($basePath, '/travelplanner') !== false) {
        $basePath = '/travelplanner';
    } else {
        $basePath = '';
    }
    
    $baseUrl = $protocol . '://' . $host . $basePath;
    
    $stmt = $conn->prepare("
        SELECT i.*, 
               u.email as created_by_email,
               CASE 
                   WHEN i.expires_at < NOW() THEN 'expired'
                   WHEN i.max_uses IS NOT NULL AND i.current_uses >= i.max_uses THEN 'maxed'
                   ELSE 'active'
               END as status
        FROM invitations i
        INNER JOIN users u ON i.created_by = u.id
        WHERE i.trip_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$tripId]);
    $invitations = $stmt->fetchAll();
    
    foreach ($invitations as &$inv) {
        $inv['url'] = $baseUrl . '/pages/invite.php?code=' . urlencode($inv['code']);
    }
    
    echo json_encode([
        'success' => true,
        'invitations' => $invitations
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get_invitations.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


