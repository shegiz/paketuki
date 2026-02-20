-- Parcel Locker Map Aggregator Database Schema
-- MySQL 8.x

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `sync_runs`;
DROP TABLE IF EXISTS `vendor_payload_snapshots`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `vendors`;
SET FOREIGN_KEY_CHECKS = 1;

-- Vendors table
CREATE TABLE `vendors` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique vendor code (e.g., foxpost)',
    `name` VARCHAR(255) NOT NULL COMMENT 'Display name',
    `api_url` VARCHAR(512) NOT NULL COMMENT 'API endpoint URL',
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locations table (normalized lockers)
CREATE TABLE `locations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT UNSIGNED NOT NULL,
    `vendor_location_id` VARCHAR(255) NOT NULL COMMENT 'Vendor-specific location ID',
    `name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'locker, parcel_shop, dropoff_point, pickup_point',
    `status` VARCHAR(50) NOT NULL DEFAULT 'active' COMMENT 'active, inactive, out_of_service',
    `address_line` VARCHAR(512),
    `city` VARCHAR(255),
    `postcode` VARCHAR(50),
    `country` VARCHAR(2) DEFAULT 'HU',
    `lat` DECIMAL(10, 8) NOT NULL,
    `lon` DECIMAL(11, 8) NOT NULL,
    `services_json` JSON COMMENT 'Additional vendor-specific services',
    `opening_hours` TEXT COMMENT 'Opening hours as string or JSON',
    `last_seen_at` TIMESTAMP NULL COMMENT 'Last time seen in vendor feed',
    `last_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_vendor_location` (`vendor_id`, `vendor_location_id`),
    INDEX `idx_vendor` (`vendor_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_active` (`active`),
    INDEX `idx_geo` (`lat`, `lon`),
    INDEX `idx_city_postcode` (`city`, `postcode`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Composite index on (lat, lon) is used for bounding box queries
-- For true spatial queries, consider adding a POINT column with SPATIAL INDEX

-- Vendor payload snapshots (for debugging/auditing)
CREATE TABLE `vendor_payload_snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT UNSIGNED NOT NULL,
    `fetched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of payload',
    `payload` LONGTEXT NOT NULL COMMENT 'Raw JSON payload',
    INDEX `idx_vendor_fetched` (`vendor_id`, `fetched_at`),
    INDEX `idx_hash` (`hash`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync runs log
CREATE TABLE `sync_runs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT UNSIGNED NOT NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` TIMESTAMP NULL,
    `created` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated` INT UNSIGNED NOT NULL DEFAULT 0,
    `inactivated` INT UNSIGNED NOT NULL DEFAULT 0,
    `errors` TEXT COMMENT 'Error messages if any',
    `status` VARCHAR(20) NOT NULL DEFAULT 'running' COMMENT 'running, completed, failed',
    INDEX `idx_vendor_started` (`vendor_id`, `started_at`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default vendor (Foxpost)
INSERT INTO `vendors` (`code`, `name`, `api_url`, `active`) VALUES
('foxpost', 'Foxpost', 'https://cdn.foxpost.hu/foxplus.json', TRUE);
