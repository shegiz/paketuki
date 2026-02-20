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
