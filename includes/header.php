<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Travel Planner';
}
if (!isset($showBack)) {
    $showBack = false;
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#4A90E2">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%23ffffff' d='M176 56V96H336V56c0-4.4-3.6-8-8-8H184c-4.4 0-8 3.6-8 8zM128 96V56c0-30.9 25.1-56 56-56H328c30.9 0 56 25.1 56 56V96v32H480c35.3 0 64 28.7 64 64V416c0 35.3-28.7 64-64 64H32c-35.3 0-64-28.7-64-64V192c0-35.3 28.7-64 64-64H128V96zM32 192H480V416H32V192zm80 64c-8.8 0-16 7.2-16 16v64c0 8.8 7.2 16 16 16H400c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H112z'/%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Travel Planner</title>
    <?php
    // Ensure paths.php is loaded first
    if (!defined('BASE_PATH')) {
        $pathsFile = __DIR__ . '/../config/paths.php';
        if (file_exists($pathsFile)) {
            require_once $pathsFile;
        }
    }
    
    // Get base path dynamically - use BASE_PATH constant if available, otherwise detect
    if (!isset($basePath)) {
        if (defined('BASE_PATH')) {
            $basePath = BASE_PATH;
        } else {
            // Simple detection: check REQUEST_URI for subdirectory
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
                // Check if this is a known subdirectory (pages, api, etc.)
                $firstDir = $matches[1];
                if (!in_array($firstDir, ['pages', 'api', 'config', 'includes', 'install', 'assets', 'uploads'])) {
                    // It's a base path subdirectory
                    $basePath = '/' . $firstDir;
                } else {
                    // We're at root
                    $basePath = '';
                }
            } else {
                // No subdirectory in URI, we're at root
                $basePath = '';
            }
        }
    }
    
    // Ensure basePath is set and normalized
    if (!isset($basePath)) {
        $basePath = '';
    }
    $basePath = rtrim($basePath, '/');
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath ? $basePath . '/' : '/'); ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath ? $basePath . '/' : '/'); ?>assets/css/header-improvements.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath ? $basePath . '/' : '/'); ?>assets/css/status_badges.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <?php if ($showBack): ?>
                    <a href="dashboard.php" class="back-button">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                <?php endif; ?>
                <h1 class="header-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            </div>
            <?php if (isLoggedIn() && $currentUser): ?>
                <div class="header-user-container">
                    <div class="header-user-info">
                        <div class="header-user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($currentUser['first_name'] ?: $currentUser['email']); ?></span>
                    </div>
                    <a href="../api/logout.php" class="header-action-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="main-content">


