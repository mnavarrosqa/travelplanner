<?php
/**
 * Authentication Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate every 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    if ($user === null) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, email, first_name, last_name, created_at FROM users WHERE id = ?");
        $stmt->execute([getCurrentUserId()]);
        $user = $stmt->fetch();
    }
    return $user;
}

/**
 * Get base path for redirects
 */
function getBasePath() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname(dirname($scriptName));
    $basePath = str_replace('\\', '/', $basePath);
    
    // Extract travelplanner from path if present
    if (strpos($basePath, '/travelplanner') !== false) {
        $basePath = '/travelplanner';
    } else {
        // Try to detect from document root
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if ($docRoot && $scriptFile) {
            $relativePath = str_replace($docRoot, '', dirname($scriptFile));
            $parts = explode('/', trim($relativePath, '/'));
            if (!empty($parts[0])) {
                $basePath = '/' . $parts[0];
            } else {
                $basePath = '';
            }
        } else {
            $basePath = '';
        }
    }
    
    return $basePath;
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $basePath = getBasePath();
        header('Location: ' . $basePath . '/pages/login.php');
        exit;
    }
}

/**
 * Redirect to dashboard if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $basePath = getBasePath();
        header('Location: ' . $basePath . '/pages/dashboard.php');
        exit;
    }
}

/**
 * Login user
 */
function loginUser($userId) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_time'] = time();
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}
?>


