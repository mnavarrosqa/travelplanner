<?php
/**
 * Travel Planner Installation Wizard
 * Clean, modern installer for easy deployment
 */

// Prevent direct access after installation
$configFile = __DIR__ . '/../config/database.php';
$installed = false;
if (file_exists($configFile)) {
    $configContent = file_get_contents($configFile);
    // Check if database is configured (not default empty values)
    // Verify all required constants are set and not empty
    if (strpos($configContent, "define('DB_HOST'") !== false && 
        strpos($configContent, "define('DB_USER'") !== false && 
        strpos($configContent, "define('DB_NAME'") !== false) {
        // Verify it's not just empty placeholders
        if (strpos($configContent, "DB_HOST', ''") === false && 
            strpos($configContent, "DB_USER', ''") === false && 
            strpos($configContent, "DB_NAME', ''") === false) {
            $installed = true;
        }
    }
}

$step = isset($_GET['step']) ? $_GET['step'] : '1';
$errors = [];
$success = false;
$warnings = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim(isset($_POST['db_host']) ? $_POST['db_host'] : '');
    $dbUser = trim(isset($_POST['db_user']) ? $_POST['db_user'] : '');
    $dbPass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
    $dbName = trim(isset($_POST['db_name']) ? $_POST['db_name'] : '');
    
    $adminEmail = trim(isset($_POST['admin_email']) ? $_POST['admin_email'] : '');
    $adminPassword = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
    $adminFirstName = trim(isset($_POST['admin_first_name']) ? $_POST['admin_first_name'] : '');
    $adminLastName = trim(isset($_POST['admin_last_name']) ? $_POST['admin_last_name'] : '');
    
    // API Keys (optional)
    $aerodataboxKey = trim(isset($_POST['aerodatabox_api_key']) ? $_POST['aerodatabox_api_key'] : '');
    $aviationStackKey = trim(isset($_POST['aviationstack_api_key']) ? $_POST['aviationstack_api_key'] : '');
    
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
    
    if (!empty($adminEmail) && empty($adminPassword)) {
        $errors[] = 'Admin password is required if email is provided';
    }
    
    if (!empty($adminEmail) && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid admin email address';
    }
    
    if (!empty($adminPassword) && strlen($adminPassword) < 6) {
        $errors[] = 'Admin password must be at least 6 characters';
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
            
            // Read and execute SQL files
            $sqlFiles = [
                __DIR__ . '/../database.sql',
                __DIR__ . '/../database_collaboration.sql',
                __DIR__ . '/../database_flight_fields.sql',
                __DIR__ . '/../database_trip_fields.sql'
            ];
            
            foreach ($sqlFiles as $sqlFile) {
                if (!file_exists($sqlFile)) {
                    $warnings[] = 'SQL file not found: ' . basename($sqlFile);
                    continue;
                }
                
                $sql = file_get_contents($sqlFile);
                
                // Remove CREATE DATABASE and USE statements
                $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                $sql = preg_replace('/USE.*?;/i', '', $sql);
                
                // Remove comments (both -- and /* */ style)
                $sql = preg_replace('/--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                
                // Split by semicolon, but handle multi-line statements
                $statements = [];
                $currentStatement = '';
                $lines = explode("\n", $sql);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    
                    $currentStatement .= $line . "\n";
                    
                    // Check if line ends with semicolon (not in a string)
                    if (substr(rtrim($line), -1) === ';') {
                        $stmt = trim($currentStatement);
                        if (!empty($stmt) && strlen($stmt) > 5) { // Minimum meaningful statement
                            $statements[] = $stmt;
                        }
                        $currentStatement = '';
                    }
                }
                
                // Execute each statement
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || strlen($statement) < 5) {
                        continue;
                    }
                    
                    try {
                        $testConn->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore "table already exists" and similar errors
                        $errorMsg = $e->getMessage();
                        if (strpos($errorMsg, 'already exists') === false &&
                            strpos($errorMsg, 'Duplicate column') === false &&
                            strpos($errorMsg, 'Duplicate key') === false &&
                            strpos($errorMsg, 'Duplicate entry') === false) {
                            // Only add error if it's not a harmless duplicate error
                            $errors[] = 'SQL Error in ' . basename($sqlFile) . ': ' . $errorMsg;
                        }
                    }
                }
            }
            
            if (empty($errors)) {
                // Create config directory if needed
                $configDir = __DIR__ . '/../config';
                if (!is_dir($configDir)) {
                    if (!@mkdir($configDir, 0755, true)) {
                        $errors[] = 'Failed to create config directory';
                    }
                }
                
                // Write config file
                if (empty($errors)) {
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
                    
                    if (@file_put_contents($configFile, $configContent) === false) {
                        $errors[] = 'Failed to write config file. Please check directory permissions.';
                    } else {
                        @chmod($configFile, 0644);
                        
                        // Write constants.php with API keys
                        $constantsFile = __DIR__ . '/../config/constants.php';
                        $constantsTemplate = <<<PHP
<?php
/**
 * Application Constants
 */

// Application settings
define('APP_NAME', 'Travel Planner');
define('APP_VERSION', '1.0.0');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf'
]);

// Session settings
define('SESSION_LIFETIME', 86400); // 24 hours

// Date/time formats
define('DATE_FORMAT', 'M d, Y');
define('DATETIME_FORMAT', 'M d, Y g:i A');
define('DATETIME_FORMAT_INPUT', 'Y-m-d\TH:i');

// Travel item types
define('TRAVEL_ITEM_TYPES', [
    'flight' => 'Flight',
    'train' => 'Train',
    'bus' => 'Bus',
    'hotel' => 'Hotel',
    'car_rental' => 'Car Rental',
    'activity' => 'Activity',
    'other' => 'Other'
]);

// Flight API Configuration

// AviationStack API (100 requests/month free, but only real-time flights)
// Get a free API key from https://aviationstack.com/
define('AVIATION_STACK_API_KEY', '%s');

// AeroDataBox API (300-600 requests/month free, supports schedules)
// Get a free API key from https://rapidapi.com/aerodatabox/api/aerodatabox/ or https://aerodatabox.com/
define('AERODATABOX_API_KEY', '%s');

// Alternative: Aviation Edge (free tier available)
// Get a free API key from https://aviation-edge.com/
// define('AVIATION_EDGE_API_KEY', 'your_aviation_edge_api_key_here');
?>
PHP;
                        
                        $constantsContent = sprintf(
                            $constantsTemplate,
                            addslashes($aviationStackKey),
                            addslashes($aerodataboxKey)
                        );
                        
                        @file_put_contents($constantsFile, $constantsContent);
                        @chmod($constantsFile, 0644);
                        
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
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .installer-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 2.5rem;
            animation: slideUp 0.4s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            color: #4A90E2;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            right: 50%;
            height: 2px;
            background: #E1E8ED;
            z-index: 0;
            transform: translateX(-50%);
            width: calc(100% - 80px);
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
            position: relative;
            z-index: 1;
            transition: all 0.3s;
        }
        
        .step.active {
            background: #4A90E2;
            color: white;
            transform: scale(1.1);
        }
        
        .step.complete {
            background: #50C878;
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #E1E8ED;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        input:focus {
            outline: none;
            border-color: #4A90E2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.375rem;
        }
        
        .help-text a {
            color: #4A90E2;
            text-decoration: none;
        }
        
        .help-text a:hover {
            text-decoration: underline;
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            background: #4A90E2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .btn:hover {
            background: #357ABD;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #FFCDD2;
        }
        
        .alert-warning {
            background: #FFF3CD;
            color: #856404;
            border: 1px solid #FFE69C;
        }
        
        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
            text-align: center;
        }
        
        .alert ul {
            margin-top: 0.5rem;
            margin-left: 1.5rem;
        }
        
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .divider {
            height: 1px;
            background: #E1E8ED;
            margin: 2rem 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .optional-badge {
            display: inline-block;
            background: #E1E8ED;
            color: #666;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        
        .link {
            color: #4A90E2;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #4A90E2;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .link:hover {
            background: #4A90E2;
            color: white;
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="header">
            <h1>✈️ Travel Planner</h1>
            <p class="subtitle">Installation Wizard</p>
        </div>
        
        <?php if ($step === 'complete' && $success): ?>
            <div class="alert alert-success">
                <div class="success-icon">✓</div>
                <h2 style="margin-bottom: 1rem;">Installation Complete!</h2>
                <p>Your Travel Planner application has been successfully installed.</p>
                <?php if (!empty($adminEmail)): ?>
                    <p style="margin-top: 1rem;"><strong>Admin Account Created:</strong><br>
                    Email: <?php echo htmlspecialchars($adminEmail); ?></p>
                <?php endif; ?>
                <p style="margin-top: 1.5rem; font-size: 0.9rem; color: #666;">
                    <strong>Security Note:</strong> For security, you may want to delete or rename the <code>install</code> folder after installation.
                </p>
                <a href="../index.php" class="link" style="margin-top: 1.5rem; text-align: center; display: block;">
                    Go to Application →
                </a>
            </div>
        <?php else: ?>
            <div class="step-indicator">
                <div class="step <?php echo $step >= '1' ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step >= '2' ? 'active' : ''; ?>">2</div>
            </div>
            
            <?php if (!empty($warnings)): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Warnings:</strong>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
                <h2 class="section-title">Database Configuration</h2>
                
                <div class="form-group">
                    <label for="db_host">Database Host *</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars(isset($_POST['db_host']) ? $_POST['db_host'] : ''); ?>" placeholder="localhost" required>
                    <div class="help-text">Usually 'localhost' for local development, or your database server hostname</div>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username *</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars(isset($_POST['db_user']) ? $_POST['db_user'] : ''); ?>" placeholder="Enter database username" required>
                    <div class="help-text">Your MySQL/MariaDB username</div>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars(isset($_POST['db_pass']) ? $_POST['db_pass'] : ''); ?>" placeholder="Enter database password">
                    <div class="help-text">Leave empty if no password is set for your database user</div>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name *</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars(isset($_POST['db_name']) ? $_POST['db_name'] : ''); ?>" placeholder="travelplanner" required>
                    <div class="help-text">Database will be created if it doesn't exist</div>
                </div>
                
                <div class="divider"></div>
                
                <h2 class="section-title">
                    Admin Account
                    <span class="optional-badge">Optional</span>
                </h2>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                    Create an admin account now, or register later from the application.
                </p>
                
                <div class="form-group">
                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars(isset($_POST['admin_email']) ? $_POST['admin_email'] : ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" minlength="6">
                    <div class="help-text">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="admin_first_name">First Name</label>
                    <input type="text" id="admin_first_name" name="admin_first_name" value="<?php echo htmlspecialchars(isset($_POST['admin_first_name']) ? $_POST['admin_first_name'] : ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="admin_last_name">Last Name</label>
                    <input type="text" id="admin_last_name" name="admin_last_name" value="<?php echo htmlspecialchars(isset($_POST['admin_last_name']) ? $_POST['admin_last_name'] : ''); ?>">
                </div>
                
                <div class="divider"></div>
                
                <h2 class="section-title">
                    Flight API Keys
                    <span class="optional-badge">Optional</span>
                </h2>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                    Configure flight lookup APIs. Leave empty if you don't need flight information lookup.
                </p>
                
                <div class="form-group">
                    <label for="aerodatabox_api_key">AeroDataBox API Key</label>
                    <input type="text" id="aerodatabox_api_key" name="aerodatabox_api_key" value="<?php echo htmlspecialchars(isset($_POST['aerodatabox_api_key']) ? $_POST['aerodatabox_api_key'] : ''); ?>" placeholder="Your RapidAPI key">
                    <div class="help-text">
                        Get free API key from <a href="https://rapidapi.com/aerodatabox/api/aerodatabox/" target="_blank" style="color: #4A90E2;">RapidAPI</a> 
                        (300-600 requests/month free, supports scheduled flights)
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="aviationstack_api_key">AviationStack API Key</label>
                    <input type="text" id="aviationstack_api_key" name="aviationstack_api_key" value="<?php echo htmlspecialchars(isset($_POST['aviationstack_api_key']) ? $_POST['aviationstack_api_key'] : ''); ?>" placeholder="Your AviationStack key">
                    <div class="help-text">
                        Get free API key from <a href="https://aviationstack.com/" target="_blank" style="color: #4A90E2;">AviationStack</a> 
                        (100 requests/month free, real-time flights only)
                    </div>
                </div>
                
                <button type="submit" class="btn">Install Now</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
