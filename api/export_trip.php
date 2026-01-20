<?php
/**
 * Export Trip - CSV Export
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

$tripId = $_GET['id'] ?? 0;
$format = $_GET['format'] ?? 'csv'; // csv or pdf

if (empty($tripId)) {
    http_response_code(400);
    die('Trip ID required');
}

$conn = getDBConnection();
$userId = getCurrentUserId();

// Check if user has access to trip
if (!hasTripAccess($tripId, $userId)) {
    http_response_code(403);
    die('Access denied');
}

// Get trip details
$stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->execute([$tripId]);
$trip = $stmt->fetch();

if (!$trip) {
    http_response_code(404);
    die('Trip not found');
}

// Get travel items
$stmt = $conn->prepare("
    SELECT * FROM travel_items 
    WHERE trip_id = ? 
    ORDER BY start_datetime ASC
");
$stmt->execute([$tripId]);
$items = $stmt->fetchAll();

// Get documents
$stmt = $conn->prepare("
    SELECT * FROM documents 
    WHERE trip_id = ? 
    ORDER BY upload_date DESC
");
$stmt->execute([$tripId]);
$documents = $stmt->fetchAll();

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trip_' . $tripId . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Trip header
    fputcsv($output, ['Trip Information']);
    fputcsv($output, ['Title', $trip['title']]);
    fputcsv($output, ['Start Date', $trip['start_date']]);
    fputcsv($output, ['End Date', $trip['end_date'] ?: '']);
    fputcsv($output, ['Description', $trip['description'] ?: '']);
    fputcsv($output, []);
    
    // Travel items
    fputcsv($output, ['Travel Items']);
    fputcsv($output, ['Type', 'Title', 'Start Date/Time', 'End Date/Time', 'Location', 'Confirmation', 'Description', 'Notes']);
    
    foreach ($items as $item) {
        fputcsv($output, [
            ucfirst($item['type']),
            $item['title'],
            $item['start_datetime'],
            $item['end_datetime'] ?: '',
            $item['location'] ?: '',
            $item['confirmation_number'] ?: '',
            str_replace(["\r\n", "\r", "\n"], ' ', $item['description'] ?: ''), // Remove newlines for CSV
            str_replace(["\r\n", "\r", "\n"], ' ', $item['notes'] ?? '') // Remove newlines for CSV
        ]);
    }
    
    fputcsv($output, []);
    
    // Documents
    fputcsv($output, ['Documents']);
    fputcsv($output, ['Filename', 'Original Filename', 'File Type', 'File Size', 'Upload Date']);
    
    foreach ($documents as $doc) {
        fputcsv($output, [
            $doc['filename'],
            $doc['original_filename'],
            $doc['file_type'],
            $doc['file_size'],
            $doc['upload_date']
        ]);
    }
    
    fclose($output);
    exit;
} else {
    // PDF Export (simple HTML to PDF conversion)
    // For a full implementation, you'd use a library like TCPDF or FPDF
    // This is a basic HTML version that can be printed to PDF
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Trip Export - <?php echo htmlspecialchars($trip['title']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            h1 { color: #4A90E2; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4A90E2; color: white; }
            @media print {
                body { padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()">Print / Save as PDF</button>
        </div>
        
        <h1><?php echo htmlspecialchars($trip['title']); ?></h1>
        
        <h2>Trip Information</h2>
        <table>
            <tr><th>Start Date</th><td><?php echo $trip['start_date']; ?></td></tr>
            <tr><th>End Date</th><td><?php echo $trip['end_date'] ?: 'N/A'; ?></td></tr>
            <tr><th>Description</th><td><?php echo nl2br(htmlspecialchars($trip['description'] ?: '')); ?></td></tr>
        </table>
        
        <h2>Travel Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Location</th>
                    <th>Confirmation</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo ucfirst($item['type']); ?></td>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td><?php echo date(DATETIME_FORMAT, strtotime($item['start_datetime'])); ?></td>
                    <td><?php echo $item['end_datetime'] ? date(DATETIME_FORMAT, strtotime($item['end_datetime'])) : ''; ?></td>
                    <td><?php echo htmlspecialchars($item['location'] ?: ''); ?></td>
                    <td><?php echo htmlspecialchars($item['confirmation_number'] ?: ''); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($item['notes'] ?? '')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (!empty($documents)): ?>
        <h2>Documents</h2>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Upload Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doc['original_filename']); ?></td>
                    <td><?php echo htmlspecialchars($doc['file_type']); ?></td>
                    <td><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</td>
                    <td><?php echo date(DATETIME_FORMAT, strtotime($doc['upload_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}
?>


