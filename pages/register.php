<?php
require_once __DIR__ . '/../includes/auth.php';
redirectIfLoggedIn();

$inviteCode = $_GET['invite'] ?? '';
$pageTitle = 'Register';
$showBack = false;
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <h2 class="card-title">Create Account</h2>
        <?php if ($inviteCode): ?>
            <p style="margin-bottom: 1rem; padding: 0.75rem; background: #E3F2FD; border-radius: 8px; color: #1976D2;">
                You're creating an account to accept a trip invitation
            </p>
        <?php endif; ?>
        <form id="registerForm">
            <?php if ($inviteCode): ?>
                <input type="hidden" name="invite_code" value="<?php echo htmlspecialchars($inviteCode); ?>">
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label" for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-input" autocomplete="given-name">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-input" autocomplete="family-name">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email *</label>
                <input type="email" id="email" name="email" class="form-input" required autocomplete="email">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password *</label>
                <input type="password" id="password" name="password" class="form-input" required autocomplete="new-password" minlength="6">
                <small class="form-help">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirm Password *</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-input" required autocomplete="new-password">
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <p class="auth-link">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../api/register.php', {
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
        alert('Error registering');
        console.error(error);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

