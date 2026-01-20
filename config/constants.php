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
    // Images
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    // Documents
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'text/plain', // .txt
    'text/csv', // .csv
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
    // Other travel-related
    'application/json',
    'application/xml',
    'text/xml'
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
define('AVIATION_STACK_API_KEY', '');

// AeroDataBox API (300-600 requests/month free, supports schedules)
// Get a free API key from https://rapidapi.com/aerodatabox/api/aerodatabox/ or https://aerodatabox.com/
define('AERODATABOX_API_KEY', '8cfb1e51bdmsh7e1293006c39a15p1bec3djsn134956d48129');

// Alternative: Aviation Edge (free tier available)
// Get a free API key from https://aviation-edge.com/
// define('AVIATION_EDGE_API_KEY', 'your_aviation_edge_api_key_here');
?>