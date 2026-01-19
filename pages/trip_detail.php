<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/permissions.php';

$tripId = $_GET['id'] ?? 0;
$conn = getDBConnection();
$userId = getCurrentUserId();

// Get trip details - check if user has access
$stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->execute([$tripId]);
$trip = $stmt->fetch();

if (!$trip || !hasTripAccess($tripId, $userId)) {
    header('Location: dashboard.php');
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

// Get documents for each travel item
$itemDocuments = [];
foreach ($items as $item) {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE travel_item_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$item['id']]);
    $itemDocuments[$item['id']] = $stmt->fetchAll();
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
            
            <div class="form-group" id="flight_number_group" style="display: none;">
                <!-- Progressive Flight Lookup Form -->
                <div id="flight_lookup_wizard">
                    <!-- Step 1: Flight Number -->
                    <div id="flight_step_1" class="flight-wizard-step">
                        <label class="form-label" for="flight_number">Step 1: Enter Flight Number *</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" id="flight_number" name="flight_number" class="form-input" 
                                   placeholder="e.g., AA123 or AA1234" 
                                   pattern="[A-Z]{2}[0-9]{1,4}[A-Z]?" 
                                   style="flex: 1;">
                            <button type="button" id="flight_next_step1" class="btn btn-small" style="margin: 0; white-space: nowrap;">
                                Next →
                            </button>
                        </div>
                        <div class="form-help" style="margin-top: 0.25rem;">
                            💡 Enter your flight number (e.g., AA123, UA456)
                        </div>
                    </div>
                    
                    <!-- Step 2: Date -->
                    <div id="flight_step_2" class="flight-wizard-step" style="display: none;">
                        <label class="form-label" for="flight_date">Step 2: Select Date *</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="date" id="flight_date" name="flight_date" class="form-input" style="flex: 1;">
                            <button type="button" id="flight_back_step2" class="btn btn-secondary btn-small" style="margin: 0; white-space: nowrap;">
                                ← Back
                            </button>
                            <button type="button" id="flight_search_step2" class="btn btn-small" style="margin: 0; white-space: nowrap;">
                                🔍 Search Flights
                            </button>
                        </div>
                        <div class="form-help" style="margin-top: 0.25rem;">
                            💡 <strong>Note:</strong> Free tier only supports real-time flights (today). For other dates, you'll need to enter details manually or upgrade your API plan.
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
                <input type="text" id="item_title" name="title" class="form-input" required placeholder="e.g., Flight to Paris">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="start_datetime">Start Date & Time *</label>
                <input type="datetime-local" id="start_datetime" name="start_datetime" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_datetime">End Date & Time</label>
                <input type="datetime-local" id="end_datetime" name="end_datetime" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="location">Location</label>
                <input type="text" id="location" name="location" class="form-input" placeholder="e.g., Paris, France">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirmation_number">Confirmation Number / Booking Reference</label>
                <input type="text" id="confirmation_number" name="confirmation_number" class="form-input" placeholder="Booking reference">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="cost">Cost</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="number" id="cost" name="cost" class="form-input" step="0.01" placeholder="0.00" style="flex: 1;">
                    <select id="currency" name="currency" class="form-select" style="width: 100px;">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="JPY">JPY</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="item_description">Description</label>
                <textarea id="item_description" name="description" class="form-textarea"></textarea>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">Add Item</button>
                <a href="trip_detail.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">Cancel</a>
            </div>
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
                        🔍 Lookup
                    </button>
                </div>
                <div class="form-help" style="margin-top: 0.25rem;">
                    💡 Enter your flight number and click Lookup. The system will search for flights and automatically fill in the details below. You can optionally enter a date first to search that specific date.
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
            
            <div class="form-group">
                <label class="form-label" for="edit_confirmation_number">Confirmation Number / Booking Reference</label>
                <input type="text" id="edit_confirmation_number" name="confirmation_number" class="form-input" 
                       value="<?php echo htmlspecialchars($editItem['confirmation_number'] ?: ''); ?>" placeholder="Booking reference">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_cost">Cost</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="number" id="edit_cost" name="cost" class="form-input" step="0.01" 
                           value="<?php echo $editItem['cost'] ?: ''; ?>" placeholder="0.00" style="flex: 1;">
                    <select id="edit_currency" name="currency" class="form-select" style="width: 100px;">
                        <option value="USD" <?php echo ($editItem['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD</option>
                        <option value="EUR" <?php echo ($editItem['currency'] ?? 'USD') === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                        <option value="GBP" <?php echo ($editItem['currency'] ?? 'USD') === 'GBP' ? 'selected' : ''; ?>>GBP</option>
                        <option value="JPY" <?php echo ($editItem['currency'] ?? 'USD') === 'JPY' ? 'selected' : ''; ?>>JPY</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="edit_description">Description</label>
                <textarea id="edit_description" name="description" class="form-textarea"><?php echo htmlspecialchars($editItem['description'] ?: ''); ?></textarea>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">Update Item</button>
                <a href="trip_detail.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
            <div style="flex: 1;">
                <h2 class="card-title"><?php echo htmlspecialchars($trip['title']); ?></h2>
                <div class="card-subtitle">
                    <?php 
                    echo date(DATE_FORMAT, strtotime($trip['start_date']));
                    if ($trip['end_date']) {
                        echo ' - ' . date(DATE_FORMAT, strtotime($trip['end_date']));
                    }
                    ?>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="trip_detail.php?id=<?php echo $tripId; ?>&action=edit" class="btn btn-secondary btn-small">Edit</a>
                <a href="../api/export_trip.php?id=<?php echo $tripId; ?>&format=csv" class="btn btn-secondary btn-small">Export CSV</a>
                <a href="../api/export_trip.php?id=<?php echo $tripId; ?>&format=pdf" target="_blank" class="btn btn-secondary btn-small">Export PDF</a>
                <button onclick="deleteTrip(<?php echo $tripId; ?>)" class="btn btn-danger btn-small">Delete</button>
            </div>
        </div>
        <?php if ($trip['description']): ?>
            <p><?php echo nl2br(htmlspecialchars($trip['description'])); ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <h3 class="card-title" style="margin: 0;">Timeline</h3>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="itemSearch" class="search-input" placeholder="Search items..." style="width: 150px; font-size: 0.9rem;">
                <?php if ($canEdit): ?>
                    <a href="trip_detail.php?id=<?php echo $tripId; ?>&action=add_item" class="btn btn-small">+ Add Item</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($items)): ?>
            <div class="timeline" id="timelineContainer">
                <?php foreach ($items as $item): ?>
                    <div class="timeline-item <?php echo $item['type']; ?>" data-item-id="<?php echo $item['id']; ?>" data-item-title="<?php echo htmlspecialchars(strtolower($item['title'])); ?>" data-item-location="<?php echo htmlspecialchars(strtolower($item['location'] ?: '')); ?>">
                        <div class="timeline-date">
                            <?php echo date(DATETIME_FORMAT, strtotime($item['start_datetime'])); ?>
                            <?php if ($item['end_datetime']): ?>
                                <br>→ <?php echo date(DATETIME_FORMAT, strtotime($item['end_datetime'])); ?>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-title">
                            <?php echo htmlspecialchars($item['title']); ?>
                            <span class="badge badge-<?php echo $item['type']; ?>"><?php echo ucfirst($item['type']); ?></span>
                        </div>
                        <?php if ($item['location']): ?>
                            <div class="timeline-details">📍 <?php echo htmlspecialchars($item['location']); ?></div>
                        <?php endif; ?>
                        <?php if ($item['confirmation_number']): ?>
                            <div class="timeline-details">🔖 Confirmation: <?php echo htmlspecialchars($item['confirmation_number']); ?></div>
                        <?php endif; ?>
                        <?php if ($item['cost']): ?>
                            <div class="timeline-details">💰 <?php echo number_format($item['cost'], 2); ?> <?php echo htmlspecialchars($item['currency']); ?></div>
                        <?php endif; ?>
                        <?php if ($item['description']): ?>
                            <div class="timeline-details"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
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
                            <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                                <a href="trip_detail.php?id=<?php echo $tripId; ?>&action=edit_item&item_id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-small" style="margin: 0;">Edit</a>
                                <button onclick="deleteItem(<?php echo $item['id']; ?>)" class="btn btn-danger btn-small" style="margin: 0;">Delete</button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($itemDocuments[$item['id']]) && !empty($itemDocuments[$item['id']])): ?>
                            <div class="timeline-documents">
                                <?php foreach ($itemDocuments[$item['id']] as $doc): ?>
                                    <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" 
                                       target="_blank" 
                                       class="document-badge">
                                        📎 <?php echo htmlspecialchars($doc['original_filename']); ?>
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
                                    <form class="upload-form" data-item-id="<?php echo $item['id']; ?>" style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input type="file" name="file" accept="image/*,application/pdf" required style="flex: 1; font-size: 0.85rem;">
                                        <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                        <input type="hidden" name="travel_item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-small" style="margin: 0;">Upload</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($canEdit): ?>
                                <div style="margin-top: 0.5rem;">
                                    <form class="upload-form" data-item-id="<?php echo $item['id']; ?>" style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input type="file" name="file" accept="image/*,application/pdf" required style="flex: 1; font-size: 0.85rem;">
                                        <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                        <input type="hidden" name="travel_item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-small" style="margin: 0;">Upload Doc</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
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
        <h3 class="card-title">Trip Documents</h3>
        <div class="document-gallery">
            <?php foreach ($tripDocuments as $doc): ?>
                <div class="document-thumb">
                    <?php if (strpos($doc['file_type'], 'image/') === 0): ?>
                        <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>" target="_blank">
                            <img src="../api/view_document.php?id=<?php echo $doc['id']; ?>" alt="<?php echo htmlspecialchars($doc['original_filename']); ?>">
                        </a>
                    <?php else: ?>
                        <a href="../api/view_document.php?id=<?php echo $doc['id']; ?>&download=1" style="display: block; padding: 1rem; text-align: center; text-decoration: none; color: var(--text-color);">
                            📄<br><?php echo htmlspecialchars($doc['original_filename']); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" class="delete-btn">×</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 class="card-title">Upload Trip Document</h3>
        <form id="tripDocumentForm" style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="file" name="file" accept="image/*,application/pdf" required style="flex: 1;">
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
            <button type="submit" class="btn btn-small" style="margin: 0;">Upload</button>
        </form>
    </div>
