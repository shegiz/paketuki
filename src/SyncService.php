<?php

namespace Paketuki;

use PDO;

/**
 * Service for syncing vendor data
 */
class SyncService
{
    private PDO $db;
    private Logger $logger;
    private array $config;
    private array $adapters = [];

    public function __construct(PDO $db, Logger $logger, array $config)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Register a vendor adapter
     */
    public function registerAdapter(string $vendorCode, VendorAdapterInterface $adapter): void
    {
        $this->adapters[$vendorCode] = $adapter;
    }

    /**
     * Sync all active vendors
     */
    public function syncAll(): array
    {
        $vendorRepo = new VendorRepository($this->db);
        $vendors = $vendorRepo->findAllActive();
        
        $results = [];
        
        foreach ($vendors as $vendor) {
            try {
                $results[$vendor['code']] = $this->syncVendor($vendor);
            } catch (\Exception $e) {
                $this->logger->error("Sync failed for vendor {$vendor['code']}", [
                    'error' => $e->getMessage(),
                ]);
                $results[$vendor['code']] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Sync a single vendor
     */
    public function syncVendor(array $vendor): array
    {
        $vendorCode = $vendor['code'];
        $vendorId = $vendor['id'];
        $apiUrl = $vendor['api_url'];
        
        $this->logger->info("Starting sync for vendor: {$vendorCode}");
        
        // Start sync run
        $runId = $this->startSyncRun($vendorId);
        
        try {
            // Get adapter
            if (!isset($this->adapters[$vendorCode])) {
                throw new \RuntimeException("No adapter registered for vendor: {$vendorCode}");
            }
            
            $adapter = $this->adapters[$vendorCode];
            
            // Fetch data with retries
            $raw = $this->fetchWithRetry($adapter, $apiUrl, $vendorCode);
            
            // Save payload snapshot
            $this->savePayloadSnapshot($vendorId, $raw);
            
            // Parse data
            $locations = $adapter->parse($raw);
            
            // Upsert locations
            $locationRepo = new LocationRepository($this->db, $this->logger);
            $created = 0;
            $updated = 0;
            
            foreach ($locations as $locationData) {
                // Check if exists
                $exists = $this->locationExists($vendorId, $locationData['vendor_location_id']);
                
                if ($locationRepo->upsert($vendorId, $locationData)) {
                    if ($exists) {
                        $updated++;
                    } else {
                        $created++;
                    }
                }
            }
            
            // Mark inactive locations
            $thresholdDays = $this->config['inactive_threshold_days'] ?? 7;
            $inactivated = $locationRepo->markInactiveByVendor($vendorId, $thresholdDays);
            
            // Complete sync run
            $this->completeSyncRun($runId, $created, $updated, $inactivated);
            
            $this->logger->info("Sync completed for vendor: {$vendorCode}", [
                'created' => $created,
                'updated' => $updated,
                'inactivated' => $inactivated,
            ]);
            
            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'inactivated' => $inactivated,
                'total' => count($locations),
            ];
            
        } catch (\Exception $e) {
            $this->failSyncRun($runId, $e->getMessage());
            throw $e;
        }
    }

    private function fetchWithRetry(VendorAdapterInterface $adapter, string $apiUrl, string $vendorCode): string
    {
        $attempts = $this->config['retry_attempts'] ?? 3;
        $delay = $this->config['retry_delay'] ?? 5;
        $lastException = null;
        
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $adapter->fetch($apiUrl);
            } catch (\Exception $e) {
                $lastException = $e;
                if ($i < $attempts) {
                    $this->logger->warning("Fetch attempt {$i} failed for {$vendorCode}, retrying...", [
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                }
            }
        }
        
        throw new \RuntimeException("Failed to fetch after {$attempts} attempts: " . $lastException->getMessage(), 0, $lastException);
    }

    private function savePayloadSnapshot(int $vendorId, string $payload): void
    {
        $hash = hash('sha256', $payload);
        
        $sql = "
            INSERT INTO vendor_payload_snapshots (vendor_id, hash, payload)
            VALUES (:vendor_id, :hash, :payload)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':vendor_id' => $vendorId,
            ':hash' => $hash,
            ':payload' => $payload,
        ]);
    }

    private function locationExists(int $vendorId, string $vendorLocationId): bool
    {
        $sql = "
            SELECT COUNT(*) as cnt
            FROM locations
            WHERE vendor_id = :vendor_id AND vendor_location_id = :vendor_location_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':vendor_id' => $vendorId,
            ':vendor_location_id' => $vendorLocationId,
        ]);
        
        $result = $stmt->fetch();
        return ($result['cnt'] ?? 0) > 0;
    }

    private function startSyncRun(int $vendorId): int
    {
        $sql = "
            INSERT INTO sync_runs (vendor_id, status, started_at)
            VALUES (:vendor_id, 'running', NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':vendor_id' => $vendorId]);
        
        return (int) $this->db->lastInsertId();
    }

    private function completeSyncRun(int $runId, int $created, int $updated, int $inactivated): void
    {
        $sql = "
            UPDATE sync_runs
            SET status = 'completed',
                ended_at = NOW(),
                created = :created,
                updated = :updated,
                inactivated = :inactivated
            WHERE id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $runId,
            ':created' => $created,
            ':updated' => $updated,
            ':inactivated' => $inactivated,
        ]);
    }

    private function failSyncRun(int $runId, string $error): void
    {
        $sql = "
            UPDATE sync_runs
            SET status = 'failed',
                ended_at = NOW(),
                errors = :error
            WHERE id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $runId,
            ':error' => $error,
        ]);
    }
}
