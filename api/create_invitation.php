<?php
/**
 * Create Invitation Link API
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
$role = $_POST['role'] ?? 'viewer'; // 'editor' or 'viewer'
$expiresDays = $_POST['expires_days'] ?? 30;
$maxUses = $_POST['max_uses'] ?? null;

if (empty($tripId)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID is required']);
    exit;
}

if (!in_array($role, ['editor', 'viewer'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Only owner can create invitations
if (!isTripOwner($tripId, $userId)) {
    echo json_encode(['success' => false, 'message' => 'Only trip owner can create invitations']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Generate unique code
    $code = bin2hex(random_bytes(16));
    
    // Calculate expiration
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresDays days"));
    
    $stmt = $conn->prepare("
        INSERT INTO invitations (trip_id, code, created_by, role, expires_at, max_uses) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tripId, $code, $userId, $role, $expiresAt, $maxUses ?: null]);
    
    $invitationId = $conn->lastInsertId();
    
    // Generate invitation URL
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
    $invitationUrl = $baseUrl . '/pages/invite.php?code=' . urlencode($code);
    
    echo json_encode([
        'success' => true,
        'invitation_id' => $invitationId,
        'code' => $code,
        'url' => $invitationUrl,
        'message' => 'Invitation created successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in create_invitation.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

