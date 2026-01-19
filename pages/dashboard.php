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
                <label class="form-label" for="start_date">Start Date *</label>
                <input type="date" id="start_date" name="start_date" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-input">
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
        <input type="date" id="dateFrom" class="filter-select" placeholder="From Date" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
        <input type="date" id="dateTo" class="filter-select" placeholder="To Date" value="<?php echo htmlspecialchars($filterDateTo); ?>">
        <button type="button" id="clearFilters" class="btn btn-secondary btn-small">Clear</button>
    </div>
    
    <div class="trips-list">
        <?php if (empty($trips)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state-icon">✈️</div>
                    <h2>No trips found</h2>
                    <p><?php echo !empty($search) || !empty($filterDateFrom) || !empty($filterDateTo) ? 'Try adjusting your search or filters.' : 'Start planning your next adventure!'; ?></p>
                    <?php if (empty($search) && empty($filterDateFrom) && empty($filterDateTo)): ?>
                        <a href="dashboard.php?action=add" class="btn" style="max-width: 200px; margin: 1rem auto 0;">Create Your First Trip</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($trips as $trip): ?>
                <a href="trip_detail.php?id=<?php echo $trip['id']; ?>" class="card" style="text-decoration: none; display: block;">
                    <div class="card-title">
                        <?php echo htmlspecialchars($trip['title']); ?>
                        <?php if (($trip['user_role'] ?? 'owner') !== 'owner'): ?>
                            <span class="badge" style="background: #FFF3E0; color: #F57C00; margin-left: 0.5rem;">Shared</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-subtitle">
                        <?php 
                        echo date(DATE_FORMAT, strtotime($trip['start_date']));
                        if ($trip['end_date']) {
                            echo ' - ' . date(DATE_FORMAT, strtotime($trip['end_date']));
                        }
                        ?>
                    </div>
                    <?php if ($trip['item_count'] > 0): ?>
                        <div class="card-subtitle">
                            <?php echo $trip['item_count']; ?> travel item<?php echo $trip['item_count'] > 1 ? 's' : ''; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($trip['description']): ?>
                        <p style="margin-top: 0.5rem; color: var(--text-light); font-size: 0.9rem;">
                            <?php echo htmlspecialchars(substr($trip['description'], 0, 100)); ?>
                            <?php echo strlen($trip['description']) > 100 ? '...' : ''; ?>
                        </p>
                    <?php endif; ?>
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
document.getElementById('dateFrom')?.addEventListener('change', applyFilters);
document.getElementById('dateTo')?.addEventListener('change', applyFilters);

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

// Trip form submission
document.getElementById('tripForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
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

