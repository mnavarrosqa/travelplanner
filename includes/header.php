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
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#4A90E2">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Travel Planner</title>
    <?php
    // Get base path dynamically
    $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__FILE__)));
    $basePath = str_replace('\\', '/', $basePath);
    if (substr($basePath, 0, 1) !== '/') {
        $basePath = '/' . $basePath;
    }
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <?php if ($showBack): ?>
                <a href="dashboard.php" class="back-button">← Back</a>
            <?php endif; ?>
            <h1 class="header-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php if (isLoggedIn() && $currentUser): ?>
                <div class="header-user">
                    <span class="user-name"><?php echo htmlspecialchars($currentUser['first_name'] ?: $currentUser['email']); ?></span>
                    <a href="../api/logout.php" class="logout-link">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="main-content">


