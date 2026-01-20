<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
?>
<!-- Custom Modal Dialogs -->
<div id="customModal" class="custom-modal" style="display: none;">
    <div class="custom-modal-overlay"></div>
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h3 class="custom-modal-title" id="modalTitle"></h3>
            <button type="button" class="custom-modal-close" id="modalCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="custom-modal-body" id="modalBody"></div>
        <div class="custom-modal-footer" id="modalFooter"></div>
    </div>
</div>
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../includes/permissions.php';

$tripId = $_GET['id'] ?? 0;
$conn = getDBConnection();
$userId = getCurrentUserId();

// Helper function to get timezone from airport code
function getAirportTimezone($iata, $icao, $countryCode) {
    // Common airport timezone mapping (IATA code -> IANA timezone)
    $timezoneMap = [
        // Major US airports
        'JFK' => 'America/New_York', 'LGA' => 'America/New_York', 'EWR' => 'America/New_York',
        'LAX' => 'America/Los_Angeles', 'SFO' => 'America/Los_Angeles', 'SAN' => 'America/Los_Angeles',
        'ORD' => 'America/Chicago', 'MDW' => 'America/Chicago',
        'DFW' => 'America/Chicago', 'IAH' => 'America/Chicago',
        'MIA' => 'America/New_York', 'ATL' => 'America/New_York',
        'SEA' => 'America/Los_Angeles', 'DEN' => 'America/Denver',
        'PHX' => 'America/Phoenix', 'LAS' => 'America/Los_Angeles',
        // Major European airports
        'LHR' => 'Europe/London', 'LGW' => 'Europe/London', 'STN' => 'Europe/London',
        'CDG' => 'Europe/Paris', 'ORY' => 'Europe/Paris',
        'FRA' => 'Europe/Berlin', 'MUC' => 'Europe/Berlin',
        'AMS' => 'Europe/Amsterdam', 'FCO' => 'Europe/Rome',
        'MAD' => 'Europe/Madrid', 'BCN' => 'Europe/Madrid',
        'ZUR' => 'Europe/Zurich', 'VIE' => 'Europe/Vienna',
        'CPH' => 'Europe/Copenhagen', 'ARN' => 'Europe/Stockholm',
        'OSL' => 'Europe/Oslo', 'HEL' => 'Europe/Helsinki',
        // Major Asian airports
        'NRT' => 'Asia/Tokyo', 'HND' => 'Asia/Tokyo',
        'PEK' => 'Asia/Shanghai', 'PVG' => 'Asia/Shanghai',
        'HKG' => 'Asia/Hong_Kong', 'SIN' => 'Asia/Singapore',
        'BKK' => 'Asia/Bangkok', 'DXB' => 'Asia/Dubai',
        'AUH' => 'Asia/Dubai', 'DOH' => 'Asia/Qatar',
        'ICN' => 'Asia/Seoul', 'TPE' => 'Asia/Taipei',
        // Major Middle East
        'TLV' => 'Asia/Jerusalem', 'CAI' => 'Africa/Cairo',
        'JED' => 'Asia/Riyadh', 'RUH' => 'Asia/Riyadh',
        // Major Australian airports
        'SYD' => 'Australia/Sydney', 'MEL' => 'Australia/Melbourne',
        'BNE' => 'Australia/Brisbane', 'PER' => 'Australia/Perth',
        // Major South American airports
        'GRU' => 'America/Sao_Paulo', 'GIG' => 'America/Sao_Paulo',
        'EZE' => 'America/Argentina/Buenos_Aires', 'SCL' => 'America/Santiago',
        'LIM' => 'America/Lima', 'BOG' => 'America/Bogota',
        // Major Canadian airports
        'YYZ' => 'America/Toronto', 'YVR' => 'America/Vancouver',
        'YUL' => 'America/Montreal', 'YYC' => 'America/Edmonton',
    ];
    
    // Try IATA first
    if ($iata && isset($timezoneMap[$iata])) {
        return $timezoneMap[$iata];
    }
    
    // Fallback: estimate from country code (less accurate)
    $countryTimezoneMap = [
        'US' => 'America/New_York', 'CA' => 'America/Toronto',
        'GB' => 'Europe/London', 'FR' => 'Europe/Paris',
        'DE' => 'Europe/Berlin', 'IT' => 'Europe/Rome',
        'ES' => 'Europe/Madrid', 'NL' => 'Europe/Amsterdam',
        'JP' => 'Asia/Tokyo', 'CN' => 'Asia/Shanghai',
        'AU' => 'Australia/Sydney', 'NZ' => 'Pacific/Auckland',
    ];
    
    if ($countryCode && isset($countryTimezoneMap[$countryCode])) {
        return $countryTimezoneMap[$countryCode];
    }
    
    // Default to UTC if unknown
    return 'UTC';
}

// Helper function to convert country code to flag emoji
function getCountryFlagEmoji($countryCode) {
    if (empty($countryCode) || strlen($countryCode) !== 2) {
        return '';
    }
    
    $countryCode = strtoupper($countryCode);
    $codePoints = [];
    
    // Convert each letter to regional indicator symbol (U+1F1E6 to U+1F1FF)
    for ($i = 0; $i < 2; $i++) {
        $char = $countryCode[$i];
        if ($char >= 'A' && $char <= 'Z') {
            $codePoints[] = 0x1F1E6 + (ord($char) - ord('A'));
        } else {
            return ''; // Invalid character
        }
    }
    
    // Convert code points to UTF-8 emoji string
    $emoji = '';
    foreach ($codePoints as $codePoint) {
        $emoji .= mb_convert_encoding('&#' . $codePoint . ';', 'UTF-8', 'HTML-ENTITIES');
    }
    
    return $emoji;
}

// Helper function to get country name from country code
function getCountryName($countryCode) {
    if (!$countryCode) return '';
    
    $countryMap = [
        'US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico',
        'GB' => 'United Kingdom', 'FR' => 'France', 'DE' => 'Germany',
        'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands',
        'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria',
        'PT' => 'Portugal', 'GR' => 'Greece', 'IE' => 'Ireland',
        'DK' => 'Denmark', 'SE' => 'Sweden', 'NO' => 'Norway',
        'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czech Republic',
        'HU' => 'Hungary', 'RO' => 'Romania', 'BG' => 'Bulgaria',
        'HR' => 'Croatia', 'SI' => 'Slovenia', 'SK' => 'Slovakia',
        'JP' => 'Japan', 'CN' => 'China', 'KR' => 'South Korea',
        'TW' => 'Taiwan', 'HK' => 'Hong Kong', 'SG' => 'Singapore',
        'TH' => 'Thailand', 'MY' => 'Malaysia', 'ID' => 'Indonesia',
        'PH' => 'Philippines', 'VN' => 'Vietnam', 'IN' => 'India',
        'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'LK' => 'Sri Lanka',
        'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia',
        'QA' => 'Qatar', 'KW' => 'Kuwait', 'BH' => 'Bahrain',
        'OM' => 'Oman', 'JO' => 'Jordan', 'LB' => 'Lebanon',
        'IL' => 'Israel', 'TR' => 'Turkey', 'EG' => 'Egypt',
        'ZA' => 'South Africa', 'KE' => 'Kenya', 'NG' => 'Nigeria',
        'GH' => 'Ghana', 'MA' => 'Morocco', 'TN' => 'Tunisia',
        'AU' => 'Australia', 'NZ' => 'New Zealand', 'FJ' => 'Fiji',
        'BR' => 'Brazil', 'AR' => 'Argentina', 'CL' => 'Chile',
        'PE' => 'Peru', 'CO' => 'Colombia', 'EC' => 'Ecuador',
        'VE' => 'Venezuela', 'UY' => 'Uruguay', 'PY' => 'Paraguay',
        'BO' => 'Bolivia', 'CR' => 'Costa Rica', 'PA' => 'Panama',
        'GT' => 'Guatemala', 'HN' => 'Honduras', 'NI' => 'Nicaragua',
        'SV' => 'El Salvador', 'DO' => 'Dominican Republic',
        'CU' => 'Cuba', 'JM' => 'Jamaica', 'TT' => 'Trinidad and Tobago',
        'RU' => 'Russia', 'UA' => 'Ukraine', 'BY' => 'Belarus',
        'KZ' => 'Kazakhstan', 'UZ' => 'Uzbekistan', 'GE' => 'Georgia',
        'AM' => 'Armenia', 'AZ' => 'Azerbaijan',
    ];
    
    return $countryMap[strtoupper($countryCode)] ?? $countryCode;
}

// Helper function to format date in timezone with airport code context
function formatDateInTimezone($dateString, $timezone, $airportCode = null) {
    if (!$dateString) return '';
    
    try {
        $date = new DateTime($dateString);
        if ($timezone && $timezone !== 'UTC') {
            $date->setTimezone(new DateTimeZone($timezone));
        }
        
        // Format: "M j, Y g:i A T" (e.g., "Dec 15, 2024 10:30 AM CET")
        $formatted = $date->format('M j, Y g:i A T');
        
        // Add airport code if provided for clarity
        if ($airportCode) {
            $formatted .= ' (' . $airportCode . ')';
        }
        
        return $formatted;
    } catch (Exception $e) {
        // Fallback to simple format
        return date('M j, Y g:i A', strtotime($dateString));
    }
}

// Helper function to format time only in timezone (for compact views)
function formatTimeInTimezone($dateString, $timezone, $airportCode = null) {
    if (!$dateString) return '';
    
    try {
        $date = new DateTime($dateString);
        if ($timezone && $timezone !== 'UTC') {
            $date->setTimezone(new DateTimeZone($timezone));
        }
        
        // Format: "g:i A T" (e.g., "10:30 AM CET")
        $formatted = $date->format('g:i A T');
        
        // Add airport code if provided for clarity
        if ($airportCode) {
            $formatted .= ' (' . $airportCode . ')';
        }
        
        return $formatted;
    } catch (Exception $e) {
        // Fallback to simple format
        return date('g:i A', strtotime($dateString));
    }
}

// Get trip details - check if user has access
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
    
    $hasSharingColumns = true;
}

// Now query with sharing columns
$stmt = $conn->prepare("SELECT *, is_publicly_shared, share_token FROM trips WHERE id = ?");
$stmt->execute([$tripId]);
$trip = $stmt->fetch();

// Set default values if columns still don't exist (fallback)
if (!isset($trip['is_publicly_shared'])) {
    $trip['is_publicly_shared'] = 0;
}
if (!isset($trip['share_token'])) {
    $trip['share_token'] = null;
}

if (!$trip || !hasTripAccess($tripId, $userId)) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : getBasePath();
    header('Location: ' . $basePath . '/pages/dashboard.php');
    exit;
}

// Get user's role for this trip
$userRole = getUserTripRole($tripId, $userId);
$isOwner = isTripOwner($tripId, $userId);
$canEdit = canEditTrip($tripId, $userId);

// Get collaborators
$collaborators = getTripCollaborators($tripId);

