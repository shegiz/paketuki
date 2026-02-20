<?php
/**
 * Test script for Foxpost adapter
 * Usage: php scripts/test_foxpost.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Paketuki\Logger;
use Paketuki\Adapters\FoxpostAdapter;

echo "=== Testing Foxpost Adapter ===\n\n";

$logger = new Logger(__DIR__ . '/../logs/test.log', true);
$adapter = new FoxpostAdapter($logger);

$apiUrl = 'https://cdn.foxpost.hu/foxplus.json';

try {
    echo "Fetching data from: {$apiUrl}\n";
    $raw = $adapter->fetch($apiUrl);
    echo "✓ Fetch successful (" . strlen($raw) . " bytes)\n\n";
    
    echo "Parsing data...\n";
    $locations = $adapter->parse($raw);
    echo "✓ Parse successful (" . count($locations) . " locations)\n\n";
    
    if (count($locations) > 0) {
        echo "Sample location:\n";
        $sample = $locations[0];
        foreach ($sample as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            echo "  {$key}: {$value}\n";
        }
        
        echo "\nLocation types distribution:\n";
        $types = [];
        foreach ($locations as $loc) {
            $types[$loc['type']] = ($types[$loc['type']] ?? 0) + 1;
        }
        foreach ($types as $type => $count) {
            echo "  {$type}: {$count}\n";
        }
        
        echo "\nStatus distribution:\n";
        $statuses = [];
        foreach ($locations as $loc) {
            $statuses[$loc['status']] = ($statuses[$loc['status']] ?? 0) + 1;
        }
        foreach ($statuses as $status => $count) {
            echo "  {$status}: {$count}\n";
        }
        
        // Validate coordinates
        echo "\nValidating coordinates...\n";
        $invalid = 0;
        foreach ($locations as $loc) {
            if ($loc['lat'] < -90 || $loc['lat'] > 90 || $loc['lon'] < -180 || $loc['lon'] > 180) {
                $invalid++;
            }
        }
        if ($invalid === 0) {
            echo "✓ All coordinates are valid\n";
        } else {
            echo "✗ Found {$invalid} invalid coordinates\n";
        }
    }
    
    echo "\n✓ Test completed successfully!\n";
    
} catch (\Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
