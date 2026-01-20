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

<!-- Edit Profile Section -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 class="card-title" style="margin: 0;">Profile Information</h2>
        <button type="button" id="editProfileBtn" class="btn btn-small" style="padding: 0.5rem 1rem;">
            <i class="fas fa-edit"></i> <span class="btn-text">Edit</span>
        </button>
    </div>
    
    <form id="profileForm" style="display: none;">
        <div class="form-group">
            <label class="form-label" for="email">
                <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--text-light); font-size: 0.85rem;"></i>Email *
            </label>
            <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="first_name">
                <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--text-light); font-size: 0.85rem;"></i>First Name
            </label>
            <input type="text" id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" maxlength="100">
        </div>
        
        <div class="form-group">
            <label class="form-label" for="last_name">
                <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--text-light); font-size: 0.85rem;"></i>Last Name
            </label>
            <input type="text" id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" maxlength="100">
        </div>
        
        <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
            <button type="submit" class="btn" style="flex: 1;">
                <i class="fas fa-save"></i> <span class="btn-text">Save Changes</span>
            </button>
            <button type="button" id="cancelProfileBtn" class="btn btn-secondary" style="flex: 1;">
                <i class="fas fa-times"></i> <span class="btn-text">Cancel</span>
            </button>
        </div>
    </form>
    
    <div id="profileView">
        <div class="form-group">
            <label class="form-label">Email</label>
            <div class="form-input" style="background: var(--bg-color); padding: 0.75rem;">
                <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--text-light);"></i>
                <?php echo htmlspecialchars($currentUser['email']); ?>
            </div>
        </div>
        
        <?php if ($currentUser['first_name'] || $currentUser['last_name']): ?>
        <div class="form-group">
            <label class="form-label">Name</label>
            <div class="form-input" style="background: var(--bg-color); padding: 0.75rem;">
                <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--text-light);"></i>
                <?php echo htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label class="form-label">Member Since</label>
            <div class="form-input" style="background: var(--bg-color); padding: 0.75rem;">
                <i class="fas fa-calendar" style="margin-right: 0.5rem; color: var(--text-light);"></i>
                <?php echo date(DATE_FORMAT, strtotime($currentUser['created_at'])); ?>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Section -->
<div class="card">
    <h3 class="card-title">Change Password</h3>
    <form id="passwordForm">
        <div class="form-group">
            <label class="form-label" for="current_password">
                <i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--text-light); font-size: 0.85rem;"></i>Current Password *
            </label>
            <input type="password" id="current_password" name="current_password" class="form-input" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="new_password">
                <i class="fas fa-key" style="margin-right: 0.5rem; color: var(--text-light); font-size: 0.85rem;"></i>New Password *
            </label>
            <input type="password" id="new_password" name="new_password" class="form-input" required minlength="6">
            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem; color: var(--text-light);">
                Must be at least 6 characters long
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="confirm_password">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem; color: var(--text-light); font-size: 0.85rem;"></i>Confirm New Password *
            </label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="6">
        </div>
        
        <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">
            <i class="fas fa-save"></i> <span class="btn-text">Change Password</span>
        </button>
    </form>
</div>

