<?php
/**
 * Application Constants
 */

// Application settings
define('APP_NAME', 'Travel Planner');
define('APP_VERSION', '1.0.0');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf'
]);

// Session settings
define('SESSION_LIFETIME', 86400); // 24 hours

// Date/time formats
define('DATE_FORMAT', 'M d, Y');
define('DATETIME_FORMAT', 'M d, Y g:i A');
define('DATETIME_FORMAT_INPUT', 'Y-m-d\TH:i');

// Travel item types
define('TRAVEL_ITEM_TYPES', [
    'flight' => 'Flight',
    'train' => 'Train',
    'bus' => 'Bus',
    'hotel' => 'Hotel',
    'car_rental' => 'Car Rental',
    'activity' => 'Activity',
    'other' => 'Other'
]);

// Flight API Configuration

// AviationStack API (100 requests/month free, but only real-time flights)
// Get a free API key from https://aviationstack.com/
define('AVIATION_STACK_API_KEY', 'f29d47b978e064ed61aa14021886c3c7');

// AeroDataBox API (300-600 requests/month free, supports schedules)
// Get a free API key from https://rapidapi.com/aerodatabox/api/aerodatabox/ or https://aerodatabox.com/
// Uncomment and set your API key to enable AeroDataBox as fallback:
// define('AERODATABOX_API_KEY', 'your_aerodatabox_api_key_here');

// Alternative: Aviation Edge (free tier available)
// Get a free API key from https://aviation-edge.com/
// define('AVIATION_EDGE_API_KEY', 'your_aviation_edge_api_key_here');
?>


