    </main>
    
    <?php if (isLoggedIn()): ?>
    <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Trips</span>
        </a>
        <a href="dashboard.php?action=add" class="nav-item">
            <span class="nav-icon">➕</span>
            <span class="nav-label">Add Trip</span>
        </a>
        <a href="profile.php" class="nav-item">
            <span class="nav-icon">👤</span>
            <span class="nav-label">Profile</span>
        </a>
    </nav>
    <?php endif; ?>
    
    <?php
    // Get base path dynamically
    $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__FILE__)));
    $basePath = str_replace('\\', '/', $basePath);
    if (substr($basePath, 0, 1) !== '/') {
        $basePath = '/' . $basePath;
    }
    ?>
    <script src="<?php echo $basePath; ?>/assets/js/main.js"></script>
    <?php if (isset($pageTitle) && strpos($pageTitle, 'Trip') !== false): ?>
        <script src="<?php echo $basePath; ?>/assets/js/timeline.js"></script>
    <?php endif; ?>
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="/travelplanner/assets/js/<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