// Get all travel items for this trip with document counts and creator/modifier info
$stmt = $conn->prepare("
    SELECT ti.*, 
           COUNT(d.id) as document_count,
           u1.email as created_by_email,
           u1.first_name as created_by_first,
           u1.last_name as created_by_last,
           u2.email as modified_by_email,
           u2.first_name as modified_by_first,
           u2.last_name as modified_by_last
    FROM travel_items ti
    LEFT JOIN documents d ON ti.id = d.travel_item_id
    LEFT JOIN users u1 ON ti.created_by = u1.id
    LEFT JOIN users u2 ON ti.modified_by = u2.id
    WHERE ti.trip_id = ?
    GROUP BY ti.id
    ORDER BY ti.start_datetime ASC
");
$stmt->execute([$tripId]);
$items = $stmt->fetchAll();

// Add timezone information to flight items for display
foreach ($items as &$item) {
    if ($item['type'] === 'flight') {
        // Get airport codes from stored flight data
        $depIata = '';
        $arrIata = '';
        $depCountry = $item['flight_departure_country'] ?? '';
        $arrCountry = $item['flight_arrival_country'] ?? '';
        
        // Try to extract IATA from location field if available (format: "Airport Name (IATA) → Airport Name (IATA)")
        if ($item['location']) {
            if (preg_match('/\(([A-Z]{3})\)/', $item['location'], $matches)) {
                $depIata = $matches[1];
            }
            if (preg_match('/→.*?\(([A-Z]{3})\)/', $item['location'], $matches)) {
                $arrIata = $matches[1];
            }
        }
        
        // Use stored ICAO codes as fallback (though timezone map uses IATA)
        $depIcao = $item['flight_departure_icao'] ?? '';
        $arrIcao = $item['flight_arrival_icao'] ?? '';
        
        $item['departure_timezone'] = getAirportTimezone($depIata, $depIcao, $depCountry);
        $item['arrival_timezone'] = getAirportTimezone($arrIata, $arrIcao, $arrCountry);
    }
}
unset($item); // Break reference

// Get documents for each travel item (optimized - single query instead of N+1)
$itemDocuments = [];
if (!empty($items)) {
    $itemIds = array_column($items, 'id');
    $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
    $stmt = $conn->prepare("SELECT * FROM documents WHERE travel_item_id IN ($placeholders) ORDER BY upload_date DESC");
    $stmt->execute($itemIds);
    $allItemDocuments = $stmt->fetchAll();
    
    // Group documents by travel_item_id
    foreach ($allItemDocuments as $doc) {
        $itemDocuments[$doc['travel_item_id']][] = $doc;
    }
    
    // Initialize empty arrays for items without documents
    foreach ($items as $item) {
        if (!isset($itemDocuments[$item['id']])) {
            $itemDocuments[$item['id']] = [];
        }
    }
}

// Get documents for trip (not attached to specific items)
$stmt = $conn->prepare("
    SELECT * FROM documents 
    WHERE trip_id = ? AND travel_item_id IS NULL
    ORDER BY upload_date DESC
");
$stmt->execute([$tripId]);
$tripDocuments = $stmt->fetchAll();

// Get item for editing if needed
$editItem = null;
$editItemId = $_GET['item_id'] ?? 0;
if ($editItemId) {
    $stmt = $conn->prepare("
        SELECT ti.* FROM travel_items ti
        WHERE ti.id = ?
    ");
    $stmt->execute([$editItemId]);
    $editItem = $stmt->fetch();
    
    // Verify access
    if ($editItem && !hasTripAccess($editItem['trip_id'], $userId)) {
        $editItem = null;
    }
}

$pageTitle = htmlspecialchars($trip['title']);
$showBack = true;
$action = $_GET['action'] ?? '';

// Get invitations if owner
$invitations = [];
if ($isOwner) {
    $stmt = $conn->prepare("
        SELECT i.*, 
               u.email as created_by_email,
               CASE 
                   WHEN i.expires_at < NOW() THEN 'expired'
                   WHEN i.max_uses IS NOT NULL AND i.current_uses >= i.max_uses THEN 'maxed'
                   ELSE 'active'
               END as status
        FROM invitations i
        INNER JOIN users u ON i.created_by = u.id
        WHERE i.trip_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$tripId]);
    $invitations = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'edit'): ?>
    <div class="card">
        <h2 class="card-title">Edit Trip</h2>
        <form id="editTripForm">
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
            <div class="form-group">
                <label class="form-label" for="title">Trip Title *</label>
                <input type="text" id="title" name="title" class="form-input" required value="<?php echo htmlspecialchars($trip['title']); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="start_date">Start Date *</label>
                <input type="date" id="start_date" name="start_date" class="form-input" required value="<?php echo $trip['start_date']; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-input" value="<?php echo $trip['end_date'] ?: ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_travel_type">Type of Travel</label>
                <select id="edit_travel_type" name="travel_type" class="form-select">
                    <option value="">Select type...</option>
                    <option value="vacations" <?php echo ($trip['travel_type'] ?? '') === 'vacations' ? 'selected' : ''; ?>>Vacations</option>
                    <option value="work" <?php echo ($trip['travel_type'] ?? '') === 'work' ? 'selected' : ''; ?>>Work</option>
                    <option value="business" <?php echo ($trip['travel_type'] ?? '') === 'business' ? 'selected' : ''; ?>>Business</option>
                    <option value="family" <?php echo ($trip['travel_type'] ?? '') === 'family' ? 'selected' : ''; ?>>Family</option>
                    <option value="leisure" <?php echo ($trip['travel_type'] ?? '') === 'leisure' ? 'selected' : ''; ?>>Leisure</option>
                    <option value="adventure" <?php echo ($trip['travel_type'] ?? '') === 'adventure' ? 'selected' : ''; ?>>Adventure</option>
                    <option value="romantic" <?php echo ($trip['travel_type'] ?? '') === 'romantic' ? 'selected' : ''; ?>>Romantic</option>
                    <option value="other" <?php echo ($trip['travel_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_status">Trip Status</label>
                <select id="edit_status" name="status" class="form-select">
                    <option value="active" <?php echo ($trip['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo ($trip['status'] ?? 'active') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="archived" <?php echo ($trip['status'] ?? 'active') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Number of Destinations</label>
                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="edit_destination_type" value="single" <?php echo (($trip['is_multiple_destinations'] ?? 0) == 0) ? 'checked' : ''; ?> onchange="toggleEditDestinations()">
                        <span>Single Destination</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="edit_destination_type" value="multiple" <?php echo (($trip['is_multiple_destinations'] ?? 0) == 1) ? 'checked' : ''; ?> onchange="toggleEditDestinations()">
                        <span>Multiple Destinations</span>
                    </label>
                </div>
            </div>
            
            <?php
            $destinations = [];
            if (!empty($trip['destinations'])) {
                $destinations = json_decode($trip['destinations'], true) ?: [];
            }
            $isMultiple = ($trip['is_multiple_destinations'] ?? 0) == 1;
            ?>
            
            <div class="form-group" id="edit_single_destination_group" style="<?php echo $isMultiple ? 'display: none;' : ''; ?>">
                <label class="form-label" for="edit_single_destination">Destination *</label>
                <div style="position: relative;">
                    <input type="text" id="edit_single_destination" name="edit_single_destination" class="form-input destination-autocomplete" 
                           placeholder="e.g., Paris, France" 
                           value="<?php 
                           if (!$isMultiple && !empty($destinations[0])) {
                               $dest = $destinations[0];
                               $display = $dest['name'] ?? '';
                               $flag = '';
                               if (!empty($dest['flag'])) {
                                   $flag = $dest['flag'] . ' ';
                               } elseif (!empty($dest['country_code'])) {
                                   $flag = getCountryFlagEmoji($dest['country_code']) . ' ';
                               }
                               echo htmlspecialchars($flag . $display, ENT_QUOTES, 'UTF-8');
                           }
                           ?>" 
                           data-destination-data="<?php echo !$isMultiple && !empty($destinations[0]) ? htmlspecialchars(json_encode($destinations[0]), ENT_QUOTES, 'UTF-8') : ''; ?>"
                           autocomplete="off"
                           <?php echo !$isMultiple ? 'required' : ''; ?>>
                    <div id="edit_single_destination_autocomplete" class="autocomplete-dropdown" style="display: none;"></div>
                </div>
            </div>
            
            <div class="form-group" id="edit_multiple_destinations_group" style="<?php echo !$isMultiple ? 'display: none;' : ''; ?>">
                <label class="form-label">Destinations *</label>
                <div id="edit_destinations_container">
                    <?php if ($isMultiple && !empty($destinations)): ?>
                        <?php foreach ($destinations as $index => $dest): ?>
                            <div class="destination-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <div style="position: relative; flex: 1;">
                                    <input type="text" name="edit_destinations[]" class="form-input destination-autocomplete" 
                                           placeholder="e.g., Paris, France" 
                                           value="<?php 
                                           $display = $dest['name'] ?? '';
                                           $flag = '';
                                           if (!empty($dest['flag'])) {
                                               $flag = $dest['flag'] . ' ';
                                           } elseif (!empty($dest['country_code'])) {
                                               $flag = getCountryFlagEmoji($dest['country_code']) . ' ';
                                           }
                                           echo htmlspecialchars($flag . $display, ENT_QUOTES, 'UTF-8');
                                           ?>"
                                           data-destination-data="<?php echo htmlspecialchars(json_encode($dest), ENT_QUOTES, 'UTF-8'); ?>"
                                           autocomplete="off"
                                           required>
                                    <div class="autocomplete-dropdown" style="display: none;"></div>
                                </div>
                                <button type="button" class="btn btn-small" onclick="removeEditDestination(this)" style="min-width: 44px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="destination-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div style="position: relative; flex: 1;">
                                <input type="text" name="edit_destinations[]" class="form-input destination-autocomplete" 
                                       placeholder="e.g., Paris, France" 
                                       autocomplete="off"
                                       required>
                                <div class="autocomplete-dropdown" style="display: none;"></div>
                            </div>
                            <button type="button" class="btn btn-small" onclick="removeEditDestination(this)" style="min-width: 44px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary btn-small" onclick="addEditDestination()" style="margin-top: 0.5rem;">
                    <i class="fas fa-plus"></i> Add Destination
                </button>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea"><?php echo htmlspecialchars($trip['description'] ?: ''); ?></textarea>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">Update Trip</button>
                <a href="trip_detail.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
<?php elseif ($action === 'add_item'): ?>
    <div class="card">
        <h2 class="card-title">Add Travel Item</h2>
        <form id="itemForm">
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
            
            <div class="form-group">
                <label class="form-label" for="type">Type *</label>
                <select id="type" name="type" class="form-select" required>
                    <option value="">Select type...</option>
                    <?php foreach (TRAVEL_ITEM_TYPES as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Message shown when no type is selected -->
            <div id="type_selection_message" style="background: rgba(74, 144, 226, 0.1); padding: 2rem; border-radius: 12px; text-align: center; margin: 1.5rem 0; border: 2px dashed rgba(74, 144, 226, 0.3);">
                <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">
                    <i class="fas fa-route"></i>
                </div>
                <h3 style="color: var(--text-color); font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">
                    Choose a Travel Item Type
                </h3>
                <p style="color: var(--text-light); font-size: 1rem; margin: 0; line-height: 1.6;">
                    Please select a travel item type from the dropdown above to start adding your travel details.
                </p>
            </div>
            
            <!-- Form fields container - hidden when no type is selected -->
            <div id="travel_item_form_fields" style="display: none;">
            <div class="form-group" id="flight_number_group" style="display: none;">
                <!-- Progressive Flight Lookup Form -->
                <div id="flight_lookup_wizard">
                    <!-- Step 1: Date -->
                    <div id="flight_step_1" class="flight-wizard-step">
                        <label class="form-label" for="flight_date">Step 1: Select Flight Date *</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="date" id="flight_date" name="flight_date" class="form-input" style="flex: 1;">
                            <button type="button" id="flight_next_step1" class="btn btn-small" style="margin: 0; white-space: nowrap;">
                                Next →
                            </button>
                        </div>
                        <div class="form-help" style="margin-top: 0.25rem;">
                            <i class="fas fa-lightbulb"></i> Select the date of your flight. This helps us find the correct flight schedule.
                        </div>
                    </div>
                    
                    <!-- Step 2: Flight Number -->
                    <div id="flight_step_2" class="flight-wizard-step" style="display: none;">
                        <label class="form-label" for="flight_number">Step 2: Enter Flight Number *</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" id="flight_number" name="flight_number" class="form-input" 
                                   placeholder="e.g., AA123 or AA1234" 
                                   pattern="[A-Z]{2}[0-9]{1,4}[A-Z]?" 
                                   style="flex: 1;">
                            <button type="button" id="flight_back_step2" class="btn btn-secondary btn-small" style="margin: 0; white-space: nowrap;">
                                ← Back
                            </button>
                            <button type="button" id="flight_search_step2" class="btn btn-small" style="margin: 0; white-space: nowrap;">
                                <i class="fas fa-search"></i> Search Flights
                            </button>
                        </div>
                        <div class="form-help" style="margin-top: 0.25rem;">
                            <i class="fas fa-lightbulb"></i> Enter your flight number (e.g., AA123, UA456). The system will search for flights on the selected date.
                        </div>
                    </div>
                    
                    <!-- Step 3: Flight Selection -->
                    <div id="flight_step_3" class="flight-wizard-step" style="display: none;">
                        <label class="form-label">Step 3: Select Your Flight</label>
                        <div id="flight_results_container" style="margin-top: 0.5rem;"></div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                            <button type="button" id="flight_back_step3" class="btn btn-secondary btn-small" style="margin: 0;">
                                ← Back
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="flight_lookup_status" style="margin-top: 0.5rem; font-size: 0.85rem; display: none;"></div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="item_title">Title *</label>
                <input type="text" id="item_title" name="title" class="form-input" placeholder="e.g., Flight to Paris">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="start_datetime">Start Date & Time *</label>
                <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_datetime">End Date & Time</label>
                <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="location">Location</label>
                <input type="text" id="location" name="location" class="form-input" placeholder="e.g., Paris, France">
            </div>
            
            <div class="form-group" id="hotel_fields_group" style="display: none;">
                <div style="background: rgba(74, 144, 226, 0.05); padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--primary-color); margin-bottom: 1rem;">
                    <h4 style="margin: 0 0 1.5rem 0; color: var(--primary-color); font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-hotel"></i> Hotel Accommodation Details
                    </h4>
                    
                    <!-- Hotel Name Section -->
                    <div style="margin-bottom: 1.5rem;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="hotel_name">
                                <i class="fas fa-building" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Hotel Name
                            </label>
                            <input type="text" id="hotel_name" name="hotel_name" class="form-input" placeholder="e.g., Grand Hotel Paris, Marriott Downtown">
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Enter the name of the hotel or accommodation
                            </div>
                        </div>
                    </div>
                    
                    <!-- Accommodation Details Section -->
                    <div style="margin-bottom: 1.5rem;">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-bed" style="color: var(--primary-color); font-size: 0.9rem;"></i> Room & Guest Information
                        </h5>
                        <div class="hotel-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="hotel_room_type">
                                    <i class="fas fa-door-open" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Room Type
                                </label>
                                <select id="hotel_room_type" name="hotel_room_type" class="form-select">
                                    <option value="">Select room type...</option>
                                    <option value="standard">Standard Room</option>
                                    <option value="deluxe">Deluxe Room</option>
                                    <option value="suite">Suite</option>
                                    <option value="executive">Executive Suite</option>
                                    <option value="presidential">Presidential Suite</option>
                                    <option value="other">Other</option>
                                </select>
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Select the type of room you've booked
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="hotel_number_of_rooms">
                                    <i class="fas fa-door-closed" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Number of Rooms
                                </label>
                                <input type="number" id="hotel_number_of_rooms" name="hotel_number_of_rooms" class="form-input" min="1" max="20" placeholder="1" step="1">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Total number of rooms booked
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="hotel_number_of_guests">
                                    <i class="fas fa-users" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Number of Guests
                                </label>
                                <input type="number" id="hotel_number_of_guests" name="hotel_number_of_guests" class="form-input" min="1" max="50" placeholder="2" step="1">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Total number of guests staying
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Check-in/Check-out Section -->
                    <div style="margin-bottom: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-calendar-check" style="color: var(--primary-color); font-size: 0.9rem;"></i> Check-in & Check-out Times
                        </h5>
                        <div class="hotel-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="hotel_check_in_time">
                                    <i class="fas fa-sign-in-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Check-in Time
                                </label>
                                <input type="time" id="hotel_check_in_time" name="hotel_check_in_time" class="form-input" value="15:00">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Standard check-in is usually 3:00 PM
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="hotel_check_out_time">
                                    <i class="fas fa-sign-out-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Check-out Time
                                </label>
                                <input type="time" id="hotel_check_out_time" name="hotel_check_out_time" class="form-input" value="11:00">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Standard check-out is usually 11:00 AM
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div style="margin-bottom: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-address-card" style="color: var(--primary-color); font-size: 0.9rem;"></i> Hotel Contact Information
                        </h5>
                        <div class="form-group" style="margin: 0 0 1rem 0;">
                            <label class="form-label" for="hotel_address">
                                <i class="fas fa-map-marker-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Hotel Address
                            </label>
                            <input type="text" id="hotel_address" name="hotel_address" class="form-input" placeholder="e.g., 123 Main Street, City, State, ZIP Code">
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Full address including street, city, and postal code
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="hotel_phone">
                                <i class="fas fa-phone" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Hotel Phone Number
                            </label>
                            <input type="tel" id="hotel_phone" name="hotel_phone" class="form-input" placeholder="+1 (555) 123-4567">
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Include country code for international hotels
                            </div>
                        </div>
                    </div>
                    
                    <!-- Special Requests Section -->
                    <div style="padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-star" style="color: var(--primary-color); font-size: 0.9rem;"></i> Special Requests & Preferences
                        </h5>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="hotel_special_requests">
                                <i class="fas fa-comment-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Special Requests
                            </label>
                            <textarea id="hotel_special_requests" name="hotel_special_requests" class="form-textarea" rows="4" placeholder="e.g., Late check-in requested, ground floor room preferred, extra towels needed, non-smoking room, early check-in if available..."></textarea>
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Any special requests, preferences, or notes for your hotel stay
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirmation_number">Confirmation Number / Booking Reference</label>
                <input type="text" id="confirmation_number" name="confirmation_number" class="form-input" placeholder="e.g., ABC123 or 6-character booking code">
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Enter your booking confirmation code or reservation reference.
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="item_description">Description</label>
                <textarea id="item_description" name="description" class="form-textarea"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-textarea" placeholder="Add any additional notes or reminders about this travel item..."></textarea>
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Add personal notes, reminders, or important information about this travel item.
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="item_files">Attachments (Optional)</label>
                <div class="file-input-wrapper">
                    <input type="file" name="files[]" id="item_files" class="file-input" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.json,.xml" multiple>
                    <label for="item_files" class="file-input-label">
                        <i class="fas fa-paperclip file-icon"></i>
                        <span class="file-label-text">Choose files or drag here</span>
                    </label>
                    <div class="file-name-display">
                        <div class="file-name">
                            <i class="fas fa-file"></i>
                            <span class="file-name-text"></span>
                        </div>
                        <div class="file-size"></div>
                    </div>
                    <button type="button" class="file-input-clear">Clear</button>
                </div>
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Upload additional documents, images, or other files related to this travel item.
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">Add Item</button>
                <a href="trip_detail.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">Cancel</a>
            </div>
            </div>
            <!-- End of travel_item_form_fields container -->
        </form>
    </div>
<?php elseif ($action === 'edit_item' && $editItem): ?>
    <div class="card">
        <h2 class="card-title">Edit Travel Item</h2>
        <form id="editItemForm">
            <input type="hidden" name="item_id" value="<?php echo $editItem['id']; ?>">
            
            <div class="form-group">
                <label class="form-label" for="edit_type">Type *</label>
                <select id="edit_type" name="type" class="form-select" required>
                    <option value="">Select type...</option>
                    <?php foreach (TRAVEL_ITEM_TYPES as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $editItem['type'] === $key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="edit_flight_number_group" style="display: none;">
                <label class="form-label" for="edit_flight_number">Flight Number *</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="text" id="edit_flight_number" name="flight_number" class="form-input" 
                           placeholder="e.g., AA123 or AA1234" 
                           pattern="[A-Z]{2}[0-9]{1,4}[A-Z]?" 
                           style="flex: 1;">
                    <button type="button" id="edit_lookup_flight" class="btn btn-small" style="margin: 0; white-space: nowrap;">
                        <i class="fas fa-search"></i> Lookup
                    </button>
                </div>
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Enter your flight number and click Lookup. The system will search for flights and automatically fill in the details below. You can optionally enter a date first to search that specific date.
                </div>
                <div id="edit_flight_lookup_status" style="margin-top: 0.5rem; font-size: 0.85rem; display: none;"></div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_title">Title *</label>
                <input type="text" id="edit_title" name="title" class="form-input" required value="<?php echo htmlspecialchars($editItem['title']); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_start_datetime">Start Date & Time *</label>
                <input type="datetime-local" id="edit_start_datetime" name="start_datetime" class="form-input" required 
                       value="<?php echo date('Y-m-d\TH:i', strtotime($editItem['start_datetime'])); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_end_datetime">End Date & Time</label>
                <input type="datetime-local" id="edit_end_datetime" name="end_datetime" class="form-input" 
                       value="<?php echo $editItem['end_datetime'] ? date('Y-m-d\TH:i', strtotime($editItem['end_datetime'])) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_location">Location</label>
                <input type="text" id="edit_location" name="location" class="form-input" 
                       value="<?php echo htmlspecialchars($editItem['location'] ?: ''); ?>" placeholder="e.g., Paris, France">
            </div>
            
            <div class="form-group" id="edit_hotel_fields_group" style="display: none;">
                <div style="background: rgba(74, 144, 226, 0.05); padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--primary-color); margin-bottom: 1rem;">
                    <h4 style="margin: 0 0 1.5rem 0; color: var(--primary-color); font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-hotel"></i> Hotel Accommodation Details
                    </h4>
                    <?php
                    $hotelData = [];
                    if (!empty($editItem['hotel_data'])) {
                        $hotelData = json_decode($editItem['hotel_data'], true) ?: [];
                    }
                    ?>
                    
                    <!-- Hotel Name Section -->
                    <div style="margin-bottom: 1.5rem;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="edit_hotel_name">
                                <i class="fas fa-building" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Hotel Name
                            </label>
                            <input type="text" id="edit_hotel_name" name="hotel_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($hotelData['hotel_name'] ?? ''); ?>" 
                                   placeholder="e.g., Grand Hotel Paris, Marriott Downtown">
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Enter the name of the hotel or accommodation
                            </div>
                        </div>
                    </div>
                    
                    <!-- Accommodation Details Section -->
                    <div style="margin-bottom: 1.5rem;">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-bed" style="color: var(--primary-color); font-size: 0.9rem;"></i> Room & Guest Information
                        </h5>
                        <div class="hotel-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="edit_hotel_room_type">
                                    <i class="fas fa-door-open" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Room Type
                                </label>
                                <select id="edit_hotel_room_type" name="hotel_room_type" class="form-select">
                                    <option value="">Select room type...</option>
                                    <option value="standard" <?php echo ($hotelData['room_type'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard Room</option>
                                    <option value="deluxe" <?php echo ($hotelData['room_type'] ?? '') === 'deluxe' ? 'selected' : ''; ?>>Deluxe Room</option>
                                    <option value="suite" <?php echo ($hotelData['room_type'] ?? '') === 'suite' ? 'selected' : ''; ?>>Suite</option>
                                    <option value="executive" <?php echo ($hotelData['room_type'] ?? '') === 'executive' ? 'selected' : ''; ?>>Executive Suite</option>
                                    <option value="presidential" <?php echo ($hotelData['room_type'] ?? '') === 'presidential' ? 'selected' : ''; ?>>Presidential Suite</option>
                                    <option value="other" <?php echo ($hotelData['room_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Select the type of room you've booked
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="edit_hotel_number_of_rooms">
                                    <i class="fas fa-door-closed" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Number of Rooms
                                </label>
                                <input type="number" id="edit_hotel_number_of_rooms" name="hotel_number_of_rooms" class="form-input" min="1" max="20" 
                                       value="<?php echo htmlspecialchars($hotelData['number_of_rooms'] ?? ''); ?>" placeholder="1" step="1">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Total number of rooms booked
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="edit_hotel_number_of_guests">
                                    <i class="fas fa-users" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Number of Guests
                                </label>
                                <input type="number" id="edit_hotel_number_of_guests" name="hotel_number_of_guests" class="form-input" min="1" max="50" 
                                       value="<?php echo htmlspecialchars($hotelData['number_of_guests'] ?? ''); ?>" placeholder="2" step="1">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Total number of guests staying
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Check-in/Check-out Section -->
                    <div style="margin-bottom: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-calendar-check" style="color: var(--primary-color); font-size: 0.9rem;"></i> Check-in & Check-out Times
                        </h5>
                        <div class="hotel-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="edit_hotel_check_in_time">
                                    <i class="fas fa-sign-in-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Check-in Time
                                </label>
                                <input type="time" id="edit_hotel_check_in_time" name="hotel_check_in_time" class="form-input" 
                                       value="<?php echo htmlspecialchars($hotelData['check_in_time'] ?? '15:00'); ?>">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Standard check-in is usually 3:00 PM
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin: 0;">
                                <label class="form-label" for="edit_hotel_check_out_time">
                                    <i class="fas fa-sign-out-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Check-out Time
                                </label>
                                <input type="time" id="edit_hotel_check_out_time" name="hotel_check_out_time" class="form-input" 
                                       value="<?php echo htmlspecialchars($hotelData['check_out_time'] ?? '11:00'); ?>">
                                <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                    Standard check-out is usually 11:00 AM
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div style="margin-bottom: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-address-card" style="color: var(--primary-color); font-size: 0.9rem;"></i> Hotel Contact Information
                        </h5>
                        <div class="form-group" style="margin: 0 0 1rem 0;">
                            <label class="form-label" for="edit_hotel_address">
                                <i class="fas fa-map-marker-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Hotel Address
                            </label>
                            <input type="text" id="edit_hotel_address" name="hotel_address" class="form-input" 
                                   value="<?php echo htmlspecialchars($hotelData['address'] ?? ''); ?>" placeholder="e.g., 123 Main Street, City, State, ZIP Code">
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Full address including street, city, and postal code
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="edit_hotel_phone">
                                <i class="fas fa-phone" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Hotel Phone Number
                            </label>
                            <input type="tel" id="edit_hotel_phone" name="hotel_phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($hotelData['phone'] ?? ''); ?>" placeholder="+1 (555) 123-4567">
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Include country code for international hotels
                            </div>
                        </div>
                    </div>
                    
                    <!-- Special Requests Section -->
                    <div style="padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <h5 style="margin: 0 0 1rem 0; color: var(--text-color); font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-star" style="color: var(--primary-color); font-size: 0.9rem;"></i> Special Requests & Preferences
                        </h5>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="edit_hotel_special_requests">
                                <i class="fas fa-comment-alt" style="margin-right: 0.25rem; color: var(--text-light); font-size: 0.85rem;"></i> Special Requests
                            </label>
                            <textarea id="edit_hotel_special_requests" name="hotel_special_requests" class="form-textarea" rows="4" 
                                      placeholder="e.g., Late check-in requested, ground floor room preferred, extra towels needed, non-smoking room, early check-in if available..."><?php echo htmlspecialchars($hotelData['special_requests'] ?? ''); ?></textarea>
                            <div class="form-help" style="margin-top: 0.25rem; font-size: 0.8rem;">
                                Any special requests, preferences, or notes for your hotel stay
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_confirmation_number">Confirmation Number / Booking Reference</label>
                <input type="text" id="edit_confirmation_number" name="confirmation_number" class="form-input" 
                       value="<?php echo htmlspecialchars($editItem['confirmation_number'] ?: ''); ?>" placeholder="e.g., ABC123 or 6-character booking code">
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Enter your booking confirmation code or reservation reference.
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_description">Description</label>
                <textarea id="edit_description" name="description" class="form-textarea"><?php echo htmlspecialchars($editItem['description'] ?: ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_notes">Notes</label>
                <textarea id="edit_notes" name="notes" class="form-textarea" placeholder="Add any additional notes or reminders about this travel item..."><?php echo htmlspecialchars($editItem['notes'] ?? ''); ?></textarea>
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Add personal notes, reminders, or important information about this travel item.
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_item_files">Attachments (Optional)</label>
                <div class="file-input-wrapper">
                    <input type="file" name="files[]" id="edit_item_files" class="file-input" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.json,.xml" multiple>
                    <label for="edit_item_files" class="file-input-label">
                        <i class="fas fa-paperclip file-icon"></i>
                        <span class="file-label-text">Choose files or drag here</span>
                    </label>
                    <div class="file-name-display">
                        <div class="file-name">
                            <i class="fas fa-file"></i>
                            <span class="file-name-text"></span>
                        </div>
                        <div class="file-size"></div>
                    </div>
                    <button type="button" class="file-input-clear">Clear</button>
                </div>
                <div class="form-help" style="margin-top: 0.25rem;">
                    <i class="fas fa-lightbulb"></i> Upload additional documents, images, or other files related to this travel item.
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">Update Item</button>
                <a href="trip_detail.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="trip-header-card">
        <div class="trip-actions-dropdown trip-actions-dropdown-header">
            <button type="button" class="trip-actions-toggle" id="tripActionsToggle" aria-label="Trip actions" aria-expanded="false">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <div class="trip-actions-menu" id="tripActionsMenu">
                <?php if ($canEdit): ?>
                    <a href="trip_detail.php?id=<?php echo $tripId; ?>&action=edit" class="trip-action-item">
                        <i class="fas fa-edit"></i>
                        <span>Edit Trip</span>
                    </a>
                <?php endif; ?>
                <a href="../api/export_trip.php?id=<?php echo $tripId; ?>&format=csv" class="trip-action-item">
                    <i class="fas fa-table"></i>
                    <span>Export as CSV</span>
                </a>
                <a href="../api/export_trip.php?id=<?php echo $tripId; ?>&format=pdf" target="_blank" class="trip-action-item">
                    <i class="fas fa-file-pdf"></i>
                    <span>Export as PDF</span>
                </a>
                <?php if ($isOwner): ?>
                    <button type="button" onclick="deleteTrip(<?php echo $tripId; ?>)" class="trip-action-item trip-action-danger">
                        <i class="fas fa-trash"></i>
                        <span>Delete Trip</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="trip-header-content">
            <div class="trip-header-main">
                <div class="trip-header-accent"></div>
                <div class="trip-header-info">
                    <!-- Title and Badges Section -->
                    <div class="trip-header-title-section">
                        <h1 class="trip-header-title"><?php echo htmlspecialchars($trip['title']); ?></h1>
                        <div class="trip-header-badges">
                            <?php 
                            $status = $trip['status'] ?? 'active';
                            $statusLabels = ['active' => 'Active', 'completed' => 'Completed', 'archived' => 'Archived'];
                            $statusIcons = ['active' => 'fa-circle', 'completed' => 'fa-check-circle', 'archived' => 'fa-archive'];
                            $statusDescriptions = ['active' => 'Trip in progress', 'completed' => 'Trip completed', 'archived' => 'Trip archived'];
                            ?>
                            <span class="trip-header-badge trip-header-badge-status trip-header-badge-status-<?php echo htmlspecialchars($status); ?>" title="<?php echo $statusDescriptions[$status] ?? 'Active trip'; ?>">
                                <i class="fas <?php echo $statusIcons[$status] ?? 'fa-circle'; ?>"></i> <?php echo $statusLabels[$status] ?? 'Active'; ?>
                            </span>
                            <?php if (!empty($trip['travel_type'])): ?>
                                <span class="trip-header-badge trip-header-badge-type">
                                    <i class="fas fa-bookmark"></i> <?php echo htmlspecialchars(ucfirst($trip['travel_type'])); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (($trip['user_role'] ?? 'owner') !== 'owner'): ?>
                                <span class="trip-header-badge trip-header-badge-shared">
                                    <i class="fas fa-users"></i> Shared
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Trip Info Section -->
                    <div class="trip-header-info-section">
                        <div class="trip-header-dates">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date('M j, Y', strtotime($trip['start_date'])); ?></span>
                            <?php if ($trip['end_date']): ?>
                                <span class="trip-header-arrow">→</span>
                                <span><?php echo date('M j, Y', strtotime($trip['end_date'])); ?></span>
                                <?php 
                                $startDate = new DateTime($trip['start_date']);
                                $endDate = new DateTime($trip['end_date']);
                                $diff = $startDate->diff($endDate);
                                $days = $diff->days;
                                ?>
                                <span class="trip-header-duration">(<?php echo $days; ?> <?php echo $days === 1 ? 'day' : 'days'; ?>)</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        $destinations = [];
                        if (!empty($trip['destinations'])) {
                            $destinations = json_decode($trip['destinations'], true) ?: [];
                        }
                        if (!empty($destinations)):
                        ?>
                            <div class="trip-header-destinations">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>
                                    <?php 
                                    $destDisplays = array_map(function($dest) {
                                        $name = htmlspecialchars($dest['name'] ?? '');
                                        $flag = '';
                                        
                                        if (!empty($dest['flag'])) {
                                            $flag = $dest['flag'] . ' ';
                                        } elseif (!empty($dest['country_code'])) {
                                            $flag = getCountryFlagEmoji($dest['country_code']) . ' ';
                                        }
                                        
                                        return $flag . $name;
                                    }, $destinations);
                                    echo implode(' <span class="trip-header-arrow">→</span> ', $destDisplays);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                        $flightCount = count(array_filter($items, fn($item) => $item['type'] === 'flight'));
                        $hotelCount = count(array_filter($items, fn($item) => $item['type'] === 'hotel'));
                        $activityCount = count(array_filter($items, fn($item) => $item['type'] === 'activity'));
                        $totalItems = count($items);
                        if ($totalItems > 0):
                        ?>
                            <div class="trip-header-stats">
                                <i class="fas fa-list-ul"></i>
                                <span><?php echo $totalItems; ?> travel item<?php echo $totalItems > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Travel Stats -->
                    <?php
                    $flightCount = count(array_filter($items, fn($item) => $item['type'] === 'flight'));
                    $hotelCount = count(array_filter($items, fn($item) => $item['type'] === 'hotel'));
                    $activityCount = count(array_filter($items, fn($item) => $item['type'] === 'activity'));
                    $totalItems = count($items);
                    $hasStats = $flightCount > 0 || $hotelCount > 0 || $activityCount > 0 || $totalItems > 0;
                    ?>
                    <?php if ($hasStats): ?>
                        <div class="trip-header-quick-stats">
                            <?php if ($flightCount > 0): ?>
                                <div class="quick-stat">
                                    <i class="fas fa-plane"></i>
                                    <span><?php echo $flightCount; ?> Flight<?php echo $flightCount !== 1 ? 's' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($hotelCount > 0): ?>
                                <div class="quick-stat">
                                    <i class="fas fa-hotel"></i>
                                    <span><?php echo $hotelCount; ?> Hotel<?php echo $hotelCount !== 1 ? 's' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($activityCount > 0): ?>
                                <div class="quick-stat">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <span><?php echo $activityCount; ?> Activit<?php echo $activityCount !== 1 ? 'ies' : 'y'; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($totalItems > 0): ?>
                                <div class="quick-stat">
                                    <i class="fas fa-list"></i>
                                    <span><?php echo $totalItems; ?> Total</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick Action Buttons -->
                    <div class="trip-header-quick-actions">
                        <?php if (!empty($destinations)): ?>
                            <?php
                            $firstDest = $destinations[0];
                            $destName = urlencode($firstDest['name'] ?? '');
                            $mapsUrl = "https://www.google.com/maps/search/?api=1&query=" . $destName;
                            ?>
                            <a href="<?php echo $mapsUrl; ?>" target="_blank" class="quick-action-btn" title="Open in Maps">
                                <i class="fas fa-map"></i>
                                <span>Maps</span>
                            </a>
                        <?php endif; ?>
                        <button type="button" class="quick-action-btn" onclick="shareTrip()" title="Share Trip" <?php if ($trip['is_publicly_shared'] ?? 0): ?>style="background: rgba(74, 144, 226, 0.15);"<?php endif; ?>>
                            <i class="fas fa-share-alt"></i>
                            <span>Share</span>
                            <?php if ($trip['is_publicly_shared'] ?? 0): ?>
                                <i class="fas fa-check-circle" style="margin-left: 0.25rem; font-size: 0.75rem; color: var(--primary-color);"></i>
                            <?php endif; ?>
                        </button>
                        <a href="../api/export_trip.php?id=<?php echo $tripId; ?>&format=pdf" target="_blank" class="quick-action-btn" title="Print Itinerary">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </a>
                    </div>
                    
                    <!-- Next Upcoming Item -->
                    <?php
                    $now = new DateTime();
                    $upcomingItems = [];
                    foreach ($items as $item) {
                        $itemDate = new DateTime($item['start_datetime']);
                        if ($itemDate >= $now) {
                            $upcomingItems[] = [
                                'item' => $item,
                                'datetime' => $itemDate,
                                'diff' => $now->diff($itemDate)
                            ];
                        }
                    }
                    usort($upcomingItems, function($a, $b) {
                        return $a['datetime'] <=> $b['datetime'];
                    });
                    $nextItem = !empty($upcomingItems) ? $upcomingItems[0] : null;
                    ?>
                    <?php if ($nextItem): ?>
                        <?php
                        $nextItemData = $nextItem['item'];
                        $nextItemDate = $nextItem['datetime'];
                        $diff = $nextItem['diff'];
                        $daysUntil = $diff->days;
                        $hoursUntil = $diff->h;
                        $minutesUntil = $diff->i;
                        $timeUntil = '';
                        if ($daysUntil > 0) {
                            $timeUntil = $daysUntil . ' day' . ($daysUntil !== 1 ? 's' : '') . ' away';
                        } elseif ($hoursUntil > 0) {
                            $timeUntil = $hoursUntil . ' hour' . ($hoursUntil !== 1 ? 's' : '') . ' away';
                        } else {
                            $timeUntil = $minutesUntil . ' minute' . ($minutesUntil !== 1 ? 's' : '') . ' away';
                        }
                        $typeIcons = [
                            'flight' => 'fa-plane',
                            'hotel' => 'fa-hotel',
                            'train' => 'fa-train',
                            'bus' => 'fa-bus',
                            'car_rental' => 'fa-car',
                            'activity' => 'fa-map-marked-alt',
                            'other' => 'fa-circle'
                        ];
                        $typeIcon = $typeIcons[$nextItemData['type']] ?? 'fa-circle';
                        ?>
                        <div class="trip-header-next-item">
                            <div class="next-item-header">
                                <i class="fas fa-clock"></i>
                                <span>Next: <?php echo htmlspecialchars($nextItemData['title']); ?></span>
                            </div>
                            <div class="next-item-details">
                                <div class="next-item-type">
                                    <i class="fas <?php echo $typeIcon; ?>"></i>
                                    <span><?php echo ucfirst($nextItemData['type']); ?></span>
                                </div>
                                <div class="next-item-time">
                                    <span class="next-item-time-label"><?php echo date('M j, g:i A', $nextItemDate->getTimestamp()); ?></span>
                                    <span class="next-item-time-until"><?php echo $timeUntil; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($trip['description']): ?>
                <div class="trip-header-description">
                    <p><?php echo nl2br(htmlspecialchars($trip['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <h3 class="card-title" style="margin: 0; font-size: 1.25rem; font-weight: 700;">Timeline</h3>
                <?php if ($canEdit): ?>
                    <a href="trip_detail.php?id=<?php echo $tripId; ?>&action=add_item" class="btn btn-small" style="padding: 0.6rem 1rem; font-size: 0.9rem; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; white-space: nowrap;">
                        <i class="fas fa-plus"></i> Add Item
                    </a>
                <?php endif; ?>
            </div>
            <div style="position: relative;">
                <input type="text" id="itemSearch" class="search-input" placeholder="Search items by title, location, description, flight number..." style="width: 100%; font-size: 16px; padding: 0.75rem; padding-left: 2.5rem; padding-right: 2.5rem; min-height: 44px; border-radius: 8px; border: 1px solid var(--border-color);">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-light); pointer-events: none;"></i>
                <button type="button" id="clearSearch" class="btn-clear-search" style="display: none; position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-light); cursor: pointer; padding: 0.25rem; font-size: 0.9rem; min-width: 32px; min-height: 32px; border-radius: 4px; transition: all 0.2s ease;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="searchResultsMessage" style="display: none; margin-top: 0.5rem; padding: 0.75rem; background: rgba(74, 144, 226, 0.1); border-radius: 6px; color: var(--primary-color); font-size: 0.9rem; text-align: center;"></div>
        </div>
        
        <?php if (!empty($items)): ?>
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
            <div class="timeline" id="timelineContainer">
                <?php foreach ($itemsByDate as $itemDate => $dateItems): ?>
                    <?php
                    // Determine the dominant type for this date group
                    $typeCounts = [];
                    foreach ($dateItems as $item) {
                        $type = $item['type'] ?? 'other';
                        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
                    }
                    // Get the most common type, or 'mixed' if multiple types
                    arsort($typeCounts);
                    $dominantType = count($typeCounts) > 1 ? 'mixed' : key($typeCounts);
                    $allTypes = array_keys($typeCounts);
                    $typesClass = implode(' ', array_map(function($type) { return 'has-type-' . $type; }, $allTypes));
                    ?>
                    <div class="timeline-date-group <?php echo $typesClass; ?>" data-date="<?php echo $itemDate; ?>" data-dominant-type="<?php echo $dominantType; ?>">
                        <div class="timeline-date-header expandable-header timeline-date-header-<?php echo $dominantType; ?>" data-date="<?php echo $itemDate; ?>">
                            <div class="timeline-date-dot timeline-date-dot-<?php echo $dominantType; ?>" style="position: absolute; left: -1.5rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; border-radius: 50%; border: 3px solid var(--card-bg); box-shadow: 0 2px 6px rgba(0,0,0,0.15); z-index: 3;"></div>
                            <div class="timeline-date-header-content timeline-date-header-content-<?php echo $dominantType; ?>" style="display: flex; align-items: center; justify-content: space-between; width: 100%; cursor: pointer; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; line-height: 1.3; touch-action: manipulation; -webkit-tap-highlight-color: rgba(255, 255, 255, 0.3); min-height: 44px; color: white;">
                                <span><?php echo date('l, F j, Y', strtotime($itemDate)); ?></span>
                                <i class="fas fa-chevron-down expand-icon" style="font-size: 1rem; transition: transform 0.3s ease; pointer-events: none;"></i>
                                <span class="item-count" style="margin-left: 0.5rem; font-size: 0.8rem; opacity: 0.9; pointer-events: none;">(<?php echo count($dateItems); ?>)</span>
                            </div>
                        </div>
                        <div class="timeline-date-content" data-date="<?php echo $itemDate; ?>" style="display: block;">
                            <?php foreach ($dateItems as $item): ?>
                                <?php
                                // Prepare searchable data attributes
                                $searchData = [
                                    'title' => strtolower($item['title'] ?: ''),
                                    'location' => strtolower($item['location'] ?: ''),
                                    'description' => strtolower($item['description'] ?: ''),
                                    'notes' => strtolower($item['notes'] ?? ''),
                                    'type' => strtolower($item['type'] ?: ''),
                                    'confirmation' => strtolower($item['confirmation_number'] ?: ''),
                                    'flight_airline' => strtolower(($item['flight_airline'] ?? '') ?: ''),
                                    'flight_number' => strtolower(($item['flight_number'] ?? '') ?: ''),
                                    'departure_icao' => strtolower(($item['flight_departure_icao'] ?? '') ?: ''),
                                    'arrival_icao' => strtolower(($item['flight_arrival_icao'] ?? '') ?: ''),
                                ];
                                $searchText = implode(' ', array_filter($searchData));
                                ?>
                                <div class="timeline-item-wrapper expandable-item <?php echo htmlspecialchars($item['type']); ?>" 
                                     data-item-id="<?php echo $item['id']; ?>" 
                                     data-item-title="<?php echo htmlspecialchars($searchData['title']); ?>" 
                                     data-item-location="<?php echo htmlspecialchars($searchData['location']); ?>"
                                     data-item-description="<?php echo htmlspecialchars($searchData['description']); ?>"
                                     data-item-type="<?php echo htmlspecialchars($searchData['type']); ?>"
                                     data-item-search="<?php echo htmlspecialchars($searchText); ?>"
                                     style="margin-bottom: 0.75rem;">
                                    <!-- Compact Summary View -->
                                    <?php 
                                    // Get timezones and airport codes for flights
                                    $depTimezone = isset($item['departure_timezone']) && $item['departure_timezone'] ? $item['departure_timezone'] : 'UTC';
                                    $arrTimezone = isset($item['arrival_timezone']) && $item['arrival_timezone'] ? $item['arrival_timezone'] : 'UTC';
                                    $depAirport = isset($item['flight_departure_icao']) && $item['flight_departure_icao'] ? $item['flight_departure_icao'] : null;
                                    $arrAirport = isset($item['flight_arrival_icao']) && $item['flight_arrival_icao'] ? $item['flight_arrival_icao'] : null;
                                    
                                    // Format times in their respective timezones
                                    if ($item['type'] === 'flight' && isset($item['departure_timezone']) && $item['departure_timezone']) {
                                        $depTime = formatTimeInTimezone($item['start_datetime'], $depTimezone, $depAirport);
                                    } else {
                                        $depTime = date('g:i A', strtotime($item['start_datetime']));
                                    }
                                    
                                    $arrTime = '';
                                    if ($item['end_datetime']) {
                                        if ($item['type'] === 'flight' && isset($item['arrival_timezone']) && $item['arrival_timezone']) {
                                            $arrTime = formatTimeInTimezone($item['end_datetime'], $arrTimezone, $arrAirport);
                                        } else {
                                            $arrTime = date('g:i A', strtotime($item['end_datetime']));
                                        }
                                    }
                                    ?>
                                    <div class="timeline-item-summary expandable-item-header" data-item-id="<?php echo $item['id']; ?>" style="cursor: pointer; padding: 0.75rem; background: var(--card-bg); border-radius: var(--border-radius); border: 1px solid var(--border-color); transition: var(--transition); touch-action: manipulation; -webkit-tap-highlight-color: rgba(74, 144, 226, 0.2); min-height: 44px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                            <?php if ($item['type'] === 'flight'): ?>
                                                <i class="fas fa-plane" style="font-size: 1.3rem; flex-shrink: 0; color: var(--primary-color);"></i>
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                                        <span style="font-weight: 700; color: var(--text-color); font-size: 0.95rem;"><?php echo htmlspecialchars($item['title']); ?></span>
                                                        <?php if (!empty($item['notes'])): ?>
                                                            <i class="fas fa-sticky-note" style="font-size: 0.85rem; color: #F57C00;" title="Has notes"></i>
                                                        <?php endif; ?>
                                                        <?php if (isset($itemDocuments[$item['id']]) && !empty($itemDocuments[$item['id']])): ?>
                                                            <i class="fas fa-paperclip" style="font-size: 0.85rem; color: var(--primary-color);" title="Has attachments"></i>
                                                        <?php endif; ?>
                                                        <?php if (isset($item['flight_airline']) && $item['flight_airline'] && isset($item['flight_number']) && $item['flight_number']): ?>
                                                            <span class="badge badge-flight" style="font-size: 0.7rem; padding: 0.25rem 0.5rem; background: rgba(74, 144, 226, 0.1); color: var(--primary-color); border-radius: 4px; font-weight: 600;">
                                                                <?php echo htmlspecialchars($item['flight_airline']); ?> <?php echo htmlspecialchars($item['flight_number']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; font-size: 0.85rem; color: var(--text-light);">
                                                        <div style="display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;">
                                                            <span style="font-weight: 600;">Depart:</span>
                                                            <span><?php echo $depTime; ?></span>
                                                        </div>
                                                        <?php 
                                                        $depCountry = isset($item['flight_departure_country']) && $item['flight_departure_country'] ? getCountryName($item['flight_departure_country']) : null;
                                                        $arrCountry = isset($item['flight_arrival_country']) && $item['flight_arrival_country'] ? getCountryName($item['flight_arrival_country']) : null;
                                                        if ($depCountry || $arrCountry): ?>
                                                            <span style="color: var(--primary-color); font-weight: 600;">
                                                                <?php echo htmlspecialchars($depCountry ?: '?'); ?> → <?php echo htmlspecialchars($arrCountry ?: '?'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($arrTime): ?>
                                                            <div style="display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;">
                                                                <span style="font-weight: 600;">Arrive:</span>
                                                                <span><?php echo $arrTime; ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 1.2rem; flex-shrink: 0;">
                                                    <?php 
                                                    $icons = [
                                                        'hotel' => '<i class="fas fa-hotel" style="font-size: 1.2rem; color: var(--primary-color);"></i>',
                                                        'train' => '<i class="fas fa-train" style="font-size: 1.2rem; color: var(--primary-color);"></i>',
                                                        'bus' => '<i class="fas fa-bus" style="font-size: 1.2rem; color: var(--primary-color);"></i>',
                                                        'car_rental' => '<i class="fas fa-car" style="font-size: 1.2rem; color: var(--primary-color);"></i>',
                                                        'activity' => '<i class="fas fa-bullseye" style="font-size: 1.2rem; color: var(--primary-color);"></i>',
                                                        'other' => '<i class="fas fa-map-marker-alt" style="font-size: 1.2rem; color: var(--primary-color);"></i>'
                                                    ];
                                                    echo $icons[$item['type']] ?? '<i class="fas fa-map-marker-alt" style="font-size: 1.2rem; color: var(--primary-color);"></i>';
                                                    ?>
                                                </span>
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                                        <span style="font-weight: 700; color: var(--text-color); font-size: 0.95rem;"><?php echo htmlspecialchars($item['title']); ?></span>
                                                        <?php if (!empty($item['notes'])): ?>
                                                            <i class="fas fa-sticky-note" style="font-size: 0.85rem; color: #F57C00;" title="Has notes"></i>
                                                        <?php endif; ?>
                                                        <?php if (isset($itemDocuments[$item['id']]) && !empty($itemDocuments[$item['id']])): ?>
                                                            <i class="fas fa-paperclip" style="font-size: 0.85rem; color: var(--primary-color);" title="Has attachments"></i>
                                                        <?php endif; ?>
                                                        <span class="badge badge-<?php echo $item['type']; ?>" style="font-size: 0.7rem; padding: 0.25rem 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo ucfirst($item['type']); ?></span>
                                                    </div>
                                                    <div style="font-size: 0.85rem; color: var(--text-light);">
                                                        <?php echo date('g:i A', strtotime($item['start_datetime'])); ?>
                                                        <?php if ($item['location']): ?>
                                                            <span> • <?php echo htmlspecialchars($item['location']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <i class="fas fa-chevron-right expand-item-icon" style="font-size: 1rem; color: var(--text-light); transition: transform 0.3s ease; flex-shrink: 0; pointer-events: none;"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Expanded Full Details View -->
                                    <div class="timeline-item <?php echo $item['type']; ?> timeline-item-details" data-item-id="<?php echo $item['id']; ?>" style="display: none; padding: 1rem; background: var(--card-bg); border-radius: var(--border-radius); border: 1px solid var(--border-color); margin-top: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                                        <div class="timeline-date" style="margin-bottom: 0.75rem;">
                                            <?php if ($item['type'] === 'flight' && isset($item['departure_timezone']) && $item['departure_timezone']): ?>
                                                <?php 
                                                $depAirport = isset($item['flight_departure_icao']) && $item['flight_departure_icao'] ? $item['flight_departure_icao'] : null;
                                                $arrAirport = isset($item['flight_arrival_icao']) && $item['flight_arrival_icao'] ? $item['flight_arrival_icao'] : null;
                                                ?>
                                                <div style="margin-bottom: 0.5rem;">
                                                    <strong>Departure:</strong> <?php echo formatDateInTimezone($item['start_datetime'], $item['departure_timezone'], $depAirport); ?>
                                                </div>
                                                <?php if ($item['end_datetime']): ?>
                                                    <div>
                                                        <strong>Arrival:</strong> <?php echo formatDateInTimezone($item['end_datetime'], isset($item['arrival_timezone']) && $item['arrival_timezone'] ? $item['arrival_timezone'] : 'UTC', $arrAirport); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo date(DATETIME_FORMAT, strtotime($item['start_datetime'])); ?>
                                                <?php if ($item['end_datetime']): ?>
                                                    <br>→ <?php echo date(DATETIME_FORMAT, strtotime($item['end_datetime'])); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-title" style="margin-bottom: 0.75rem;">
                                            <span style="flex: 1;"><?php echo htmlspecialchars($item['title']); ?></span>
                                            <span class="badge badge-<?php echo $item['type']; ?>" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo ucfirst($item['type']); ?></span>
                                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem;">
                            <?php if ($item['location']): ?>
                                <div class="timeline-details" style="display: flex; align-items: start; gap: 0.5rem; padding: 0.75rem; background: rgba(74, 144, 226, 0.05); border-radius: 6px; min-height: 44px;">
                                    <i class="fas fa-map-marker-alt" style="font-size: 1.1rem; flex-shrink: 0; color: var(--primary-color);"></i>
                                    <span style="flex: 1; font-weight: 500; word-break: break-word;"><?php echo htmlspecialchars($item['location']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($item['confirmation_number']): ?>
                                <div class="timeline-details" style="display: flex; align-items: start; gap: 0.5rem; padding: 0.75rem; background: rgba(74, 144, 226, 0.05); border-radius: 6px; min-height: 44px;">
                                    <i class="fas fa-bookmark" style="font-size: 1.1rem; flex-shrink: 0; color: var(--primary-color);"></i>
                                    <span style="flex: 1; word-break: break-word;"><strong>Confirmation:</strong> <?php echo htmlspecialchars($item['confirmation_number']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['description']): ?>
                            <div class="timeline-details" style="padding: 0.75rem; background: #f8f9fa; border-radius: 6px; border-left: 3px solid var(--border-color); margin-bottom: 0.75rem; word-break: break-word; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['notes'])): ?>
                            <div class="timeline-details" style="display: flex; align-items: start; gap: 0.5rem; padding: 0.75rem; background: rgba(255, 193, 7, 0.1); border-radius: 6px; min-height: 44px; border-left: 3px solid #FFC107; margin-bottom: 0.75rem;">
                                <i class="fas fa-sticky-note" style="font-size: 1.1rem; flex-shrink: 0; color: #F57C00;"></i>
                                <div style="flex: 1; word-break: break-word;">
                                    <div style="font-weight: 600; color: #F57C00; margin-bottom: 0.25rem;">Notes:</div>
                                    <div style="color: var(--text-color); line-height: 1.5;"><?php echo nl2br(htmlspecialchars($item['notes'])); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['type'] === 'flight'): ?>
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #E3F2FD;">
                                <?php if ((isset($item['flight_departure_icao']) && $item['flight_departure_icao']) || (isset($item['flight_arrival_icao']) && $item['flight_arrival_icao'])): ?>
                                    <div class="timeline-details" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%); border-radius: 8px; margin-bottom: 0.75rem; font-weight: 600; font-size: 0.95rem; color: #1976D2; min-height: 44px;">
                                        <i class="fas fa-plane" style="font-size: 1.2rem; flex-shrink: 0; color: #1976D2;"></i>
                                        <span style="flex: 1; word-break: break-word; line-height: 1.4;">
                                            Route: 
                                            <?php if (isset($item['flight_departure_icao']) && $item['flight_departure_icao']): ?>
                                                <strong><?php echo htmlspecialchars($item['flight_departure_icao']); ?></strong>
                                            <?php endif; ?>
                                            <?php if ((isset($item['flight_departure_icao']) && $item['flight_departure_icao']) && (isset($item['flight_arrival_icao']) && $item['flight_arrival_icao'])): ?> 
                                                <span style="margin: 0 0.5rem; color: var(--primary-color);">→</span>
                                            <?php endif; ?>
                                            <?php if (isset($item['flight_arrival_icao']) && $item['flight_arrival_icao']): ?>
                                                <strong><?php echo htmlspecialchars($item['flight_arrival_icao']); ?></strong>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php 
                            $depTimezone = $item['departure_timezone'] ?? 'UTC';
                            $arrTimezone = $item['arrival_timezone'] ?? 'UTC';
                            ?>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <?php if (isset($item['flight_departure_revised']) && $item['flight_departure_revised'] && isset($item['flight_departure_scheduled']) && $item['flight_departure_scheduled'] && $item['flight_departure_revised'] !== $item['flight_departure_scheduled']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #FFF3E0; border-left: 4px solid #FF9800; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #E65100; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-exclamation-triangle"></i> <span>Departure Delayed</span>
                                            </div>
                                            <div style="font-size: 0.85rem; color: var(--text-light); text-decoration: line-through; word-break: break-word;">Scheduled: <?php echo formatDateInTimezone($item['flight_departure_scheduled'], $depTimezone); ?></div>
                                            <div style="font-size: 0.9rem; color: #E65100; font-weight: 600; word-break: break-word;">Revised: <?php echo formatDateInTimezone($item['flight_departure_revised'], $depTimezone); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_arrival_revised']) && $item['flight_arrival_revised'] && isset($item['flight_arrival_scheduled']) && $item['flight_arrival_scheduled'] && $item['flight_arrival_revised'] !== $item['flight_arrival_scheduled']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #FFF3E0; border-left: 4px solid #FF9800; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #E65100; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-exclamation-triangle"></i> <span>Arrival Delayed</span>
                                            </div>
                                            <div style="font-size: 0.85rem; color: var(--text-light); text-decoration: line-through; word-break: break-word;">Scheduled: <?php echo formatDateInTimezone($item['flight_arrival_scheduled'], $arrTimezone); ?></div>
                                            <div style="font-size: 0.9rem; color: #E65100; font-weight: 600; word-break: break-word;">Revised: <?php echo formatDateInTimezone($item['flight_arrival_revised'], $arrTimezone); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_departure_runway']) && $item['flight_departure_runway']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #E8F5E9; border-left: 4px solid #4CAF50; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #2E7D32; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-check-circle"></i> <span>Actual Takeoff</span>
                                            </div>
                                            <div style="font-size: 0.9rem; color: #2E7D32; word-break: break-word;"><?php echo formatDateInTimezone($item['flight_departure_runway'], $depTimezone); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_arrival_runway']) && $item['flight_arrival_runway']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #E8F5E9; border-left: 4px solid #4CAF50; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #2E7D32; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-check-circle"></i> <span>Actual Landing</span>
                                            </div>
                                            <div style="font-size: 0.9rem; color: #2E7D32; word-break: break-word;"><?php echo formatDateInTimezone($item['flight_arrival_runway'], $arrTimezone); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_duration_minutes']) && $item['flight_duration_minutes']): ?>
                                        <?php 
                                        $hours = floor($item['flight_duration_minutes'] / 60);
                                        $minutes = $item['flight_duration_minutes'] % 60;
                                        ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #E3F2FD; border-left: 4px solid #2196F3; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #1976D2; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-clock"></i> <span>Duration</span>
                                            </div>
                                            <div style="font-size: 1rem; color: #1976D2; font-weight: 700;"><?php echo $hours; ?>h <?php echo $minutes; ?>m</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_aircraft_registration']) && $item['flight_aircraft_registration']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #F3E5F5; border-left: 4px solid #9C27B0; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #7B1FA2; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-plane"></i> <span>Aircraft</span>
                                            </div>
                                            <div style="font-size: 0.9rem; color: #7B1FA2; word-break: break-word;">
                                                <?php echo htmlspecialchars($item['flight_aircraft_registration']); ?>
                                                <?php if (isset($item['flight_aircraft_age']) && $item['flight_aircraft_age']): ?>
                                                    <span style="color: var(--text-light);"> - <?php echo $item['flight_aircraft_age']; ?> years old</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_status']) && $item['flight_status']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #FFF9C4; border-left: 4px solid #FBC02D; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #F57F17; margin-bottom: 0.25rem;">Status</div>
                                            <div style="font-size: 0.9rem; color: #F57F17; font-weight: 600; text-transform: uppercase; word-break: break-word;"><?php echo htmlspecialchars($item['flight_status']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($item['flight_codeshare']) && $item['flight_codeshare']): ?>
                                        <div class="timeline-details" style="padding: 0.75rem; background: #ECEFF1; border-left: 4px solid #607D8B; border-radius: 6px; min-height: 44px;">
                                            <div style="font-weight: 600; color: #455A64; margin-bottom: 0.25rem;">Codeshare</div>
                                            <div style="font-size: 0.9rem; color: #455A64; word-break: break-word;"><?php echo htmlspecialchars($item['flight_codeshare']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['created_by_email'] || $item['modified_by_email']): ?>
                            <div class="timeline-details" style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.5rem;">
                                <?php if ($item['created_by_email']): ?>
                                    Created by: <?php 
                                    echo htmlspecialchars(trim(($item['created_by_first'] ?? '') . ' ' . ($item['created_by_last'] ?? '')) ?: $item['created_by_email']); 
                                    ?>
                                <?php endif; ?>
                                <?php if ($item['modified_by_email'] && $item['modified_by_email'] !== $item['created_by_email']): ?>
                                    <?php echo $item['created_by_email'] ? ' • ' : ''; ?>
                                    Modified by: <?php 
                                    echo htmlspecialchars(trim(($item['modified_by_first'] ?? '') . ' ' . ($item['modified_by_last'] ?? '')) ?: $item['modified_by_email']); 
                                    ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="trip_detail.php?id=<?php echo $tripId; ?>&action=edit_item&item_id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-small" style="margin: 0; flex: 1; min-width: 80px; text-align: center; padding: 0.6rem 1rem; font-size: 0.9rem;">Edit</a>
                                <button onclick="deleteItem(<?php echo $item['id']; ?>)" class="btn btn-danger btn-small" style="margin: 0; flex: 1; min-width: 80px; padding: 0.6rem 1rem; font-size: 0.9rem;">Delete</button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($itemDocuments[$item['id']]) && !empty($itemDocuments[$item['id']])): ?>
                            <div class="timeline-documents">
                                <?php foreach ($itemDocuments[$item['id']] as $doc): ?>
                                    <?php
                                    // Remove extension from filename display
                                    $docFilenameWithoutExt = pathinfo($doc['original_filename'], PATHINFO_FILENAME);
                                    ?>
                                    <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" 
                                       target="_blank" 
                                       class="document-badge"
                                       title="<?php echo htmlspecialchars($doc['original_filename']); ?>">
                                        <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($docFilenameWithoutExt ?: $doc['original_filename']); ?>
                                    </a>
                                    <?php if ($canEdit): ?>
                                        <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                                class="btn btn-danger btn-small" 
                                                style="margin: 0; padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                            ×
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($canEdit): ?>
                                <div style="margin-top: 0.5rem;">
                                    <form class="upload-form file-upload-form" data-item-id="<?php echo $item['id']; ?>">
                                        <div class="file-input-wrapper">
                                            <input type="file" name="file[]" id="file_<?php echo $item['id']; ?>" class="file-input" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.json,.xml" multiple required>
                                            <label for="file_<?php echo $item['id']; ?>" class="file-input-label">
                                                <i class="fas fa-paperclip file-icon"></i>
                                                <span class="file-label-text">Choose files or drag here</span>
                                            </label>
                                            <div class="file-name-display">
                                                <div class="file-name">
                                                    <i class="fas fa-file"></i>
                                                    <span class="file-name-text"></span>
                                                </div>
                                                <div class="file-size"></div>
                                            </div>
                                            <button type="button" class="file-input-clear">Clear</button>
                                        </div>
                                        <div class="upload-button-row">
                                            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                            <input type="hidden" name="travel_item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-small" style="margin: 0;">Upload</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($canEdit): ?>
                                <div style="margin-top: 0.5rem;">
                                    <form class="upload-form file-upload-form" data-item-id="<?php echo $item['id']; ?>">
                                        <div class="file-input-wrapper">
                                            <input type="file" name="file[]" id="file_item_<?php echo $item['id']; ?>" class="file-input" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.json,.xml" multiple required>
                                            <label for="file_item_<?php echo $item['id']; ?>" class="file-input-label">
                                                <i class="fas fa-paperclip file-icon"></i>
                                                <span class="file-label-text">Choose files or drag here</span>
                                            </label>
                                            <div class="file-name-display">
                                                <div class="file-name">
                                                    <i class="fas fa-file"></i>
                                                    <span class="file-name-text"></span>
                                                </div>
                                                <div class="file-size"></div>
                                            </div>
                                            <button type="button" class="file-input-clear">Clear</button>
                                        </div>
                                        <div class="upload-button-row">
                                            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                            <input type="hidden" name="travel_item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-small" style="margin: 0;">Upload Doc</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No travel items yet. Add your first item above!</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($tripDocuments)): ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 class="card-title" style="margin: 0;">Trip Documents</h3>
            <span class="document-count-badge"><?php echo count($tripDocuments); ?> document<?php echo count($tripDocuments) !== 1 ? 's' : ''; ?></span>
        </div>
        <div class="document-list">
            <?php foreach ($tripDocuments as $doc): ?>
                <?php
                // Get file icon based on extension and type
                $extension = strtolower(pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
                $fileIcon = 'fa-file';
                $fileIconColor = 'var(--text-light)';
                
                // Determine file type and icon
                if (strpos($doc['file_type'], 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $fileIcon = 'fa-image';
                    $fileIconColor = 'var(--primary-color)';
                } elseif ($doc['file_type'] === 'application/pdf' || $extension === 'pdf') {
                    $fileIcon = 'fa-file-pdf';
                    $fileIconColor = '#E74C3C';
                } elseif (in_array($extension, ['doc', 'docx']) || strpos($doc['file_type'], 'wordprocessingml') !== false || strpos($doc['file_type'], 'msword') !== false) {
                    $fileIcon = 'fa-file-word';
                    $fileIconColor = '#2B579A';
                } elseif (in_array($extension, ['xls', 'xlsx']) || strpos($doc['file_type'], 'spreadsheetml') !== false || strpos($doc['file_type'], 'ms-excel') !== false) {
                    $fileIcon = 'fa-file-excel';
                    $fileIconColor = '#1D6F42';
                } elseif (in_array($extension, ['ppt', 'pptx']) || strpos($doc['file_type'], 'presentationml') !== false || strpos($doc['file_type'], 'ms-powerpoint') !== false) {
                    $fileIcon = 'fa-file-powerpoint';
                    $fileIconColor = '#D04423';
                } elseif (in_array($extension, ['txt', 'text']) || strpos($doc['file_type'], 'text/') === 0) {
                    $fileIcon = 'fa-file-alt';
                    $fileIconColor = 'var(--primary-color)';
                } elseif ($extension === 'csv' || $doc['file_type'] === 'text/csv') {
                    $fileIcon = 'fa-file-csv';
                    $fileIconColor = '#1D6F42';
                } elseif (in_array($extension, ['json', 'xml']) || strpos($doc['file_type'], 'json') !== false || strpos($doc['file_type'], 'xml') !== false) {
                    $fileIcon = 'fa-file-code';
                    $fileIconColor = 'var(--primary-color)';
                }
                
                // Format file size
                $fileSize = $doc['file_size'] ?? 0;
                $fileSizeFormatted = '';
                if ($fileSize < 1024) {
                    $fileSizeFormatted = $fileSize . ' B';
                } elseif ($fileSize < 1024 * 1024) {
                    $fileSizeFormatted = round($fileSize / 1024, 1) . ' KB';
                } else {
                    $fileSizeFormatted = round($fileSize / (1024 * 1024), 1) . ' MB';
                }
                
                // Format upload date
                $uploadDate = isset($doc['upload_date']) ? date('M j, Y', strtotime($doc['upload_date'])) : '';
                ?>
                <div class="document-list-item" data-document-id="<?php echo $doc['id']; ?>">
                    <div class="document-list-link-wrapper">
                        <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" target="_blank" class="document-list-link">
                            <div class="document-list-icon">
                                <i class="fas <?php echo $fileIcon; ?>" style="color: <?php echo $fileIconColor; ?>;"></i>
                            </div>
                            <div class="document-list-info">
                                <div class="document-list-name" title="<?php echo htmlspecialchars($doc['original_filename']); ?>">
                                    <?php 
                                    // Remove extension from filename display
                                    $filenameWithoutExt = pathinfo($doc['original_filename'], PATHINFO_FILENAME);
                                    echo htmlspecialchars($filenameWithoutExt ?: $doc['original_filename']); 
                                    ?>
                                </div>
                                <div class="document-list-meta">
                                    <span class="document-list-size"><?php echo $fileSizeFormatted; ?></span>
                                    <?php if ($uploadDate): ?>
                                        <span class="document-list-date"><?php echo $uploadDate; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <div class="document-list-actions">
                            <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>&download=1" target="_blank" class="document-list-download" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php if ($canEdit): ?>
                                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                        class="document-list-delete" 
                                        title="Delete document"
                                        aria-label="Delete <?php echo htmlspecialchars($doc['original_filename']); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 class="card-title">Upload Trip Document</h3>
        <form id="tripDocumentForm" class="file-upload-form">
            <div class="file-input-wrapper">
                <input type="file" name="file[]" id="trip_file_input" class="file-input" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.json,.xml" multiple required>
                <label for="trip_file_input" class="file-input-label">
                    <i class="fas fa-cloud-upload-alt file-icon"></i>
                    <span class="file-label-text">Choose files or drag here</span>
                    <span class="file-label-hint">Supports: Images, PDF, Word, Excel, PowerPoint, Text, CSV, JSON, XML (Multiple files allowed)</span>
                </label>
                <div class="file-name-display">
                    <div class="file-name">
                        <i class="fas fa-file"></i>
                        <span class="file-name-text"></span>
                    </div>
                    <div class="file-size"></div>
                </div>
                <button type="button" class="file-input-clear">Clear</button>
            </div>
            <div id="tripDocumentUploadStatus" class="upload-status" style="display: none;"></div>
            <div class="upload-button-row">
                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                <button type="submit" class="btn btn-small" id="tripDocumentUploadBtn" style="margin: 0;">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Custom Modal JavaScript - Must be loaded before functions that use it -->
<script>
// Custom Modal System
const customModal = {
    modal: null,
    overlay: null,
    title: null,
    body: null,
    footer: null,
    closeBtn: null,
    resolve: null,
    
    init() {
        this.modal = document.getElementById('customModal');
        this.overlay = this.modal?.querySelector('.custom-modal-overlay');
        this.title = document.getElementById('modalTitle');
        this.body = document.getElementById('modalBody');
        this.footer = document.getElementById('modalFooter');
        this.closeBtn = document.getElementById('modalCloseBtn');
        
        if (this.overlay) {
            this.overlay.addEventListener('click', () => {
                if (this.resolve) {
                    this.resolve(false);
                }
                this.close(false);
            });
        }
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => {
                if (this.resolve) {
                    this.resolve(false);
                }
                this.close(false);
            });
        }
        
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal?.style.display !== 'none') {
                this.close();
            }
        });
    },
    
    show(title, message, type = 'alert', options = {}) {
        return new Promise((resolve) => {
            this.resolve = resolve;
            
            if (!this.modal) {
                this.init();
            }
            
            // Set title
            if (this.title) {
                this.title.textContent = title;
            }
            
            // Set body
            if (this.body) {
                if (type === 'dialog' && options.html) {
                    // For dialog type, use HTML directly
                    this.body.innerHTML = message;
                } else {
                    // For other types, wrap in paragraph
                    this.body.innerHTML = `<p>${message}</p>`;
                }
            }
            
            // Set footer buttons based on type
            if (this.footer) {
                if (type === 'dialog' && options.footer) {
                    // For dialog type, use custom footer HTML
                    this.footer.innerHTML = options.footer;
                } else if (type === 'confirm') {
                    this.footer.innerHTML = `
                        <button type="button" class="btn btn-secondary" id="modalCancelBtn">Cancel</button>
                        <button type="button" class="btn btn-danger" id="modalConfirmBtn">${options.confirmText || 'Confirm'}</button>
                    `;
                    
                    const cancelBtn = document.getElementById('modalCancelBtn');
                    const confirmBtn = document.getElementById('modalConfirmBtn');
                    
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', () => {
                            resolve(false);
                            this.close(false); // Don't resolve again
                        });
                    }
                    
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', () => {
                            resolve(true);
                            this.close(false); // Don't resolve again
                        });
                        confirmBtn.focus();
                    }
                } else {
                    this.footer.innerHTML = `
                        <button type="button" class="btn" id="modalOkBtn">OK</button>
                    `;
                    
                    const okBtn = document.getElementById('modalOkBtn');
                    if (okBtn) {
                        okBtn.addEventListener('click', () => {
                            resolve(true);
                            this.close(false); // Don't resolve again
                        });
                        okBtn.focus();
                    }
                }
            }
            
            // Show modal
            if (this.modal) {
                this.modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Trigger animation
                setTimeout(() => {
                    if (this.modal) {
                        this.modal.classList.add('show');
                    }
                }, 10);
            }
        });
    },
    
    close(shouldResolve = true) {
        if (this.modal) {
            this.modal.classList.remove('show');
            setTimeout(() => {
                if (this.modal) {
                    this.modal.style.display = 'none';
                }
                document.body.style.overflow = '';
            }, 300);
        }
        if (shouldResolve && this.resolve) {
            this.resolve(false);
            this.resolve = null;
        } else if (!shouldResolve) {
            // Clear resolve without calling it (already resolved)
            this.resolve = null;
        }
    }
};

// Custom Alert function - Make available globally immediately
window.customAlert = function(message, title = 'Alert') {
    return customModal.show(title, message, 'alert');
};

// Custom Confirm function - Make available globally immediately
window.customConfirm = function(message, title = 'Confirm', options = {}) {
    return customModal.show(title, message, 'confirm', options);
};

// Initialize modal on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        customModal.init();
    });
} else {
    // DOM already loaded
    customModal.init();
}
</script>

<script>
// Toggle between single and multiple destinations (edit form)
function toggleEditDestinations() {
    const destinationType = document.querySelector('input[name="edit_destination_type"]:checked')?.value;
    if (!destinationType) return;
    
    const singleGroup = document.getElementById('edit_single_destination_group');
    const multipleGroup = document.getElementById('edit_multiple_destinations_group');
    const singleInput = document.getElementById('edit_single_destination');
    
    if (destinationType === 'single') {
        if (singleGroup) singleGroup.style.display = 'block';
        if (multipleGroup) multipleGroup.style.display = 'none';
        if (singleInput) singleInput.required = true;
        document.querySelectorAll('#edit_multiple_destinations_group input[name="edit_destinations[]"]').forEach(input => {
            input.required = false;
        });
    } else {
        if (singleGroup) singleGroup.style.display = 'none';
        if (multipleGroup) multipleGroup.style.display = 'block';
        if (singleInput) singleInput.required = false;
        document.querySelectorAll('#edit_multiple_destinations_group input[name="edit_destinations[]"]').forEach(input => {
            input.required = true;
        });
    }
}

// Add destination field (edit form)
function addEditDestination() {
    const container = document.getElementById('edit_destinations_container');
    if (!container) return;
    
    const div = document.createElement('div');
    div.className = 'destination-item';
    div.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.5rem;';
    div.innerHTML = `
        <div style="position: relative; flex: 1;">
            <input type="text" name="edit_destinations[]" class="form-input destination-autocomplete" 
                   placeholder="e.g., Paris, France" 
                   autocomplete="off"
                   required>
            <div class="autocomplete-dropdown" style="display: none;"></div>
        </div>
        <button type="button" class="btn btn-small" onclick="removeEditDestination(this)" style="min-width: 44px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
    initAutocomplete(div.querySelector('.destination-autocomplete'));
}

// Remove destination field (edit form)
function removeEditDestination(button) {
    const container = document.getElementById('edit_destinations_container');
    if (!container) return;
    
    if (container.children.length > 1) {
        button.closest('.destination-item').remove();
    } else {
        customAlert('You must have at least one destination.', 'Validation Error');
    }
}

// Edit trip form
document.getElementById('editTripForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Handle destinations based on type
    const destinationType = document.querySelector('input[name="edit_destination_type"]:checked')?.value;
    if (destinationType === 'single') {
        const singleDest = document.getElementById('edit_single_destination');
        const singleDestData = singleDest?.dataset.destinationData;
        if (singleDest && singleDest.value.trim()) {
            let destObj = {name: singleDest.value.trim()};
            if (singleDestData) {
                try {
                    const data = JSON.parse(singleDestData);
                    destObj = {...destObj, ...data};
                } catch(e) {}
            }
            formData.set('destinations', JSON.stringify([destObj]));
            formData.set('is_multiple_destinations', '0');
        }
    } else {
        const destinations = Array.from(document.querySelectorAll('#edit_multiple_destinations_group input[name="edit_destinations[]"]'))
            .map(input => {
                let destObj = {name: input.value.trim()};
                if (input.dataset.destinationData) {
                    try {
                        const data = JSON.parse(input.dataset.destinationData);
                        destObj = {...destObj, ...data};
                    } catch(e) {}
                }
                return destObj;
            })
            .filter(dest => dest.name);
        if (destinations.length > 0) {
            formData.set('destinations', JSON.stringify(destinations));
            formData.set('is_multiple_destinations', '1');
        }
    }
    
    // Remove the original destination inputs from formData
    formData.delete('edit_single_destination');
    formData.delete('edit_destinations[]');
    formData.delete('edit_destination_type');
    
    fetch('../api/update_trip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
        } else {
            customAlert(data.message, 'Error');
        }
    })
    .catch(error => {
        customAlert('Error updating trip', 'Error');
        console.error(error);
    });
});

// Delete trip
async function deleteTrip(tripId) {
    const confirmed = await customConfirm(
        'Are you sure you want to delete this trip? All travel items and documents will be deleted.',
        'Delete Trip',
        { confirmText: 'Delete' }
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('trip_id', tripId);
    
    fetch('../api/delete_trip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            customAlert(data.message, 'Error');
        }
    })
    .catch(error => {
        customAlert('Error deleting trip', 'Error');
        console.error(error);
    });
}

// Progressive Flight Lookup Wizard
const typeSelect = document.getElementById('type');
const flightNumberGroup = document.getElementById('flight_number_group');
const flightNumberInput = document.getElementById('flight_number');
const flightDateInput = document.getElementById('flight_date');
const flightLookupStatus = document.getElementById('flight_lookup_status');

// Step elements
const step1 = document.getElementById('flight_step_1');
const step2 = document.getElementById('flight_step_2');
const step3 = document.getElementById('flight_step_3');
const flightResultsContainer = document.getElementById('flight_results_container');

// Store current flight number
let currentFlightNumber = '';

// Show/hide flight number field based on type selection
if (typeSelect && flightNumberGroup) {
    const hotelFieldsGroup = document.getElementById('hotel_fields_group');
    const typeSelectionMessage = document.getElementById('type_selection_message');
    const travelItemFormFields = document.getElementById('travel_item_form_fields');
    
    // Function to handle type selection visibility
    function handleTypeSelection() {
        const selectedType = typeSelect.value;
        
        if (!selectedType || selectedType === '') {
            // No type selected - show message, hide form fields
            if (typeSelectionMessage) typeSelectionMessage.style.display = 'block';
            if (travelItemFormFields) travelItemFormFields.style.display = 'none';
            if (flightNumberGroup) flightNumberGroup.style.display = 'none';
            if (hotelFieldsGroup) hotelFieldsGroup.style.display = 'none';
            // Remove required attributes from all form fields when hidden
            if (flightDateInput) {
                flightDateInput.removeAttribute('required');
            }
            const itemTitle = document.getElementById('item_title');
            const startDatetime = document.getElementById('start_datetime');
            if (itemTitle) itemTitle.removeAttribute('required');
            if (startDatetime) startDatetime.removeAttribute('required');
        } else {
            // Type selected - hide message, show form fields
            if (typeSelectionMessage) typeSelectionMessage.style.display = 'none';
            if (travelItemFormFields) travelItemFormFields.style.display = 'block';
            
            // Add required attributes to main form fields when visible
            const itemTitle = document.getElementById('item_title');
            const startDatetime = document.getElementById('start_datetime');
            if (itemTitle) itemTitle.setAttribute('required', 'required');
            if (startDatetime) startDatetime.setAttribute('required', 'required');
            
            // Handle specific type fields
            if (selectedType === 'flight') {
                flightNumberGroup.style.display = 'block';
                if (hotelFieldsGroup) hotelFieldsGroup.style.display = 'none';
                // Add required attribute to flight_date when visible
                if (flightDateInput) {
                    flightDateInput.setAttribute('required', 'required');
                }
                // Reset wizard to step 1 (date selection)
                if (typeof showFlightStep === 'function') {
                    showFlightStep(1);
                }
                // Clear previous values
                if (flightNumberInput) flightNumberInput.value = '';
                if (flightDateInput) {
                    flightDateInput.value = new Date().toISOString().split('T')[0];
                }
                if (flightLookupStatus) flightLookupStatus.style.display = 'none';
                if (flightResultsContainer) flightResultsContainer.innerHTML = '';
            } else if (selectedType === 'hotel') {
                if (hotelFieldsGroup) hotelFieldsGroup.style.display = 'block';
                if (flightNumberGroup) flightNumberGroup.style.display = 'none';
                // Remove required attribute from flight_date when hidden
                if (flightDateInput) {
                    flightDateInput.removeAttribute('required');
                }
                if (flightNumberInput) flightNumberInput.value = '';
                if (flightDateInput) flightDateInput.value = '';
            } else {
                flightNumberGroup.style.display = 'none';
                if (hotelFieldsGroup) hotelFieldsGroup.style.display = 'none';
                // Remove required attribute from flight_date when hidden
                if (flightDateInput) {
                    flightDateInput.removeAttribute('required');
                }
                if (flightNumberInput) flightNumberInput.value = '';
                if (flightDateInput) flightDateInput.value = '';
                if (flightLookupStatus) flightLookupStatus.style.display = 'none';
                if (typeof showFlightStep === 'function') {
                    showFlightStep(1);
                }
            }
        }
    }
    
    // Handle initial state on page load
    handleTypeSelection();
    
    // Handle type selection changes
    typeSelect.addEventListener('change', handleTypeSelection);
}

// Step navigation functions
function showFlightStep(step) {
    if (step1) step1.style.display = step === 1 ? 'block' : 'none';
    if (step2) step2.style.display = step === 2 ? 'block' : 'none';
    if (step3) step3.style.display = step === 3 ? 'block' : 'none';
}

// Step 1: Next button - validate date and go to step 2
const flightNextStep1 = document.getElementById('flight_next_step1');
if (flightNextStep1 && flightDateInput) {
    flightNextStep1.addEventListener('click', function() {
        const date = flightDateInput.value;
        
        if (!date) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Please select a flight date';
            return;
        }
        
        // Validate date is not in the past (optional - you can remove this if you want to allow past dates)
        const selectedDate = new Date(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#856404';
            flightLookupStatus.textContent = 'Note: Past dates may have limited flight information available.';
        } else {
            flightLookupStatus.style.display = 'none';
        }
        
        showFlightStep(2);
        
        // Focus on flight number input
        if (flightNumberInput) {
            setTimeout(() => flightNumberInput.focus(), 100);
        }
    });
    
    // Enter key on date input
    flightDateInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            flightNextStep1.click();
        }
    });
    
    // Set default date to today when wizard opens
    if (flightDateInput && !flightDateInput.value) {
        flightDateInput.value = new Date().toISOString().split('T')[0];
    }
}

// Step 2: Back button - go back to date selection
const flightBackStep2 = document.getElementById('flight_back_step2');
if (flightBackStep2) {
    flightBackStep2.addEventListener('click', function() {
        showFlightStep(1);
        flightLookupStatus.style.display = 'none';
    });
}

// Step 2: Search flights button
const flightSearchStep2 = document.getElementById('flight_search_step2');
if (flightSearchStep2 && flightDateInput && flightNumberInput) {
    flightSearchStep2.addEventListener('click', async function() {
        const date = flightDateInput.value;
        const flightNumber = flightNumberInput.value.trim().toUpperCase();
        
        if (!date) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Please select a date first';
            showFlightStep(1);
            return;
        }
        
        if (!flightNumber) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Please enter a flight number';
            return;
        }
        
        // Validate flight number format
        if (!/^[A-Z]{2}[0-9]{1,4}[A-Z]?$/.test(flightNumber)) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Invalid format. Use format like AA123 or AA1234';
            return;
        }
        
        currentFlightNumber = flightNumber;
        
        // Show loading state
        flightSearchStep2.disabled = true;
        flightSearchStep2.textContent = 'Searching...';
        flightLookupStatus.style.display = 'block';
        flightLookupStatus.style.color = '#666';
        flightLookupStatus.textContent = 'Searching for flights...';
        
        if (flightResultsContainer) {
            flightResultsContainer.innerHTML = '';
        }
        
        try {
            // Add timeout to fetch request
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 20000); // 20 second timeout
            
            const response = await fetch(`../api/lookup_flight.php?flight=${encodeURIComponent(flightNumber)}&date=${encodeURIComponent(date)}`, {
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.data) {
                // Always show results, even if single flight
                let flights = [];
                if (Array.isArray(data.data)) {
                    flights = data.data;
                } else {
                    flights = [data.data];
                }
                
                if (flights.length > 0) {
                    showFlightResults(flights);
                    showFlightStep(3);
                    flightLookupStatus.style.display = 'none'; // Hide status when showing results
                } else {
                    flightLookupStatus.style.color = '#856404';
                    flightLookupStatus.textContent = 'No flights found for this flight number on the selected date.';
                }
            } else {
                flightLookupStatus.style.color = '#856404';
                flightLookupStatus.textContent = data.message || 'No flights found. Please try a different date or enter details manually.';
            }
        } catch (error) {
            console.error('Flight lookup error:', error);
            flightLookupStatus.style.color = '#C62828';
            if (error.name === 'AbortError') {
                flightLookupStatus.textContent = 'Request timed out. The API may be slow or unavailable. Please try again.';
            } else {
                flightLookupStatus.textContent = `Error searching for flights: ${error.message}. Please check the browser console (F12) for details.`;
            }
        } finally {
            flightSearchStep2.disabled = false;
            flightSearchStep2.innerHTML = '<i class="fas fa-search"></i> Search Flights';
        }
    });
    
    // Enter key on flight number input (step 2)
    if (flightNumberInput) {
        flightNumberInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                flightSearchStep2.click();
            }
        });
    }
}

// Step 3: Back button
const flightBackStep3 = document.getElementById('flight_back_step3');
if (flightBackStep3) {
    flightBackStep3.addEventListener('click', function() {
        showFlightStep(2);
    });
}

// Helper function to format date/time for display in a specific timezone
function formatFlightDateTime(dateTimeString, timezone = null) {
    if (!dateTimeString) return 'N/A';
    
    try {
        let date = new Date(dateTimeString);
        if (isNaN(date.getTime())) {
            date = new Date(dateTimeString.replace(' ', 'T'));
        }
        if (!isNaN(date.getTime())) {
            const options = {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            };
            
            // If timezone is provided, use it; otherwise use browser's local timezone
            if (timezone && timezone !== 'UTC') {
                options.timeZone = timezone;
                options.timeZoneName = 'short';
            }
            
            return date.toLocaleString('en-US', options);
        }
    } catch (e) {
        // Return original if can't parse
    }
    return dateTimeString;
}

// Function to display flight results
function showFlightResults(flights) {
    if (!flightResultsContainer) return;
    
    flightResultsContainer.innerHTML = '';
    
    if (flights.length === 0) {
        flightResultsContainer.innerHTML = '<p style="color: #856404; padding: 1rem; text-align: center;">No flights found.</p>';
        return;
    }
    
    // Show header if multiple flights
    if (flights.length > 1) {
        const header = document.createElement('div');
        header.style.cssText = 'padding: 0.75rem; margin-bottom: 1rem; background: #E3F2FD; border-radius: 8px; text-align: center; font-weight: 500; color: #1976D2;';
        header.textContent = `Found ${flights.length} flights - Click to select one:`;
        flightResultsContainer.appendChild(header);
    }
    
    flights.forEach((flight, index) => {
        const flightCard = document.createElement('div');
        flightCard.className = 'flight-result-card';
        flightCard.style.cssText = 'padding: 1.25rem; border: 2px solid var(--border-color); border-radius: 12px; margin-bottom: 1rem; cursor: pointer; transition: all 0.2s; background: white; position: relative;';
        
        // Parse times for display - prefer revised times if available (shows delays/changes)
        const depScheduledStr = flight.departure_scheduled || flight.departure_time || flight.departure_scheduledTimeLocal || flight.departure_scheduledTimeUtc;
        const depRevisedStr = flight.departure_revised;
        const depRunwayStr = flight.departure_runway;
        const depTimeDisplay = depRevisedStr || depScheduledStr; // Show revised if available
        
        const arrScheduledStr = flight.arrival_scheduled || flight.arrival_time || flight.arrival_scheduledTimeLocal || flight.arrival_scheduledTimeUtc;
        const arrRevisedStr = flight.arrival_revised;
        const arrRunwayStr = flight.arrival_runway;
        const arrTimeDisplay = arrRevisedStr || arrScheduledStr; // Show revised if available
        
        // Get timezones for departure and arrival
        const depTimezone = flight.departure_timezone || null;
        const arrTimezone = flight.arrival_timezone || null;
        
        const depScheduled = formatFlightDateTime(depScheduledStr, depTimezone);
        const depRevised = depRevisedStr ? formatFlightDateTime(depRevisedStr, depTimezone) : null;
        const depRunway = depRunwayStr ? formatFlightDateTime(depRunwayStr, depTimezone) : null;
        const depTime = formatFlightDateTime(depTimeDisplay, depTimezone);
        
        const arrScheduled = formatFlightDateTime(arrScheduledStr, arrTimezone);
        const arrRevised = arrRevisedStr ? formatFlightDateTime(arrRevisedStr, arrTimezone) : null;
        const arrRunway = arrRunwayStr ? formatFlightDateTime(arrRunwayStr, arrTimezone) : null;
        const arrTime = formatFlightDateTime(arrTimeDisplay, arrTimezone);
        
        // Check if there are delays/changes
        const hasDepDelay = depRevisedStr && depRevisedStr !== depScheduledStr;
        const hasArrDelay = arrRevisedStr && arrRevisedStr !== arrScheduledStr;
        
        // Build route display (include ICAO if IATA not available)
        const depAirport = flight.departure_airport || flight.departure_city || '';
        const arrAirport = flight.arrival_airport || flight.arrival_city || '';
        const depCode = flight.departure_iata || flight.departure_icao || '';
        const arrCode = flight.arrival_iata || flight.arrival_icao || '';
        const route = `${depCode} ${depAirport ? `(${depAirport})` : ''} → ${arrCode} ${arrAirport ? `(${arrAirport})` : ''}`.trim();
        
        // Build terminal/gate info
        let depInfo = '';
        if (flight.departure_terminal || flight.departure_gate) {
            const parts = [];
            if (flight.departure_terminal) parts.push(`Terminal ${flight.departure_terminal}`);
            if (flight.departure_gate) parts.push(`Gate ${flight.departure_gate}`);
            depInfo = ` (${parts.join(', ')})`;
        }
        
        let arrInfo = '';
        if (flight.arrival_terminal || flight.arrival_gate) {
            const parts = [];
            if (flight.arrival_terminal) parts.push(`Terminal ${flight.arrival_terminal}`);
            if (flight.arrival_gate) parts.push(`Gate ${flight.arrival_gate}`);
            arrInfo = ` (${parts.join(', ')})`;
        }
        
        // Flight duration
        let durationDisplay = '';
        if (flight.duration_minutes) {
            const hours = Math.floor(flight.duration_minutes / 60);
            const minutes = flight.duration_minutes % 60;
            durationDisplay = `${hours}h ${minutes}m`;
        } else if (flight.duration_formatted) {
            durationDisplay = flight.duration_formatted;
        }
        
        // Aircraft details
        let aircraftDisplay = '';
        if (flight.aircraft_model || flight.aircraft) {
            aircraftDisplay = flight.aircraft_model || flight.aircraft;
            if (flight.aircraft_registration) {
                aircraftDisplay += ` (${flight.aircraft_registration})`;
            }
            if (flight.aircraft_age) {
                aircraftDisplay += ` - ${flight.aircraft_age} years old`;
            }
        }
        
        flightCard.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <div style="font-weight: 600; color: var(--text-color); font-size: 1.15rem;">
                    ${flight.airline || 'Unknown Airline'} ${flight.flight_number || currentFlightNumber}
                </div>
                ${flights.length > 1 ? `<div style="background: var(--primary-color); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Option ${index + 1}</div>` : ''}
            </div>
            <div style="font-size: 0.95rem; color: var(--text-color); margin-bottom: 0.75rem; font-weight: 500;">
                ${route || 'Route information not available'}
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                <div style="padding: 0.75rem; background: ${hasDepDelay ? '#FFF3E0' : '#F5F5F5'}; border-radius: 6px; ${hasDepDelay ? 'border-left: 3px solid #FF9800;' : ''}">
                    <div style="font-size: 0.75rem; color: var(--text-light); margin-bottom: 0.25rem; text-transform: uppercase; font-weight: 600;">Departure</div>
                    <div style="font-size: 0.9rem; color: ${hasDepDelay ? '#E65100' : 'var(--text-color)'}; font-weight: 500;">${depTime}</div>
                    ${hasDepDelay && depScheduled ? `<div style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.25rem; text-decoration: line-through;">Scheduled: ${depScheduled}</div>` : ''}
                    ${depRunway ? `<div style="font-size: 0.75rem; color: #4CAF50; margin-top: 0.25rem; font-weight: 500;"><i class="fas fa-check-circle"></i> Actual Takeoff: ${depRunway}</div>` : ''}
                    ${depInfo ? `<div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">${depInfo}</div>` : ''}
                </div>
                <div style="padding: 0.75rem; background: ${hasArrDelay ? '#FFF3E0' : '#F5F5F5'}; border-radius: 6px; ${hasArrDelay ? 'border-left: 3px solid #FF9800;' : ''}">
                    <div style="font-size: 0.75rem; color: var(--text-light); margin-bottom: 0.25rem; text-transform: uppercase; font-weight: 600;">Arrival</div>
                    <div style="font-size: 0.9rem; color: ${hasArrDelay ? '#E65100' : 'var(--text-color)'}; font-weight: 500;">${arrTime}</div>
                    ${hasArrDelay && arrScheduled ? `<div style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.25rem; text-decoration: line-through;">Scheduled: ${arrScheduled}</div>` : ''}
                    ${arrRunway ? `<div style="font-size: 0.75rem; color: #4CAF50; margin-top: 0.25rem; font-weight: 500;"><i class="fas fa-check-circle"></i> Actual Landing: ${arrRunway}</div>` : ''}
                    ${arrInfo ? `<div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">${arrInfo}</div>` : ''}
                </div>
            </div>
            ${durationDisplay ? `<div style="font-size: 0.85rem; color: var(--text-color); margin-top: 0.5rem; font-weight: 500;"><i class="fas fa-clock"></i> Flight Duration: ${durationDisplay}</div>` : ''}
            ${flight.status ? `<div style="font-size: 0.85rem; color: var(--primary-color); margin-top: 0.5rem; font-weight: 500;">Status: ${flight.status}</div>` : ''}
            ${aircraftDisplay ? `<div style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.25rem;"><i class="fas fa-plane"></i> Aircraft: ${aircraftDisplay}</div>` : ''}
            ${flight.codeshare ? `<div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">Codeshare: ${flight.codeshare}</div>` : ''}
            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color); text-align: center; color: var(--primary-color); font-weight: 500; font-size: 0.9rem;">
                Click to select this flight →
            </div>
        `;
        
        flightCard.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary-color)';
            this.style.background = '#F8F9FA';
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(74, 144, 226, 0.2)';
        });
        
        flightCard.addEventListener('mouseleave', function() {
            this.style.borderColor = 'var(--border-color)';
            this.style.background = 'white';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
        
        flightCard.addEventListener('click', function() {
            // Fill form with selected flight
            fillFlightForm(flight);
            
            // Hide wizard and show success
            document.getElementById('flight_lookup_wizard').style.display = 'none';
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#2E7D32';
            flightLookupStatus.innerHTML = `<i class="fas fa-check-circle"></i> Flight selected: ${flight.airline || ''} ${flight.flight_number || currentFlightNumber} - All details filled in!`;
            
            // Reset wizard for next time
            showFlightStep(1);
            if (flightNumberInput) flightNumberInput.value = '';
            if (flightDateInput) {
                flightDateInput.value = new Date().toISOString().split('T')[0];
            }
            
            // Clear results
            flightResultsContainer.innerHTML = '';
        });
        
        flightResultsContainer.appendChild(flightCard);
    });
}

// Helper function to parse date/time from various formats
// Preserves the actual date/time without timezone conversion
function parseFlightDateTime(dateTimeString, fallbackDate = null) {
    if (!dateTimeString) {
        // If no time string but we have a fallback date, use that date with default time
        if (fallbackDate) {
            try {
                // Parse fallback date (should be YYYY-MM-DD format)
                const dateParts = fallbackDate.split('-');
                if (dateParts.length === 3) {
                    return `${fallbackDate}T00:00`;
                }
            } catch (e) {
                console.error('Error using fallback date:', e);
            }
        }
        return null;
    }
    
    try {
        // Extract date and time components directly from the string
        // Handle formats like:
        // - "2024-12-15T10:30:00" (ISO without timezone)
        // - "2024-12-15T10:30:00+00:00" (ISO with timezone)
        // - "2024-12-15 10:30:00" (space separated)
        // - "2024-12-15 10:30" (space separated, no seconds)
        
        let dateStr = '';
        let timeStr = '';
        
        // Check if it's ISO format with T separator
        if (dateTimeString.includes('T')) {
            const parts = dateTimeString.split('T');
            dateStr = parts[0];
            // Remove timezone info if present (e.g., "+00:00" or "Z")
            timeStr = parts[1].replace(/[+\-]\d{2}:\d{2}$/, '').replace(/Z$/, '').split('.')[0];
        } 
        // Check if it's space separated
        else if (dateTimeString.includes(' ')) {
            const parts = dateTimeString.split(' ');
            dateStr = parts[0];
            timeStr = parts[1].replace(/[+\-]\d{2}:\d{2}$/, '').replace(/Z$/, '').split('.')[0];
        }
        // Try to parse as date only
        else {
            dateStr = dateTimeString.split(' ')[0];
        }
        
        // Validate date format (YYYY-MM-DD)
        const dateMatch = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!dateMatch) {
            // If date format is invalid, try to use fallback
            if (fallbackDate) {
                dateStr = fallbackDate;
            } else {
                return null;
            }
        }
        
        // Parse time (HH:MM:SS or HH:MM)
        let hours = '00';
        let minutes = '00';
        
        if (timeStr) {
            const timeMatch = timeStr.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?/);
            if (timeMatch) {
                hours = String(parseInt(timeMatch[1])).padStart(2, '0');
                minutes = String(parseInt(timeMatch[2])).padStart(2, '0');
            }
        }
        
        // Return in datetime-local format (YYYY-MM-DDTHH:mm)
        // This preserves the actual date/time without timezone conversion
        return `${dateStr}T${hours}:${minutes}`;
        
    } catch (e) {
        console.error('Error parsing date/time:', dateTimeString, e);
        // Fallback: try to extract date and time manually
        if (fallbackDate) {
            const timeMatch = dateTimeString.match(/(\d{1,2}):(\d{2})/);
            if (timeMatch) {
                const hours = String(parseInt(timeMatch[1])).padStart(2, '0');
                const minutes = String(parseInt(timeMatch[2])).padStart(2, '0');
                return `${fallbackDate}T${hours}:${minutes}`;
            }
            return `${fallbackDate}T00:00`;
        }
    }
    
    return null;
}

// Function to fill form with flight data
function fillFlightForm(flight) {
    try {
        // Get the selected date from the flight lookup wizard as fallback
        const selectedDate = flightDateInput ? flightDateInput.value : null;
        
        // Fill title
        const titleField = document.getElementById('item_title');
        if (titleField) {
            if (flight.airline && flight.flight_number) {
                titleField.value = `${flight.airline} ${flight.flight_number}`.trim();
            } else if (flight.airline) {
                titleField.value = flight.airline;
            } else if (flight.flight_number) {
                titleField.value = `Flight ${flight.flight_number}`;
            }
        }
        
        // Fill start date/time (departure) - use local scheduled time from API
        const startField = document.getElementById('start_datetime');
        if (startField) {
            // Prefer local scheduled time (in airport's timezone) - this is what the API returns
            const depTimeStr = flight.departure_scheduledTimeLocal || 
                               flight.departure_scheduled || 
                               flight.departure_revised || 
                               flight.departure_time;
            
            let depTime = parseFlightDateTime(depTimeStr, selectedDate);
            
            // If still no time, use selected date with a default time
            if (!depTime && selectedDate) {
                depTime = `${selectedDate}T00:00`;
            }
            
            if (depTime) {
                startField.value = depTime;
                // Debug: Set departure time (remove in production)
                // console.log('Set departure time:', depTime, 'from:', depTimeStr);
            }
        }
        
        // Fill end date/time (arrival) - use local scheduled time from API
        const endField = document.getElementById('end_datetime');
        if (endField) {
            // Prefer local scheduled time (in airport's timezone) - this is what the API returns
            const arrTimeStr = flight.arrival_scheduledTimeLocal || 
                               flight.arrival_scheduled || 
                               flight.arrival_revised || 
                               flight.arrival_time;
            
            let arrTime = parseFlightDateTime(arrTimeStr, selectedDate);
            
            // If still no time but we have departure time, use flight duration or estimate
            if (!arrTime && startField && startField.value) {
                try {
                    // Parse the departure datetime string (YYYY-MM-DDTHH:mm format)
                    const depParts = startField.value.split('T');
                    if (depParts.length === 2) {
                        const depDateStr = depParts[0];
                        const depTimeParts = depParts[1].split(':');
                        const depHours = parseInt(depTimeParts[0]) || 0;
                        const depMinutes = parseInt(depTimeParts[1]) || 0;
                        
                        // Calculate arrival time using flight duration
                        let arrHours = depHours;
                        let arrMinutes = depMinutes;
                        
                        if (flight.duration_minutes) {
                            const totalMinutes = (depHours * 60) + depMinutes + flight.duration_minutes;
                            arrHours = Math.floor(totalMinutes / 60) % 24;
                            arrMinutes = totalMinutes % 60;
                            // Handle day overflow (simplified - assumes same day)
                        } else {
                            arrHours = (depHours + 2) % 24; // Default 2 hour flight
                        }
                        
                        arrTime = `${depDateStr}T${String(arrHours).padStart(2, '0')}:${String(arrMinutes).padStart(2, '0')}`;
                    }
                } catch (e) {
                    console.error('Error estimating arrival time:', e);
                }
            }
            
            if (arrTime) {
                endField.value = arrTime;
                // Debug: Set arrival time (remove in production)
                // console.log('Set arrival time:', arrTime, 'from:', arrTimeStr);
            }
        }
        
        // Store all flight data in a hidden field for form submission
        const flightDataField = document.getElementById('flight_data_json');
        if (!flightDataField) {
            // Create hidden field to store flight data
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.id = 'flight_data_json';
            hiddenField.name = 'flight_data_json';
            const form = document.getElementById('itemForm');
            if (form) {
                form.appendChild(hiddenField);
            }
        }
        if (document.getElementById('flight_data_json')) {
            document.getElementById('flight_data_json').value = JSON.stringify(flight);
        }
        
        // Build location string with full airport names (include ICAO if IATA not available)
        const locationField = document.getElementById('location');
        if (locationField) {
            let locationParts = [];
            
            // Departure
            if (flight.departure_airport || flight.departure_city) {
                const depName = flight.departure_airport || flight.departure_city;
                locationParts.push(depName);
            }
            if (flight.departure_iata) {
                locationParts.push(`(${flight.departure_iata})`);
            } else if (flight.departure_icao) {
                locationParts.push(`(${flight.departure_icao})`);
            }
            
            // Arrow
            if (locationParts.length > 0 && (flight.arrival_airport || flight.arrival_city)) {
                locationParts.push('→');
            }
            
            // Arrival
            if (flight.arrival_airport || flight.arrival_city) {
                const arrName = flight.arrival_airport || flight.arrival_city;
                locationParts.push(arrName);
            }
            if (flight.arrival_iata) {
                locationParts.push(`(${flight.arrival_iata})`);
            } else if (flight.arrival_icao) {
                locationParts.push(`(${flight.arrival_icao})`);
            }
            
            if (locationParts.length > 0) {
                locationField.value = locationParts.join(' ');
            }
        }
        
        // Don't auto-fill confirmation number - let user enter their booking confirmation code manually
        // The confirmation code is different from the flight number (it's the booking reference)
        
        // Build comprehensive description with all flight details
        const descField = document.getElementById('item_description');
        if (descField) {
            let descParts = [];
            
            // Basic info
            if (flight.airline) {
                descParts.push(`Airline: ${flight.airline}`);
            }
            if (flight.flight_number) {
                descParts.push(`Flight Number: ${flight.flight_number}`);
            }
            
            // Departure details
            if (flight.departure_airport || flight.departure_city) {
                let depInfo = `Departure: ${flight.departure_airport || flight.departure_city}`;
                if (flight.departure_iata) {
                    depInfo += ` (${flight.departure_iata})`;
                }
                if (flight.departure_terminal) {
                    depInfo += ` - Terminal ${flight.departure_terminal}`;
                }
                if (flight.departure_gate) {
                    depInfo += `, Gate ${flight.departure_gate}`;
                }
                descParts.push(depInfo);
            }
            
            // Arrival details
            if (flight.arrival_airport || flight.arrival_city) {
                let arrInfo = `Arrival: ${flight.arrival_airport || flight.arrival_city}`;
                if (flight.arrival_iata) {
                    arrInfo += ` (${flight.arrival_iata})`;
                }
                if (flight.arrival_terminal) {
                    arrInfo += ` - Terminal ${flight.arrival_terminal}`;
                }
                if (flight.arrival_gate) {
                    arrInfo += `, Gate ${flight.arrival_gate}`;
                }
                descParts.push(arrInfo);
            }
            
            // Additional info
            if (flight.aircraft) {
                descParts.push(`Aircraft: ${flight.aircraft}`);
            }
            if (flight.status) {
                descParts.push(`Status: ${flight.status}`);
            }
            
            if (descParts.length > 0) {
                descField.value = descParts.join('\n');
            }
        }
        
        // Set type to flight if not already set
        const typeField = document.getElementById('item_type');
        if (typeField && !typeField.value) {
            typeField.value = 'flight';
        }
        
    } catch (error) {
        console.error('Error filling flight form:', error);
        console.error('Flight data:', flight);
    }
}

// Function to show flight selection modal
function showFlightSelectionModal(flights, flightNumber) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'flight-selection-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem;';
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 12px; padding: 1.5rem; max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
    
    modalContent.innerHTML = `
        <h2 style="margin: 0 0 1rem 0; color: var(--text-color);">Multiple Flights Found</h2>
        <p style="margin: 0 0 1rem 0; color: var(--text-light);">Found ${flights.length} flights for ${flightNumber}. Please select the correct one:</p>
        <div id="flight-options" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button id="cancel-flight-selection" class="btn btn-secondary" style="margin: 0;">Cancel</button>
        </div>
    `;
    
    const flightOptions = modalContent.querySelector('#flight-options');
    
    // Create option for each flight
    flights.forEach((flight, index) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'flight-option';
        option.style.cssText = 'text-align: left; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s;';
        
        // Use same enhanced display logic as showFlightResults
        const depScheduledStr = flight.departure_scheduled || flight.departure_time || flight.departure_scheduledTimeLocal || flight.departure_scheduledTimeUtc;
        const depRevisedStr = flight.departure_revised;
        const depRunwayStr = flight.departure_runway;
        const depTimeDisplay = depRevisedStr || depScheduledStr;
        
        const arrScheduledStr = flight.arrival_scheduled || flight.arrival_time || flight.arrival_scheduledTimeLocal || flight.arrival_scheduledTimeUtc;
        const arrRevisedStr = flight.arrival_revised;
        const arrRunwayStr = flight.arrival_runway;
        const arrTimeDisplay = arrRevisedStr || arrScheduledStr;
        
        // Get timezones for departure and arrival
        const depTimezone = flight.departure_timezone || null;
        const arrTimezone = flight.arrival_timezone || null;
        
        const depTime = formatFlightDateTime(depTimeDisplay, depTimezone);
        const arrTime = formatFlightDateTime(arrTimeDisplay, arrTimezone);
        const depScheduled = depScheduledStr ? formatFlightDateTime(depScheduledStr, depTimezone) : null;
        const depRevised = depRevisedStr ? formatFlightDateTime(depRevisedStr, depTimezone) : null;
        const depRunway = depRunwayStr ? formatFlightDateTime(depRunwayStr, depTimezone) : null;
        const arrScheduled = arrScheduledStr ? formatFlightDateTime(arrScheduledStr, arrTimezone) : null;
        const arrRevised = arrRevisedStr ? formatFlightDateTime(arrRevisedStr, arrTimezone) : null;
        const arrRunway = arrRunwayStr ? formatFlightDateTime(arrRunwayStr, arrTimezone) : null;
        
        const hasDepDelay = depRevisedStr && depRevisedStr !== depScheduledStr;
        const hasArrDelay = arrRevisedStr && arrRevisedStr !== arrScheduledStr;
        
        const depCode = flight.departure_iata || flight.departure_icao || '';
        const arrCode = flight.arrival_iata || flight.arrival_icao || '';
        const route = `${depCode} → ${arrCode}`;
        
        let durationDisplay = '';
        if (flight.duration_minutes) {
            const hours = Math.floor(flight.duration_minutes / 60);
            const minutes = flight.duration_minutes % 60;
            durationDisplay = `${hours}h ${minutes}m`;
        } else if (flight.duration_formatted) {
            durationDisplay = flight.duration_formatted;
        }
        
        let aircraftDisplay = '';
        if (flight.aircraft_model || flight.aircraft) {
            aircraftDisplay = flight.aircraft_model || flight.aircraft;
            if (flight.aircraft_registration) {
                aircraftDisplay += ` (${flight.aircraft_registration})`;
            }
            if (flight.aircraft_age) {
                aircraftDisplay += ` - ${flight.aircraft_age} years old`;
            }
        }
        
        option.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--text-color);">
                ${flight.airline || 'Unknown Airline'} ${flight.flight_number}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">
                <strong>Route:</strong> ${route}
            </div>
            <div style="font-size: 0.9rem; color: ${hasDepDelay ? '#E65100' : 'var(--text-light)'}; margin-bottom: 0.25rem;">
                <strong>Departure:</strong> ${depTime}
                ${hasDepDelay && depScheduled ? ` <span style="text-decoration: line-through; color: var(--text-light);">(${depScheduled})</span>` : ''}
                ${depRunway ? ` <span style="color: #4CAF50;"><i class="fas fa-check-circle"></i> Actual: ${depRunway}</span>` : ''}
                ${flight.departure_terminal ? ` (Terminal ${flight.departure_terminal})` : ''} ${flight.departure_gate ? `Gate ${flight.departure_gate}` : ''}
            </div>
            <div style="font-size: 0.9rem; color: ${hasArrDelay ? '#E65100' : 'var(--text-light)'}; margin-bottom: 0.25rem;">
                <strong>Arrival:</strong> ${arrTime}
                ${hasArrDelay && arrScheduled ? ` <span style="text-decoration: line-through; color: var(--text-light);">(${arrScheduled})</span>` : ''}
                ${arrRunway ? ` <span style="color: #4CAF50;"><i class="fas fa-check-circle"></i> Actual: ${arrRunway}</span>` : ''}
                ${flight.arrival_terminal ? ` (Terminal ${flight.arrival_terminal})` : ''} ${flight.arrival_gate ? `Gate ${flight.arrival_gate}` : ''}
            </div>
            ${durationDisplay ? `<div style="font-size: 0.85rem; color: var(--text-color); margin-top: 0.25rem;"><i class="fas fa-clock"></i> Duration: ${durationDisplay}</div>` : ''}
            ${aircraftDisplay ? `<div style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.25rem;"><i class="fas fa-plane"></i> Aircraft: ${aircraftDisplay}</div>` : ''}
            ${flight.status ? `<div style="font-size: 0.85rem; color: var(--primary-color); margin-top: 0.5rem;">Status: ${flight.status}</div>` : ''}
            ${flight.codeshare ? `<div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">Codeshare: ${flight.codeshare}</div>` : ''}
        `;
        
        option.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary-color)';
            this.style.background = '#F8F9FA';
        });
        
        option.addEventListener('mouseleave', function() {
            this.style.borderColor = 'var(--border-color)';
            this.style.background = 'white';
        });
        
        option.addEventListener('click', function() {
            fillFlightForm(flight);
            document.body.removeChild(modal);
            const flightLookupStatus = document.getElementById('flight_lookup_status');
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#2E7D32';
            flightLookupStatus.innerHTML = `<i class="fas fa-check-circle"></i> Flight information loaded: ${flight.airline} ${flight.flight_number}`;
            const lookupFlightBtn = document.getElementById('lookup_flight');
            lookupFlightBtn.disabled = false;
            lookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
        });
        
        flightOptions.appendChild(option);
    });
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Close on cancel
    modalContent.querySelector('#cancel-flight-selection').addEventListener('click', function() {
        document.body.removeChild(modal);
        const lookupFlightBtn = document.getElementById('lookup_flight');
        lookupFlightBtn.disabled = false;
        lookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
        const flightLookupStatus = document.getElementById('flight_lookup_status');
        flightLookupStatus.style.display = 'none';
    });
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
            const lookupFlightBtn = document.getElementById('lookup_flight');
            lookupFlightBtn.disabled = false;
            lookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
            const flightLookupStatus = document.getElementById('flight_lookup_status');
            flightLookupStatus.style.display = 'none';
        }
    });
}

// Add item form
document.getElementById('itemForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Remove flight_number from form data (it's not stored, just used for lookup)
    formData.delete('flight_number');
    
    // Extract and add flight-specific fields if type is flight
    const itemType = formData.get('type');
    if (itemType === 'flight') {
        const flightDataJson = formData.get('flight_data_json');
        if (flightDataJson) {
            try {
                const flightData = JSON.parse(flightDataJson);
                
                // Helper function to format datetime for database
                const formatDateTime = (dateStr) => {
                    if (!dateStr) return null;
                    try {
                        const date = new Date(dateStr);
                        if (isNaN(date.getTime())) return null;
                        // Format as YYYY-MM-DDTHH:mm
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        return `${year}-${month}-${day}T${hours}:${minutes}`;
                    } catch (e) {
                        return null;
                    }
                };
                
                // Add all flight-specific fields
                formData.set('flight_departure_scheduled', formatDateTime(flightData.departure_scheduled) || '');
                formData.set('flight_departure_revised', formatDateTime(flightData.departure_revised) || '');
                formData.set('flight_departure_runway', formatDateTime(flightData.departure_runway) || '');
                formData.set('flight_arrival_scheduled', formatDateTime(flightData.arrival_scheduled) || '');
                formData.set('flight_arrival_revised', formatDateTime(flightData.arrival_revised) || '');
                formData.set('flight_arrival_runway', formatDateTime(flightData.arrival_runway) || '');
                formData.set('flight_duration_minutes', flightData.duration_minutes || '');
                formData.set('flight_departure_icao', flightData.departure_icao || '');
                formData.set('flight_departure_country', flightData.departure_country || '');
                formData.set('flight_arrival_icao', flightData.arrival_icao || '');
                formData.set('flight_arrival_country', flightData.arrival_country || '');
                formData.set('flight_aircraft_registration', flightData.aircraft_registration || '');
                formData.set('flight_aircraft_icao24', flightData.aircraft_icao24 || '');
                formData.set('flight_aircraft_age', flightData.aircraft_age || '');
                formData.set('flight_status', flightData.status || '');
                formData.set('flight_codeshare', flightData.codeshare || '');
            } catch (e) {
                console.error('Error parsing flight data:', e);
            }
        }
    }
    
    // Get files before submitting (they'll be removed from formData)
    const fileInput = document.getElementById('item_files');
    const files = fileInput ? Array.from(fileInput.files) : [];
    
    // Remove files from formData (we'll upload them separately after item is created)
    formData.delete('files[]');
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding item...';
    }
    
    fetch('../api/add_travel_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const itemId = data.item_id || data.id;
            
            // Upload files if any were selected
            if (files.length > 0 && itemId) {
                const uploadFormData = new FormData();
                uploadFormData.append('trip_id', '<?php echo $tripId; ?>');
                uploadFormData.append('travel_item_id', itemId);
                
                files.forEach(file => {
                    uploadFormData.append('file[]', file);
                });
                
                return fetch('../api/upload_document.php', {
                    method: 'POST',
                    body: uploadFormData
                })
                .then(response => response.json())
                .then(uploadData => {
                    if (uploadData.success) {
                        window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
                    } else {
                        // Item was created but file upload failed
                        customAlert('Item added successfully, but some files failed to upload: ' + (uploadData.message || 'Unknown error'), 'Partial Success');
                        setTimeout(() => {
                            window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
                        }, 2000);
                    }
                })
                .catch(uploadError => {
                    // Item was created but file upload failed
                    customAlert('Item added successfully, but file upload failed. You can add files later.', 'Partial Success');
                    setTimeout(() => {
                        window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
                    }, 2000);
                });
            } else {
                // No files to upload, just redirect
                window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
            }
        } else {
            customAlert(data.message, 'Error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(error => {
        customAlert('Error adding item', 'Error');
        console.error(error);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
});

// Show/hide flight number field in edit form based on type selection
const editTypeSelect = document.getElementById('edit_type');
const editFlightNumberGroup = document.getElementById('edit_flight_number_group');
const editFlightNumberInput = document.getElementById('edit_flight_number');
const editLookupFlightBtn = document.getElementById('edit_lookup_flight');
const editFlightLookupStatus = document.getElementById('edit_flight_lookup_status');

if (editTypeSelect && editFlightNumberGroup) {
    // Show if already a flight
    if (editTypeSelect.value === 'flight') {
        editFlightNumberGroup.style.display = 'block';
    }
    
    const editHotelFieldsGroup = document.getElementById('edit_hotel_fields_group');
    
    editTypeSelect.addEventListener('change', function() {
        if (this.value === 'flight') {
            editFlightNumberGroup.style.display = 'block';
            if (editHotelFieldsGroup) editHotelFieldsGroup.style.display = 'none';
        } else if (this.value === 'hotel') {
            if (editHotelFieldsGroup) editHotelFieldsGroup.style.display = 'block';
            if (editFlightNumberGroup) editFlightNumberGroup.style.display = 'none';
            editFlightNumberInput.value = '';
            editFlightLookupStatus.style.display = 'none';
        } else {
            editFlightNumberGroup.style.display = 'none';
            if (editHotelFieldsGroup) editHotelFieldsGroup.style.display = 'none';
            editFlightNumberInput.value = '';
            editFlightLookupStatus.style.display = 'none';
        }
    });
    
    // Show hotel fields if editing a hotel item
    if (editTypeSelect && editTypeSelect.value === 'hotel' && editHotelFieldsGroup) {
        editHotelFieldsGroup.style.display = 'block';
    }
}

// Flight lookup functionality for edit form
if (editLookupFlightBtn) {
    editLookupFlightBtn.addEventListener('click', async function() {
        const flightNumber = editFlightNumberInput.value.trim().toUpperCase();
        
        if (!flightNumber) {
            editFlightLookupStatus.style.display = 'block';
            editFlightLookupStatus.style.color = '#C62828';
            editFlightLookupStatus.textContent = 'Please enter a flight number';
            return;
        }
        
        // Validate format
        if (!/^[A-Z]{2}[0-9]{1,4}[A-Z]?$/.test(flightNumber)) {
            editFlightLookupStatus.style.display = 'block';
            editFlightLookupStatus.style.color = '#C62828';
            editFlightLookupStatus.textContent = 'Invalid format. Use format like AA123 or AA1234';
            return;
        }
        
        // Show loading state
        editLookupFlightBtn.disabled = true;
        editLookupFlightBtn.textContent = 'Looking up...';
        editFlightLookupStatus.style.display = 'block';
        editFlightLookupStatus.style.color = '#666';
        editFlightLookupStatus.textContent = 'Looking up flight information...';
        
        try {
            const startDateInput = document.getElementById('edit_start_datetime');
            const dateTimeValue = startDateInput && startDateInput.value ? startDateInput.value : '';
            const dateParam = dateTimeValue ? dateTimeValue.split('T')[0] : '';
            const timeParam = dateTimeValue ? dateTimeValue.split('T')[1] : '';
            
            // If no date provided, use today's date for search
            const searchDate = dateParam || new Date().toISOString().split('T')[0];
            
            const response = await fetch(`../api/lookup_flight.php?flight=${encodeURIComponent(flightNumber)}&date=${encodeURIComponent(searchDate)}${timeParam ? '&time=' + encodeURIComponent(timeParam) : ''}`);
            const data = await response.json();
            
            if (data.success && data.data) {
                // Check if multiple flights found
                const isMultiple = data.multiple === true || (Array.isArray(data.data) && data.data.length > 1);
                
                if (isMultiple) {
                    // Show flight selection modal for edit form
                    showEditFlightSelectionModal(data.data, flightNumber);
                } else {
                    // Single flight - extract it from array if needed
                    let flight;
                    if (Array.isArray(data.data)) {
                        flight = data.data[0];
                    } else {
                        flight = data.data;
                    }
                    
                    // Verify flight has required data
                    if (flight && (flight.airline || flight.flight_number)) {
                        fillEditFlightForm(flight);
                        
                        editFlightLookupStatus.style.color = '#2E7D32';
                        editFlightLookupStatus.innerHTML = `<i class="fas fa-check-circle"></i> Flight information loaded: ${flight.airline || ''} ${flight.flight_number || flightNumber}`;
                    } else {
                        editFlightLookupStatus.style.color = '#856404';
                        editFlightLookupStatus.textContent = 'Flight found but missing required information. Please fill in details manually.';
                        // Debug: Flight data structure (remove in production)
                        // console.error('Flight data structure:', flight);
                    }
                }
            } else {
                editFlightLookupStatus.style.color = '#856404';
                editFlightLookupStatus.textContent = data.message || 'Flight information not found. Please fill in details manually.';
            }
        } catch (error) {
            console.error('Flight lookup error:', error);
            editFlightLookupStatus.style.color = '#C62828';
            editFlightLookupStatus.textContent = 'Error looking up flight. Please enter details manually.';
        } finally {
            editLookupFlightBtn.disabled = false;
            editLookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
        }
    });
    
    // Auto-lookup on Enter key in flight number field
    editFlightNumberInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            editLookupFlightBtn.click();
        }
    });
}

// Function to fill edit form with flight data (uses same logic as fillFlightForm)
function fillEditFlightForm(flight) {
    try {
        // Get the date from the edit form's date input if available, or use current date
        const editDateInput = document.getElementById('edit_flight_date');
        const selectedDate = editDateInput ? editDateInput.value : null;
        
        // Fill title
        const titleField = document.getElementById('edit_title');
        if (titleField) {
            if (flight.airline && flight.flight_number) {
                titleField.value = `${flight.airline} ${flight.flight_number}`.trim();
            } else if (flight.airline) {
                titleField.value = flight.airline;
            } else if (flight.flight_number) {
                titleField.value = `Flight ${flight.flight_number}`;
            }
        }
        
        // Fill start date/time (departure) - prefer revised time if available
        // Use the selected date as fallback if time parsing fails
        const startField = document.getElementById('edit_start_datetime');
        if (startField) {
            // Prefer local scheduled time (in airport's timezone) - this is what the API returns
            const depTimeStr = flight.departure_scheduledTimeLocal || 
                               flight.departure_scheduled || 
                               flight.departure_revised || 
                               flight.departure_time;
            
            let depTime = parseFlightDateTime(depTimeStr, selectedDate);
            
            // If still no time, use selected date with a default time
            if (!depTime && selectedDate) {
                depTime = `${selectedDate}T00:00`;
            }
            
            if (depTime) {
                startField.value = depTime;
            }
        }
        
        // Fill end date/time (arrival) - prefer revised time if available
        // Use the selected date as fallback if time parsing fails
        const endField = document.getElementById('edit_end_datetime');
        if (endField) {
            // Prefer local scheduled time (in airport's timezone) - this is what the API returns
            const arrTimeStr = flight.arrival_scheduledTimeLocal || 
                               flight.arrival_scheduled || 
                               flight.arrival_revised || 
                               flight.arrival_time;
            
            let arrTime = parseFlightDateTime(arrTimeStr, selectedDate);
            
            // If still no time but we have departure time, use flight duration or estimate
            if (!arrTime && startField && startField.value) {
                try {
                    // Parse the departure datetime string (YYYY-MM-DDTHH:mm format)
                    const depParts = startField.value.split('T');
                    if (depParts.length === 2) {
                        const depDateStr = depParts[0];
                        const depTimeParts = depParts[1].split(':');
                        const depHours = parseInt(depTimeParts[0]) || 0;
                        const depMinutes = parseInt(depTimeParts[1]) || 0;
                        
                        // Calculate arrival time using flight duration
                        let arrHours = depHours;
                        let arrMinutes = depMinutes;
                        
                        if (flight.duration_minutes) {
                            const totalMinutes = (depHours * 60) + depMinutes + flight.duration_minutes;
                            arrHours = Math.floor(totalMinutes / 60) % 24;
                            arrMinutes = totalMinutes % 60;
                            // Handle day overflow (simplified - assumes same day)
                        } else {
                            arrHours = (depHours + 2) % 24; // Default 2 hour flight
                        }
                        
                        arrTime = `${depDateStr}T${String(arrHours).padStart(2, '0')}:${String(arrMinutes).padStart(2, '0')}`;
                    }
                } catch (e) {
                    console.error('Error estimating arrival time:', e);
                }
            }
            
            if (arrTime) {
                endField.value = arrTime;
            }
        }
        
        // Store all flight data in a hidden field for form submission
        const flightDataField = document.getElementById('edit_flight_data_json');
        if (!flightDataField) {
            // Create hidden field to store flight data
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.id = 'edit_flight_data_json';
            hiddenField.name = 'edit_flight_data_json';
            const form = document.getElementById('editItemForm');
            if (form) {
                form.appendChild(hiddenField);
            }
        }
        if (document.getElementById('edit_flight_data_json')) {
            document.getElementById('edit_flight_data_json').value = JSON.stringify(flight);
        }
        
        // Build location string with full airport names (include ICAO if IATA not available)
        const locationField = document.getElementById('edit_location');
        if (locationField) {
            let locationParts = [];
            
            // Departure
            if (flight.departure_airport || flight.departure_city) {
                const depName = flight.departure_airport || flight.departure_city;
                locationParts.push(depName);
            }
            if (flight.departure_iata) {
                locationParts.push(`(${flight.departure_iata})`);
            } else if (flight.departure_icao) {
                locationParts.push(`(${flight.departure_icao})`);
            }
            
            // Arrow
            if (locationParts.length > 0 && (flight.arrival_airport || flight.arrival_city)) {
                locationParts.push('→');
            }
            
            // Arrival
            if (flight.arrival_airport || flight.arrival_city) {
                const arrName = flight.arrival_airport || flight.arrival_city;
                locationParts.push(arrName);
            }
            if (flight.arrival_iata) {
                locationParts.push(`(${flight.arrival_iata})`);
            } else if (flight.arrival_icao) {
                locationParts.push(`(${flight.arrival_icao})`);
            }
            
            if (locationParts.length > 0) {
                locationField.value = locationParts.join(' ');
            }
        }
        
        // Don't auto-fill confirmation number - let user enter their booking confirmation code manually
        // The confirmation code is different from the flight number (it's the booking reference)
        
        // Build comprehensive description with all flight details
        const descField = document.getElementById('edit_description');
        if (descField) {
            let descParts = [];
            
            // Basic info
            if (flight.airline) {
                descParts.push(`Airline: ${flight.airline}`);
            }
            if (flight.flight_number) {
                descParts.push(`Flight Number: ${flight.flight_number}`);
            }
            
            // Departure details
            if (flight.departure_airport || flight.departure_city) {
                let depInfo = `Departure: ${flight.departure_airport || flight.departure_city}`;
                if (flight.departure_iata) {
                    depInfo += ` (${flight.departure_iata})`;
                }
                if (flight.departure_terminal) {
                    depInfo += ` - Terminal ${flight.departure_terminal}`;
                }
                if (flight.departure_gate) {
                    depInfo += `, Gate ${flight.departure_gate}`;
                }
                descParts.push(depInfo);
            }
            
            // Arrival details
            if (flight.arrival_airport || flight.arrival_city) {
                let arrInfo = `Arrival: ${flight.arrival_airport || flight.arrival_city}`;
                if (flight.arrival_iata) {
                    arrInfo += ` (${flight.arrival_iata})`;
                }
                if (flight.arrival_terminal) {
                    arrInfo += ` - Terminal ${flight.arrival_terminal}`;
                }
                if (flight.arrival_gate) {
                    arrInfo += `, Gate ${flight.arrival_gate}`;
                }
                descParts.push(arrInfo);
            }
            
            // Additional info
            if (flight.aircraft) {
                descParts.push(`Aircraft: ${flight.aircraft}`);
            }
            if (flight.status) {
                descParts.push(`Status: ${flight.status}`);
            }
            
            if (descParts.length > 0) {
                descField.value = descParts.join('\n');
            }
        }
        
        // Set type to flight if not already set
        const typeField = document.getElementById('edit_type');
        if (typeField && !typeField.value) {
            typeField.value = 'flight';
        }
        
    } catch (error) {
        console.error('Error filling edit flight form:', error);
        console.error('Flight data:', flight);
    }
}

// Function to show flight selection modal for edit form
function showEditFlightSelectionModal(flights, flightNumber) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'edit-flight-selection-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem;';
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 12px; padding: 1.5rem; max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
    
    modalContent.innerHTML = `
        <h2 style="margin: 0 0 1rem 0; color: var(--text-color);">Multiple Flights Found</h2>
        <p style="margin: 0 0 1rem 0; color: var(--text-light);">Found ${flights.length} flights for ${flightNumber}. Please select the correct one:</p>
        <div id="edit-flight-options" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
            <button id="cancel-edit-flight-selection" class="btn btn-secondary" style="margin: 0;">Cancel</button>
        </div>
    `;
    
    const flightOptions = modalContent.querySelector('#edit-flight-options');
    
    // Create option for each flight
    flights.forEach((flight, index) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'flight-option';
        option.style.cssText = 'text-align: left; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s;';
        
        // Use same enhanced display logic with timezones
        const depScheduledStr = flight.departure_scheduled || flight.departure_time || flight.departure_scheduledTimeLocal || flight.departure_scheduledTimeUtc;
        const depRevisedStr = flight.departure_revised;
        const depRunwayStr = flight.departure_runway;
        const depTimeDisplay = depRevisedStr || depScheduledStr;
        
        const arrScheduledStr = flight.arrival_scheduled || flight.arrival_time || flight.arrival_scheduledTimeLocal || flight.arrival_scheduledTimeUtc;
        const arrRevisedStr = flight.arrival_revised;
        const arrRunwayStr = flight.arrival_runway;
        const arrTimeDisplay = arrRevisedStr || arrScheduledStr;
        
        const depTimezone = flight.departure_timezone || null;
        const arrTimezone = flight.arrival_timezone || null;
        
        const depTime = formatFlightDateTime(depTimeDisplay, depTimezone);
        const arrTime = formatFlightDateTime(arrTimeDisplay, arrTimezone);
        const depScheduled = depScheduledStr ? formatFlightDateTime(depScheduledStr, depTimezone) : null;
        const depRevised = depRevisedStr ? formatFlightDateTime(depRevisedStr, depTimezone) : null;
        const depRunway = depRunwayStr ? formatFlightDateTime(depRunwayStr, depTimezone) : null;
        const arrScheduled = arrScheduledStr ? formatFlightDateTime(arrScheduledStr, arrTimezone) : null;
        const arrRevised = arrRevisedStr ? formatFlightDateTime(arrRevisedStr, arrTimezone) : null;
        const arrRunway = arrRunwayStr ? formatFlightDateTime(arrRunwayStr, arrTimezone) : null;
        
        const hasDepDelay = depRevisedStr && depRevisedStr !== depScheduledStr;
        const hasArrDelay = arrRevisedStr && arrRevisedStr !== arrScheduledStr;
        
        const depCode = flight.departure_iata || flight.departure_icao || '';
        const arrCode = flight.arrival_iata || flight.arrival_icao || '';
        const route = `${depCode} → ${arrCode}`;
        
        let durationDisplay = '';
        if (flight.duration_minutes) {
            const hours = Math.floor(flight.duration_minutes / 60);
            const minutes = flight.duration_minutes % 60;
            durationDisplay = `${hours}h ${minutes}m`;
        } else if (flight.duration_formatted) {
            durationDisplay = flight.duration_formatted;
        }
        
        let aircraftDisplay = '';
        if (flight.aircraft_model || flight.aircraft) {
            aircraftDisplay = flight.aircraft_model || flight.aircraft;
            if (flight.aircraft_registration) {
                aircraftDisplay += ` (${flight.aircraft_registration})`;
            }
            if (flight.aircraft_age) {
                aircraftDisplay += ` - ${flight.aircraft_age} years old`;
            }
        }
        
        option.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--text-color);">
                ${flight.airline || 'Unknown Airline'} ${flight.flight_number}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.25rem;">
                <strong>Route:</strong> ${route}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.25rem;">
                <strong>Departure:</strong> ${depTime} ${flight.departure_terminal ? `(Terminal ${flight.departure_terminal})` : ''} ${flight.departure_gate ? `Gate ${flight.departure_gate}` : ''}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light);">
                <strong>Arrival:</strong> ${arrTime} ${flight.arrival_terminal ? `(Terminal ${flight.arrival_terminal})` : ''} ${flight.arrival_gate ? `Gate ${flight.arrival_gate}` : ''}
            </div>
            ${flight.status ? `<div style="font-size: 0.85rem; color: var(--primary-color); margin-top: 0.5rem;">Status: ${flight.status}</div>` : ''}
        `;
        
        option.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary-color)';
            this.style.background = '#F8F9FA';
        });
        
        option.addEventListener('mouseleave', function() {
            this.style.borderColor = 'var(--border-color)';
            this.style.background = 'white';
        });
        
        option.addEventListener('click', function() {
            fillEditFlightForm(flight);
            document.body.removeChild(modal);
            const editFlightLookupStatus = document.getElementById('edit_flight_lookup_status');
            editFlightLookupStatus.style.display = 'block';
            editFlightLookupStatus.style.color = '#2E7D32';
            editFlightLookupStatus.innerHTML = `<i class="fas fa-check-circle"></i> Flight information loaded: ${flight.airline} ${flight.flight_number}`;
            const editLookupFlightBtn = document.getElementById('edit_lookup_flight');
            editLookupFlightBtn.disabled = false;
            editLookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
        });
        
        flightOptions.appendChild(option);
    });
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Close on cancel
    modalContent.querySelector('#cancel-edit-flight-selection').addEventListener('click', function() {
        document.body.removeChild(modal);
        const editLookupFlightBtn = document.getElementById('edit_lookup_flight');
        editLookupFlightBtn.disabled = false;
        editLookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
        const editFlightLookupStatus = document.getElementById('edit_flight_lookup_status');
        editFlightLookupStatus.style.display = 'none';
    });
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
            const editLookupFlightBtn = document.getElementById('edit_lookup_flight');
            editLookupFlightBtn.disabled = false;
            editLookupFlightBtn.innerHTML = '<i class="fas fa-search"></i> Lookup';
            const editFlightLookupStatus = document.getElementById('edit_flight_lookup_status');
            editFlightLookupStatus.style.display = 'none';
        }
    });
}

