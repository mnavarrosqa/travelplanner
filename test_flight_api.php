<?php
/**
 * Test Flight API Connection
 * Run this file directly to test AviationStack API
 */

require_once __DIR__ . '/config/constants.php';

$apiKey = defined('AVIATION_STACK_API_KEY') ? AVIATION_STACK_API_KEY : '';
$flightNumber = 'LA2399'; // Test with a common flight
$date = date('2026-01-25');

echo "<h2>Testing AviationStack API</h2>";
echo "<p><strong>API Key:</strong> " . (empty($apiKey) ? 'NOT SET' : substr($apiKey, 0, 10) . '...') . "</p>";
echo "<p><strong>Flight Number:</strong> $flightNumber</p>";
echo "<p><strong>Date:</strong> $date</p>";

if (empty($apiKey)) {
    die("<p style='color: red;'><strong>ERROR:</strong> API key not found in config/constants.php</p>");
}

$url = "https://api.aviationstack.com/v1/flights?access_key=" . urlencode($apiKey) . 
       "&flight_iata=" . urlencode($flightNumber) . 
       "&flight_date=" . urlencode($date);

echo "<p><strong>API URL:</strong> <a href='" . htmlspecialchars($url) . "' target='_blank'>" . htmlspecialchars($url) . "</a></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

echo "<h3>Making API Request...</h3>";
$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$duration = round(($endTime - $startTime) * 1000, 2);

echo "<p><strong>Request Duration:</strong> {$duration}ms</p>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

if ($curlError) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> $curlError</p>";
}

if ($response) {
    $data = json_decode($response, true);
    
    echo "<h3>API Response:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 1rem; border-radius: 5px; overflow-x: auto;'>";
    echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "</pre>";
    
    if (isset($data['error'])) {
        echo "<p style='color: red;'><strong>API Error:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Code:</strong> " . htmlspecialchars($data['error']['code'] ?? 'N/A') . "</li>";
        echo "<li><strong>Message:</strong> " . htmlspecialchars($data['error']['message'] ?? 'N/A') . "</li>";
        echo "</ul>";
    }
    
    if (isset($data['data']) && is_array($data['data'])) {
        echo "<p style='color: green;'><strong>✓ Found " . count($data['data']) . " flight(s)</strong></p>";
        if (count($data['data']) > 0) {
            echo "<h4>First Flight Details:</h4>";
            echo "<pre style='background: #e8f5e9; padding: 1rem; border-radius: 5px;'>";
            print_r($data['data'][0]);
            echo "</pre>";
        }
    } else {
        echo "<p style='color: orange;'><strong>⚠ No flight data in response</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>ERROR:</strong> No response from API</p>";
}
?>
