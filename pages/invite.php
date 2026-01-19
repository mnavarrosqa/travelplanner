<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

$code = $_GET['code'] ?? '';

if (empty($code)) {
    die('Invalid invitation code');
}

$conn = getDBConnection();

// Get invitation details
$stmt = $conn->prepare("
    SELECT i.*, 
           t.title as trip_title, 
           t.id as trip_id, 
           u.email as created_by_email,
           u.first_name as created_by_first,
           u.last_name as created_by_last
    FROM invitations i
    INNER JOIN trips t ON i.trip_id = t.id
    INNER JOIN users u ON i.created_by = u.id
    WHERE i.code = ?
");
$stmt->execute([$code]);
$invitation = $stmt->fetch();

if (!$invitation) {
    die('Invitation not found');
}

// Check if expired
if ($invitation['expires_at'] && strtotime($invitation['expires_at']) < time()) {
    die('This invitation has expired');
}

// Check if max uses reached
if ($invitation['max_uses'] && $invitation['current_uses'] >= $invitation['max_uses']) {
    die('This invitation has reached its maximum number of uses');
}

$pageTitle = 'Trip Invitation';
$showBack = false;
include __DIR__ . '/../includes/header.php';

$isLoggedIn = isLoggedIn();
$currentUserId = getCurrentUserId();

// If logged in, check if already has access
$alreadyHasAccess = false;
if ($isLoggedIn) {
    $alreadyHasAccess = hasTripAccess($invitation['trip_id'], $currentUserId);
    
    // If already has access, redirect to trip
    if ($alreadyHasAccess) {
        header('Location: trip_detail.php?id=' . $invitation['trip_id']);
        exit;
    }
}
?>

<div class="auth-container">
    <div class="card">
        <h2 class="card-title">Trip Invitation</h2>
        <p style="margin-bottom: 1rem;">
            You've been invited to collaborate on:<br>
            <strong><?php echo htmlspecialchars($invitation['trip_title']); ?></strong>
        </p>
        <p style="margin-bottom: 1rem; color: var(--text-light); font-size: 0.9rem;">
            Invited by: <?php 
            $inviterName = trim(($invitation['created_by_first'] ?? '') . ' ' . ($invitation['created_by_last'] ?? ''));
            echo htmlspecialchars($inviterName ?: $invitation['created_by_email']); 
            ?><br>
            Permission level: <strong><?php echo ucfirst($invitation['role']); ?></strong>
        </p>

        <?php if ($isLoggedIn): ?>
            <form id="acceptInvitationForm">
                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                <button type="submit" class="btn">Accept Invitation</button>
            </form>
        <?php else: ?>
            <div style="margin: 1.5rem 0;">
                <p style="margin-bottom: 1rem;">Please login or create an account to accept this invitation:</p>
                <div style="display: flex; gap: 0.5rem; flex-direction: column;">
                    <a href="login.php?invite=<?php echo urlencode($code); ?>" class="btn">Login</a>
                    <a href="register.php?invite=<?php echo urlencode($code); ?>" class="btn btn-secondary">Create Account</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isLoggedIn): ?>
<script>
document.getElementById('acceptInvitationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../api/accept_invitation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'trip_detail.php?id=' + data.trip_id;
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error accepting invitation');
        console.error(error);
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

