<?php
/**
 * Update Trip API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$tripId = $_POST['trip_id'] ?? 0;
$title = trim($_POST['title'] ?? '');
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? null;
$description = $_POST['description'] ?? null;
$travelType = $_POST['travel_type'] ?? null;
$isMultipleDestinations = isset($_POST['is_multiple_destinations']) ? (int)$_POST['is_multiple_destinations'] : 0;
$destinations = $_POST['destinations'] ?? null;
$status = $_POST['status'] ?? 'active';
$removeCoverImage = isset($_POST['remove_cover_image']) && (int)$_POST['remove_cover_image'] === 1;

// Validation
if (empty($tripId) || empty($title) || empty($startDate)) {
    echo json_encode(['success' => false, 'message' => 'Trip ID, title and start date are required']);
    exit;
}

// Validate title length
if (strlen($title) > 255) {
    echo json_encode(['success' => false, 'message' => 'Title must be 255 characters or less']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid start date format']);
    exit;
}

// Validate end date if provided
if ($endDate) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid end date format']);
        exit;
    }
    if ($endDate < $startDate) {
        echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
        exit;
    }
}

try {
    $conn = getDBConnection();
    
    // Check permissions
    if (!canEditTrip($tripId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this trip']);
        exit;
    }

    // Auto-migrate: ensure cover_image column exists (for older installs)
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM trips LIKE 'cover_image'");
        if ($checkStmt->rowCount() == 0) {
            $conn->exec("ALTER TABLE trips ADD COLUMN cover_image VARCHAR(2048) NULL COMMENT 'Cover image URL for trip header/cards'");
        }
    } catch (PDOException $e) {
        // Ignore migration errors (duplicate column, permissions, etc.)
    }

    // Load current cover image path (so we can keep/delete it safely)
    $oldCover = null;
    try {
        $stmt = $conn->prepare("SELECT cover_image FROM trips WHERE id = ?");
        $stmt->execute([$tripId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldCover = $row['cover_image'] ?? null;
    } catch (PDOException $e) {
        $oldCover = null;
    }

    // Handle cover image upload (optional)
    $newCoverRelativePath = null;
    $uploadedCoverAbsolutePath = null;
    if (isset($_FILES['cover_image']) && ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['cover_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Cover image upload error']);
            exit;
        }

        if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'message' => 'Cover image is too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB']);
            exit;
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid cover image type. Allowed: JPG, PNG, GIF, WEBP']);
            exit;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid cover image file']);
            exit;
        }

        $coverDir = rtrim(UPLOAD_DIR, "/\\") . DIRECTORY_SEPARATOR . 'trip_covers' . DIRECTORY_SEPARATOR;
        if (!file_exists($coverDir)) {
            mkdir($coverDir, 0755, true);
        }

        $filename = uniqid() . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
        $coverDirReal = realpath($coverDir);
        if ($coverDirReal === false) {
            echo json_encode(['success' => false, 'message' => 'Server error preparing upload directory']);
            exit;
        }

        $destination = $coverDirReal . DIRECTORY_SEPARATOR . basename($filename);
        if (strpos($destination, $coverDirReal) !== 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid file path']);
            exit;
        }

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save cover image']);
            exit;
        }

        $uploadedCoverAbsolutePath = $destination;
        $newCoverRelativePath = 'uploads/trip_covers/' . $filename;
    }

    // Decide final cover value
    $finalCover = $oldCover;
    if ($removeCoverImage) {
        $finalCover = null;
    }
    if (!empty($newCoverRelativePath)) {
        $finalCover = $newCoverRelativePath;
    }
    
    // Validate and parse destinations
    $destinationsJson = null;
    if ($destinations) {
        $destinationsArray = json_decode($destinations, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($destinationsArray)) {
            // Filter out empty destinations
            $destinationsArray = array_filter($destinationsArray, function($dest) {
                return !empty($dest['name']);
            });
            if (!empty($destinationsArray)) {
                $destinationsJson = json_encode(array_values($destinationsArray));
            }
        }
    }
    
    // Validate travel type
    $validTravelTypes = ['vacations', 'work', 'business', 'family', 'leisure', 'adventure', 'romantic', 'other'];
    if ($travelType && !in_array($travelType, $validTravelTypes)) {
        $travelType = null;
    }
    
    // Validate status
    $validStatuses = ['active', 'completed', 'archived'];
    if (!in_array($status, $validStatuses)) {
        $status = 'active';
    }
    
    $stmt = $conn->prepare("
        UPDATE trips 
        SET title = ?, start_date = ?, end_date = ?, description = ?, cover_image = ?, travel_type = ?, is_multiple_destinations = ?, destinations = ?, status = ?, modified_by = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $title, 
        $startDate, 
        $endDate ?: null, 
        $description ?: null,
        $finalCover,
        $travelType ?: null,
        $isMultipleDestinations,
        $destinationsJson,
        $status,
        $userId, 
        $tripId
    ]);

    // Cleanup old cover file if it was replaced or removed (only within uploads/trip_covers)
    $shouldDeleteOld = ($removeCoverImage && !empty($oldCover)) || (!empty($newCoverRelativePath) && !empty($oldCover) && $oldCover !== $newCoverRelativePath);
    if ($shouldDeleteOld && is_string($oldCover) && strpos($oldCover, 'uploads/trip_covers/') === 0) {
        $oldAbsolute = rtrim(UPLOAD_DIR, "/\\") . DIRECTORY_SEPARATOR . 'trip_covers' . DIRECTORY_SEPARATOR . basename($oldCover);
        $oldDirReal = realpath(rtrim(UPLOAD_DIR, "/\\") . DIRECTORY_SEPARATOR . 'trip_covers');
        $oldAbsReal = realpath($oldAbsolute);
        if ($oldDirReal !== false && $oldAbsReal !== false && strpos($oldAbsReal, $oldDirReal) === 0 && file_exists($oldAbsReal)) {
            @unlink($oldAbsReal);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Trip updated successfully'
    ]);
} catch (PDOException $e) {
    // Delete uploaded cover file if DB update fails
    if (!empty($uploadedCoverAbsolutePath) && file_exists($uploadedCoverAbsolutePath)) {
        @unlink($uploadedCoverAbsolutePath);
    }
    error_log('Database error in update_trip.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

