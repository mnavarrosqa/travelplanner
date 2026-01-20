<?php
/**
 * Update User Profile API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$email = trim($_POST['email'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');

// Validation
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate name lengths
if (strlen($firstName) > 100) {
    echo json_encode(['success' => false, 'message' => 'First name must be 100 characters or less']);
    exit;
}

if (strlen($lastName) > 100) {
    echo json_encode(['success' => false, 'message' => 'Last name must be 100 characters or less']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email is already registered']);
        exit;
    }
    
    // Update user profile
    $stmt = $conn->prepare("
        UPDATE users 
        SET email = ?, first_name = ?, last_name = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $email,
        $firstName ?: null,
        $lastName ?: null,
        $userId
    ]);
    
    // Clear cached user data by regenerating session
    // The getCurrentUser() function uses a static variable, so we need to reload the page
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in update_profile.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
