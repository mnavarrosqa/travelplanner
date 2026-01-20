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
    // Get base path dynamically from script location (reuse from header if set)
    if (!isset($basePath)) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        
        // Remove subdirectories to get base path
        $basePath = $scriptDir;
        $basePath = str_replace(['/pages', '/api', '/config', '/includes', '/install'], '', $basePath);
        
        // Normalize path separators (Windows to Unix)
        $basePath = str_replace('\\', '/', $basePath);
        
        // Clean up: ensure starts with /, remove trailing slash, remove double slashes
        $basePath = '/' . ltrim($basePath, '/');
        $basePath = rtrim($basePath, '/');
        $basePath = preg_replace('#/+#', '/', $basePath);
        
        // If still empty or just root, default to /travelplanner or detect from REQUEST_URI
        if (empty($basePath) || $basePath === '/') {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            // Extract base path from request URI (e.g., /travelplanner/pages/dashboard.php -> /travelplanner)
            if (preg_match('#^/([^/]+)#', $requestUri, $matches)) {
                $basePath = '/' . $matches[1];
            } else {
                $basePath = '/travelplanner'; // Default fallback
            }
        }
    }
    ?>
    <script src="<?php echo htmlspecialchars($basePath); ?>/assets/js/main.js"></script>
    <?php if (isset($pageTitle) && strpos($pageTitle, 'Trip') !== false): ?>
        <script src="<?php echo htmlspecialchars($basePath); ?>/assets/js/timeline.js"></script>
    <?php endif; ?>
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?php echo htmlspecialchars($basePath); ?>/assets/js/<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

