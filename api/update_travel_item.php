<?php
/**
 * Update Travel Item API
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

$userId = getCurrentUserId();
$itemId = $_POST['item_id'] ?? 0;
$type = $_POST['type'] ?? '';
$title = trim($_POST['title'] ?? '');
$startDatetime = $_POST['start_datetime'] ?? '';
$endDatetime = $_POST['end_datetime'] ?? null;
$location = $_POST['location'] ?? null;
$confirmationNumber = $_POST['confirmation_number'] ?? null;
$description = $_POST['description'] ?? null;
$notes = trim($_POST['notes'] ?? '') ?: null;

// Flight-specific fields (only used when type = 'flight')
$flightDepartureScheduled = $type === 'flight' ? ($_POST['flight_departure_scheduled'] ?? null) : null;
$flightDepartureRevised = $type === 'flight' ? ($_POST['flight_departure_revised'] ?? null) : null;
$flightDepartureRunway = $type === 'flight' ? ($_POST['flight_departure_runway'] ?? null) : null;
$flightArrivalScheduled = $type === 'flight' ? ($_POST['flight_arrival_scheduled'] ?? null) : null;
$flightArrivalRevised = $type === 'flight' ? ($_POST['flight_arrival_revised'] ?? null) : null;
$flightArrivalRunway = $type === 'flight' ? ($_POST['flight_arrival_runway'] ?? null) : null;
$flightDurationMinutes = $type === 'flight' ? ($_POST['flight_duration_minutes'] ?? null) : null;
$flightDepartureIcao = $type === 'flight' ? (trim($_POST['flight_departure_icao'] ?? '') ?: null) : null;
$flightDepartureCountry = $type === 'flight' ? (trim($_POST['flight_departure_country'] ?? '') ?: null) : null;
$flightArrivalIcao = $type === 'flight' ? (trim($_POST['flight_arrival_icao'] ?? '') ?: null) : null;
$flightArrivalCountry = $type === 'flight' ? (trim($_POST['flight_arrival_country'] ?? '') ?: null) : null;
$flightAircraftRegistration = $type === 'flight' ? (trim($_POST['flight_aircraft_registration'] ?? '') ?: null) : null;
$flightAircraftIcao24 = $type === 'flight' ? (trim($_POST['flight_aircraft_icao24'] ?? '') ?: null) : null;
$flightAircraftAge = $type === 'flight' ? ($_POST['flight_aircraft_age'] ?? null) : null;
$flightStatus = $type === 'flight' ? (trim($_POST['flight_status'] ?? '') ?: null) : null;
$flightCodeshare = $type === 'flight' ? (trim($_POST['flight_codeshare'] ?? '') ?: null) : null;

// Hotel-specific fields (only used when type = 'hotel')
$hotelData = null;
if ($type === 'hotel') {
    $hotelDataArray = [
        'hotel_name' => trim($_POST['hotel_name'] ?? '') ?: null,
        'room_type' => trim($_POST['hotel_room_type'] ?? '') ?: null,
        'number_of_rooms' => isset($_POST['hotel_number_of_rooms']) && $_POST['hotel_number_of_rooms'] !== '' ? (int)$_POST['hotel_number_of_rooms'] : null,
        'number_of_guests' => isset($_POST['hotel_number_of_guests']) && $_POST['hotel_number_of_guests'] !== '' ? (int)$_POST['hotel_number_of_guests'] : null,
        'check_in_time' => trim($_POST['hotel_check_in_time'] ?? '') ?: null,
        'check_out_time' => trim($_POST['hotel_check_out_time'] ?? '') ?: null,
        'phone' => trim($_POST['hotel_phone'] ?? '') ?: null,
        'address' => trim($_POST['hotel_address'] ?? '') ?: null,
        'special_requests' => trim($_POST['hotel_special_requests'] ?? '') ?: null,
    ];
    // Remove null values
    $hotelDataArray = array_filter($hotelDataArray, function($value) {
        return $value !== null && $value !== '';
    });
    if (!empty($hotelDataArray)) {
        $hotelData = json_encode($hotelDataArray);
    }
}

// Validation
if (empty($itemId) || empty($type) || empty($title) || empty($startDatetime)) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit;
}

// Validate type
$allowedTypes = ['flight', 'train', 'bus', 'hotel', 'car_rental', 'activity', 'other'];
if (!in_array($type, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid travel item type']);
    exit;
}

// Validate title length
if (strlen($title) > 255) {
    echo json_encode(['success' => false, 'message' => 'Title must be 255 characters or less']);
    exit;
}

// Validate datetime format
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $startDatetime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid start datetime format']);
    exit;
}

// Validate end datetime if provided
if ($endDatetime) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $endDatetime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid end datetime format']);
        exit;
    }
    if ($endDatetime < $startDatetime) {
        echo json_encode(['success' => false, 'message' => 'End datetime must be after start datetime']);
        exit;
    }
}

// Validate notes length
if ($notes && strlen($notes) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Notes must be 1000 characters or less']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get trip_id from item
    $stmt = $conn->prepare("SELECT trip_id FROM travel_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Travel item not found']);
        exit;
    }
    
    // Check permissions
    if (!canEditTrip($item['trip_id'], $userId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this item']);
        exit;
    }
    
    // Build SQL with type-specific fields
    $sql = "
        UPDATE travel_items 
        SET type = ?, title = ?, description = ?, notes = ?, start_datetime = ?, end_datetime = ?, 
            location = ?, confirmation_number = ?, modified_by = ?";
    
    $params = [$type, $title, $description ?: null, $notes, $startDatetime, $endDatetime ?: null, $location ?: null, $confirmationNumber ?: null, $userId];
    
    if ($type === 'hotel') {
        $sql .= ", hotel_data = ?";
        $params[] = $hotelData;
    } else {
        // Clear hotel_data when changing from hotel to another type
        $sql .= ", hotel_data = NULL";
    }
    
    if ($type === 'flight') {
        $sql .= ", flight_departure_scheduled = ?, flight_departure_revised = ?, flight_departure_runway = ?,
                  flight_arrival_scheduled = ?, flight_arrival_revised = ?, flight_arrival_runway = ?,
                  flight_duration_minutes = ?, flight_departure_icao = ?, flight_departure_country = ?,
                  flight_arrival_icao = ?, flight_arrival_country = ?, flight_aircraft_registration = ?,
                  flight_aircraft_icao24 = ?, flight_aircraft_age = ?, flight_status = ?, flight_codeshare = ?";
        
        $params = array_merge($params, [
            $flightDepartureScheduled ?: null,
            $flightDepartureRevised ?: null,
            $flightDepartureRunway ?: null,
            $flightArrivalScheduled ?: null,
            $flightArrivalRevised ?: null,
            $flightArrivalRunway ?: null,
            $flightDurationMinutes ?: null,
            $flightDepartureIcao,
            $flightDepartureCountry,
            $flightArrivalIcao,
            $flightArrivalCountry,
            $flightAircraftRegistration,
            $flightAircraftIcao24,
            $flightAircraftAge ?: null,
            $flightStatus,
            $flightCodeshare
        ]);
    } else {
        // If changing from flight to non-flight, clear flight fields
        $sql .= ", flight_departure_scheduled = NULL, flight_departure_revised = NULL, flight_departure_runway = NULL,
                  flight_arrival_scheduled = NULL, flight_arrival_revised = NULL, flight_arrival_runway = NULL,
                  flight_duration_minutes = NULL, flight_departure_icao = NULL, flight_departure_country = NULL,
                  flight_arrival_icao = NULL, flight_arrival_country = NULL, flight_aircraft_registration = NULL,
                  flight_aircraft_icao24 = NULL, flight_aircraft_age = NULL, flight_status = NULL, flight_codeshare = NULL";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $itemId;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Travel item updated successfully'
    ]);
} catch (PDOException $e) {
    error_log('Database error in update_travel_item.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