<?php endif; ?>

<script>
// Edit trip form
document.getElementById('editTripForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../api/update_trip.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating trip');
        console.error(error);
    });
});

// Delete trip
function deleteTrip(tripId) {
    if (!confirm('Are you sure you want to delete this trip? All travel items and documents will be deleted.')) {
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
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error deleting trip');
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
    typeSelect.addEventListener('change', function() {
        if (this.value === 'flight') {
            flightNumberGroup.style.display = 'block';
            // Reset wizard to step 1
            showFlightStep(1);
        } else {
            flightNumberGroup.style.display = 'none';
            flightNumberInput.value = '';
            flightDateInput.value = '';
            flightLookupStatus.style.display = 'none';
            showFlightStep(1);
        }
    });
}

// Step navigation functions
function showFlightStep(step) {
    if (step1) step1.style.display = step === 1 ? 'block' : 'none';
    if (step2) step2.style.display = step === 2 ? 'block' : 'none';
    if (step3) step3.style.display = step === 3 ? 'block' : 'none';
}

// Step 1: Next button - validate flight number and go to step 2
const flightNextStep1 = document.getElementById('flight_next_step1');
if (flightNextStep1 && flightNumberInput) {
    flightNextStep1.addEventListener('click', function() {
        const flightNumber = flightNumberInput.value.trim().toUpperCase();
        
        if (!flightNumber) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Please enter a flight number';
            return;
        }
        
        // Validate format
        if (!/^[A-Z]{2}[0-9]{1,4}[A-Z]?$/.test(flightNumber)) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Invalid format. Use format like AA123 or AA1234';
            return;
        }
        
        currentFlightNumber = flightNumber;
        flightLookupStatus.style.display = 'none';
        
        // Set default date to today if not set
        if (flightDateInput && !flightDateInput.value) {
            flightDateInput.value = new Date().toISOString().split('T')[0];
        }
        
        // Set max date to today (free tier limitation)
        if (flightDateInput) {
            flightDateInput.max = new Date().toISOString().split('T')[0];
        }
        
        showFlightStep(2);
    });
    
    // Enter key on flight number input
    flightNumberInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            flightNextStep1.click();
        }
    });
}

