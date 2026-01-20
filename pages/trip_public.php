<?php
/**
 * Public Trip View
 * Displays trip details for users with a share link (no authentication required)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// Define BASE_PATH if not already defined
if (!defined('BASE_PATH')) {
    $scriptPath = dirname(dirname($_SERVER['PHP_SELF']));
    $scriptPath = str_replace('\\', '/', $scriptPath);
    $scriptPath = rtrim($scriptPath, '/');
    define('BASE_PATH', $scriptPath);
}

$pageTitle = 'Shared Trip';
$showBack = false;
$showHeader = false; // Don't show the normal header for public view

// Get share token from URL
$shareToken = $_GET['token'] ?? '';

if (empty($shareToken)) {
    http_response_code(404);
    die('Invalid share link');
}

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
        // Auto-create columns silently
        try {
            $conn->exec("ALTER TABLE trips ADD COLUMN share_token VARCHAR(64) NULL UNIQUE COMMENT 'Unique token for public sharing'");
            try {
                $conn->exec("ALTER TABLE trips ADD INDEX idx_share_token (share_token)");
            } catch (PDOException $e) {
                // Index might already exist, ignore
            }
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        try {
            $conn->exec("ALTER TABLE trips ADD COLUMN is_publicly_shared TINYINT(1) DEFAULT 0 COMMENT 'Whether trip is publicly shared via share link'");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
    }
    
    // Get trip by share token
    $stmt = $conn->prepare("SELECT * FROM trips WHERE share_token = ? AND is_publicly_shared = 1");
    $stmt->execute([$shareToken]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        http_response_code(404);
        die('Trip not found or sharing is disabled');
    }
    
    $tripId = $trip['id'];
    
    // Get all travel items for this trip
    $stmt = $conn->prepare("
        SELECT ti.*, 
               COUNT(d.id) as document_count
        FROM travel_items ti
        LEFT JOIN documents d ON ti.id = d.travel_item_id
        WHERE ti.trip_id = ?
        GROUP BY ti.id
        ORDER BY ti.start_datetime ASC
    ");
    $stmt->execute([$tripId]);
    $items = $stmt->fetchAll();
    
    // Parse destinations
    $destinations = [];
    if (!empty($trip['destinations'])) {
        $destinations = json_decode($trip['destinations'], true) ?: [];
    }
    
} catch (PDOException $e) {
    error_log('Database error in trip_public.php: ' . $e->getMessage());
    http_response_code(500);
    die('An error occurred while loading the trip');
}

// Include a simplified header for public view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($trip['title']); ?> - Shared Trip</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .public-trip-header {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            color: white;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        .public-trip-header h1 {
            margin: 0;
            font-size: 1.25rem;
        }
        .public-trip-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        .public-trip-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        .public-trip-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .public-trip-info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }
        .public-trip-info-row i {
            width: 20px;
            color: var(--primary-color);
        }
        .public-trip-description {
            color: var(--text-light);
            line-height: 1.6;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .public-note {
            background: rgba(74, 144, 226, 0.1);
            border-left: 3px solid var(--primary-color);
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--text-color);
        }
        @media (max-width: 768px) {
            .public-trip-container {
                padding: 0.5rem;
            }
            .public-trip-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="public-trip-header">
        <h1><i class="fas fa-share-alt"></i> Shared Trip</h1>
    </div>
    
    <div class="public-trip-container">
        <div class="public-note">
            <i class="fas fa-info-circle"></i> This trip has been shared with you. You can view all trip details and travel items.
        </div>
        
        <div class="public-trip-card">
            <h2 style="margin-top: 0; margin-bottom: 1rem; color: var(--text-color);"><?php echo htmlspecialchars($trip['title']); ?></h2>
            
            <div class="public-trip-info">
                <div class="public-trip-info-row">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo date('M j, Y', strtotime($trip['start_date'])); ?></span>
                    <?php if ($trip['end_date']): ?>
                        <span>→</span>
                        <span><?php echo date('M j, Y', strtotime($trip['end_date'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($destinations)): ?>
                    <div class="public-trip-info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>
                            <?php 
                            $destDisplays = array_map(function($dest) {
                                $name = htmlspecialchars($dest['name'] ?? '');
                                $flag = '';
                                if (!empty($dest['flag'])) {
                                    $flag = $dest['flag'] . ' ';
                                } elseif (!empty($dest['country_code'])) {
                                    // Simple flag emoji function
                                    $countryCode = strtoupper($dest['country_code']);
                                    $flag = '';
                                    if (strlen($countryCode) === 2) {
                                        $flag = mb_convert_encoding('&#' . (127397 + ord($countryCode[0])) . ';', 'UTF-8', 'HTML-ENTITIES');
                                        $flag .= mb_convert_encoding('&#' . (127397 + ord($countryCode[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
                                        $flag .= ' ';
                                    }
                                }
                                return $flag . $name;
                            }, $destinations);
                            echo implode(' → ', $destDisplays);
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($trip['travel_type'])): ?>
                    <div class="public-trip-info-row">
                        <i class="fas fa-bookmark"></i>
                        <span><?php echo htmlspecialchars(ucfirst($trip['travel_type'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($trip['description']): ?>
                <div class="public-trip-description">
                    <?php echo nl2br(htmlspecialchars($trip['description'])); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($items)): ?>
            <div class="public-trip-card">
                <h3 style="margin-top: 0; margin-bottom: 1rem; color: var(--text-color);">Travel Items</h3>
                <div class="timeline" id="timelineContainer">
                    <?php
                    // Group items by date
                    $itemsByDate = [];
                    foreach ($items as $item) {
                        $itemDate = date('Y-m-d', strtotime($item['start_datetime']));
                        if (!isset($itemsByDate[$itemDate])) {
                            $itemsByDate[$itemDate] = [];
                        }
                        $itemsByDate[$itemDate][] = $item;
                    }
                    ?>
                    <?php foreach ($itemsByDate as $itemDate => $dateItems): ?>
                        <div class="timeline-date-group" data-date="<?php echo $itemDate; ?>">
                            <div class="timeline-date-header">
                                <div class="timeline-date-header-content">
                                    <?php echo date('l, F j, Y', strtotime($itemDate)); ?>
                                </div>
                            </div>
                            <div class="timeline-date-content">
                                <?php foreach ($dateItems as $item): ?>
                                    <div class="timeline-item <?php echo $item['type']; ?>">
                                        <div class="timeline-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                        <div class="timeline-date">
                                            <?php echo date('g:i A', strtotime($item['start_datetime'])); ?>
                                            <?php if ($item['end_datetime']): ?>
                                                → <?php echo date('g:i A', strtotime($item['end_datetime'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($item['location']): ?>
                                            <div class="timeline-details">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($item['location']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['description']): ?>
                                            <div class="timeline-description">
                                                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="public-trip-card">
                <p style="color: var(--text-light); text-align: center; margin: 0;">No travel items yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="<?php echo BASE_PATH; ?>/assets/js/main.js"></script>
</body>
</html>