// Edit item form
document.getElementById('editItemForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Remove flight_number from form data (it's not stored, just used for lookup)
    formData.delete('flight_number');
    
    // Extract and add flight-specific fields if type is flight
    const itemType = formData.get('type');
    if (itemType === 'flight') {
        const flightDataJson = formData.get('edit_flight_data_json');
        if (flightDataJson) {
            try {
                const flightData = JSON.parse(flightDataJson);
                
                // Helper function to format datetime for database
                const formatDateTime = (dateStr) => {
                    if (!dateStr) return null;
                    try {
                        const date = new Date(dateStr);
                        if (isNaN(date.getTime())) return null;
                        // Format as YYYY-MM-DDTHH:mm
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        return `${year}-${month}-${day}T${hours}:${minutes}`;
                    } catch (e) {
                        return null;
                    }
                };
                
                // Add all flight-specific fields
                formData.set('flight_departure_scheduled', formatDateTime(flightData.departure_scheduled) || '');
                formData.set('flight_departure_revised', formatDateTime(flightData.departure_revised) || '');
                formData.set('flight_departure_runway', formatDateTime(flightData.departure_runway) || '');
                formData.set('flight_arrival_scheduled', formatDateTime(flightData.arrival_scheduled) || '');
                formData.set('flight_arrival_revised', formatDateTime(flightData.arrival_revised) || '');
                formData.set('flight_arrival_runway', formatDateTime(flightData.arrival_runway) || '');
                formData.set('flight_duration_minutes', flightData.duration_minutes || '');
                formData.set('flight_departure_icao', flightData.departure_icao || '');
                formData.set('flight_departure_country', flightData.departure_country || '');
                formData.set('flight_arrival_icao', flightData.arrival_icao || '');
                formData.set('flight_arrival_country', flightData.arrival_country || '');
                formData.set('flight_aircraft_registration', flightData.aircraft_registration || '');
                formData.set('flight_aircraft_icao24', flightData.aircraft_icao24 || '');
                formData.set('flight_aircraft_age', flightData.aircraft_age || '');
                formData.set('flight_status', flightData.status || '');
                formData.set('flight_codeshare', flightData.codeshare || '');
            } catch (e) {
                console.error('Error parsing flight data:', e);
            }
        }
    }
    
    // Get files before submitting (they'll be removed from formData)
    const fileInput = document.getElementById('edit_item_files');
    const files = fileInput ? Array.from(fileInput.files) : [];
    const itemId = formData.get('item_id');
    
    // Remove files from formData (we'll upload them separately after item is updated)
    formData.delete('files[]');
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating item...';
    }
    
    fetch('../api/update_travel_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Upload files if any were selected
            if (files.length > 0 && itemId) {
                const uploadFormData = new FormData();
                uploadFormData.append('trip_id', '<?php echo $tripId; ?>');
                uploadFormData.append('travel_item_id', itemId);
                
                files.forEach(file => {
                    uploadFormData.append('file[]', file);
                });
                
                return fetch('../api/upload_document.php', {
                    method: 'POST',
                    body: uploadFormData
                })
                .then(response => response.json())
                .then(uploadData => {
                    if (uploadData.success) {
                        window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
                    } else {
                        // Item was updated but file upload failed
                        customAlert('Item updated successfully, but some files failed to upload: ' + (uploadData.message || 'Unknown error'), 'Partial Success');
                        setTimeout(() => {
                            window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
                        }, 2000);
                    }
                })
                .catch(uploadError => {
                    // Item was updated but file upload failed
                    customAlert('Item updated successfully, but file upload failed. You can add files later.', 'Partial Success');
                    setTimeout(() => {
                        window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
                    }, 2000);
                });
            } else {
                // No files to upload, just redirect
                window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
            }
        } else {
            customAlert(data.message, 'Error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(error => {
        customAlert('Error updating item', 'Error');
        console.error(error);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
});

