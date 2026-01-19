<?php
/**
 * Main entry point - redirects to dashboard or login
 */
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: /travelplanner/pages/dashboard.php');
} else {
    header('Location: /travelplanner/pages/login.php');
}
exit;
?>


