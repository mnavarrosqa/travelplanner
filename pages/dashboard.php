<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$pageTitle = 'My Trips';
$showBack = false;
include __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$userId = getCurrentUserId();

// Handle search and filters
$search = $_GET['search'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Build query - include trips user owns or has access to
// Using subquery to avoid GROUP BY issues in strict mode
$query = "
    SELECT t.*, 
           COALESCE(item_counts.item_count, 0) as item_count,
           item_counts.earliest_date,
           CASE 
               WHEN t.user_id = ? THEN 'owner'
               ELSE tu.role
           END as user_role
    FROM trips t
    LEFT JOIN trip_users tu ON t.id = tu.trip_id AND tu.user_id = ?
    LEFT JOIN (
        SELECT trip_id, 
               COUNT(*) as item_count,
               MIN(start_datetime) as earliest_date
        FROM travel_items
        GROUP BY trip_id
    ) item_counts ON t.id = item_counts.trip_id
    WHERE (t.user_id = ? OR tu.user_id = ?)
";

$params = [$userId, $userId, $userId, $userId];

if (!empty($search)) {
    $query .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($filterDateFrom)) {
    $query .= " AND t.start_date >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $query .= " AND t.end_date <= ?";
    $params[] = $filterDateTo;
}

$query .= " GROUP BY t.id ORDER BY t.start_date DESC, t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// Check if we're adding a new trip
$action = $_GET['action'] ?? '';
?>

<?php if ($action === 'add'): ?>
    <div class="card">
        <h2 class="card-title">Create New Trip</h2>
        <form id="tripForm" method="POST" action="../api/add_trip.php">
            <div class="form-group">
                <label class="form-label" for="title">Trip Title *</label>
                <input type="text" id="title" name="title" class="form-input" required placeholder="e.g., Summer Europe 2024">
            </div>

            <div class="form-group">
                <label class="form-label" for="cover_image">Cover Image</label>
                <input type="file" id="cover_image" name="cover_image" class="form-input" accept="image/*">
                <span class="form-help">Optional. Upload an image to use as the background in the trip header and trip cards.</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="start_date">Start Date *</label>
                <input type="date" id="start_date" name="start_date" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="travel_type">Type of Travel</label>
                <select id="travel_type" name="travel_type" class="form-select">
                    <option value="">Select type...</option>
                    <option value="vacations">Vacations</option>
                    <option value="work">Work</option>
                    <option value="business">Business</option>
                    <option value="family">Family</option>
                    <option value="leisure">Leisure</option>
                    <option value="adventure">Adventure</option>
                    <option value="romantic">Romantic</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="status">Trip Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active" selected>Active</option>
                    <option value="completed">Completed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Number of Destinations</label>
                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="destination_type" value="single" checked onchange="toggleDestinations()">
                        <span>Single Destination</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="destination_type" value="multiple" onchange="toggleDestinations()">
                        <span>Multiple Destinations</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" id="single_destination_group">
                <label class="form-label" for="single_destination">Destination *</label>
                <div style="position: relative;">
                    <input type="text" id="single_destination" name="single_destination" class="form-input destination-autocomplete" 
                           placeholder="e.g., Paris, France" 
                           autocomplete="off"
                           required>
                    <div id="single_destination_autocomplete" class="autocomplete-dropdown" style="display: none;"></div>
                </div>
            </div>
            
            <div class="form-group" id="multiple_destinations_group" style="display: none;">
                <label class="form-label">Destinations *</label>
                <div id="destinations_container">
                    <div class="destination-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <div style="position: relative; flex: 1;">
                            <input type="text" name="destinations[]" class="form-input destination-autocomplete" 
                                   placeholder="e.g., Paris, France" 
                                   autocomplete="off"
                                   required>
                            <div class="autocomplete-dropdown" style="display: none;"></div>
                        </div>
                        <button type="button" class="btn btn-small" onclick="removeDestination(this)" style="min-width: 44px;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-small" onclick="addDestination()" style="margin-top: 0.5rem;">
                    <i class="fas fa-plus"></i> Add Destination
                </button>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" placeholder="Notes about your trip..."></textarea>
            </div>
            
            <button type="submit" class="btn">Create Trip</button>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-top: 0.5rem;">Cancel</a>
        </form>
    </div>
<?php else: ?>
    <div class="search-bar">
        <input type="text" id="searchInput" class="search-input" placeholder="Search trips..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <div class="filter-section">
        <div class="date-filter-wrapper">
            <label for="dateFrom" class="date-filter-label">
                <i class="fas fa-calendar-alt" style="color: var(--primary-color);"></i>
                <span class="date-filter-text">From Date</span>
                <span class="date-filter-value" id="dateFromValue"><?php echo $filterDateFrom ? date('M d, Y', strtotime($filterDateFrom)) : ''; ?></span>
            </label>
            <input type="date" id="dateFrom" class="filter-select date-input-hidden" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
        </div>
        <div class="date-filter-wrapper">
            <label for="dateTo" class="date-filter-label">
                <i class="fas fa-calendar-alt" style="color: var(--primary-color);"></i>
                <span class="date-filter-text">To Date</span>
                <span class="date-filter-value" id="dateToValue"><?php echo $filterDateTo ? date('M d, Y', strtotime($filterDateTo)) : ''; ?></span>
            </label>
            <input type="date" id="dateTo" class="filter-select date-input-hidden" value="<?php echo htmlspecialchars($filterDateTo); ?>">
        </div>
        <button type="button" id="clearFilters" class="btn btn-secondary btn-small">Clear</button>
    </div>
    
    <div class="trips-list">
        <?php if (empty($trips)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-plane" style="font-size: 3rem; color: var(--primary-color);"></i></div>
                    <h2>No trips found</h2>
                    <p><?php echo !empty($search) || !empty($filterDateFrom) || !empty($filterDateTo) ? 'Try adjusting your search or filters.' : 'Start planning your next adventure!'; ?></p>
                    <?php if (empty($search) && empty($filterDateFrom) && empty($filterDateTo)): ?>
                        <a href="dashboard.php?action=add" class="btn" style="max-width: 200px; margin: 1rem auto 0;">Create Your First Trip</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php 
            // Helper function to convert country code to flag emoji
            function getCountryFlagEmoji($countryCode) {
                if (empty($countryCode) || strlen($countryCode) !== 2) {
                    return '';
                }
                
                $countryCode = strtoupper($countryCode);
                $char1 = ord($countryCode[0]) - ord('A');
                $char2 = ord($countryCode[1]) - ord('A');
                
                if ($char1 < 0 || $char1 > 25 || $char2 < 0 || $char2 > 25) {
                    return '';
                }
                
                $codePoint1 = 0x1F1E6 + $char1;
                $codePoint2 = 0x1F1E6 + $char2;
                
                return json_decode('"' . sprintf('\\u%04X\\u%04X', $codePoint1, $codePoint2) . '"');
            }
            
            foreach ($trips as $trip): 
                $destinations = [];
                if (!empty($trip['destinations'])) {
                    $destinations = json_decode($trip['destinations'], true) ?: [];
                }

                $coverImage = trim($trip['cover_image'] ?? '');
                $hasCoverImage = $coverImage !== '';
                $coverImageUrl = '';
                if ($hasCoverImage) {
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    $coverImageUrl = ($basePath ? $basePath : '') . '/' . ltrim($coverImage, '/');
                    if (substr($coverImageUrl, 0, 1) !== '/') {
                        $coverImageUrl = '/' . $coverImageUrl;
                    }
                }
                
                // Calculate duration
                $duration = '';
                if ($trip['end_date']) {
                    $startDate = new DateTime($trip['start_date']);
                    $endDate = new DateTime($trip['end_date']);
                    $diff = $startDate->diff($endDate);
                    $days = $diff->days;
                    $duration = $days . ' ' . ($days === 1 ? 'day' : 'days');
                }
            ?>
                <a href="trip_detail.php?id=<?php echo $trip['id']; ?>" class="trip-card<?php echo $hasCoverImage ? ' has-cover' : ''; ?>"<?php if ($hasCoverImage): ?> style='--trip-cover-image: url(<?php echo htmlspecialchars(json_encode($coverImageUrl), ENT_QUOTES, 'UTF-8'); ?>);'<?php endif; ?>>
                    <div class="trip-card-content">
                        <div class="trip-card-header">
                            <div class="trip-card-title-section">
                                <h3 class="trip-card-title">
                                    <?php echo htmlspecialchars($trip['title']); ?>
                                </h3>
                                <div class="trip-card-badges">
                                    <?php 
                                    $status = $trip['status'] ?? 'active';
                                    $statusLabels = ['active' => 'Active', 'completed' => 'Completed', 'archived' => 'Archived'];
                                    $statusIcons = ['active' => 'fa-circle', 'completed' => 'fa-check-circle', 'archived' => 'fa-archive'];
                                    ?>
                                    <span class="trip-badge trip-badge-status trip-badge-status-<?php echo htmlspecialchars($status); ?>">
                                        <i class="fas <?php echo $statusIcons[$status] ?? 'fa-circle'; ?>"></i> <?php echo $statusLabels[$status] ?? 'Active'; ?>
                                    </span>
                                    <?php if (!empty($trip['travel_type'])): ?>
                                        <span class="trip-badge trip-badge-type">
                                            <i class="fas fa-bookmark"></i> <?php echo htmlspecialchars(ucfirst($trip['travel_type'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (($trip['user_role'] ?? 'owner') !== 'owner'): ?>
                                        <span class="trip-badge trip-badge-shared">
                                            <i class="fas fa-users"></i> Shared
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="trip-card-info">
                            <div class="trip-card-dates">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo date('M j, Y', strtotime($trip['start_date'])); ?></span>
                                <?php if ($trip['end_date']): ?>
                                    <span class="trip-card-arrow">→</span>
                                    <span><?php echo date('M j, Y', strtotime($trip['end_date'])); ?></span>
                                <?php endif; ?>
                                <?php if ($duration): ?>
                                    <span class="trip-card-duration">(<?php echo $duration; ?>)</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($destinations)): ?>
                                <div class="trip-card-destinations">
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
                                        echo implode(' <span class="trip-card-arrow">→</span> ', $destDisplays);
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($trip['item_count'] > 0): ?>
                                <div class="trip-card-stats">
                                    <i class="fas fa-list-ul"></i>
                                    <span><?php echo $trip['item_count']; ?> travel item<?php echo $trip['item_count'] > 1 ? 's' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($trip['description']): ?>
                            <p class="trip-card-description">
                                <?php echo htmlspecialchars(substr($trip['description'], 0, 120)); ?>
                                <?php echo strlen($trip['description']) > 120 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function() {
    const search = this.value;
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    
    // Debounce search
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        window.location.href = 'dashboard.php?' + params.toString();
    }, 500);
});

// Date filter changes
const dateFromInput = document.getElementById('dateFrom');
const dateToInput = document.getElementById('dateTo');
const dateFromValue = document.getElementById('dateFromValue');
const dateToValue = document.getElementById('dateToValue');

// Function to format date for display
function formatDateForDisplay(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString + 'T00:00:00');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
    } catch (e) {
        return dateString;
    }
}

