<?php
/**
 * Path Configuration
 * Dynamic base path detection
 */

// Define base path constant
// Note: getBasePath() function is defined in includes/auth.php to avoid conflicts
if (!defined('BASE_PATH')) {
    // Try to detect from common patterns
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    
    // Simple detection: check REQUEST_URI for subdirectory
    // This is more reliable than script name for subdomain installations
    $basePath = '';
    
    if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
        // Check if this is a known subdirectory (pages, api, etc.)
        $firstDir = $matches[1];
        if (!in_array($firstDir, ['pages', 'api', 'config', 'includes', 'install', 'assets', 'uploads'])) {
            // It's a base path subdirectory (e.g., /travel)
            $basePath = '/' . $firstDir;
        } else {
            // We're at root (first dir is a known app directory)
            $basePath = '';
        }
    } else {
        // No subdirectory in URI, we're at root
        $basePath = '';
    }
    
    define('BASE_PATH', $basePath);
}

/**
 * Get full URL for a path
 */
function url($path = '') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base = BASE_PATH;
    
    // Remove leading slash from path if base has it
    if ($base && substr($path, 0, 1) === '/') {
        $path = substr($path, 1);
    }
    
    return $protocol . '://' . $host . $base . ($path ? '/' . ltrim($path, '/') : '');
}
?>

