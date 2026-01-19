<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Profile';
$showBack = true;
include __DIR__ . '/../includes/header.php';

$currentUser = getCurrentUser();
$conn = getDBConnection();

// Get user stats
$stmt = $conn->prepare("SELECT COUNT(*) as trip_count FROM trips WHERE user_id = ?");
$stmt->execute([getCurrentUserId()]);
$tripCount = $stmt->fetch()['trip_count'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as item_count 
    FROM travel_items ti
    INNER JOIN trips t ON ti.trip_id = t.id
    WHERE t.user_id = ?
");
$stmt->execute([getCurrentUserId()]);
$itemCount = $stmt->fetch()['item_count'];
?>

<div class="card">
    <h2 class="card-title">User Profile</h2>
    <div class="form-group">
        <label class="form-label">Email</label>
        <div class="form-input" style="background: var(--bg-color);"><?php echo htmlspecialchars($currentUser['email']); ?></div>
    </div>
    
    <?php if ($currentUser['first_name'] || $currentUser['last_name']): ?>
    <div class="form-group">
        <label class="form-label">Name</label>
        <div class="form-input" style="background: var(--bg-color);">
            <?php echo htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="form-group">
        <label class="form-label">Member Since</label>
        <div class="form-input" style="background: var(--bg-color);">
            <?php echo date(DATE_FORMAT, strtotime($currentUser['created_at'])); ?>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Statistics</h3>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
        <div style="text-align: center; padding: 1rem; background: var(--bg-color); border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: 600; color: var(--primary-color);"><?php echo $tripCount; ?></div>
            <div style="color: var(--text-light);">Trips</div>
        </div>
        <div style="text-align: center; padding: 1rem; background: var(--bg-color); border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: 600; color: var(--primary-color);"><?php echo $itemCount; ?></div>
            <div style="color: var(--text-light);">Travel Items</div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


