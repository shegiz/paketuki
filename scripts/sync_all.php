<?php
/**
 * Sync script - runs daily to update all vendor data
 * Usage: php scripts/sync_all.php
 * Cron: 0 5 * * * cd /path/to/project && php scripts/sync_all.php >> logs/sync.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Paketuki\Database;
use Paketuki\Logger;
use Paketuki\SyncService;
use Paketuki\Adapters\FoxpostAdapter;
use Paketuki\Adapters\GlsAdapter;
use Paketuki\Adapters\MplAdapter;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize
date_default_timezone_set($config['app']['timezone']);
Database::init($config['database']);
$logger = new Logger(__DIR__ . '/../logs/sync.log', $config['app']['debug'] ?? false);

$logger->info("=== Starting sync job ===");

try {
    $db = Database::getInstance();
    $syncService = new SyncService($db, $logger, $config['sync']);
    
    // Register adapters (GLS = single vendor with multiple feeds: HU, CZ, SK, RO)
    $syncService->registerAdapter('foxpost', new FoxpostAdapter($logger));
    $syncService->registerAdapter('gls', new GlsAdapter($logger));
    $syncService->registerAdapter('mpl', new MplAdapter($logger));
    
    // Sync all vendors
    $results = $syncService->syncAll();
    
    // Log summary
    $logger->info("=== Sync job completed ===");
    foreach ($results as $vendorCode => $result) {
        if ($result['success']) {
            $logger->info("Vendor {$vendorCode}: created={$result['created']}, updated={$result['updated']}, inactivated={$result['inactivated']}");
        } else {
            $logger->error("Vendor {$vendorCode} failed: {$result['error']}");
        }
    }
    
    exit(0);
    
} catch (\Exception $e) {
    $logger->error("Sync job failed: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}