// Function to update date display
function updateDateDisplay(input, valueElement) {
    if (input && valueElement) {
        if (input.value) {
            valueElement.textContent = formatDateForDisplay(input.value);
            valueElement.style.color = 'var(--text-color)';
            valueElement.style.fontWeight = '600';
        } else {
            valueElement.textContent = '';
        }
    }
}

// Update date displays on load
if (dateFromInput && dateFromValue) {
    updateDateDisplay(dateFromInput, dateFromValue);
    
    dateFromInput.addEventListener('change', function() {
        updateDateDisplay(this, dateFromValue);
        applyFilters();
    });
    
    dateFromInput.addEventListener('input', function() {
        updateDateDisplay(this, dateFromValue);
    });
}

if (dateToInput && dateToValue) {
    updateDateDisplay(dateToInput, dateToValue);
    
    dateToInput.addEventListener('change', function() {
        updateDateDisplay(this, dateToValue);
        applyFilters();
    });
    
    dateToInput.addEventListener('input', function() {
        updateDateDisplay(this, dateToValue);
    });
}

function applyFilters() {
    const search = document.getElementById('searchInput')?.value || '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    
    window.location.href = 'dashboard.php?' + params.toString();
}

// Clear filters
document.getElementById('clearFilters')?.addEventListener('click', function() {
    window.location.href = 'dashboard.php';
});

