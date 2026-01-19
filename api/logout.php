<?php
/**
 * User Logout API
 */

require_once __DIR__ . '/../includes/auth.php';

logoutUser();

header('Location: /travelplanner/pages/login.php');
exit;
?>


