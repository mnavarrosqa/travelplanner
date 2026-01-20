<?php
/**
 * Main entry point - redirects to dashboard or login
 */
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $basePath = getBasePath();
    header('Location: ' . $basePath . '/pages/dashboard.php');
} else {
    $basePath = getBasePath();
    header('Location: ' . $basePath . '/pages/login.php');
}
exit;
?>


