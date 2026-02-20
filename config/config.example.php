<?php
/**
 * Configuration file example
 * Copy to config.php and adjust values
 */

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'parcel_lockers',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    
    'app' => [
        'timezone' => 'Europe/Vienna',
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
