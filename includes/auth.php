<?php
/**
 * Authentication Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters
    // For single subdomain installations, don't set domain - let PHP handle it automatically
    // This ensures the cookie works correctly for the current domain
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    // PHP 7.3+ supports array syntax with samesite
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie lifetime (0 = until browser closes)
            'path' => '/', // Cookie path - root path works for all subdirectories
            'domain' => '', // Empty = current domain only (PHP handles this automatically)
            'secure' => $isSecure, // Only send over HTTPS if site is HTTPS
            'httponly' => true, // Not accessible via JavaScript
            'samesite' => 'Lax' // CSRF protection
        ]);
    } else {
        // For PHP < 7.3, use separate parameters (samesite not supported)
        session_set_cookie_params(0, '/', '', $isSecure, true);
    }
    
    session_start();
    
    // Regenerate session ID periodically for security (but preserve session data)
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate every 30 minutes, but preserve session data
        $oldSessionData = $_SESSION;
        session_regenerate_id(false); // false = don't delete old session
        $_SESSION = $oldSessionData; // Restore session data
        $_SESSION['created'] = time();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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
    // Use BASE_PATH constant if available (from config/paths.php)
    if (defined('BASE_PATH')) {
        return BASE_PATH;
    }
    
    // Fallback: Try to load paths.php
    $pathsFile = __DIR__ . '/../config/paths.php';
    if (file_exists($pathsFile)) {
        require_once $pathsFile;
        if (defined('BASE_PATH')) {
            return BASE_PATH;
        }
    }
    
    // Final fallback: Use the same detection logic as paths.php
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    
    $basePath = dirname($scriptName);
    $basePath = str_replace(['/pages', '/api', '/config', '/includes', '/install'], '', $basePath);
    $basePath = str_replace('\\', '/', $basePath);
    $basePath = rtrim($basePath, '/');
    $basePath = preg_replace('#/+#', '/', $basePath);
    
    if (empty($basePath) || $basePath === '/') {
        if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
            $firstDir = $matches[1];
            if (!in_array($firstDir, ['pages', 'api', 'config', 'includes', 'install', 'assets', 'uploads'])) {
                $basePath = '/' . $firstDir;
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
        // Build redirect URL
        $loginUrl = $basePath ? $basePath . '/pages/login.php' : '/pages/login.php';
        // Ensure we have a leading slash
        if (substr($loginUrl, 0, 1) !== '/') {
            $loginUrl = '/' . $loginUrl;
        }
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Redirect to dashboard if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $basePath = getBasePath();
        // Build redirect URL
        $dashboardUrl = $basePath ? $basePath . '/pages/dashboard.php' : '/pages/dashboard.php';
        // Ensure we have a leading slash
        if (substr($dashboardUrl, 0, 1) !== '/') {
            $dashboardUrl = '/' . $dashboardUrl;
        }
        header('Location: ' . $dashboardUrl);
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