// Delete item
async function deleteItem(itemId) {
    const confirmed = await customConfirm(
        'Are you sure you want to delete this travel item?',
        'Delete Item',
        { confirmText: 'Delete' }
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('item_id', itemId);
    
    fetch('../api/delete_travel_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            customAlert(data.message, 'Error');
        }
    })
    .catch(error => {
        customAlert('Error deleting item', 'Error');
        console.error(error);
    });
}

// File input handling - show selected file name and size
function setupFileInput(fileInput) {
    const wrapper = fileInput.closest('.file-input-wrapper');
    if (!wrapper) return;
    
    const label = wrapper.querySelector('.file-input-label');
    const nameDisplay = wrapper.querySelector('.file-name-display');
    const nameText = wrapper.querySelector('.file-name-text');
    const sizeText = wrapper.querySelector('.file-size');
    const clearBtn = wrapper.querySelector('.file-input-clear');
    
    function updateFileDisplay() {
        const files = fileInput.files;
        if (files && files.length > 0) {
            label.classList.add('has-file');
            nameDisplay.classList.add('show');
            clearBtn.classList.add('show');
            
            if (nameText) {
                if (files.length === 1) {
                    nameText.textContent = files[0].name;
                } else {
                    nameText.textContent = files.length + ' files selected';
                }
            }
            
            if (sizeText) {
                let totalSize = 0;
                for (let i = 0; i < files.length; i++) {
                    totalSize += files[i].size;
                }
                let sizeStr = '';
                if (totalSize < 1024) {
                    sizeStr = totalSize + ' B';
                } else if (totalSize < 1024 * 1024) {
                    sizeStr = (totalSize / 1024).toFixed(1) + ' KB';
                } else {
                    sizeStr = (totalSize / (1024 * 1024)).toFixed(2) + ' MB';
                }
                sizeText.textContent = sizeStr + (files.length > 1 ? ' total' : '');
            }
        } else {
            label.classList.remove('has-file');
            nameDisplay.classList.remove('show');
            clearBtn.classList.remove('show');
        }
    }
    
    fileInput.addEventListener('change', updateFileDisplay);
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.value = '';
            updateFileDisplay();
        });
    }
    
    // Drag and drop support
    const labelElement = label;
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        labelElement.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        labelElement.addEventListener(eventName, function() {
            labelElement.style.background = 'linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%)';
            labelElement.style.borderColor = 'var(--primary-color)';
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        labelElement.addEventListener(eventName, function() {
            if (!fileInput.files || fileInput.files.length === 0) {
                labelElement.style.background = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
                labelElement.style.borderColor = 'var(--border-color)';
            }
        }, false);
    });
    
    labelElement.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileDisplay();
        }
    }, false);
}

