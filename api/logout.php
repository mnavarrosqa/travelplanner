<?php
/**
 * User Logout API
 */

require_once __DIR__ . '/../includes/auth.php';

logoutUser();

$basePath = getBasePath();
header('Location: ' . $basePath . '/pages/login.php');
exit;
?>