<!-- Export/Import Section -->
<div class="card">
    <h3 class="card-title">
        <i class="fas fa-database" style="margin-right: 0.5rem; color: var(--primary-color); font-size: 0.9rem;"></i>
        Data Management
    </h3>
    <p style="color: var(--text-light); font-size: 0.85rem; margin-bottom: 1.5rem; line-height: 1.6;">
        Export all your trips and travel data as a JSON file, or import data from a previous export. This allows you to backup your data or transfer it to another account.
    </p>
    
    <div style="display: flex; flex-direction: column; gap: 0.875rem;">
        <button type="button" id="exportAllBtn" class="btn" style="width: 100%;">
            <i class="fas fa-download"></i> <span class="btn-text">Export All Data</span>
        </button>
        
        <div style="position: relative;">
            <input type="file" id="importFile" accept=".json" style="position: absolute; width: 0.1px; height: 0.1px; opacity: 0; overflow: hidden; z-index: -1;">
            <label for="importFile" class="btn btn-secondary" style="width: 100%; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin: 0;">
                <i class="fas fa-upload"></i> <span class="btn-text">Import Data</span>
            </label>
        </div>
    </div>
    
    <div id="importStatus" style="display: none; margin-top: 1rem; padding: 0.875rem; border-radius: 8px; font-size: 0.9rem; line-height: 1.6;"></div>
    
    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(74, 144, 226, 0.05); border-radius: 8px; border-left: 3px solid var(--primary-color);">
        <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
            <i class="fas fa-info-circle" style="color: var(--primary-color); margin-top: 0.125rem; flex-shrink: 0;"></i>
            <div style="font-size: 0.8rem; color: var(--text-light); line-height: 1.5;">
                <strong style="color: var(--text-color);">Note:</strong> The export includes all trips, travel items, and document metadata. Document files themselves are not included in the export and will need to be re-uploaded after import.
            </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile Edit Toggle
    const editProfileBtn = document.getElementById('editProfileBtn');
    const cancelProfileBtn = document.getElementById('cancelProfileBtn');
    const profileForm = document.getElementById('profileForm');
    const profileView = document.getElementById('profileView');
    
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function() {
            profileForm.style.display = 'block';
            profileView.style.display = 'none';
            editProfileBtn.style.display = 'none';
        });
    }
    
    if (cancelProfileBtn) {
        cancelProfileBtn.addEventListener('click', function() {
            profileForm.style.display = 'none';
            profileView.style.display = 'block';
            editProfileBtn.style.display = 'inline-flex';
            // Reset form to original values
            profileForm.reset();
            document.getElementById('email').value = '<?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES); ?>';
            document.getElementById('first_name').value = '<?php echo htmlspecialchars($currentUser['first_name'] ?? '', ENT_QUOTES); ?>';
            document.getElementById('last_name').value = '<?php echo htmlspecialchars($currentUser['last_name'] ?? '', ENT_QUOTES); ?>';
        });
    }
    
    // Profile Form Submission
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = profileForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="btn-text">Saving...</span>';
            
            const formData = new FormData(profileForm);
            
            fetch('../api/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage('Profile updated successfully!', 'success');
                    // Reload page after a short delay to show updated info
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(data.message || 'Failed to update profile', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Password Form Submission
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                showMessage('New passwords do not match', 'error');
                return;
            }
            
            const submitBtn = passwordForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="btn-text">Changing...</span>';
            
            const formData = new FormData(passwordForm);
            
            fetch('../api/change_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Password changed successfully!', 'success');
                    passwordForm.reset();
                } else {
                    showMessage(data.message || 'Failed to change password', 'error');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Export All Data
    const exportAllBtn = document.getElementById('exportAllBtn');
    if (exportAllBtn) {
        exportAllBtn.addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="btn-text">Exporting...</span>';
            
            // Create a link to download the export
            const link = document.createElement('a');
            link.href = '../api/export_all.php?format=download';
            link.download = 'travelplanner_export_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showMessage('Export started. Your file will download shortly.', 'success');
            }, 500);
        });
    }
    
    // Import Data
    const importFile = document.getElementById('importFile');
    const importStatus = document.getElementById('importStatus');
    
    if (importFile) {
        importFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                showMessage('Please select a valid JSON file.', 'error');
                this.value = '';
                return;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                showMessage('File too large. Maximum size is 10MB.', 'error');
                this.value = '';
                return;
            }
            
            // Confirm import
            if (!confirm('This will import all trips and travel items from the file. Existing data will not be deleted. Continue?')) {
                this.value = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('import_file', file);
            
            importStatus.style.display = 'block';
            importStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing data...';
            importStatus.style.background = 'rgba(74, 144, 226, 0.1)';
            importStatus.style.color = 'var(--primary-color)';
            
            fetch('../api/import_all.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const summary = data.summary || {};
                    let message = 'Import completed successfully!<br>';
                    message += `Trips: ${summary.trips || 0}<br>`;
                    message += `Travel Items: ${summary.travel_items || 0}`;
                    
                    if (data.errors && data.errors.length > 0) {
                        message += '<br><small style="color: var(--text-light);">Some items were skipped due to errors.</small>';
                    }
                    
                    importStatus.innerHTML = message;
                    importStatus.style.background = 'rgba(80, 200, 120, 0.1)';
                    importStatus.style.color = 'var(--secondary-color)';
                    
                    // Reload page after 2 seconds to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    importStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Import failed');
                    importStatus.style.background = 'rgba(231, 76, 60, 0.1)';
                    importStatus.style.color = 'var(--danger-color)';
                }
                
                this.value = '';
            })
            .catch(error => {
                console.error('Error:', error);
                importStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred during import.';
                importStatus.style.background = 'rgba(231, 76, 60, 0.1)';
                importStatus.style.color = 'var(--danger-color)';
                this.value = '';
            });
        });
    }
    
    // Show message function
    function showMessage(message, type) {
        // Remove existing messages
        const existingMsg = document.querySelector('.profile-message');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'profile-message';
        messageDiv.style.cssText = `
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-width: 90%;
            text-align: center;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        `;
        
        if (type === 'success') {
            messageDiv.style.background = 'var(--secondary-color)';
            messageDiv.style.color = 'white';
        } else {
            messageDiv.style.background = 'var(--danger-color)';
            messageDiv.style.color = 'white';
        }
        
        messageDiv.textContent = message;
        document.body.appendChild(messageDiv);
        
        // Remove after 3 seconds
        setTimeout(() => {
            messageDiv.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => messageDiv.remove(), 300);
        }, 3000);
    }
    
    // Add CSS animations
    if (!document.getElementById('profile-message-styles')) {
        const style = document.createElement('style');
        style.id = 'profile-message-styles';
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
            @keyframes slideUp {
                from {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
            }
        `;
        document.head.appendChild(style);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


