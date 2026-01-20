<?php
/**
 * Geocode Location API
 * Provides location autocomplete and geocoding using REST Countries API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

/**
 * Convert ISO 3166-1 alpha-2 country code to flag emoji
 */
function countryCodeToFlagEmoji($countryCode) {
    if (empty($countryCode) || strlen($countryCode) !== 2) {
        return '';
    }
    
    $countryCode = strtoupper($countryCode);
    
    // Convert each letter to regional indicator symbol (U+1F1E6 to U+1F1FF)
    $char1 = ord($countryCode[0]) - ord('A');
    $char2 = ord($countryCode[1]) - ord('A');
    
    if ($char1 < 0 || $char1 > 25 || $char2 < 0 || $char2 > 25) {
        return '';
    }
    
    // Create flag emoji using UTF-8 encoding
    $codePoint1 = 0x1F1E6 + $char1;
    $codePoint2 = 0x1F1E6 + $char2;
    
    // Convert Unicode code points to UTF-8 bytes
    return json_decode('"' . sprintf('\\u%04X\\u%04X', $codePoint1, $codePoint2) . '"');
}

$query = $_GET['q'] ?? '';
$limit = min((int)($_GET['limit'] ?? 10), 20);

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    // Use REST Countries API for country search
    $url = 'https://restcountries.com/v3.1/name/' . urlencode($query) . '?fields=name,cca2,cca3,flag';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results = [];
    
    if ($httpCode === 200 && $response) {
        $countries = json_decode($response, true);
        if (is_array($countries)) {
            foreach (array_slice($countries, 0, $limit) as $country) {
                $commonName = $country['name']['common'] ?? '';
                $officialName = $country['name']['official'] ?? '';
                $flag = $country['flag'] ?? '';
                $cca2 = $country['cca2'] ?? '';
                
                // Convert country code to flag emoji if flag is not already an emoji
                if (empty($flag) && !empty($cca2) && strlen($cca2) === 2) {
                    $flag = countryCodeToFlagEmoji($cca2);
                }
                
                // Also search for cities using a simple pattern match
                // For now, we'll return country results and let users type city, country
                $results[] = [
                    'name' => $commonName,
                    'full_name' => $officialName,
                    'country' => $commonName,
                    'country_code' => $cca2,
                    'flag' => $flag,
                    'display' => $flag . ' ' . $commonName
                ];
            }
        }
    }
    
    // Also try to search for common city patterns
    // Simple city-country combinations
    $cityPatterns = [
        'paris' => ['name' => 'Paris', 'country' => 'France', 'country_code' => 'FR', 'flag' => '🇫🇷'],
        'london' => ['name' => 'London', 'country' => 'United Kingdom', 'country_code' => 'GB', 'flag' => '🇬🇧'],
        'new york' => ['name' => 'New York', 'country' => 'United States', 'country_code' => 'US', 'flag' => '🇺🇸'],
        'tokyo' => ['name' => 'Tokyo', 'country' => 'Japan', 'country_code' => 'JP', 'flag' => '🇯🇵'],
        'madrid' => ['name' => 'Madrid', 'country' => 'Spain', 'country_code' => 'ES', 'flag' => '🇪🇸'],
        'rome' => ['name' => 'Rome', 'country' => 'Italy', 'country_code' => 'IT', 'flag' => '🇮🇹'],
        'berlin' => ['name' => 'Berlin', 'country' => 'Germany', 'country_code' => 'DE', 'flag' => '🇩🇪'],
        'amsterdam' => ['name' => 'Amsterdam', 'country' => 'Netherlands', 'country_code' => 'NL', 'flag' => '🇳🇱'],
        'barcelona' => ['name' => 'Barcelona', 'country' => 'Spain', 'country_code' => 'ES', 'flag' => '🇪🇸'],
        'dubai' => ['name' => 'Dubai', 'country' => 'United Arab Emirates', 'country_code' => 'AE', 'flag' => '🇦🇪'],
        'singapore' => ['name' => 'Singapore', 'country' => 'Singapore', 'country_code' => 'SG', 'flag' => '🇸🇬'],
        'sydney' => ['name' => 'Sydney', 'country' => 'Australia', 'country_code' => 'AU', 'flag' => '🇦🇺'],
        'lima' => ['name' => 'Lima', 'country' => 'Peru', 'country_code' => 'PE', 'flag' => '🇵🇪'],
        'buenos aires' => ['name' => 'Buenos Aires', 'country' => 'Argentina', 'country_code' => 'AR', 'flag' => '🇦🇷'],
        'rio de janeiro' => ['name' => 'Rio de Janeiro', 'country' => 'Brazil', 'country_code' => 'BR', 'flag' => '🇧🇷'],
    ];
    
    $queryLower = strtolower($query);
    foreach ($cityPatterns as $pattern => $data) {
        if (strpos($pattern, $queryLower) !== false || strpos($queryLower, $pattern) !== false) {
            $results[] = [
                'name' => $data['name'] . ', ' . $data['country'],
                'full_name' => $data['name'] . ', ' . $data['country'],
                'city' => $data['name'],
                'country' => $data['country'],
                'country_code' => $data['country_code'],
                'flag' => $data['flag'],
                'display' => $data['flag'] . ' ' . $data['name'] . ', ' . $data['country']
            ];
        }
    }
    
    // Remove duplicates
    $uniqueResults = [];
    $seen = [];
    foreach ($results as $result) {
        $key = $result['name'] . '|' . ($result['country_code'] ?? '');
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $uniqueResults[] = $result;
        }
    }
    
    echo json_encode(['results' => array_slice($uniqueResults, 0, $limit)]);
    
} catch (Exception $e) {
    error_log('Geocode error: ' . $e->getMessage());
    echo json_encode(['results' => []]);
}
?>
