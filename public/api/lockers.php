<?php
/**
 * API endpoint: GET /api/lockers
 * Returns locations filtered by bounding box and other criteria
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
    
    // Parse query parameters
    $bbox = null;
    if (!empty($_GET['bbox'])) {
        $bboxParts = explode(',', $_GET['bbox']);
        if (count($bboxParts) === 4) {
            $bbox = array_map('floatval', $bboxParts);
        }
    }
    
    $filters = [];
    
    if (!empty($_GET['vendor'])) {
        $filters['vendor'] = explode(',', $_GET['vendor']);
    }
    
    if (!empty($_GET['type'])) {
        $filters['type'] = explode(',', $_GET['type']);
    }
    
    if (!empty($_GET['status'])) {
        $filters['status'] = explode(',', $_GET['status']);
    }
    
    if (!empty($_GET['q'])) {
        $filters['q'] = trim($_GET['q']);
    }
    
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : ($config['api']['default_limit'] ?? 1000);
    $limit = min($limit, $config['api']['max_results'] ?? 5000);
    
    // Fetch locations
    $locations = $locationRepo->findByBboxAndFilters($bbox, $filters, $limit);
    
    // Format response
    $items = [];
    foreach ($locations as $loc) {
        $services = null;
        if (!empty($loc['services_json'])) {
            $services = json_decode($loc['services_json'], true);
        }
        
        $items[] = [
            'id' => (int) $loc['id'],
            'vendor_location_id' => $loc['vendor_location_id'],
            'name' => $loc['name'],
            'type' => $loc['type'],
            'status' => $loc['status'],
            'address_line' => $loc['address_line'],
            'city' => $loc['city'],
            'postcode' => $loc['postcode'],
            'country' => $loc['country'],
            'lat' => (float) $loc['lat'],
            'lon' => (float) $loc['lon'],
            'services' => $services,
            'opening_hours' => $loc['opening_hours'],
            'vendor_code' => $loc['vendor_code'],
            'vendor_name' => $loc['vendor_name'],
            'last_seen_at' => $loc['last_seen_at'],
            'last_updated_at' => $loc['last_updated_at'],
        ];
    }
    
    echo json_encode([
        'items' => $items,
        'meta' => [
            'count' => count($items),
            'limit' => $limit,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    error_log("API error: " . $e->getMessage());
}
