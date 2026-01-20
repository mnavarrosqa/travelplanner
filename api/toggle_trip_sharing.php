<?php
/**
 * Toggle Trip Public Sharing
 * Enables/disables public sharing and generates/removes share token
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tripId = $_POST['trip_id'] ?? 0;
$enable = isset($_POST['enable']) && $_POST['enable'] === '1';

if (empty($tripId)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID is required']);
    exit;
}

$userId = getCurrentUserId();
$conn = getDBConnection();

try {
    // Auto-migrate: Check if sharing columns exist, create them if not
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'is_publicly_shared'");
        $hasSharingColumns = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        $hasSharingColumns = false;
    }
    
    if (!$hasSharingColumns) {
        // Auto-create columns
        try {
            // Add share_token column
            $conn->exec("ALTER TABLE trips ADD COLUMN share_token VARCHAR(64) NULL UNIQUE COMMENT 'Unique token for public sharing'");
            // Add index for share_token
            try {
                $conn->exec("ALTER TABLE trips ADD INDEX idx_share_token (share_token)");
            } catch (PDOException $e) {
                // Index might already exist, ignore
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                error_log('Error adding share_token column: ' . $e->getMessage());
            }
        }
        
        try {
            // Add is_publicly_shared column
            $conn->exec("ALTER TABLE trips ADD COLUMN is_publicly_shared TINYINT(1) DEFAULT 0 COMMENT 'Whether trip is publicly shared via share link'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                error_log('Error adding is_publicly_shared column: ' . $e->getMessage());
            }
        }
        
        $hasSharingColumns = true; // Assume success after attempting to create
    }
    
    // Check if user is the owner of the trip
    if (!isTripOwner($tripId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'Only trip owners can enable/disable sharing']);
        exit;
    }
    
    // Get trip details
    $stmt = $conn->prepare("SELECT id, share_token, is_publicly_shared FROM trips WHERE id = ?");
    $stmt->execute([$tripId]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        echo json_encode(['success' => false, 'message' => 'Trip not found']);
        exit;
    }
    
    if ($enable) {
        // Enable sharing - generate token if it doesn't exist
        if (empty($trip['share_token'])) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32)); // 64 character hex string
            
            // Ensure token is unique
            $stmt = $conn->prepare("SELECT id FROM trips WHERE share_token = ?");
            $stmt->execute([$token]);
            while ($stmt->fetch()) {
                $token = bin2hex(random_bytes(32)); // Regenerate if collision
                $stmt->execute([$token]);
            }
            
            // Update trip with token and enable sharing
            $stmt = $conn->prepare("UPDATE trips SET share_token = ?, is_publicly_shared = 1 WHERE id = ?");
            $stmt->execute([$token, $tripId]);
        } else {
            // Token exists, just enable sharing
            $stmt = $conn->prepare("UPDATE trips SET is_publicly_shared = 1 WHERE id = ?");
            $stmt->execute([$tripId]);
            $token = $trip['share_token'];
        }
        
        // Get base URL for share link
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname(dirname($_SERVER['PHP_SELF']));
        $shareUrl = $protocol . $host . $basePath . '/pages/trip_public.php?token=' . $token;
        
        echo json_encode([
            'success' => true,
            'message' => 'Sharing enabled successfully',
            'share_token' => $token,
            'share_url' => $shareUrl,
            'is_publicly_shared' => true
        ]);
    } else {
        // Disable sharing (but keep token for potential re-enabling)
        $stmt = $conn->prepare("UPDATE trips SET is_publicly_shared = 0 WHERE id = ?");
        $stmt->execute([$tripId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sharing disabled successfully',
            'is_publicly_shared' => false
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Database error in toggle_trip_sharing.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