// Toggle between single and multiple destinations
function toggleDestinations() {
    const destinationType = document.querySelector('input[name="destination_type"]:checked').value;
    const singleGroup = document.getElementById('single_destination_group');
    const multipleGroup = document.getElementById('multiple_destinations_group');
    const singleInput = document.getElementById('single_destination');
    
    if (destinationType === 'single') {
        singleGroup.style.display = 'block';
        multipleGroup.style.display = 'none';
        singleInput.required = true;
        document.querySelectorAll('#multiple_destinations_group input[name="destinations[]"]').forEach(input => {
            input.required = false;
        });
    } else {
        singleGroup.style.display = 'none';
        multipleGroup.style.display = 'block';
        singleInput.required = false;
        document.querySelectorAll('#multiple_destinations_group input[name="destinations[]"]').forEach(input => {
            input.required = true;
        });
    }
}

// Add destination field
function addDestination() {
    const container = document.getElementById('destinations_container');
    const div = document.createElement('div');
    div.className = 'destination-item';
    div.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.5rem;';
    div.innerHTML = `
        <div style="position: relative; flex: 1;">
            <input type="text" name="destinations[]" class="form-input destination-autocomplete" 
                   placeholder="e.g., Paris, France" 
                   autocomplete="off"
                   required>
            <div class="autocomplete-dropdown" style="display: none;"></div>
        </div>
        <button type="button" class="btn btn-small" onclick="removeDestination(this)" style="min-width: 44px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
    initAutocomplete(div.querySelector('.destination-autocomplete'));
}

// Remove destination field
function removeDestination(button) {
    const container = document.getElementById('destinations_container');
    if (container.children.length > 1) {
        button.closest('.destination-item').remove();
    } else {
        alert('You must have at least one destination.');
    }
}

// Trip form submission
document.getElementById('tripForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Handle destinations based on type
    const destinationType = document.querySelector('input[name="destination_type"]:checked').value;
    if (destinationType === 'single') {
        const singleDest = document.getElementById('single_destination');
        const singleDestValue = singleDest.value.trim();
        if (singleDestValue) {
            let destObj = {name: singleDestValue.replace(/[\u{1F1E6}-\u{1F1FF}]/gu, '').trim()};
            if (singleDest.dataset.destinationData) {
                try {
                    const data = JSON.parse(singleDest.dataset.destinationData);
                    destObj = {...destObj, ...data};
                } catch(e) {}
            }
            formData.set('destinations', JSON.stringify([destObj]));
            formData.set('is_multiple_destinations', '0');
        }
    } else {
        const destinations = Array.from(document.querySelectorAll('#multiple_destinations_group input[name="destinations[]"]'))
            .map(input => {
                let destObj = {name: input.value.trim().replace(/[\u{1F1E6}-\u{1F1FF}]/gu, '').trim()};
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
    formData.delete('single_destination');
    formData.delete('destination_type');
    
    fetch('../api/add_trip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'trip_detail.php?id=' + data.trip_id;
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error creating trip');
        console.error(error);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

