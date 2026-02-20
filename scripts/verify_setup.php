<?php
/**
 * Verification script to check if setup is correct
 * Usage: php scripts/verify_setup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Paketuki\Database;
use Paketuki\Logger;

echo "=== Parcel Locker Aggregator Setup Verification ===\n\n";

$errors = [];
$warnings = [];

// Check PHP version
echo "Checking PHP version... ";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "✓ PHP " . PHP_VERSION . "\n";
} else {
    echo "✗ PHP " . PHP_VERSION . " (requires 7.4+)\n";
    $errors[] = "PHP version too old";
}

// Check required extensions
echo "Checking PHP extensions...\n";
$required = ['pdo', 'pdo_mysql', 'curl', 'json'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "  ✓ {$ext}\n";
    } else {
        echo "  ✗ {$ext} (missing)\n";
        $errors[] = "Missing PHP extension: {$ext}";
    }
}

// Check config file
echo "Checking configuration... ";
$configFile = __DIR__ . '/../config/config.php';
if (file_exists($configFile)) {
    echo "✓ config.php exists\n";
    $config = require $configFile;
} else {
    echo "✗ config.php not found\n";
    $errors[] = "Configuration file missing. Copy config.example.php to config.php";
}

// Check database connection
if (isset($config)) {
    echo "Checking database connection... ";
    try {
        Database::init($config['database']);
        $db = Database::getInstance();
        echo "✓ Connected\n";
        
        // Check tables
        echo "Checking database tables...\n";
        $tables = ['vendors', 'locations', 'sync_runs', 'vendor_payload_snapshots'];
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() > 0) {
                echo "  ✓ {$table}\n";
            } else {
                echo "  ✗ {$table} (missing)\n";
                $errors[] = "Database table missing: {$table}";
            }
        }
        
        // Check vendors
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM vendors WHERE active = TRUE");
        $result = $stmt->fetch();
        $vendorCount = $result['cnt'] ?? 0;
        echo "  Active vendors: {$vendorCount}\n";
        
        // Check locations
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM locations WHERE active = TRUE");
        $result = $stmt->fetch();
        $locationCount = $result['cnt'] ?? 0;
        echo "  Active locations: {$locationCount}\n";
        
        if ($locationCount === 0) {
            $warnings[] = "No locations found. Run sync script: php scripts/sync_all.php";
        }
        
    } catch (\Exception $e) {
        echo "✗ Connection failed: " . $e->getMessage() . "\n";
        $errors[] = "Database connection failed: " . $e->getMessage();
    }
}

// Check directories
echo "Checking directories...\n";
$dirs = [
    'logs' => __DIR__ . '/../logs',
    'cache' => __DIR__ . '/../cache',
];
foreach ($dirs as $name => $path) {
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "  ✓ {$name} (exists, writable)\n";
        } else {
            echo "  ⚠ {$name} (exists, not writable)\n";
            $warnings[] = "Directory not writable: {$name}";
        }
    } else {
        echo "  ⚠ {$name} (missing, will be created)\n";
        if (!mkdir($path, 0755, true)) {
            $warnings[] = "Could not create directory: {$name}";
        }
    }
}

// Check vendor adapter
echo "Checking vendor adapters... ";
$adapterFile = __DIR__ . '/../src/Adapters/FoxpostAdapter.php';
if (file_exists($adapterFile)) {
    echo "✓ FoxpostAdapter exists\n";
} else {
    echo "✗ FoxpostAdapter missing\n";
    $errors[] = "Vendor adapter file missing";
}

// Summary
echo "\n=== Summary ===\n";
if (empty($errors) && empty($warnings)) {
    echo "✓ All checks passed! Setup looks good.\n";
    exit(0);
}

if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}

exit(0);
