<?php
require_once __DIR__ . '/../includes/auth.php';
redirectIfLoggedIn();

$inviteCode = $_GET['invite'] ?? '';
$pageTitle = 'Login';
$showBack = false;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <h2 class="card-title">Login</h2>
        <?php if ($inviteCode): ?>
            <p style="margin-bottom: 1rem; padding: 0.75rem; background: #E3F2FD; border-radius: 8px; color: #1976D2;">
                You're logging in to accept a trip invitation
            </p>
        <?php endif; ?>
        
        <form id="loginForm">
            <?php if ($inviteCode): ?>
                <input type="hidden" name="invite_code" value="<?php echo htmlspecialchars($inviteCode); ?>">
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label" for="email">Email *</label>
                <input type="email" id="email" name="email" class="form-input" required autocomplete="email">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password *</label>
                <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <p class="auth-link">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../api/login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const inviteCode = document.querySelector('input[name="invite_code"]')?.value;
            if (inviteCode) {
                window.location.href = 'invite.php?code=' + encodeURIComponent(inviteCode);
            } else {
                window.location.href = 'dashboard.php';
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error logging in');
        console.error(error);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

