<?php

namespace Paketuki;

use PDO;
use PDOException;

/**
 * Database connection wrapper using PDO
 */
class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Initialize database configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get PDO instance (singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = self::$config;
            
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE utf8mb4_unicode_ci",
            ];
            
            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $options
                );
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new \RuntimeException("Database connection failed", 0, $e);
            }
        }
        
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