// Initialize all file inputs
document.querySelectorAll('.file-input').forEach(setupFileInput);

// Document upload forms - use event delegation to prevent duplicate listeners
// Check if handler already exists to prevent duplicate attachments
if (!document.uploadFormHandlerAttached) {
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || !form.classList.contains('upload-form')) {
            return;
        }
        
        e.preventDefault();
        e.stopImmediatePropagation(); // Prevent any other handlers
        
        // Prevent duplicate submissions
        if (form.dataset.uploading === 'true') {
            return;
        }
        
        const formData = new FormData(form);
        const fileInput = form.querySelector('input[type="file"]');
        
        // Check if files are selected
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            customAlert('Please select at least one file to upload', 'Error');
            return;
        }
        
        // Mark form as uploading to prevent duplicate submissions
        form.dataset.uploading = 'true';
        
        // Get item ID and trip ID from form
        const itemId = form.dataset.itemId || form.querySelector('input[name="travel_item_id"]')?.value;
        const tripId = form.querySelector('input[name="trip_id"]')?.value;
        
        // Files are already in FormData from the form, but we need to ensure they're properly named
        // The form input has name="file[]" so they should already be there
        // Just verify and ensure proper format
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        const fileCount = fileInput.files.length;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = `Uploading ${fileCount} file${fileCount > 1 ? 's' : ''}...`;
        }
        
        fetch('../api/upload_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.failed && data.failed.length > 0) {
                    let message = data.message;
                    if (data.failed.length > 0) {
                        message += '\n\nFailed files:\n';
                        data.failed.forEach(f => {
                            message += `- ${f.name}: ${f.error}\n`;
                        });
                    }
                    customAlert(message, 'Partial Success');
                } else {
                    customAlert(data.message, 'Success');
                }
                
                // Add uploaded documents to DOM without reloading
                if (data.uploaded && data.uploaded.length > 0) {
                    addDocumentsToDOM(data.uploaded, itemId, tripId);
                }
                
                // Reset form
                form.reset();
                form.dataset.uploading = 'false';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                
                // Reset file input display
                const fileInputWrapper = form.querySelector('.file-input-wrapper');
                if (fileInputWrapper) {
                    const fileNameDisplay = fileInputWrapper.querySelector('.file-name-text');
                    const fileSizeDisplay = fileInputWrapper.querySelector('.file-size');
                    if (fileNameDisplay) fileNameDisplay.textContent = '';
                    if (fileSizeDisplay) fileSizeDisplay.textContent = '';
                }
            } else {
                customAlert(data.message || 'Error uploading files', 'Error');
                form.dataset.uploading = 'false';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        })
        .catch(error => {
            customAlert('Error uploading documents', 'Error');
            console.error(error);
            form.dataset.uploading = 'false';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }, true); // Use capture phase
    
    document.uploadFormHandlerAttached = true;
}

