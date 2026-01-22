    </main>
    
    <?php if (isLoggedIn()): ?>
    <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-label">Trips</span>
        </a>
        <a href="dashboard.php?action=add" class="nav-item">
            <i class="fas fa-plus nav-icon"></i>
            <span class="nav-label">Add Trip</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span class="nav-label">Profile</span>
        </a>
    </nav>
    <?php endif; ?>
    
    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top" aria-label="Back to top" title="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>
    
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
    <script src="<?php echo htmlspecialchars($basePath ? $basePath . '/' : '/'); ?>assets/js/main.js"></script>
    <?php if (isset($pageTitle) && strpos($pageTitle, 'Trip') !== false): ?>
        <script src="<?php echo htmlspecialchars($basePath ? $basePath . '/' : '/'); ?>assets/js/timeline.js"></script>
    <?php endif; ?>
    <?php if (!empty($includeCropper)): ?>
        <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <?php endif; ?>
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?php echo htmlspecialchars($basePath ? $basePath . '/' : '/'); ?>assets/js/<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

