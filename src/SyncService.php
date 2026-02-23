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
     * Sync a single vendor (supports multiple feeds via vendor_feeds table)
     */
    public function syncVendor(array $vendor): array
    {
        $vendorCode = $vendor['code'];
        $vendorId = $vendor['id'];

        $this->logger->info("Starting sync for vendor: {$vendorCode}");

        // Start sync run
        $runId = $this->startSyncRun($vendorId);

        try {
            if (!isset($this->adapters[$vendorCode])) {
                throw new \RuntimeException("No adapter registered for vendor: {$vendorCode}");
            }

            $adapter = $this->adapters[$vendorCode];
            $vendorRepo = new VendorRepository($this->db);
            $feeds = $vendorRepo->getFeedsForVendor($vendorId);

            if (empty($feeds)) {
                throw new \RuntimeException("Vendor {$vendorCode} has no feed URL configured");
            }

            $locationRepo = new LocationRepository($this->db, $this->logger);
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalLocations = 0;

            foreach ($feeds as $feed) {
                $apiUrl = $feed['url'];
                $feedKey = isset($feed['feed_key']) ? trim((string) $feed['feed_key']) : '';

                $raw = $this->fetchWithRetry($adapter, $apiUrl, $vendorCode);
                $this->savePayloadSnapshot($vendorId, $raw);

                $locations = $adapter->parse($raw);

                foreach ($locations as $locationData) {
                    $locationId = $locationData['vendor_location_id'];
                    if ($feedKey !== '') {
                        $locationData['vendor_location_id'] = $feedKey . '_' . $locationId;
                    }

                    $exists = $this->locationExists($vendorId, $locationData['vendor_location_id']);
                    if ($locationRepo->upsert($vendorId, $locationData)) {
                        if ($exists) {
                            $totalUpdated++;
                        } else {
                            $totalCreated++;
                        }
                        $totalLocations++;
                    }
                }
            }

            $thresholdDays = $this->config['inactive_threshold_days'] ?? 7;
            $inactivated = $locationRepo->markInactiveByVendor($vendorId, $thresholdDays);

            $this->completeSyncRun($runId, $totalCreated, $totalUpdated, $inactivated);

            $this->logger->info("Sync completed for vendor: {$vendorCode}", [
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'inactivated' => $inactivated,
            ]);

            return [
                'success' => true,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'inactivated' => $inactivated,
                'total' => $totalLocations,
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
