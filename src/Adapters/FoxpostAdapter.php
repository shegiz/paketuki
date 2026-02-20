<?php

namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

/**
 * Adapter for Foxpost API
 * Example feed: https://cdn.foxpost.hu/foxplus.json
 */
class FoxpostAdapter implements VendorAdapterInterface
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function fetch(string $apiUrl): string
    {
        $ch = curl_init($apiUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'ParcelLockerAggregator/1.0',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \RuntimeException("Foxpost fetch failed: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("Foxpost API returned HTTP {$httpCode}");
        }
        
        return $response;
    }

    public function parse(string $raw): array
    {
        $data = json_decode($raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Foxpost JSON parse error: " . json_last_error_msg());
        }
        
        if (!is_array($data)) {
            throw new \RuntimeException("Foxpost data is not an array");
        }
        
        $locations = [];
        
        foreach ($data as $item) {
            // Validate required fields
            if (empty($item['id']) || !isset($item['latitude']) || !isset($item['longitude'])) {
                $this->logger->warning("Skipping Foxpost item with missing required fields", ['item' => $item]);
                continue;
            }
            
            $lat = (float) $item['latitude'];
            $lon = (float) $item['longitude'];
            
            // Validate coordinates
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping Foxpost item with invalid coordinates", [
                    'id' => $item['id'] ?? 'unknown',
                    'lat' => $lat,
                    'lon' => $lon,
                ]);
                continue;
            }
            
            // Normalize type
            $type = $this->normalizeType($item['type'] ?? 'locker');
            
            // Normalize status
            $status = $this->normalizeStatus($item['status'] ?? 'active');
            
            // Extract address components
            $addressLine = $item['address'] ?? $item['address_line'] ?? null;
            $city = $item['city'] ?? null;
            $postcode = $item['postcode'] ?? $item['zip'] ?? null;
            $country = $item['country'] ?? 'HU';
            
            // Extract services
            $services = [];
            if (isset($item['services']) && is_array($item['services'])) {
                $services = $item['services'];
            }
            if (isset($item['available_24_7']) && $item['available_24_7']) {
                $services['available_24_7'] = true;
            }
            if (isset($item['indoor']) && $item['indoor']) {
                $services['indoor'] = true;
            }
            
            // Opening hours
            $openingHours = null;
            if (isset($item['opening_hours'])) {
                $openingHours = is_array($item['opening_hours']) 
                    ? json_encode($item['opening_hours']) 
                    : (string) $item['opening_hours'];
            }
            
            $locations[] = [
                'vendor_location_id' => (string) $item['id'],
                'name' => $item['name'] ?? $item['title'] ?? "Foxpost {$item['id']}",
                'type' => $type,
                'status' => $status,
                'lat' => $lat,
                'lon' => $lon,
                'address_line' => $addressLine,
                'city' => $city,
                'postcode' => $postcode,
                'country' => $country,
                'services' => $services,
                'opening_hours' => $openingHours,
            ];
        }
        
        $this->logger->info("Parsed " . count($locations) . " Foxpost locations");
        
        return $locations;
    }

    /**
     * Normalize vendor-specific type to standard taxonomy
     */
    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        
        $mapping = [
            'locker' => 'locker',
            'parcel_locker' => 'locker',
            'automata' => 'locker',
            'parcel_shop' => 'parcel_shop',
            'shop' => 'parcel_shop',
            'dropoff' => 'dropoff_point',
            'drop_off' => 'dropoff_point',
            'pickup' => 'pickup_point',
            'pick_up' => 'pickup_point',
        ];
        
        return $mapping[$type] ?? 'locker';
    }

    /**
     * Normalize vendor-specific status to standard taxonomy
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        
        $mapping = [
            'active' => 'active',
            'available' => 'active',
            'inactive' => 'inactive',
            'unavailable' => 'inactive',
            'out_of_service' => 'out_of_service',
            'maintenance' => 'out_of_service',
            'closed' => 'inactive',
        ];
        
        return $mapping[$status] ?? 'active';
    }
}