// Step 2: Back button
const flightBackStep2 = document.getElementById('flight_back_step2');
if (flightBackStep2) {
    flightBackStep2.addEventListener('click', function() {
        showFlightStep(1);
    });
}

// Step 2: Search flights button
const flightSearchStep2 = document.getElementById('flight_search_step2');
if (flightSearchStep2 && flightDateInput) {
    flightSearchStep2.addEventListener('click', async function() {
        const date = flightDateInput.value;
        
        if (!date) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Please select a date';
            return;
        }
        
        if (!currentFlightNumber) {
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#C62828';
            flightLookupStatus.textContent = 'Please enter a flight number first';
            showFlightStep(1);
            return;
        }
        
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
            
            const response = await fetch(`../api/lookup_flight.php?flight=${encodeURIComponent(currentFlightNumber)}&date=${encodeURIComponent(date)}`, {
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Log response for debugging
            console.log('Flight lookup response:', data);
            
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
            flightSearchStep2.textContent = '🔍 Search Flights';
        }
    });
}

// Step 3: Back button
const flightBackStep3 = document.getElementById('flight_back_step3');
if (flightBackStep3) {
    flightBackStep3.addEventListener('click', function() {
        showFlightStep(2);
    });
}

// Function to display flight results
function showFlightResults(flights) {
    if (!flightResultsContainer) return;
    
    flightResultsContainer.innerHTML = '';
    
    if (flights.length === 0) {
        flightResultsContainer.innerHTML = '<p style="color: #856404; padding: 1rem; text-align: center;">No flights found.</p>';
        return;
    }
    
    flights.forEach((flight, index) => {
        const flightCard = document.createElement('div');
        flightCard.className = 'flight-result-card';
        flightCard.style.cssText = 'padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 0.75rem; cursor: pointer; transition: all 0.2s; background: white;';
        
        const depTime = flight.departure_time ? new Date(flight.departure_time).toLocaleString() : 'N/A';
        const arrTime = flight.arrival_time ? new Date(flight.arrival_time).toLocaleString() : 'N/A';
        const route = `${flight.departure_iata || 'N/A'} → ${flight.arrival_iata || 'N/A'}`;
        
        flightCard.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--text-color); font-size: 1.1rem;">
                ${flight.airline || 'Unknown Airline'} ${flight.flight_number || currentFlightNumber}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">
                <strong>Route:</strong> ${route}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.25rem;">
                <strong>Departure:</strong> ${depTime} ${flight.departure_terminal ? `(Terminal ${flight.departure_terminal})` : ''} ${flight.departure_gate ? `Gate ${flight.departure_gate}` : ''}
            </div>
            <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 0.5rem;">
                <strong>Arrival:</strong> ${arrTime} ${flight.arrival_terminal ? `(Terminal ${flight.arrival_terminal})` : ''} ${flight.arrival_gate ? `Gate ${flight.arrival_gate}` : ''}
            </div>
            ${flight.status ? `<div style="font-size: 0.85rem; color: var(--primary-color); margin-top: 0.5rem;">Status: ${flight.status}</div>` : ''}
        `;
        
        flightCard.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary-color)';
            this.style.background = '#F8F9FA';
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
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
            flightNumberGroup.style.display = 'none';
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#2E7D32';
            flightLookupStatus.textContent = `✓ Flight selected: ${flight.airline || ''} ${flight.flight_number || currentFlightNumber}`;
            
            // Reset wizard for next time
            showFlightStep(1);
            flightNumberInput.value = '';
            flightDateInput.value = '';
        });
        
        flightResultsContainer.appendChild(flightCard);
    });
}

// Function to fill form with flight data
function fillFlightForm(flight) {
    try {
        // Fill title
        const titleField = document.getElementById('item_title');
        if (titleField && flight.airline) {
            titleField.value = `${flight.airline} ${flight.flight_number || ''}`.trim();
        } else if (titleField && flight.flight_number) {
            titleField.value = `Flight ${flight.flight_number}`;
        }
        
        // Fill start date/time (departure)
        const startField = document.getElementById('start_datetime');
        if (startField && flight.departure_time) {
            try {
                const depTime = new Date(flight.departure_time);
                if (!isNaN(depTime.getTime())) {
                    // Convert to local datetime format (YYYY-MM-DDTHH:mm)
                    const year = depTime.getFullYear();
                    const month = String(depTime.getMonth() + 1).padStart(2, '0');
                    const day = String(depTime.getDate()).padStart(2, '0');
                    const hours = String(depTime.getHours()).padStart(2, '0');
                    const minutes = String(depTime.getMinutes()).padStart(2, '0');
                    startField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            } catch (e) {
                console.error('Error parsing departure time:', e);
            }
        }
        
        // Fill end date/time (arrival)
        const endField = document.getElementById('end_datetime');
        if (endField && flight.arrival_time) {
            try {
                const arrTime = new Date(flight.arrival_time);
                if (!isNaN(arrTime.getTime())) {
                    // Convert to local datetime format (YYYY-MM-DDTHH:mm)
                    const year = arrTime.getFullYear();
                    const month = String(arrTime.getMonth() + 1).padStart(2, '0');
                    const day = String(arrTime.getDate()).padStart(2, '0');
                    const hours = String(arrTime.getHours()).padStart(2, '0');
                    const minutes = String(arrTime.getMinutes()).padStart(2, '0');
                    endField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            } catch (e) {
                console.error('Error parsing arrival time:', e);
            }
        }
        
        // Build location string
        const locationField = document.getElementById('location');
        if (locationField) {
            let locationParts = [];
            if (flight.departure_airport) locationParts.push(flight.departure_airport);
            if (flight.departure_iata) locationParts.push(`(${flight.departure_iata})`);
            if (flight.arrival_airport) {
                if (locationParts.length > 0) locationParts.push('→');
                locationParts.push(flight.arrival_airport);
            }
            if (flight.arrival_iata) locationParts.push(`(${flight.arrival_iata})`);
            
            if (locationParts.length > 0) {
                locationField.value = locationParts.join(' ');
            }
        }
        
        // Set confirmation number to flight number
        const confirmationField = document.getElementById('confirmation_number');
        if (confirmationField && flight.flight_number) {
            confirmationField.value = flight.flight_number;
        }
        
        // Build description
        const descField = document.getElementById('item_description');
        if (descField) {
            let descParts = [];
            if (flight.airline) descParts.push(`Airline: ${flight.airline}`);
            if (flight.aircraft) descParts.push(`Aircraft: ${flight.aircraft}`);
            if (flight.status) descParts.push(`Status: ${flight.status}`);
            if (descParts.length > 0) {
                descField.value = descParts.join('\n');
            }
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
        
        const depTime = flight.departure_time ? new Date(flight.departure_time).toLocaleString() : 'N/A';
        const arrTime = flight.arrival_time ? new Date(flight.arrival_time).toLocaleString() : 'N/A';
        const route = `${flight.departure_iata || ''} → ${flight.arrival_iata || ''}`;
        
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
            fillFlightForm(flight);
            document.body.removeChild(modal);
            const flightLookupStatus = document.getElementById('flight_lookup_status');
            flightLookupStatus.style.display = 'block';
            flightLookupStatus.style.color = '#2E7D32';
            flightLookupStatus.textContent = `✓ Flight information loaded: ${flight.airline} ${flight.flight_number}`;
            const lookupFlightBtn = document.getElementById('lookup_flight');
            lookupFlightBtn.disabled = false;
            lookupFlightBtn.textContent = '🔍 Lookup';
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
        lookupFlightBtn.textContent = '🔍 Lookup';
        const flightLookupStatus = document.getElementById('flight_lookup_status');
        flightLookupStatus.style.display = 'none';
    });
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
            const lookupFlightBtn = document.getElementById('lookup_flight');
            lookupFlightBtn.disabled = false;
            lookupFlightBtn.textContent = '🔍 Lookup';
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
    
    fetch('../api/add_travel_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error adding item');
        console.error(error);
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
    
    editTypeSelect.addEventListener('change', function() {
        if (this.value === 'flight') {
            editFlightNumberGroup.style.display = 'block';
        } else {
            editFlightNumberGroup.style.display = 'none';
            editFlightNumberInput.value = '';
            editFlightLookupStatus.style.display = 'none';
        }
    });
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
                        editFlightLookupStatus.textContent = `✓ Flight information loaded: ${flight.airline || ''} ${flight.flight_number || flightNumber}`;
                    } else {
                        editFlightLookupStatus.style.color = '#856404';
                        editFlightLookupStatus.textContent = 'Flight found but missing required information. Please fill in details manually.';
                        console.error('Flight data structure:', flight);
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
            editLookupFlightBtn.textContent = '🔍 Lookup';
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

// Function to fill edit form with flight data
function fillEditFlightForm(flight) {
    try {
        // Fill title
        const titleField = document.getElementById('edit_title');
        if (titleField && flight.airline) {
            titleField.value = `${flight.airline} ${flight.flight_number || ''}`.trim();
        } else if (titleField && flight.flight_number) {
            titleField.value = `Flight ${flight.flight_number}`;
        }
        
        // Fill start date/time (departure)
        const startField = document.getElementById('edit_start_datetime');
        if (startField && flight.departure_time) {
            try {
                const depTime = new Date(flight.departure_time);
                if (!isNaN(depTime.getTime())) {
                    // Convert to local datetime format (YYYY-MM-DDTHH:mm)
                    const year = depTime.getFullYear();
                    const month = String(depTime.getMonth() + 1).padStart(2, '0');
                    const day = String(depTime.getDate()).padStart(2, '0');
                    const hours = String(depTime.getHours()).padStart(2, '0');
                    const minutes = String(depTime.getMinutes()).padStart(2, '0');
                    startField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            } catch (e) {
                console.error('Error parsing departure time:', e);
            }
        }
        
        // Fill end date/time (arrival)
        const endField = document.getElementById('edit_end_datetime');
        if (endField && flight.arrival_time) {
            try {
                const arrTime = new Date(flight.arrival_time);
                if (!isNaN(arrTime.getTime())) {
                    // Convert to local datetime format (YYYY-MM-DDTHH:mm)
                    const year = arrTime.getFullYear();
                    const month = String(arrTime.getMonth() + 1).padStart(2, '0');
                    const day = String(arrTime.getDate()).padStart(2, '0');
                    const hours = String(arrTime.getHours()).padStart(2, '0');
                    const minutes = String(arrTime.getMinutes()).padStart(2, '0');
                    endField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                }
            } catch (e) {
                console.error('Error parsing arrival time:', e);
            }
        }
        
        // Build location string
        const locationField = document.getElementById('edit_location');
        if (locationField) {
            let locationParts = [];
            if (flight.departure_airport) locationParts.push(flight.departure_airport);
            if (flight.departure_iata) locationParts.push(`(${flight.departure_iata})`);
            if (flight.arrival_airport) {
                if (locationParts.length > 0) locationParts.push('→');
                locationParts.push(flight.arrival_airport);
            }
            if (flight.arrival_iata) locationParts.push(`(${flight.arrival_iata})`);
            
            if (locationParts.length > 0) {
                locationField.value = locationParts.join(' ');
            }
        }
        
        // Set confirmation number to flight number
        const confirmationField = document.getElementById('edit_confirmation_number');
        if (confirmationField && flight.flight_number) {
            confirmationField.value = flight.flight_number;
        }
        
        // Build description
        const descField = document.getElementById('edit_description');
        if (descField) {
            let descParts = [];
            if (flight.airline) descParts.push(`Airline: ${flight.airline}`);
            if (flight.aircraft) descParts.push(`Aircraft: ${flight.aircraft}`);
            if (flight.status) descParts.push(`Status: ${flight.status}`);
            if (descParts.length > 0) {
                descField.value = descParts.join('\n');
            }
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
        
        const depTime = flight.departure_time ? new Date(flight.departure_time).toLocaleString() : 'N/A';
        const arrTime = flight.arrival_time ? new Date(flight.arrival_time).toLocaleString() : 'N/A';
        const route = `${flight.departure_iata || ''} → ${flight.arrival_iata || ''}`;
        
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
            editFlightLookupStatus.textContent = `✓ Flight information loaded: ${flight.airline} ${flight.flight_number}`;
            const editLookupFlightBtn = document.getElementById('edit_lookup_flight');
            editLookupFlightBtn.disabled = false;
            editLookupFlightBtn.textContent = '🔍 Lookup';
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
        editLookupFlightBtn.textContent = '🔍 Lookup';
        const editFlightLookupStatus = document.getElementById('edit_flight_lookup_status');
        editFlightLookupStatus.style.display = 'none';
    });
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
            const editLookupFlightBtn = document.getElementById('edit_lookup_flight');
            editLookupFlightBtn.disabled = false;
            editLookupFlightBtn.textContent = '🔍 Lookup';
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
    
    fetch('../api/update_travel_item.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'trip_detail.php?id=<?php echo $tripId; ?>';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating item');
        console.error(error);
    });
});

// Delete item
function deleteItem(itemId) {
    if (!confirm('Are you sure you want to delete this travel item?')) {
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
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error deleting item');
        console.error(error);
    });
}

// Document upload forms
document.querySelectorAll('.upload-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('../api/upload_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error uploading document');
            console.error(error);
        });
    });
});

// Trip document upload
document.getElementById('tripDocumentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../api/upload_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error uploading document');
        console.error(error);
    });
});

// Delete document
function deleteDocument(documentId) {
    if (!confirm('Are you sure you want to delete this document?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('document_id', documentId);
    
    fetch('../api/delete_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error deleting document');
        console.error(error);
    });
}

// Item search filter
document.getElementById('itemSearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const items = document.querySelectorAll('.timeline-item');
    
    items.forEach(item => {
        const title = item.getAttribute('data-item-title') || '';
        const location = item.getAttribute('data-item-location') || '';
        const matches = title.includes(searchTerm) || location.includes(searchTerm);
        
        item.style.display = matches ? '' : 'none';
    });
});

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
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error creating invitation');
        console.error(error);
    });
});

// Delete invitation
function deleteInvitation(invitationId) {
    if (!confirm('Are you sure you want to delete this invitation?')) {
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
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error deleting invitation');
        console.error(error);
    });
}

// Remove collaborator
function removeCollaborator(userId) {
    if (!confirm('Are you sure you want to remove this collaborator?')) {
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
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error removing collaborator');
        console.error(error);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

