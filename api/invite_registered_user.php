<?php
/**
 * Invite a user (by email) to collaborate on a trip.
 * - If the email belongs to a registered user: add/update trip_users directly.
 * - If not registered: create an invitation code and email the invite link.
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
$tripId = (int)($_POST['trip_id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'viewer'; // editor|viewer
$expiresDays = (int)($_POST['expires_days'] ?? 30);

if ($tripId <= 0 || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Trip ID and email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if (!in_array($role, ['editor', 'viewer'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

if ($expiresDays < 1 || $expiresDays > 365) {
    $expiresDays = 30;
}

// Only owner can invite users
if (!isTripOwner($tripId, $userId)) {
    echo json_encode(['success' => false, 'message' => 'Only trip owner can invite collaborators']);
    exit;
}

function buildBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = getBasePath(); // from includes/auth.php (already required by permissions.php)
    return $protocol . '://' . $host . $basePath;
}

function sendInviteEmail($toEmail, $subject, $body) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $from = 'no-reply@' . preg_replace('/[^a-z0-9\.\-]/i', '', $host);

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $from;
    $headers[] = 'Reply-To: ' . $from;

    return @mail($toEmail, $subject, $body, implode("\r\n", $headers));
}

try {
    $conn = getDBConnection();

    // Get trip (validate it exists and get title/owner)
    $stmt = $conn->prepare("SELECT id, title, user_id FROM trips WHERE id = ? LIMIT 1");
    $stmt->execute([$tripId]);
    $tripRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tripRow) {
        echo json_encode(['success' => false, 'message' => 'Trip not found']);
        exit;
    }

    // Find user by email (registered invite)
    $stmt = $conn->prepare("SELECT id, email, first_name, last_name FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $invitee = $stmt->fetch(PDO::FETCH_ASSOC);

    $tripTitle = $tripRow['title'] ?? 'Trip';

    if ($invitee) {
        $inviteeId = (int)$invitee['id'];

        // Cannot invite yourself (already owner)
        if ($inviteeId === (int)$userId) {
            echo json_encode(['success' => false, 'message' => 'You are already on this trip']);
            exit;
        }

        // Cannot invite the owner of the trip
        if ((int)$tripRow['user_id'] === $inviteeId) {
            echo json_encode(['success' => false, 'message' => 'That user is already the trip owner']);
            exit;
        }

        // Add/update collaborator role
        $ok = addUserToTrip($tripId, $inviteeId, $role, $userId);
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Failed to add collaborator']);
            exit;
        }

        // Optional notification email (best-effort)
        $baseUrl = buildBaseUrl();
        $tripUrl = $baseUrl . '/pages/trip_detail.php?id=' . urlencode((string)$tripId);
        $subject = 'You were added to a trip: ' . $tripTitle;
        $body = "Hello,\n\nYou have been added as a " . $role . " to the trip \"" . $tripTitle . "\".\n\nOpen the trip:\n" . $tripUrl . "\n\nIf you are not logged in, please log in first.\n";
        sendInviteEmail($email, $subject, $body);

        echo json_encode([
            'success' => true,
            'message' => 'Collaborator added successfully',
            'mode' => 'added',
            'collaborator' => [
                'id' => $inviteeId,
                'email' => $invitee['email'],
                'first_name' => $invitee['first_name'] ?? null,
                'last_name' => $invitee['last_name'] ?? null,
                'role' => $role
            ]
        ]);
        exit;
    }

    // Not registered: create invitation + send email
    $code = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiresDays . ' days'));
    $maxUses = 1;

    $stmt = $conn->prepare("
        INSERT INTO invitations (trip_id, code, created_by, role, expires_at, max_uses)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tripId, $code, $userId, $role, $expiresAt, $maxUses]);

    $baseUrl = buildBaseUrl();
    $inviteUrl = $baseUrl . '/pages/invite.php?code=' . urlencode($code);

    $subject = 'Invitation to collaborate: ' . $tripTitle;
    $body = "Hello,\n\nYou have been invited to collaborate on the trip \"" . $tripTitle . "\" as a " . $role . ".\n\nTo accept, open this link:\n" . $inviteUrl . "\n\nIf you don't have an account yet, the link will let you create one and then accept the invite.\n\nThis invitation expires on: " . $expiresAt . "\n";

    $sent = sendInviteEmail($email, $subject, $body);
    if (!$sent) {
        echo json_encode([
            'success' => false,
            'message' => 'Invitation created, but email could not be sent from the server',
            'mode' => 'invite_created',
            'invite_url' => $inviteUrl
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Invitation email sent successfully',
        'mode' => 'email_sent'
    ]);
} catch (PDOException $e) {
    error_log('Database error in invite_registered_user.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