// Function to add uploaded documents to DOM
function addDocumentsToDOM(uploadedDocs, itemId, tripId) {
    if (!uploadedDocs || uploadedDocs.length === 0) return;
    
    const canEdit = true; // Assume user can edit if they just uploaded
    
    uploadedDocs.forEach(doc => {
        if (itemId) {
            // Add to timeline item documents
            let documentsContainer = document.querySelector(`.timeline-item-wrapper[data-item-id="${itemId}"] .timeline-documents`);
            
            if (!documentsContainer) {
                // Create documents container if it doesn't exist
                const expandableContent = document.querySelector(`.timeline-item-wrapper[data-item-id="${itemId}"] .expandable-content`);
                if (expandableContent) {
                    documentsContainer = document.createElement('div');
                    documentsContainer.className = 'timeline-documents';
                    expandableContent.appendChild(documentsContainer);
                }
            }
            
            if (documentsContainer) {
                // Ensure container is enabled (in case it was disabled from a previous operation)
                documentsContainer.style.pointerEvents = '';
                documentsContainer.style.opacity = '1';
                
                // Create document badge
                const badge = document.createElement('a');
                badge.href = `../api/view_document.php?id=${doc.document_id}`;
                badge.target = '_blank';
                badge.className = 'document-badge';
                // Remove extension from filename display
                const docFilenameWithoutExt = doc.original_filename.replace(/\.[^/.]+$/, '');
                badge.innerHTML = `<i class="fas fa-paperclip"></i> ${escapeHtml(docFilenameWithoutExt || doc.original_filename)}`;
                badge.title = doc.original_filename; // Show full filename in tooltip
                
                documentsContainer.appendChild(badge);
                
                // Add delete button if user can edit
                if (canEdit) {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.onclick = () => deleteDocument(doc.document_id);
                    deleteBtn.className = 'btn btn-danger btn-small';
                    deleteBtn.style.cssText = 'margin: 0; padding: 0.25rem 0.5rem; font-size: 0.75rem;';
                    deleteBtn.textContent = '×';
                    deleteBtn.disabled = false;
                    documentsContainer.appendChild(deleteBtn);
                }
                
                // Show attachment icon in item header if not already shown
                const itemHeader = document.querySelector(`.timeline-item-wrapper[data-item-id="${itemId}"] .expandable-item-header`);
                if (itemHeader) {
                    let attachmentIcon = itemHeader.querySelector('.fa-paperclip[title="Has attachments"]');
                    if (!attachmentIcon) {
                        attachmentIcon = document.createElement('i');
                        attachmentIcon.className = 'fas fa-paperclip';
                        attachmentIcon.title = 'Has attachments';
                        attachmentIcon.style.cssText = 'margin-left: 0.5rem; color: #666; font-size: 0.875rem;';
                        const titleElement = itemHeader.querySelector('.expandable-item-title');
                        if (titleElement) {
                            titleElement.appendChild(attachmentIcon);
                        }
                    }
                }
            }
        } else if (tripId) {
            // Add to trip documents list
            let documentsList = document.querySelector('.document-list');
            if (!documentsList) {
                // Create documents list if it doesn't exist
                const tripDocumentsCard = document.querySelector('.card:has(.card-title)');
                if (tripDocumentsCard) {
                    documentsList = document.createElement('div');
                    documentsList.className = 'document-list';
                    tripDocumentsCard.appendChild(documentsList);
                }
            }
            
            if (documentsList) {
                // Ensure list is enabled (in case it was disabled from a previous operation)
                documentsList.style.pointerEvents = '';
                documentsList.style.opacity = '1';
                
                const docItem = document.createElement('div');
                docItem.className = 'document-list-item';
                docItem.setAttribute('data-document-id', doc.document_id);
                
                const docLinkWrapper = document.createElement('div');
                docLinkWrapper.className = 'document-list-link-wrapper';
                
                const docLink = document.createElement('a');
                docLink.href = `../api/view_document.php?id=${doc.document_id}`;
                docLink.target = '_blank';
                docLink.className = 'document-list-link';
                docLink.title = doc.original_filename;
                
                // Determine file icon based on extension and type
                const extension = doc.original_filename.split('.').pop().toLowerCase();
                let fileIcon = 'fa-file';
                let fileIconColor = 'var(--text-light)';
                
                if (doc.file_type && doc.file_type.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                    fileIcon = 'fa-image';
                    fileIconColor = 'var(--primary-color)';
                } else if (doc.file_type === 'application/pdf' || extension === 'pdf') {
                    fileIcon = 'fa-file-pdf';
                    fileIconColor = '#E74C3C';
                } else if (['doc', 'docx'].includes(extension) || (doc.file_type && (doc.file_type.includes('wordprocessingml') || doc.file_type.includes('msword')))) {
                    fileIcon = 'fa-file-word';
                    fileIconColor = '#2B579A';
                } else if (['xls', 'xlsx'].includes(extension) || (doc.file_type && (doc.file_type.includes('spreadsheetml') || doc.file_type.includes('ms-excel')))) {
                    fileIcon = 'fa-file-excel';
                    fileIconColor = '#1D6F42';
                } else if (['ppt', 'pptx'].includes(extension) || (doc.file_type && (doc.file_type.includes('presentationml') || doc.file_type.includes('ms-powerpoint')))) {
                    fileIcon = 'fa-file-powerpoint';
                    fileIconColor = '#D04423';
                } else if (['txt', 'text'].includes(extension) || (doc.file_type && doc.file_type.startsWith('text/'))) {
                    fileIcon = 'fa-file-alt';
                    fileIconColor = 'var(--primary-color)';
                } else if (extension === 'csv' || doc.file_type === 'text/csv') {
                    fileIcon = 'fa-file-csv';
                    fileIconColor = '#1D6F42';
                } else if (['json', 'xml'].includes(extension) || (doc.file_type && (doc.file_type.includes('json') || doc.file_type.includes('xml')))) {
                    fileIcon = 'fa-file-code';
                    fileIconColor = 'var(--primary-color)';
                }
                
                const docIconDiv = document.createElement('div');
                docIconDiv.className = 'document-list-icon';
                const docIcon = document.createElement('i');
                docIcon.className = `fas ${fileIcon}`;
                docIcon.style.color = fileIconColor;
                docIconDiv.appendChild(docIcon);
                docLink.appendChild(docIconDiv);
                
                const docInfo = document.createElement('div');
                docInfo.className = 'document-list-info';
                
                const docName = document.createElement('div');
                docName.className = 'document-list-name';
                docName.title = doc.original_filename;
                // Remove extension from filename display
                const filenameWithoutExt = doc.original_filename.replace(/\.[^/.]+$/, '');
                docName.textContent = filenameWithoutExt || doc.original_filename;
                docInfo.appendChild(docName);
                
                const docMeta = document.createElement('div');
                docMeta.className = 'document-list-meta';
                
                // Format file size
                const fileSize = doc.file_size || 0;
                let fileSizeFormatted = '';
                if (fileSize < 1024) {
                    fileSizeFormatted = fileSize + ' B';
                } else if (fileSize < 1024 * 1024) {
                    fileSizeFormatted = Math.round(fileSize / 1024 * 10) / 10 + ' KB';
                } else {
                    fileSizeFormatted = Math.round(fileSize / (1024 * 1024) * 10) / 10 + ' MB';
                }
                
                const docSize = document.createElement('span');
                docSize.className = 'document-list-size';
                docSize.textContent = fileSizeFormatted;
                docMeta.appendChild(docSize);
                
                // Format upload date
                if (doc.upload_date) {
                    const uploadDate = new Date(doc.upload_date);
                    const dateFormatted = uploadDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const docDate = document.createElement('span');
                    docDate.className = 'document-list-date';
                    docDate.textContent = dateFormatted;
                    docMeta.appendChild(docDate);
                }
                
                docInfo.appendChild(docMeta);
                docLink.appendChild(docInfo);
                
                docLinkWrapper.appendChild(docLink);
                docItem.appendChild(docLinkWrapper);
                
                const docActions = document.createElement('div');
                docActions.className = 'document-list-actions';
                
                const downloadLink = document.createElement('a');
                downloadLink.href = `../api/view_document.php?id=${doc.document_id}&download=1`;
                downloadLink.target = '_blank';
                downloadLink.className = 'document-list-download';
                downloadLink.title = 'Download';
                downloadLink.innerHTML = '<i class="fas fa-download"></i>';
                docActions.appendChild(downloadLink);
                
                if (canEdit) {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.onclick = () => deleteDocument(doc.document_id);
                    deleteBtn.className = 'document-list-delete';
                    deleteBtn.title = 'Delete document';
                    deleteBtn.setAttribute('aria-label', `Delete ${escapeHtml(doc.original_filename)}`);
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    deleteBtn.disabled = false;
                    docActions.appendChild(deleteBtn);
                }
                
                docItem.appendChild(docActions);
                documentsList.appendChild(docItem);
            }
        }
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Setup trip document file input
const tripFileInput = document.getElementById('trip_file_input');
if (tripFileInput) {
    setupFileInput(tripFileInput);
}

// Trip document upload
const tripDocumentForm = document.getElementById('tripDocumentForm');
if (tripDocumentForm) {
    // Prevent duplicate handler attachment
    if (tripDocumentForm.dataset.handlerAttached !== 'true') {
        tripDocumentForm.dataset.handlerAttached = 'true';
        
        tripDocumentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        // Prevent duplicate submissions
        if (this.dataset.uploading === 'true') {
            return;
        }
        
        const formData = new FormData(this);
        const fileInput = document.getElementById('trip_file_input');
        const statusDiv = document.getElementById('tripDocumentUploadStatus');
        const submitBtn = document.getElementById('tripDocumentUploadBtn');
        
        if (!fileInput.files || fileInput.files.length === 0) {
            showUploadStatus('error', 'Please select at least one file to upload');
            return;
        }
        
        // Mark as uploading
        this.dataset.uploading = 'true';
        
        // Get tripId from URL or form
        const urlParams = new URLSearchParams(window.location.search);
        const tripIdFromUrl = urlParams.get('id');
        const tripIdInput = this.querySelector('input[name="trip_id"]');
        const tripId = tripIdInput ? tripIdInput.value : tripIdFromUrl;
        
        // Files are already in FormData from the form (name="file[]"), no need to append again
        
        // Show loading state
        const originalHTML = submitBtn.innerHTML;
        const fileCount = fileInput.files.length;
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Uploading ${fileCount} file${fileCount > 1 ? 's' : ''}...`;
        
        if (statusDiv) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'upload-status upload-status-loading';
            statusDiv.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Uploading ${fileCount} file${fileCount > 1 ? 's' : ''}...`;
        }
        
        fetch('../api/upload_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let message = data.message || 'Files uploaded successfully!';
                if (data.failed && data.failed.length > 0) {
                    message += '\n\nSome files failed to upload.';
                }
                showUploadStatus('success', message);
                
                // Add uploaded documents to DOM without reloading
                if (data.uploaded && data.uploaded.length > 0) {
                    addDocumentsToDOM(data.uploaded, null, tripId);
                }
                
                // Reset form
                this.reset();
                this.dataset.uploading = 'false';
                if (statusDiv) {
                    setTimeout(() => {
                        statusDiv.style.display = 'none';
                    }, 3000);
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
                
                // Reset file input display
                const fileInputWrapper = this.querySelector('.file-input-wrapper');
                if (fileInputWrapper) {
                    const fileNameDisplay = fileInputWrapper.querySelector('.file-name-text');
                    const fileSizeDisplay = fileInputWrapper.querySelector('.file-size');
                    if (fileNameDisplay) fileNameDisplay.textContent = '';
                    if (fileSizeDisplay) fileSizeDisplay.textContent = '';
                }
            } else {
                showUploadStatus('error', 'Error: ' + (data.message || 'Failed to upload files'));
                this.dataset.uploading = 'false';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            showUploadStatus('error', 'Error uploading document. Please try again.');
            customAlert('Error uploading document. Please try again.', 'Upload Error');
            console.error(error);
            this.dataset.uploading = 'false';
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHTML;
        });
        });
    }
    
    function showUploadStatus(type, message) {
        const statusDiv = document.getElementById('tripDocumentUploadStatus');
        if (!statusDiv) return;
        
        statusDiv.style.display = 'block';
        statusDiv.className = 'upload-status upload-status-' + type;
        
        const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
        statusDiv.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
        
        if (type === 'success' || type === 'error') {
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }
    }
}

