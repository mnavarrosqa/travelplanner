<?php
/**
 * Path Configuration
 * Dynamic base path detection
 */

// Define base path constant
// Note: getBasePath() function is defined in includes/auth.php to avoid conflicts
if (!defined('BASE_PATH')) {
    // Try to detect from common patterns
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Extract base path from script name
    $basePath = dirname($scriptName);
    
    // If script is in subdirectory, use that
    if (strpos($basePath, '/travelplanner') !== false) {
        $basePath = '/travelplanner';
    } else {
        // Fallback: try to detect from REQUEST_URI
        if (preg_match('#(/[^/]+)/#', $requestUri, $matches)) {
            $basePath = $matches[1];
        } else {
            $basePath = '';
        }
    }
    
    define('BASE_PATH', $basePath);
}

/**
 * Get full URL for a path
 */
function url($path = '') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = BASE_PATH;
    
    // Remove leading slash from path if base has it
    if ($base && substr($path, 0, 1) === '/') {
        $path = substr($path, 1);
    }
    
    return $protocol . '://' . $host . $base . ($path ? '/' . ltrim($path, '/') : '');
}
?>

