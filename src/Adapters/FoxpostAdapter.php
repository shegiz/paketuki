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
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $message = $error !== '' ? $error : 'Unknown cURL error';
            throw new \RuntimeException("Foxpost fetch failed: {$message}");
        }
        
        if ($httpCode !== 200) {
            curl_close($ch);
            throw new \RuntimeException("Foxpost API returned HTTP {$httpCode}");
        }
        
        curl_close($ch);
        
        return (string) $response;
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
        
        // Foxpost API uses: place_id, operator_id, geolat, geolng, name, address, zip, city, street, country, open, service, isOutdoor, etc.
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            // Required: unique id and coordinates (Foxpost uses place_id, geolat, geolng)
            $placeId = isset($item['place_id']) ? $item['place_id'] : (isset($item['id']) ? $item['id'] : null);
            $lat = isset($item['geolat']) ? (float) $item['geolat'] : (isset($item['latitude']) ? (float) $item['latitude'] : null);
            $lon = isset($item['geolng']) ? (float) $item['geolng'] : (isset($item['longitude']) ? (float) $item['longitude'] : null);
            if ($placeId === null || $lat === null || $lon === null) {
                $this->logger->warning("Skipping Foxpost item with missing required fields (place_id/id, geolat/latitude, geolng/longitude)", ['place_id' => $placeId, 'geolat' => isset($item['geolat']) ? $item['geolat'] : null, 'geolng' => isset($item['geolng']) ? $item['geolng'] : null]);
                continue;
            }
            
            // Validate coordinates
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping Foxpost item with invalid coordinates", [
                    'place_id' => $placeId,
                    'lat' => $lat,
                    'lon' => $lon,
                ]);
                continue;
            }
            
            // Normalize type (API has variant e.g. "FOXPOST A-BOX", apmType e.g. "Rollkon")
            $type = $this->normalizeType(isset($item['apmType']) ? $item['apmType'] : (isset($item['type']) ? $item['type'] : 'locker'));
            
            // Normalize status (API may not expose status; assume active)
            $status = $this->normalizeStatus(isset($item['status']) ? $item['status'] : 'active');
            
            // Extract address components
            $addressLine = isset($item['address']) ? $item['address'] : (isset($item['address_line']) ? $item['address_line'] : null);
            $city = isset($item['city']) ? $item['city'] : null;
            $postcode = isset($item['zip']) ? $item['zip'] : (isset($item['postcode']) ? $item['postcode'] : null);
            $country = isset($item['country']) ? strtoupper((string) $item['country']) : 'HU';
            
            // Extract services
            $services = [];
            if (isset($item['service']) && is_array($item['service'])) {
                $services['service'] = $item['service'];
            }
            if (isset($item['paymentOptions']) && is_array($item['paymentOptions'])) {
                $services['payment_options'] = $item['paymentOptions'];
            }
            $services['card_payment'] = !empty($item['cardPayment']);
            $services['indoor'] = isset($item['isOutdoor']) ? !$item['isOutdoor'] : null;
            if (isset($item['variant'])) {
                $services['variant'] = $item['variant'];
            }
            // Derive 24/7 from open hours (e.g. "00:00-24:00")
            $open = isset($item['open']) && is_array($item['open']) ? $item['open'] : [];
            $all24_7 = count($open) > 0;
            foreach ($open as $hours) {
                if ((string) $hours !== '00:00-24:00') {
                    $all24_7 = false;
                    break;
                }
            }
            $services['available_24_7'] = $all24_7;
            
            // Opening hours: Foxpost uses "open" object (hetfo, kedd, ...)
            $openingHours = null;
            if (isset($item['open']) && is_array($item['open'])) {
                $openingHours = json_encode($item['open']);
            } elseif (isset($item['opening_hours'])) {
                $openingHours = is_array($item['opening_hours']) ? json_encode($item['opening_hours']) : (string) $item['opening_hours'];
            }
            
            $locations[] = [
                'vendor_location_id' => (string) $placeId,
                'name' => isset($item['name']) ? $item['name'] : (isset($item['title']) ? $item['title'] : "Foxpost {$placeId}"),
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
            'rollkon' => 'locker',
            'cleveron' => 'locker',
            'keba' => 'locker',
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