// Delete document
async function deleteDocument(documentId) {
    try {
        // Find document in both locations: timeline items and trip documents list
        const documentItem = document.querySelector(`.document-list-item[data-document-id="${documentId}"]`);
        const documentBadge = document.querySelector(`.document-badge[href*="id=${documentId}"]`);
        const documentButton = document.querySelector(`button[onclick*="deleteDocument(${documentId})"]`);
        
        // Get document name for confirmation
        let documentName = 'this document';
        if (documentItem) {
            const nameEl = documentItem.querySelector('.document-list-name');
            if (nameEl) documentName = nameEl.textContent.trim();
        } else if (documentBadge) {
            documentName = documentBadge.textContent.trim().replace(/[×\s]+$/, '');
        }
        
        const confirmed = await window.customConfirm(
            `Are you sure you want to delete "${documentName}"? This action cannot be undone.`,
            'Delete Document',
            { confirmText: 'Delete' }
        );
        
        if (!confirmed) {
            return;
        }
        
        // Show loading state - only disable the specific document, not the entire container
        if (documentItem) {
            documentItem.style.opacity = '0.5';
            documentItem.style.pointerEvents = 'none';
        }
        if (documentBadge) {
            // Only disable the specific badge and its delete button, not the entire container
            documentBadge.style.opacity = '0.5';
            documentBadge.style.pointerEvents = 'none';
            const deleteBtn = documentBadge.nextElementSibling;
            if (deleteBtn && deleteBtn.tagName === 'BUTTON') {
                deleteBtn.style.opacity = '0.5';
                deleteBtn.style.pointerEvents = 'none';
                deleteBtn.disabled = true;
            }
        }
        
        const formData = new FormData();
        formData.append('document_id', documentId);
        
        const response = await fetch('../api/delete_document.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove from trip documents list
            if (documentItem) {
                documentItem.style.transition = 'all 0.3s ease';
                documentItem.style.transform = 'scale(0.95)';
                documentItem.style.opacity = '0';
                setTimeout(() => {
                    documentItem.remove();
                    // Check if trip documents section should be hidden
                    const remainingTripDocs = document.querySelectorAll('.document-list-item');
                    if (remainingTripDocs.length === 0) {
                        const tripDocumentsCard = document.querySelector('.card:has(.document-list)');
                        if (tripDocumentsCard) {
                            tripDocumentsCard.style.transition = 'all 0.3s ease';
                            tripDocumentsCard.style.opacity = '0';
                            setTimeout(() => tripDocumentsCard.remove(), 300);
                        }
                    }
                }, 300);
            }
            
            // Remove from timeline item documents
            if (documentBadge) {
                const badgeContainer = documentBadge.closest('.timeline-documents');
                const itemWrapper = documentBadge.closest('.timeline-item-wrapper');
                const deleteBtn = documentBadge.nextElementSibling;
                
                if (badgeContainer) {
                    // Get item ID before removing elements
                    const itemId = itemWrapper ? itemWrapper.getAttribute('data-item-id') : null;
                    
                    // Animate and remove both badge and button
                    documentBadge.style.transition = 'all 0.3s ease';
                    documentBadge.style.opacity = '0';
                    documentBadge.style.transform = 'scale(0.95)';
                    
                    if (deleteBtn && deleteBtn.tagName === 'BUTTON') {
                        deleteBtn.style.transition = 'all 0.3s ease';
                        deleteBtn.style.opacity = '0';
                        deleteBtn.style.transform = 'scale(0.95)';
                    }
                    
                    setTimeout(() => {
                        documentBadge.remove();
                        if (deleteBtn && deleteBtn.tagName === 'BUTTON') {
                            deleteBtn.remove();
                        }
                        
                        // Check if timeline documents section is now empty
                        const remainingBadges = badgeContainer.querySelectorAll('.document-badge');
                        if (remainingBadges.length === 0) {
                            badgeContainer.style.transition = 'all 0.3s ease';
                            badgeContainer.style.opacity = '0';
                            setTimeout(() => {
                                badgeContainer.remove();
                            }, 300);
                        }
                        
                        // Update attachment icon if no documents remain for that item
                        if (itemId && itemWrapper) {
                            const remainingItemDocs = itemWrapper.querySelectorAll('.document-badge');
                            if (remainingItemDocs.length === 0) {
                                // Remove attachment icon from item header
                                const itemHeader = itemWrapper.querySelector('.expandable-item-header');
                                if (itemHeader) {
                                    const attachmentIcon = itemHeader.querySelector('.fa-paperclip[title="Has attachments"]');
                                    if (attachmentIcon) {
                                        attachmentIcon.style.transition = 'all 0.3s ease';
                                        attachmentIcon.style.opacity = '0';
                                        setTimeout(() => attachmentIcon.remove(), 300);
                                    }
                                }
                            }
                        }
                    }, 300);
                }
            }
            
        } else {
            await window.customAlert(data.message || 'Failed to delete document', 'Error');
            if (documentItem) {
                documentItem.style.opacity = '1';
                documentItem.style.pointerEvents = '';
            }
            if (documentBadge) {
                // Restore the specific badge and button
                documentBadge.style.opacity = '1';
                documentBadge.style.pointerEvents = '';
                const deleteBtn = documentBadge.nextElementSibling;
                if (deleteBtn && deleteBtn.tagName === 'BUTTON') {
                    deleteBtn.style.opacity = '1';
                    deleteBtn.style.pointerEvents = '';
                    deleteBtn.disabled = false;
                }
            }
        }
    } catch (error) {
        console.error('Error deleting document:', error);
        await window.customAlert('Error deleting document. Please try again.', 'Error');
        const documentItem = document.querySelector(`.document-list-item[data-document-id="${documentId}"]`);
        const documentBadge = document.querySelector(`.document-badge[href*="id=${documentId}"]`);
        if (documentItem) {
            documentItem.style.opacity = '1';
            documentItem.style.pointerEvents = '';
        }
        if (documentBadge) {
            // Restore the specific badge and button
            documentBadge.style.opacity = '1';
            documentBadge.style.pointerEvents = '';
            const deleteBtn = documentBadge.nextElementSibling;
            if (deleteBtn && deleteBtn.tagName === 'BUTTON') {
                deleteBtn.style.opacity = '1';
                deleteBtn.style.pointerEvents = '';
                deleteBtn.disabled = false;
            }
        }
    }
}

