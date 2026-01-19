<?php
/**
 * Flight Lookup API - Fixed Version
 * Fetches flight information from public APIs
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$flightNumber = trim($_GET['flight'] ?? '');
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? ''; // Optional time parameter

if (empty($flightNumber)) {
    echo json_encode(['success' => false, 'message' => 'Flight number is required']);
    exit;
}

// If date is provided, use it; otherwise use today
if (empty($date)) {
    $date = date('Y-m-d');
}

// Validate flight number format (e.g., AA123, AA1234, LH456)
if (!preg_match('/^[A-Z]{2}[0-9]{1,4}[A-Z]?$/', strtoupper($flightNumber))) {
    echo json_encode(['success' => false, 'message' => 'Invalid flight number format. Use format like AA123 or AA1234']);
    exit;
}

$flightNumber = strtoupper($flightNumber);

try {
    // Extract airline code (first 2 letters) and flight number
    $airlineCode = substr($flightNumber, 0, 2);
    $flightNum = substr($flightNumber, 2);
    
    // Try multiple free flight APIs
    $flightData = null;
    
    // Method 1: AviationStack API (requires free API key)
    // You can get a free API key from https://aviationstack.com/
    $aviationStackKey = defined('AVIATION_STACK_API_KEY') ? AVIATION_STACK_API_KEY : '';
    
    if ($aviationStackKey) {
        error_log('AviationStack API Key found, length: ' . strlen($aviationStackKey));
        
        // Free tier only supports real-time flights (no flight_date parameter)
        // For paid plans, we can use flight_date
        $allFlights = [];
        
        // Build URL - only add flight_date if provided (may fail on free tier for non-today dates)
        $url = "https://api.aviationstack.com/v1/flights?access_key=" . urlencode($aviationStackKey) . 
               "&flight_iata=" . urlencode($flightNumber);
        
        // Only add flight_date if provided (may fail on free tier for non-today dates)
        if (!empty($date)) {
            $url .= "&flight_date=" . urlencode($date);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log errors for debugging
        if ($curlError) {
            error_log('AviationStack cURL Error: ' . $curlError);
        }
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            // Check for API errors in response (per AviationStack documentation)
            if (isset($data['error'])) {
                $errorCode = $data['error']['code'] ?? 'unknown';
                $errorMsg = $data['error']['message'] ?? 'Unknown error';
                error_log('AviationStack API Error: ' . $errorCode . ' - ' . $errorMsg);
                
                // Handle function_access_restricted (free tier limitation)
                if ($errorCode === 'function_access_restricted') {
                    // Free tier doesn't support date-based searches
                    echo json_encode([
                        'success' => false,
                        'message' => 'Date-based flight search requires a paid AviationStack plan. The free tier only supports real-time flights (today). Please search for flights happening today, or enter flight details manually.',
                        'error_code' => $errorCode
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Return error to user if it's a critical authentication error
                if (in_array($errorCode, ['invalid_access_key', 'missing_access_key', 'inactive_user'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'API authentication error: ' . $errorMsg . ' (Code: ' . $errorCode . ')'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            
            if (isset($data['data']) && !empty($data['data'])) {
                foreach ($data['data'] as $flight) {
                    $allFlights[] = [
                        'airline' => $flight['airline']['name'] ?? '',
                        'flight_number' => $flight['flight']['iata'] ?? $flightNumber,
                        'departure_airport' => $flight['departure']['airport'] ?? '',
                        'departure_iata' => $flight['departure']['iata'] ?? '',
                        'departure_city' => $flight['departure']['timezone'] ?? '',
                        'departure_time' => $flight['departure']['scheduled'] ?? '',
                        'departure_terminal' => $flight['departure']['terminal'] ?? '',
                        'departure_gate' => $flight['departure']['gate'] ?? '',
                        'arrival_airport' => $flight['arrival']['airport'] ?? '',
                        'arrival_iata' => $flight['arrival']['iata'] ?? '',
                        'arrival_city' => $flight['arrival']['timezone'] ?? '',
                        'arrival_time' => $flight['arrival']['scheduled'] ?? '',
                        'arrival_terminal' => $flight['arrival']['terminal'] ?? '',
                        'arrival_gate' => $flight['arrival']['gate'] ?? '',
                        'aircraft' => $flight['aircraft']['registration'] ?? '',
                        'status' => $flight['flight_status'] ?? ''
                    ];
                }
            }
        } else {
            error_log('AviationStack HTTP Error: ' . $httpCode . ' - Response: ' . substr($response, 0, 500));
        }
        
        if (!empty($allFlights)) {
            // Remove duplicates based on departure time and route
            $uniqueFlights = [];
            $seen = [];
            foreach ($allFlights as $flight) {
                $key = ($flight['departure_iata'] ?? '') . '-' . ($flight['arrival_iata'] ?? '') . '-' . ($flight['departure_time'] ?? '');
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $uniqueFlights[] = $flight;
                }
            }
            
            // If multiple flights, return all; if single, return as single object for backward compatibility
            if (count($uniqueFlights) === 1) {
                $flightData = $uniqueFlights[0];
            } else {
                $flightData = $uniqueFlights; // Array of flights
            }
        }
    }
    
    // Method 2: Try AeroDataBox API (better free tier, supports schedules)
    if (!$flightData) {
        $aeroDataBoxKey = defined('AERODATABOX_API_KEY') ? AERODATABOX_API_KEY : '';
        
        if ($aeroDataBoxKey && !empty($date)) {
            // AeroDataBox via RapidAPI
            $url = "https://aerodatabox.p.rapidapi.com/flights/number/" . urlencode($flightNumber) . "/" . urlencode($date);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-RapidAPI-Key: ' . $aeroDataBoxKey,
                'X-RapidAPI-Host: aerodatabox.p.rapidapi.com'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if (!$curlError && $httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data) && is_array($data) && !empty($data)) {
                    $aeroFlights = [];
                    foreach ($data as $flight) {
                        $aeroFlights[] = [
                            'airline' => $flight['airline']['name'] ?? '',
                            'flight_number' => $flight['number'] ?? $flightNumber,
                            'departure_airport' => $flight['departure']['airport']['name'] ?? '',
                            'departure_iata' => $flight['departure']['airport']['iata'] ?? '',
                            'departure_city' => $flight['departure']['airport']['municipalityName'] ?? '',
                            'departure_time' => $flight['departure']['scheduledTimeLocal'] ?? $flight['departure']['scheduledTimeUtc'] ?? '',
                            'departure_terminal' => $flight['departure']['terminal'] ?? '',
                            'departure_gate' => $flight['departure']['gate'] ?? '',
                            'arrival_airport' => $flight['arrival']['airport']['name'] ?? '',
                            'arrival_iata' => $flight['arrival']['airport']['iata'] ?? '',
                            'arrival_city' => $flight['arrival']['airport']['municipalityName'] ?? '',
                            'arrival_time' => $flight['arrival']['scheduledTimeLocal'] ?? $flight['arrival']['scheduledTimeUtc'] ?? '',
                            'arrival_terminal' => $flight['arrival']['terminal'] ?? '',
                            'arrival_gate' => $flight['arrival']['gate'] ?? '',
                            'aircraft' => $flight['aircraft']['model'] ?? '',
                            'status' => $flight['status'] ?? 'scheduled'
                        ];
                    }
                    
                    if (!empty($aeroFlights)) {
                        if (count($aeroFlights) === 1) {
                            $flightData = $aeroFlights[0];
                        } else {
                            $flightData = $aeroFlights;
                        }
                        error_log('AeroDataBox: Found ' . count($aeroFlights) . ' flight(s)');
                    }
                }
            } else {
                error_log('AeroDataBox API Error: HTTP ' . $httpCode . ' - ' . $curlError);
            }
        }
    }
    
    // Method 3: Fallback - Basic airline lookup by IATA code
    if (!$flightData) {
        error_log('No flight data from APIs, using basic airline lookup for flight: ' . $flightNumber);
        // Comprehensive airline database by IATA code
        $airlineData = [
            'AA' => 'American Airlines', 'UA' => 'United Airlines', 'DL' => 'Delta Air Lines',
            'BA' => 'British Airways', 'LH' => 'Lufthansa', 'AF' => 'Air France',
            'KL' => 'KLM Royal Dutch Airlines', 'EK' => 'Emirates', 'QR' => 'Qatar Airways',
            'SQ' => 'Singapore Airlines', 'CX' => 'Cathay Pacific', 'JL' => 'Japan Airlines',
            'QF' => 'Qantas', 'VS' => 'Virgin Atlantic', 'IB' => 'Iberia',
            'SN' => 'Brussels Airlines', 'OS' => 'Austrian Airlines', 'LX' => 'Swiss International Air Lines',
            'TP' => 'TAP Air Portugal', 'AF' => 'Air France', 'AZ' => 'Alitalia',
            'AC' => 'Air Canada', 'AS' => 'Alaska Airlines', 'B6' => 'JetBlue Airways',
            'F9' => 'Frontier Airlines', 'NK' => 'Spirit Airlines', 'WN' => 'Southwest Airlines',
            'HA' => 'Hawaiian Airlines', 'VX' => 'Virgin America', 'NK' => 'Spirit Airlines',
            'AM' => 'Aeroméxico', 'CM' => 'Copa Airlines', 'AV' => 'Avianca',
            'LA' => 'LATAM Airlines', 'JJ' => 'LATAM Brasil', 'G3' => 'Gol Transportes Aéreos',
            'AI' => 'Air India', '9W' => 'Jet Airways', '6E' => 'IndiGo',
            'TG' => 'Thai Airways', 'MH' => 'Malaysia Airlines', 'GA' => 'Garuda Indonesia',
            'PR' => 'Philippine Airlines', 'CI' => 'China Airlines', 'BR' => 'EVA Air',
            'KE' => 'Korean Air', 'OZ' => 'Asiana Airlines', 'NH' => 'All Nippon Airways',
            'CA' => 'Air China', 'MU' => 'China Eastern Airlines', 'CZ' => 'China Southern Airlines',
            'QF' => 'Qantas', 'VA' => 'Virgin Australia', 'NZ' => 'Air New Zealand',
            'SA' => 'South African Airways', 'ET' => 'Ethiopian Airlines', 'MS' => 'EgyptAir',
            'RJ' => 'Royal Jordanian', 'EY' => 'Etihad Airways', 'GF' => 'Gulf Air',
            'SV' => 'Saudia', 'KU' => 'Kuwait Airways', 'ME' => 'Middle East Airlines',
            'TK' => 'Turkish Airlines', 'SU' => 'Aeroflot', 'LO' => 'LOT Polish Airlines',
            'OK' => 'Czech Airlines', 'SK' => 'SAS Scandinavian Airlines', 'AY' => 'Finnair',
            'DY' => 'Norwegian Air Shuttle', 'FR' => 'Ryanair', 'U2' => 'easyJet',
            'VY' => 'Vueling', 'IB' => 'Iberia', 'UX' => 'Air Europa'
        ];
        
        $airlineName = $airlineData[$airlineCode] ?? $airlineCode . ' Airlines';
        
        // Return basic structure - user can fill in details manually
        $flightData = [
            'airline' => $airlineName,
            'flight_number' => $flightNumber,
            'departure_airport' => '',
            'departure_iata' => '',
            'departure_city' => '',
            'departure_time' => '',
            'arrival_airport' => '',
            'arrival_iata' => '',
            'arrival_city' => '',
            'arrival_time' => '',
            'aircraft' => '',
            'status' => 'scheduled'
        ];
    }
    
    if ($flightData) {
        // Check if we have multiple flights
        $isMultiple = is_array($flightData) && isset($flightData[0]) && is_array($flightData[0]) && count($flightData) > 1;
        
        error_log('Flight lookup success: Found ' . ($isMultiple ? count($flightData) : 1) . ' flight(s)');
        
        echo json_encode([
            'success' => true,
            'data' => $flightData,
            'multiple' => $isMultiple,
            'count' => $isMultiple ? count($flightData) : 1
        ], JSON_UNESCAPED_UNICODE);
    } else {
        error_log('Flight lookup failed: No flight data returned for ' . $flightNumber . ' on ' . $date);
        echo json_encode([
            'success' => false,
            'message' => 'Flight information not found for ' . $flightNumber . ' on ' . $date . '. Please try a different date or enter details manually.'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('Flight lookup error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching flight information. Please enter details manually.'
    ]);
}
?>
