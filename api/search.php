<?php
/**
 * Search API - Search trips and travel items
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = getCurrentUserId();
$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all'; // 'trips', 'items', 'all'
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$itemType = $_GET['item_type'] ?? '';

$results = [
    'trips' => [],
    'items' => []
];

try {
    $conn = getDBConnection();
    
    // Search trips - include shared trips
    if ($type === 'all' || $type === 'trips') {
        $sql = "
            SELECT t.*, 
                   COALESCE(item_counts.item_count, 0) as item_count
            FROM trips t
            LEFT JOIN trip_users tu ON t.id = tu.trip_id AND tu.user_id = ?
            LEFT JOIN (
                SELECT trip_id, COUNT(*) as item_count
                FROM travel_items
                GROUP BY trip_id
            ) item_counts ON t.id = item_counts.trip_id
            WHERE (t.user_id = ? OR tu.user_id = ?)
        ";
        $params = [$userId, $userId, $userId];
        
        if (!empty($query)) {
            $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
            $searchParam = "%$query%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($dateFrom)) {
            $sql .= " AND t.start_date >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND (t.end_date <= ? OR t.end_date IS NULL)";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY t.start_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results['trips'] = $stmt->fetchAll();
    }
    
    // Search travel items - include items from shared trips
    if ($type === 'all' || $type === 'items') {
        $sql = "
            SELECT ti.*, t.title as trip_title, t.id as trip_id
            FROM travel_items ti
            INNER JOIN trips t ON ti.trip_id = t.id
            LEFT JOIN trip_users tu ON t.id = tu.trip_id AND tu.user_id = ?
            WHERE (t.user_id = ? OR tu.user_id = ?)
        ";
        $params = [$userId, $userId, $userId];
        
        if (!empty($query)) {
            $sql .= " AND (ti.title LIKE ? OR ti.description LIKE ? OR ti.location LIKE ?)";
            $searchParam = "%$query%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($dateFrom)) {
            $sql .= " AND ti.start_datetime >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND ti.start_datetime <= ?";
            $params[] = $dateTo;
        }
        
        if (!empty($itemType)) {
            $sql .= " AND ti.type = ?";
            $params[] = $itemType;
        }
        
        $sql .= " ORDER BY ti.start_datetime DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results['items'] = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in search.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


