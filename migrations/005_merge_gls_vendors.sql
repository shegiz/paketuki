-- Merge GLS Hungary, Czech, Slovakia, Romania into a single "GLS" vendor with multiple feeds
-- 1. Create vendor_feeds table for multiple URLs per vendor
-- 2. Point GLS to use feeds (keep gls row, add feeds for hu, cz, sk, ro)
-- 3. Reassign locations from gls_cz, gls_sk, gls_ro to gls
-- 4. Deactivate gls_cz, gls_sk, gls_ro

CREATE TABLE IF NOT EXISTS `vendor_feeds` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT UNSIGNED NOT NULL,
    `feed_url` VARCHAR(512) NOT NULL,
    `feed_key` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Short key for vendor_location_id prefix e.g. hu, cz',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_vendor` (`vendor_id`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure single GLS vendor has name "GLS" and a primary api_url (used when no feeds)
UPDATE `vendors` SET `name` = 'GLS', `api_url` = 'https://map.gls-hungary.com/data/deliveryPoints/hu.json' WHERE `code` = 'gls';

-- Add all four country feeds for GLS (vendor_id for 'gls' – get id in application or use subquery)
INSERT INTO `vendor_feeds` (`vendor_id`, `feed_url`, `feed_key`, `sort_order`)
SELECT v.id, 'https://map.gls-hungary.com/data/deliveryPoints/hu.json', 'hu', 1 FROM vendors v WHERE v.code = 'gls' LIMIT 1;
INSERT INTO `vendor_feeds` (`vendor_id`, `feed_url`, `feed_key`, `sort_order`)
SELECT v.id, 'https://map.gls-hungary.com/data/deliveryPoints/cz.json', 'cz', 2 FROM vendors v WHERE v.code = 'gls' LIMIT 1;
INSERT INTO `vendor_feeds` (`vendor_id`, `feed_url`, `feed_key`, `sort_order`)
SELECT v.id, 'https://map.gls-hungary.com/data/deliveryPoints/sk.json', 'sk', 3 FROM vendors v WHERE v.code = 'gls' LIMIT 1;
INSERT INTO `vendor_feeds` (`vendor_id`, `feed_url`, `feed_key`, `sort_order`)
SELECT v.id, 'https://map.gls-hungary.com/data/deliveryPoints/ro.json', 'ro', 4 FROM vendors v WHERE v.code = 'gls' LIMIT 1;

-- Prefix existing GLS (Hungary) location IDs so they match multi-feed format (hu_xxx)
UPDATE `locations` l
JOIN `vendors` v ON v.id = l.vendor_id AND v.code = 'gls'
SET l.vendor_location_id = CONCAT('hu_', l.vendor_location_id)
WHERE l.vendor_location_id NOT LIKE 'hu\_%';

-- Reassign locations from gls_cz, gls_sk, gls_ro to gls with feed_key prefix (cz_, sk_, ro_)
UPDATE `locations` l
JOIN `vendors` v_gls ON v_gls.code = 'gls'
JOIN `vendors` v_other ON v_other.id = l.vendor_id AND v_other.code IN ('gls_cz', 'gls_sk', 'gls_ro')
SET l.vendor_id = v_gls.id,
    l.vendor_location_id = CONCAT(
        CASE v_other.code WHEN 'gls_cz' THEN 'cz' WHEN 'gls_sk' THEN 'sk' WHEN 'gls_ro' THEN 'ro' END,
        '_',
        l.vendor_location_id
    );

-- Deactivate and clear api_url for the separate GLS country vendors (keep rows for sync_runs history)
UPDATE `vendors` SET `active` = FALSE, `api_url` = '' WHERE `code` IN ('gls_cz', 'gls_sk', 'gls_ro');