// Expandable date headers - support both click and touch events
document.querySelectorAll('.expandable-header').forEach(header => {
    function handleToggle(e) {
        e.preventDefault();
        e.stopPropagation();
        const date = header.getAttribute('data-date');
        const content = document.querySelector(`.timeline-date-content[data-date="${date}"]`);
        const icon = header.querySelector('.expand-icon');
        
        if (content) {
            const isExpanded = content.style.display !== 'none';
            content.style.display = isExpanded ? 'none' : 'block';
            if (icon) {
                icon.style.transform = isExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';
            }
            
            // Store state in localStorage
            localStorage.setItem(`timeline-date-${date}`, isExpanded ? 'collapsed' : 'expanded');
        }
    }
    
    header.addEventListener('click', handleToggle);
    header.addEventListener('touchend', handleToggle);
    
    // Restore state from localStorage
    const date = header.getAttribute('data-date');
    const savedState = localStorage.getItem(`timeline-date-${date}`);
    if (savedState === 'collapsed') {
        const content = document.querySelector(`.timeline-date-content[data-date="${date}"]`);
        const icon = header.querySelector('.expand-icon');
        if (content) {
            content.style.display = 'none';
            if (icon) {
                icon.style.transform = 'rotate(-90deg)';
            }
        }
    }
});

// Expandable item details - support both click and touch events
document.querySelectorAll('.expandable-item-header').forEach(header => {
    function handleToggle(e) {
        e.preventDefault();
        e.stopPropagation();
        const itemId = header.getAttribute('data-item-id');
        const details = document.querySelector(`.timeline-item-details[data-item-id="${itemId}"]`);
        const icon = header.querySelector('.expand-item-icon');
        
        if (details) {
            const isExpanded = details.style.display !== 'none';
            details.style.display = isExpanded ? 'none' : 'block';
            if (icon) {
                icon.className = isExpanded ? 'fas fa-chevron-down expand-item-icon' : 'fas fa-chevron-right expand-item-icon';
                icon.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(0deg)';
            }
        }
    }
    
    header.addEventListener('click', handleToggle);
    header.addEventListener('touchend', handleToggle);
});

// Item search filter
const itemSearchInput = document.getElementById('itemSearch');
const clearSearchBtn = document.getElementById('clearSearch');
const searchResultsMessage = document.getElementById('searchResultsMessage');

if (itemSearchInput) {
    itemSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        // Show/hide clear button
        if (clearSearchBtn) {
            clearSearchBtn.style.display = searchTerm ? 'flex' : 'none';
        }
        
        // If search is empty, show all items and date groups
        if (!searchTerm) {
            document.querySelectorAll('.expandable-item').forEach(item => {
                item.style.display = '';
            });
            document.querySelectorAll('.timeline-date-group').forEach(group => {
                group.style.display = '';
            });
            if (searchResultsMessage) {
                searchResultsMessage.style.display = 'none';
            }
            return;
        }
        
        // Search through all items
        let visibleCount = 0;
        let hiddenCount = 0;
        const items = document.querySelectorAll('.expandable-item');
        
        items.forEach(item => {
            // Get all searchable data
            const searchData = item.getAttribute('data-item-search') || '';
            const title = item.getAttribute('data-item-title') || '';
            const location = item.getAttribute('data-item-location') || '';
            const description = item.getAttribute('data-item-description') || '';
            const type = item.getAttribute('data-item-type') || '';
            
            // Check if search term matches any field
            const matches = searchData.includes(searchTerm) || 
                          title.includes(searchTerm) || 
                          location.includes(searchTerm) || 
                          description.includes(searchTerm) ||
                          type.includes(searchTerm);
            
            // Hide/show the entire item wrapper
            item.style.display = matches ? '' : 'none';
            
            if (matches) {
                visibleCount++;
            } else {
                hiddenCount++;
            }
        });
        
        // Hide/show date groups based on whether they have visible items
        document.querySelectorAll('.timeline-date-group').forEach(dateGroup => {
            const dateItems = dateGroup.querySelectorAll('.expandable-item');
            const visibleItems = Array.from(dateItems).filter(item => item.style.display !== 'none');
            
            if (visibleItems.length === 0 && dateItems.length > 0) {
                // Hide the entire date group if no items are visible
                dateGroup.style.display = 'none';
            } else {
                // Show the date group if it has visible items
                dateGroup.style.display = '';
            }
        });
        
        // Show search results message
        if (searchResultsMessage) {
            if (visibleCount === 0 && items.length > 0) {
                searchResultsMessage.textContent = `No items found matching "${this.value}"`;
                searchResultsMessage.style.display = 'block';
                searchResultsMessage.style.background = 'rgba(231, 76, 60, 0.1)';
                searchResultsMessage.style.color = '#E74C3C';
            } else if (visibleCount > 0) {
                searchResultsMessage.textContent = `Found ${visibleCount} item${visibleCount === 1 ? '' : 's'}`;
                searchResultsMessage.style.display = 'block';
                searchResultsMessage.style.background = 'rgba(74, 144, 226, 0.1)';
                searchResultsMessage.style.color = 'var(--primary-color)';
            } else {
                searchResultsMessage.style.display = 'none';
            }
        }
    });
    
    // Clear search button
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            itemSearchInput.value = '';
            itemSearchInput.dispatchEvent(new Event('input'));
            itemSearchInput.focus();
        });
    }
    
    // Clear search on Escape key
    itemSearchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            this.dispatchEvent(new Event('input'));
        }
    });
}

// Create invitation
document.getElementById('createInvitationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../api/create_invitation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            customAlert(data.message, 'Error');
        }
    })
    .catch(error => {
        customAlert('Error creating invitation', 'Error');
        console.error(error);
    });
});

// Delete invitation
async function deleteInvitation(invitationId) {
    const confirmed = await customConfirm(
        'Are you sure you want to delete this invitation?',
        'Delete Invitation',
        { confirmText: 'Delete' }
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('invitation_id', invitationId);
    
    fetch('../api/delete_invitation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            customAlert(data.message, 'Error');
        }
    })
    .catch(error => {
        customAlert('Error deleting invitation', 'Error');
        console.error(error);
    });
}

// Remove collaborator
async function removeCollaborator(userId) {
    const confirmed = await customConfirm(
        'Are you sure you want to remove this collaborator?',
        'Remove Collaborator',
        { confirmText: 'Remove' }
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
    formData.append('trip_id', <?php echo $tripId; ?>);
    formData.append('user_id', userId);
    
    fetch('../api/remove_collaborator.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            customAlert(data.message, 'Error');
        }
    })
    .catch(error => {
        customAlert('Error removing collaborator', 'Error');
        console.error(error);
    });
}

// Share Trip Functionality
function shareTrip() {
    const tripId = <?php echo $tripId; ?>;
    const isOwner = <?php echo $isOwner ? 'true' : 'false'; ?>;
    const isPubliclyShared = <?php echo ($trip['is_publicly_shared'] ?? 0) ? 'true' : 'false'; ?>;
    const shareToken = <?php echo !empty($trip['share_token']) ? json_encode($trip['share_token']) : 'null'; ?>;
    
    if (!isOwner) {
        // Non-owners can use the old share method (share current page)
        const tripTitle = document.querySelector('.trip-header-title')?.textContent || 'Trip';
        const tripUrl = window.location.href;
        
        if (navigator.share) {
            navigator.share({
                title: tripTitle,
                text: `Check out my trip: ${tripTitle}`,
                url: tripUrl
            }).catch(err => {
                copyToClipboard(tripUrl);
            });
        } else {
            copyToClipboard(tripUrl);
        }
        return;
    }
    
    // Show sharing modal for owners
    showSharingModal(tripId, isPubliclyShared, shareToken);
}

function showSharingModal(tripId, isPubliclyShared, shareToken) {
    const modalTitle = 'Share Trip';
    let modalBody = '';
    let footerButtons = '';
    
    if (isPubliclyShared && shareToken) {
        // Already shared - show link and option to disable
        const protocol = window.location.protocol;
        const host = window.location.host;
        const basePath = '<?php echo defined('BASE_PATH') ? BASE_PATH : ''; ?>';
        const shareUrl = protocol + '//' + host + basePath + '/pages/trip_public.php?token=' + shareToken;
        
        modalBody = `
            <div style="margin-bottom: 1rem;">
                <p style="margin-bottom: 0.75rem; color: var(--text-color);">Your trip is publicly shared. Anyone with this link can view it:</p>
                <div style="display: flex; gap: 0.5rem; align-items: center; background: var(--bg-color); padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border-color);">
                    <input type="text" id="shareLinkInput" value="${shareUrl}" readonly style="flex: 1; border: none; background: transparent; color: var(--text-color); font-size: 0.9rem; padding: 0;">
                    <button type="button" onclick="copyShareLink()" class="btn btn-small" style="flex-shrink: 0;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <p style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-light);">
                    <i class="fas fa-info-circle"></i> Only people with this link can access your trip.
                </p>
            </div>
        `;
        
        footerButtons = `
            <button type="button" onclick="disableSharing(${tripId})" class="btn btn-secondary">
                <i class="fas fa-times"></i> Disable Sharing
            </button>
            <button type="button" onclick="customModal.hide()" class="btn">
                Close
            </button>
        `;
    } else {
        // Not shared - show option to enable
        modalBody = `
            <div style="margin-bottom: 1rem;">
                <p style="margin-bottom: 0.75rem; color: var(--text-color);">Enable public sharing to create a shareable link for your trip.</p>
                <div style="background: rgba(74, 144, 226, 0.1); padding: 0.75rem; border-radius: 6px; border-left: 3px solid var(--primary-color); margin-bottom: 1rem;">
                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-color);">
                        <i class="fas fa-shield-alt"></i> <strong>Privacy:</strong> Only people with the link can access your trip. The link is not discoverable by search engines or other users.
                    </p>
                </div>
            </div>
        `;
        
        footerButtons = `
            <button type="button" onclick="customModal.hide()" class="btn btn-secondary">
                Cancel
            </button>
            <button type="button" onclick="enableSharing(${tripId})" class="btn">
                <i class="fas fa-share-alt"></i> Enable Sharing
            </button>
        `;
    }
    
    customModal.show(modalTitle, modalBody, 'dialog', { html: true, footer: footerButtons });
}

function copyShareLink() {
    const input = document.getElementById('shareLinkInput');
    if (input) {
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices
        try {
            document.execCommand('copy');
            customAlert('Share link copied to clipboard!', 'Success');
        } catch (err) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(() => {
                    customAlert('Share link copied to clipboard!', 'Success');
                });
            } else {
                customAlert('Unable to copy. Please select and copy manually.', 'Info');
            }
        }
    }
}

function enableSharing(tripId) {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enabling...';
    
    fetch('<?php echo (defined('BASE_PATH') ? BASE_PATH : '') . '/api/toggle_trip_sharing.php'; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `trip_id=${tripId}&enable=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to show updated sharing status
            window.location.reload();
        } else {
            customAlert(data.message || 'Failed to enable sharing', 'Error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        customAlert('An error occurred. Please try again.', 'Error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function disableSharing(tripId) {
    if (!confirm('Are you sure you want to disable public sharing? People with the link will no longer be able to access this trip.')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Disabling...';
    
    fetch('<?php echo (defined('BASE_PATH') ? BASE_PATH : '') . '/api/toggle_trip_sharing.php'; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `trip_id=${tripId}&enable=0`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to show updated sharing status
            window.location.reload();
        } else {
            customAlert(data.message || 'Failed to disable sharing', 'Error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        customAlert('An error occurred. Please try again.', 'Error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            customAlert('Trip link copied to clipboard!', 'Success');
        }).catch(() => {
            // Fallback for older browsers
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        customAlert('Trip link copied to clipboard!', 'Success');
    } catch (err) {
        customAlert('Unable to copy link. Please copy manually: ' + text, 'Info');
    }
    document.body.removeChild(textArea);
}

// Location Autocomplete Functionality
let autocompleteTimeout;
let activeAutocomplete = null;

function initAutocomplete(input) {
    if (!input || input.dataset.autocompleteInit) return;
    input.dataset.autocompleteInit = 'true';
    
    const dropdown = input.parentElement.querySelector('.autocomplete-dropdown');
    
    input.addEventListener('input', function() {
        clearTimeout(autocompleteTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }
        
        // Remove flag emoji from query for search
        const searchQuery = query.replace(/[\u{1F1E6}-\u{1F1FF}]/gu, '').trim();
        
        autocompleteTimeout = setTimeout(() => {
            fetch(`../api/geocode_location.php?q=${encodeURIComponent(searchQuery)}&limit=10`)
                .then(response => response.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        showAutocompleteResults(dropdown, data.results, input);
                    } else {
                        dropdown.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Autocomplete error:', error);
                    dropdown.style.display = 'none';
                });
        }, 300);
    });
    
    input.addEventListener('blur', function() {
        // Delay hiding to allow click on dropdown
        setTimeout(() => {
            if (activeAutocomplete !== input) {
                dropdown.style.display = 'none';
            }
        }, 200);
    });
    
    input.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            const searchQuery = this.value.replace(/[\u{1F1E6}-\u{1F1FF}]/gu, '').trim();
            fetch(`../api/geocode_location.php?q=${encodeURIComponent(searchQuery)}&limit=10`)
                .then(response => response.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        showAutocompleteResults(dropdown, data.results, input);
                    }
                })
                .catch(error => console.error('Autocomplete error:', error));
        }
    });
}

function showAutocompleteResults(dropdown, results, input) {
    dropdown.innerHTML = '';
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'autocomplete-item';
        item.textContent = result.display || (result.flag + ' ' + result.name);
        item.addEventListener('click', function() {
            input.value = result.display || (result.flag + ' ' + result.name);
            input.dataset.destinationData = JSON.stringify({
                name: result.name,
                country: result.country || result.name,
                country_code: result.country_code || '',
                flag: result.flag || '',
                city: result.city || '',
                full_name: result.full_name || result.name
            });
            dropdown.style.display = 'none';
            activeAutocomplete = null;
        });
        dropdown.appendChild(item);
    });
    dropdown.style.display = 'block';
    activeAutocomplete = input;
}

// Initialize autocomplete on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.destination-autocomplete').forEach(input => {
        initAutocomplete(input);
    });
    
    // Trip actions dropdown
    const tripActionsToggle = document.getElementById('tripActionsToggle');
    const tripActionsMenu = document.getElementById('tripActionsMenu');
    
    if (tripActionsToggle && tripActionsMenu) {
        function positionMenu() {
            const toggleRect = tripActionsToggle.getBoundingClientRect();
            tripActionsMenu.style.top = (toggleRect.bottom + 8) + 'px';
            tripActionsMenu.style.right = (window.innerWidth - toggleRect.right) + 'px';
        }
        
        tripActionsToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = tripActionsMenu.classList.contains('show');
            
            if (isOpen) {
                tripActionsMenu.classList.remove('show');
                tripActionsToggle.setAttribute('aria-expanded', 'false');
            } else {
                positionMenu();
                tripActionsMenu.classList.add('show');
                tripActionsToggle.setAttribute('aria-expanded', 'true');
            }
        });
        
        // Reposition on window resize
        window.addEventListener('resize', function() {
            if (tripActionsMenu.classList.contains('show')) {
                positionMenu();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!tripActionsToggle.contains(e.target) && !tripActionsMenu.contains(e.target)) {
                tripActionsMenu.classList.remove('show');
                tripActionsToggle.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close dropdown when clicking on an action item
        tripActionsMenu.querySelectorAll('.trip-action-item').forEach(item => {
            item.addEventListener('click', function() {
                // Small delay for visual feedback before closing
                setTimeout(() => {
                    tripActionsMenu.classList.remove('show');
                    tripActionsToggle.setAttribute('aria-expanded', 'false');
                }, 100);
            });
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && tripActionsMenu.classList.contains('show')) {
                tripActionsMenu.classList.remove('show');
                tripActionsToggle.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Prevent menu from closing when clicking inside it (for better mobile experience)
        tripActionsMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
</script>

<style>
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    margin-top: 2px;
}

.autocomplete-item {
    padding: 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover {
    background-color: #f5f5f5;
}

.autocomplete-item:active {
    background-color: #e0e0e0;
}

.destination-item {
    position: relative;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

