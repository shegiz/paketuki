<?php
/**
 * Configuration file example
 * Copy to config.php and adjust values.
 * Sensitive values (e.g. database password) belong in secrets.php on the server only.
 */

$config = [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'paketuki',
        'username' => 'paketuki',
        'password' => '', // override in config/secrets.php on the server
        'charset' => 'utf8mb4',
    ],
    
    'app' => [
        'timezone' => 'Europe/Budapest',
        'debug' => false,
        'cache_ttl' => 300, // seconds
    ],
    
    'sync' => [
        'timeout' => 30, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 5, // seconds
        'inactive_threshold_days' => 7, // mark inactive if not seen for N days
    ],
    
    'api' => [
        'max_results' => 5000,
        'default_limit' => 1000,
    ],
    
    'geocoding' => [
        'enabled' => true,
        'nominatim_url' => 'https://nominatim.openstreetmap.org/search',
        'user_agent' => 'ParcelLockerAggregator/1.0',
        'cache_ttl' => 86400, // 24 hours
    ],
];

// Merge server-only secrets (config/secrets.php) if present
if (is_file(__DIR__ . '/secrets.php')) {
    $secrets = require __DIR__ . '/secrets.php';
    if (!empty($secrets['database']) && is_array($secrets['database'])) {
        $config['database'] = array_merge($config['database'], $secrets['database']);
    }
}

return $config;
