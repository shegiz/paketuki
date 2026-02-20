<?php

namespace Paketuki;

use PDO;

/**
 * Repository for location data access
 */
class LocationRepository
{
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Upsert a location (insert or update)
     */
    public function upsert(int $vendorId, array $locationData): bool
    {
        $sql = "
            INSERT INTO locations (
                vendor_id, vendor_location_id, name, type, status,
                address_line, city, postcode, country,
                lat, lon, services_json, opening_hours,
                last_seen_at, active
            ) VALUES (
                :vendor_id, :vendor_location_id, :name, :type, :status,
                :address_line, :city, :postcode, :country,
                :lat, :lon, :services_json, :opening_hours,
                NOW(), TRUE
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                type = VALUES(type),
                status = VALUES(status),
                address_line = VALUES(address_line),
                city = VALUES(city),
                postcode = VALUES(postcode),
                country = VALUES(country),
                lat = VALUES(lat),
                lon = VALUES(lon),
                services_json = VALUES(services_json),
                opening_hours = VALUES(opening_hours),
                last_seen_at = NOW(),
                active = TRUE,
                last_updated_at = NOW()
        ";
        
        $stmt = $this->db->prepare($sql);
        
        $servicesJson = !empty($locationData['services']) 
            ? json_encode($locationData['services']) 
            : null;
        
        return $stmt->execute([
            ':vendor_id' => $vendorId,
            ':vendor_location_id' => $locationData['vendor_location_id'],
            ':name' => $locationData['name'],
            ':type' => $locationData['type'],
            ':status' => $locationData['status'],
            ':address_line' => $locationData['address_line'] ?? null,
            ':city' => $locationData['city'] ?? null,
            ':postcode' => $locationData['postcode'] ?? null,
            ':country' => $locationData['country'] ?? 'HU',
            ':lat' => $locationData['lat'],
            ':lon' => $locationData['lon'],
            ':services_json' => $servicesJson,
            ':opening_hours' => $locationData['opening_hours'] ?? null,
        ]);
    }

    /**
     * Mark locations as inactive if not seen recently
     */
    public function markInactiveByVendor(int $vendorId, int $thresholdDays): int
    {
        $sql = "
            UPDATE locations
            SET active = FALSE, last_updated_at = NOW()
            WHERE vendor_id = :vendor_id
            AND active = TRUE
            AND last_seen_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':vendor_id' => $vendorId,
            ':days' => $thresholdDays,
        ]);
        
        return $stmt->rowCount();
    }

    /**
     * Find locations by bounding box and filters
     * 
     * @param array $bbox [minLon, minLat, maxLon, maxLat] or null
     * @param array $filters ['vendor' => [...], 'type' => [...], 'status' => [...], 'q' => 'search term']
     * @param int $limit Maximum results
     * @return array
     */
    public function findByBboxAndFilters(?array $bbox, array $filters = [], int $limit = 1000): array
    {
        $where = ['l.active = TRUE'];
        $params = [];
        
        // Bounding box filter
        if ($bbox && count($bbox) === 4) {
            [$minLon, $minLat, $maxLon, $maxLat] = $bbox;
            $where[] = 'l.lat BETWEEN :min_lat AND :max_lat';
            $where[] = 'l.lon BETWEEN :min_lon AND :max_lon';
            $params[':min_lat'] = $minLat;
            $params[':max_lat'] = $maxLat;
            $params[':min_lon'] = $minLon;
            $params[':max_lon'] = $maxLon;
        }
        
        // Vendor filter
        if (!empty($filters['vendor'])) {
            $vendorCodes = is_array($filters['vendor']) ? $filters['vendor'] : [$filters['vendor']];
            $placeholders = [];
            foreach ($vendorCodes as $i => $code) {
                $key = ':vendor_' . $i;
                $placeholders[] = $key;
                $params[$key] = $code;
            }
            $where[] = 'v.code IN (' . implode(',', $placeholders) . ')';
        }
        
        // Type filter
        if (!empty($filters['type'])) {
            $types = is_array($filters['type']) ? $filters['type'] : [$filters['type']];
            $placeholders = [];
            foreach ($types as $i => $type) {
                $key = ':type_' . $i;
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $where[] = 'l.type IN (' . implode(',', $placeholders) . ')';
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $placeholders = [];
            foreach ($statuses as $i => $status) {
                $key = ':status_' . $i;
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $where[] = 'l.status IN (' . implode(',', $placeholders) . ')';
        }
        
        // Text search (name, address, city, postcode)
        if (!empty($filters['q'])) {
            $searchTerm = '%' . $filters['q'] . '%';
            $where[] = '(
                l.name LIKE :q OR
                l.address_line LIKE :q OR
                l.city LIKE :q OR
                l.postcode LIKE :q
            )';
            $params[':q'] = $searchTerm;
        }
        
        $sql = "
            SELECT 
                l.id,
                l.vendor_location_id,
                l.name,
                l.type,
                l.status,
                l.address_line,
                l.city,
                l.postcode,
                l.country,
                l.lat,
                l.lon,
                l.services_json,
                l.opening_hours,
                l.last_seen_at,
                l.last_updated_at,
                v.code as vendor_code,
                v.name as vendor_name
            FROM locations l
            INNER JOIN vendors v ON l.vendor_id = v.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.id
            LIMIT :limit
        ";
        
        $params[':limit'] = $limit;
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get location counts by vendor
     */
    public function getCountsByVendor(): array
    {
        $sql = "
            SELECT 
                v.id,
                v.code,
                v.name,
                COUNT(l.id) as count
            FROM vendors v
            LEFT JOIN locations l ON v.id = l.vendor_id AND l.active = TRUE
            WHERE v.active = TRUE
            GROUP BY v.id, v.code, v.name
            ORDER BY v.name
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get location counts by type
     */
    public function getCountsByType(): array
    {
        $sql = "
            SELECT 
                type,
                COUNT(*) as count
            FROM locations
            WHERE active = TRUE
            GROUP BY type
            ORDER BY count DESC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
