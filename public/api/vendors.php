<?php
/**
 * API endpoint: GET /api/vendors
 * Returns list of vendors with location counts
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Paketuki\Database;
use Paketuki\LocationRepository;
use Paketuki\Logger;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Load configuration
    $config = require __DIR__ . '/../../config/config.php';
    
    // Initialize
    date_default_timezone_set($config['app']['timezone']);
    Database::init($config['database']);
    $logger = new Logger(__DIR__ . '/../../logs/app.log', $config['app']['debug'] ?? false);
    
    $db = Database::getInstance();
    $locationRepo = new LocationRepository($db, $logger);
    
    $vendors = $locationRepo->getCountsByVendor();
    
    echo json_encode([
        'vendors' => $vendors,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    error_log("API error: " . $e->getMessage());
}
