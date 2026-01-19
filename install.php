<?php
/**
 * Travel Planner Installation Wizard
 * Interactive installer for database setup
 */

// Start session for storing config content if manual copy is needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access after installation
$configDir = __DIR__ . '/config';
$configFile = $configDir . '/database.php';
$installed = false;
if (file_exists($configFile)) {
    $configContent = file_get_contents($configFile);
    // Check if database is configured (not default empty values)
    if (strpos($configContent, "DB_PASS', ''") === false && 
        strpos($configContent, "DB_NAME', 'travelplanner'") !== false) {
        $installed = true;
    }
}

$step = $_GET['step'] ?? '1';
$errors = [];
$success = false;
$permissionWarnings = [];

// Check permissions before installation (configDir and configFile already defined above)

if (!is_dir($configDir)) {
    $permissionWarnings[] = 'Config directory does not exist. It will be created during installation.';
} else {
    if (!is_writable($configDir)) {
        $permissionWarnings[] = 'Config directory is not writable. You may need to fix permissions before installation.';
    }
}

if (file_exists($configFile) && !is_writable($configFile)) {
    $permissionWarnings[] = 'Config file exists but is not writable. You may need to fix permissions before installation.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if config file already exists (manual configuration)
    $useExistingConfig = false;
    if (file_exists($configFile)) {
        require_once $configFile;
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
            $useExistingConfig = true;
            $dbHost = DB_HOST;
            $dbUser = DB_USER;
            $dbPass = DB_PASS;
            $dbName = DB_NAME;
        }
    }
    
    // If not using existing config, get from form
    if (!$useExistingConfig) {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';
        $dbName = trim($_POST['db_name'] ?? 'travelplanner');
    }
    
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminFirstName = trim($_POST['admin_first_name'] ?? '');
    $adminLastName = trim($_POST['admin_last_name'] ?? '');
    
    // Validation
    if (empty($dbHost)) {
        $errors[] = 'Database host is required';
    }
    if (empty($dbUser)) {
        $errors[] = 'Database user is required';
    }
    if (empty($dbName)) {
        $errors[] = 'Database name is required';
    }
    
    if (empty($errors)) {
        // Test database connection
        try {
            $testConn = new PDO(
                "mysql:host=$dbHost;charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create database if it doesn't exist
            $testConn->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $testConn->exec("USE `$dbName`");
            
            // Read and execute SQL file
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) {
                $errors[] = 'database.sql file not found!';
            } else {
                $sql = file_get_contents($sqlFile);
                
                // Remove CREATE DATABASE and USE statements
                $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                $sql = preg_replace('/USE.*?;/i', '', $sql);
                
                // Split and execute statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                        try {
                            $testConn->exec($statement);
                            $successCount++;
                        } catch (PDOException $e) {
                            // Ignore "table already exists" errors
                            if (strpos($e->getMessage(), 'already exists') === false) {
                                $errorCount++;
                                $errors[] = 'SQL Error: ' . $e->getMessage();
                            } else {
                                $successCount++;
                            }
                        }
                    }
                }
                
                // Run collaboration migration if exists
                $collabFile = __DIR__ . '/database_collaboration.sql';
                if (file_exists($collabFile)) {
                    $collabSql = file_get_contents($collabFile);
                    $collabSql = preg_replace('/USE.*?;/i', '', $collabSql);
                    $collabStatements = array_filter(array_map('trim', explode(';', $collabSql)));
                    
                    foreach ($collabStatements as $statement) {
                        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                            try {
                                $testConn->exec($statement);
                            } catch (PDOException $e) {
                                // Ignore errors for existing columns/tables
                            }
                        }
                    }
                }
                
                if (empty($errors)) {
                    // Update config file (only if not using existing config)
                    if (!$useExistingConfig) {
                    $configTemplate = <<<PHP
<?php
/**
 * Database Configuration
 * Generated by installer
 */

define('DB_HOST', '%s');
define('DB_USER', '%s');
define('DB_PASS', '%s');
define('DB_NAME', '%s');

/**
 * Get database connection
 * Uses singleton pattern to reuse connection
 */
function getDBConnection() {
    static \$conn = null;
    if (\$conn === null) {
        try {
            \$conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException \$e) {
            die("Database connection failed: " . \$e->getMessage());
        }
    }
    return \$conn;
}
?>
PHP;
                    
                    $configContent = sprintf(
                        $configTemplate,
                        addslashes($dbHost),
                        addslashes($dbUser),
                        addslashes($dbPass),
                        addslashes($dbName)
                    );
                    
                    // Check and create config directory if needed (configDir already defined above)
                    if (!is_dir($configDir)) {
                        if (!@mkdir($configDir, 0755, true)) {
                            $errors[] = 'Failed to create config directory: ' . $configDir . 
                                       '<br>Please create it manually or check parent directory permissions.';
                        }
                    }
                    
                    // Check if directory is writable
                    if (is_dir($configDir)) {
                        // Try to make it writable if we can
                        @chmod($configDir, 0775);
                        
                        if (!is_writable($configDir)) {
                            $phpUser = 'unknown';
                            $dirOwner = 'unknown';
                            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                                $phpUser = posix_getpwuid(posix_geteuid())['name'];
                                $dirOwner = posix_getpwuid(fileowner($configDir))['name'];
                            }
                            
                            $errorMsg = 'Config directory is not writable: ' . $configDir;
                            $errorMsg .= '<br><br><strong>Current Status:</strong><br>';
                            $errorMsg .= 'Directory owner: <strong>' . htmlspecialchars($dirOwner) . '</strong><br>';
                            $errorMsg .= 'PHP running as: <strong>' . htmlspecialchars($phpUser) . '</strong><br>';
                            
                            $perms = substr(sprintf('%o', fileperms($configDir)), -4);
                            $errorMsg .= 'Current permissions: <strong>' . $perms . '</strong>';
                            
                            $errorMsg .= '<br><br><strong>Solution - Run these commands in Terminal:</strong><br>';
                            $errorMsg .= '<code style="background: #f5f5f5; padding: 0.75rem; display: block; margin-top: 0.5rem; font-family: monospace; white-space: pre;">';
                            $errorMsg .= 'cd ' . htmlspecialchars(__DIR__) . "\n";
                            $errorMsg .= 'sudo chmod 775 config' . "\n";
                            $errorMsg .= 'sudo chmod 664 config/database.php' . "\n";
                            $errorMsg .= '# Remove extended attributes (macOS security)' . "\n";
                            $errorMsg .= 'xattr -c config/*' . "\n";
                            $errorMsg .= 'xattr -c config';
                            $errorMsg .= '</code>';
                            
                            $errors[] = $errorMsg;
                        } else {
                            // Directory is writable, check if we can actually write
                            $testFile = $configDir . '/.test_write_' . time();
                            $testWrite = @file_put_contents($testFile, 'test');
                            if ($testWrite !== false) {
                                @unlink($testFile);
                            } else {
                                $errors[] = 'Cannot write to config directory: ' . $configDir . 
                                           '<br>Directory appears writable but write test failed.<br>' .
                                           '<strong>Solution:</strong> Run this command in Terminal:<br>' .
                                           '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem;">sudo chmod -R 755 ' . $configDir . '</code>';
                            }
                        }
                    }
                    
                    // Check if file exists and is writable
                    if (file_exists($configFile) && !is_writable($configFile)) {
                        $fileOwner = 'unknown';
                        if (function_exists('posix_getpwuid')) {
                            $fileOwnerInfo = posix_getpwuid(fileowner($configFile));
                            $fileOwner = $fileOwnerInfo['name'];
                        }
                        
                        $errorMsg = 'Config file exists but is not writable: ' . $configFile;
                        $errorMsg .= '<br><br><strong>File owner:</strong> ' . htmlspecialchars($fileOwner);
                        $errorMsg .= '<br><br><strong>Solution (choose one):</strong><br><br>';
                        $errorMsg .= '<strong>Option 1:</strong> Change ownership to web server user (recommended):<br>';
                        $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem; font-family: monospace;">';
                        $errorMsg .= 'sudo chown _www ' . $configFile . '<br>';
                        $errorMsg .= 'sudo chmod 664 ' . $configFile;
                        $errorMsg .= '</code><br><br>';
                        
                        $errorMsg .= '<strong>Option 2:</strong> Make file world-writable (less secure):<br>';
                        $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem; font-family: monospace;">';
                        $errorMsg .= 'chmod 666 ' . $configFile;
                        $errorMsg .= '</code>';
                        
                        $errors[] = $errorMsg;
                    }
                    
                    // Try to write the file - attempt even if is_writable() says no
                    // Sometimes is_writable() gives false negatives on macOS with extended attributes
                    if (empty($errors) || (isset($errors) && count($errors) <= 2)) {
                        // Try to make directory and file writable
                        @chmod($configDir, 0777);
                        if (file_exists($configFile)) {
                            @chmod($configFile, 0666);
                        }
                        
                        // Suppress errors to handle them ourselves
                        $oldErrorReporting = error_reporting(E_ALL & ~E_WARNING);
                        
                        // Try multiple approaches
                        $writeResult = false;
                        
                        // Approach 1: Normal write
                        $writeResult = @file_put_contents($configFile, $configContent, LOCK_EX);
                        
                        // Approach 2: Without lock
                        if ($writeResult === false) {
                            $writeResult = @file_put_contents($configFile, $configContent);
                        }
                        
                        // Approach 3: Try creating a new file and renaming
                        if ($writeResult === false) {
                            $tempFile = $configFile . '.tmp';
                            $tempWrite = @file_put_contents($tempFile, $configContent);
                            if ($tempWrite !== false) {
                                if (@rename($tempFile, $configFile)) {
                                    $writeResult = $tempWrite;
                                } else {
                                    @unlink($tempFile);
                                }
                            }
                        }
                        
                        error_reporting($oldErrorReporting);
                        
                        if ($writeResult === false) {
                            $lastError = error_get_last();
                            $errorMsg = 'Failed to write config file: ' . $configFile;
                            
                            // Get more specific error information
                            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                                $currentUser = posix_getpwuid(posix_geteuid());
                                $fileOwner = file_exists($configFile) ? posix_getpwuid(fileowner($configFile)) : null;
                                
                                if ($fileOwner && $currentUser['name'] !== $fileOwner['name']) {
                                    $errorMsg .= '<br><br>File is owned by: <strong>' . $fileOwner['name'] . '</strong><br>';
                                    $errorMsg .= 'Current user: <strong>' . $currentUser['name'] . '</strong>';
                                }
                            }
                            
                            $errorMsg .= '<br><br><strong>Solutions (try in order):</strong><br>';
                            $errorMsg .= '1. Make the config directory writable:<br>';
                            $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem;">chmod 755 ' . $configDir . '</code><br><br>';
                            
                            if (file_exists($configFile)) {
                                $errorMsg .= '2. Make the config file writable:<br>';
                                $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem;">chmod 644 ' . $configFile . '</code><br><br>';
                            }
                            
                            $errorMsg .= '3. On macOS/XAMPP, change ownership and make writable:<br>';
                            $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem;">sudo chown -R _www ' . $configDir . '<br>chmod -R 775 ' . $configDir . '</code><br><br>';
                            
                            $errorMsg .= '4. Or change ownership to your user and make writable:<br>';
                            $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem;">sudo chown -R $USER ' . $configDir . '<br>chmod -R 775 ' . $configDir . '</code><br><br>';
                            
                            $errorMsg .= '5. <strong>Alternative:</strong> Manually create/edit the file:<br>';
                            $errorMsg .= '<code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem; white-space: pre-wrap; font-size: 0.85rem;">';
                            $errorMsg .= htmlspecialchars('<?php
define(\'DB_HOST\', \'' . addslashes($dbHost) . '\');
define(\'DB_USER\', \'' . addslashes($dbUser) . '\');
define(\'DB_PASS\', \'' . addslashes($dbPass) . '\');
define(\'DB_NAME\', \'' . addslashes($dbName) . '\');

function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $conn;
}
?>');
                            $errorMsg .= '</code>';
                            
                            if ($lastError && isset($lastError['message'])) {
                                $errorMsg .= '<br><br><strong>System Error:</strong> ' . htmlspecialchars($lastError['message']);
                            }
                            
                            $errors[] = $errorMsg;
                            
                            // Store config content in session for manual copy option
                            $_SESSION['installer_config_content'] = $configContent;
                            $_SESSION['installer_config_file'] = $configFile;
                        } else {
                            // Successfully wrote the file, set proper permissions
                            @chmod($configFile, 0644);
                        }
                    }
                    } // End of if (!$useExistingConfig)
                    
                    if (empty($errors)) {
                        // Create admin user if provided
                        if (!empty($adminEmail) && !empty($adminPassword)) {
                            require_once $configFile;
                            $conn = getDBConnection();
                            
                            // Check if user already exists
                            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                            $stmt->execute([$adminEmail]);
                            
                            if (!$stmt->fetch()) {
                                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                                $stmt = $conn->prepare("
                                    INSERT INTO users (email, password_hash, first_name, last_name) 
                                    VALUES (?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $adminEmail,
                                    $passwordHash,
                                    $adminFirstName ?: null,
                                    $adminLastName ?: null
                                ]);
                            }
                        }
                        
                        $success = true;
                        $step = 'complete';
                        
                        // Clear session data
                        unset($_SESSION['installer_config_content']);
                        unset($_SESSION['installer_config_file']);
                        unset($_SESSION['installer_admin_email']);
                        unset($_SESSION['installer_admin_password']);
                        unset($_SESSION['installer_admin_first_name']);
                        unset($_SESSION['installer_admin_last_name']);
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Planner - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 2rem;
        }
        h1 {
            color: #4A90E2;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #E1E8ED;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #4A90E2;
        }
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #4A90E2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #357ABD;
        }
        .btn-secondary {
            background: #6C757D;
            margin-top: 0.5rem;
        }
        .btn-secondary:hover {
            background: #5A6268;
        }
        .error {
            background: #FFEBEE;
            color: #C62828;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .error code {
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 0.9rem;
            background: #f5f5f5;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
            margin: 0.25rem 0;
        }
        .success {
            background: #E8F5E9;
            color: #2E7D32;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .success-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #E1E8ED;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 0.5rem;
        }
        .step.active {
            background: #4A90E2;
            color: white;
        }
        .step.complete {
            background: #50C878;
            color: white;
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <h1>✈️ Travel Planner</h1>
        <p class="subtitle">Installation Wizard</p>
        
        <?php 
        // Check if config file exists and has valid content (manual installation)
        $manualComplete = false;
        $adminEmail = '';
        
        if (isset($_GET['manual']) && $_GET['manual'] == '1') {
            if (file_exists($configFile)) {
                // Load config to get database credentials
                require_once $configFile;
                
                // Check if database constants are defined
                if (defined('DB_HOST') && defined('DB_NAME')) {
                    try {
                        // Connect to database
                        $conn = getDBConnection();
                        
                        // Check if tables exist
                        $tablesCheck = $conn->query("SHOW TABLES")->fetchAll();
                        
                        if (empty($tablesCheck)) {
                            // Database is empty, run setup
                            $sqlFile = __DIR__ . '/database.sql';
                            if (file_exists($sqlFile)) {
                                $sql = file_get_contents($sqlFile);
                                
                                // Remove CREATE DATABASE and USE statements
                                $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                                $sql = preg_replace('/USE.*?;/i', '', $sql);
                                
                                // Split and execute statements
                                $statements = array_filter(array_map('trim', explode(';', $sql)));
                                
                                foreach ($statements as $statement) {
                                    if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                                        try {
                                            $conn->exec($statement);
                                        } catch (PDOException $e) {
                                            if (strpos($e->getMessage(), 'already exists') === false) {
                                                $errors[] = 'SQL Error: ' . $e->getMessage();
                                            }
                                        }
                                    }
                                }
                                
                                // Run collaboration migration if exists
                                $collabFile = __DIR__ . '/database_collaboration.sql';
                                if (file_exists($collabFile)) {
                                    $collabSql = file_get_contents($collabFile);
                                    $collabSql = preg_replace('/USE.*?;/i', '', $collabSql);
                                    $collabStatements = array_filter(array_map('trim', explode(';', $collabSql)));
                                    
                                    foreach ($collabStatements as $statement) {
                                        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                                            try {
                                                $conn->exec($statement);
                                            } catch (PDOException $e) {
                                                // Ignore errors for existing columns/tables
                                            }
                                        }
                                    }
                                }
                                
                                // Check if admin user was provided in session
                                if (isset($_SESSION['installer_admin_email']) && isset($_SESSION['installer_admin_password'])) {
                                    $adminEmail = $_SESSION['installer_admin_email'];
                                    $adminPassword = $_SESSION['installer_admin_password'];
                                    $adminFirstName = $_SESSION['installer_admin_first_name'] ?? '';
                                    $adminLastName = $_SESSION['installer_admin_last_name'] ?? '';
                                    
                                    // Check if user already exists
                                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                                    $stmt->execute([$adminEmail]);
                                    
                                    if (!$stmt->fetch()) {
                                        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                                        $stmt = $conn->prepare("
                                            INSERT INTO users (email, password_hash, first_name, last_name) 
                                            VALUES (?, ?, ?, ?)
                                        ");
                                        $stmt->execute([
                                            $adminEmail,
                                            $passwordHash,
                                            $adminFirstName ?: null,
                                            $adminLastName ?: null
                                        ]);
                                    }
                                    
                                    unset($_SESSION['installer_admin_email']);
                                    unset($_SESSION['installer_admin_password']);
                                    unset($_SESSION['installer_admin_first_name']);
                                    unset($_SESSION['installer_admin_last_name']);
                                }
                                
                                $manualComplete = true;
                                $success = true;
                                unset($_SESSION['installer_config_content']);
                                unset($_SESSION['installer_config_file']);
                            } else {
                                $errors[] = 'database.sql file not found!';
                            }
                        } else {
                            // Tables already exist
                            $manualComplete = true;
                            $success = true;
                        }
                    } catch (PDOException $e) {
                        $errors[] = 'Database connection failed: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Config file exists but database constants are not properly defined.';
                }
            } else {
                $errors[] = 'Config file not found. Please configure it first.';
            }
        }
        ?>
        
        <?php if (($step === 'complete' && $success) || $manualComplete): ?>
            <?php if ($manualComplete): ?>
                <div style="background: #FFF3CD; color: #856404; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #FFE69C;">
                    <strong>⚠️ Manual Configuration Detected</strong><br>
                    Config file was manually updated. Installation will continue...
                </div>
            <?php endif; ?>
            <div class="success">
                <div class="success-icon">✓</div>
                <h2>Installation Complete!</h2>
                <p style="margin-top: 1rem;">Your Travel Planner application has been successfully installed.</p>
                <?php if (!empty($adminEmail)): ?>
                    <p style="margin-top: 0.5rem;"><strong>Admin Account Created:</strong><br>
                    Email: <?php echo htmlspecialchars($adminEmail); ?></p>
                <?php endif; ?>
                <p style="margin-top: 1rem;">
                    <strong>Important:</strong> For security, please delete or rename this install.php file.
                </p>
                <a href="index.php" class="btn" style="margin-top: 1rem; text-decoration: none; display: block; text-align: center;">
                    Go to Application
                </a>
            </div>
        <?php else: ?>
            <div class="step-indicator">
                <div class="step <?php echo $step >= '1' ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step >= '2' ? 'active' : ''; ?>">2</div>
            </div>
            
            <?php if (!empty($permissionWarnings)): ?>
                <div class="error" style="background: #FFF3CD; color: #856404; border: 1px solid #FFE69C;">
                    <strong>⚠️ Permission Warning:</strong>
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                        <?php foreach ($permissionWarnings as $warning): ?>
                            <li><?php echo $warning; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top: 0.75rem; font-size: 0.9rem;">
                        <strong>Quick Fix:</strong> Open Terminal and run:<br>
                        <code style="background: #f5f5f5; padding: 0.5rem; display: block; margin-top: 0.5rem; font-size: 0.85rem;">
                            cd <?php echo htmlspecialchars(__DIR__); ?><br>
                            chmod -R 755 config<br>
                            <?php if (file_exists($configFile)): ?>
                            chmod 644 config/database.php
                            <?php endif; ?>
                        </code>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <strong>Errors:</strong>
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if (isset($_SESSION['installer_config_content'])): ?>
                        <div style="margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                            <strong>Manual Configuration Option:</strong>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                                If you cannot fix the permissions, you can manually create the config file. 
                                Copy the content below and save it to: <code><?php echo htmlspecialchars($configFile); ?></code>
                            </p>
                            <textarea readonly style="width: 100%; min-height: 200px; font-family: monospace; font-size: 0.85rem; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; margin-top: 0.75rem;" onclick="this.select();"><?php echo htmlspecialchars($_SESSION['installer_config_content']); ?></textarea>
                            <p style="margin-top: 0.75rem; font-size: 0.85rem; color: #666;">
                                After saving the file, <a href="install.php?step=complete&manual=1" style="color: #4A90E2;">click here to continue</a>.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="install.php">
                <h2 style="margin-bottom: 1rem; color: #333;">Database Configuration</h2>
                
                <div class="form-group">
                    <label for="db_host">Database Host *</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                    <div class="help-text">Usually 'localhost' for XAMPP</div>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username *</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                    <div class="help-text">Default XAMPP username is 'root'</div>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    <div class="help-text">Leave empty if no password is set (default XAMPP)</div>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name *</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'travelplanner'); ?>" required>
                    <div class="help-text">Database will be created if it doesn't exist</div>
                </div>
                
                <hr style="margin: 2rem 0; border: none; border-top: 1px solid #E1E8ED;">
                
                <h2 style="margin-bottom: 1rem; color: #333;">Admin Account (Optional)</h2>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                    Create an admin account now, or register later from the application.
                </p>
                
                <div class="form-group">
                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" minlength="6">
                    <div class="help-text">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="admin_first_name">First Name</label>
                    <input type="text" id="admin_first_name" name="admin_first_name" value="<?php echo htmlspecialchars($_POST['admin_first_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="admin_last_name">Last Name</label>
                    <input type="text" id="admin_last_name" name="admin_last_name" value="<?php echo htmlspecialchars($_POST['admin_last_name'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn">Install Now</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
