<?php

namespace Paketuki;

use PDO;

/**
 * Repository for vendor data access
 */
class VendorRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all active vendors
     */
    public function findAllActive(): array
    {
        $sql = "SELECT id, code, name, api_url FROM vendors WHERE active = TRUE ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get feed URLs for a vendor. If vendor has rows in vendor_feeds, return those (url, feed_key);
     * otherwise return single feed from vendor.api_url with feed_key ''.
     *
     * @return array<int, array{url: string, feed_key: string}>
     */
    public function getFeedsForVendor(int $vendorId): array
    {
        $sql = "SELECT feed_url, feed_key FROM vendor_feeds WHERE vendor_id = :vendor_id ORDER BY sort_order, id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':vendor_id' => $vendorId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $feeds = [];
            foreach ($rows as $row) {
                $feeds[] = ['url' => $row['feed_url'], 'feed_key' => $row['feed_key'] ?? ''];
            }
            return $feeds;
        }
        $vendor = $this->findById($vendorId);
        if ($vendor && !empty($vendor['api_url'])) {
            return [['url' => $vendor['api_url'], 'feed_key' => '']];
        }
        return [];
    }

    /**
     * Find vendor by code
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT id, code, name, api_url FROM vendors WHERE code = :code AND active = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get vendor by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id, code, name, api_url FROM vendors WHERE id = :id AND active = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
