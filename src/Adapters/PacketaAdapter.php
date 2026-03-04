<?php

namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

/**
 * Adapter for Packeta (Zásilkovna) pickup points.
 * Expects JSON: array of branches, or object with "data" / "branches" / "pickupPoints" array.
 * Each item: id or branchId/pointId, name or branchName, address/street, city, zip/postalCode,
 * latitude/longitude or lat/lng.
 * API typically requires credentials. Use a feed URL with HTTP Basic auth if needed, e.g.
 * https://your_api_password:@api.packeta.com/... (password before the colon).
 */
class PacketaAdapter implements VendorAdapterInterface
{
    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $apiUrl
     * @return string
     */
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
            throw new \RuntimeException("Packeta fetch failed: {$message}");
        }

        if ($httpCode !== 200) {
            curl_close($ch);
            throw new \RuntimeException("Packeta API returned HTTP {$httpCode}");
        }

        curl_close($ch);

        return (string) $response;
    }

    /**
     * @param string $raw
     * @return array
     */
    public function parse(string $raw): array
    {
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Packeta JSON parse error: " . json_last_error_msg());
        }

        $items = $this->extractItemsArray($data);

        $locations = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $this->extractId($item);
            if ($id === null || $id === '') {
                continue;
            }

            $lat = $this->extractLat($item);
            $lon = $this->extractLon($item);
            if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping Packeta item with missing or invalid coordinates", ['id' => $id]);
                continue;
            }

            $name = isset($item['name']) ? (string) $item['name'] : (isset($item['branchName']) ? (string) $item['branchName'] : (isset($item['label']) ? (string) $item['label'] : "Packeta {$id}"));
            $addressLine = isset($item['address']) ? (string) $item['address'] : (isset($item['street']) ? (string) $item['street'] : (isset($item['streetName']) ? (string) $item['streetName'] : (isset($item['addressStreet']) ? (string) $item['addressStreet'] : null)));
            $city = isset($item['city']) ? (string) $item['city'] : null;
            $postcode = isset($item['zip']) ? (string) $item['zip'] : (isset($item['postalCode']) ? (string) $item['postalCode'] : null);
            $country = isset($item['country']) ? strtoupper(substr((string) $item['country'], 0, 2)) : (isset($item['countryCode']) ? strtoupper(substr((string) $item['countryCode'], 0, 2)) : null);

            $type = $this->normalizeType($item);
            $openingHours = isset($item['openingHours']) ? (is_string($item['openingHours']) ? $item['openingHours'] : json_encode($item['openingHours'])) : null;

            $locations[] = [
                'vendor_location_id' => $id,
                'name' => $name,
                'type' => $type,
                'status' => 'active',
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'address_line' => $addressLine,
                'city' => $city,
                'postcode' => $postcode,
                'country' => $country,
                'services' => [],
                'opening_hours' => $openingHours,
            ];
        }

        $this->logger->info("Parsed " . count($locations) . " Packeta locations");

        return $locations;
    }

    /**
     * Extract items array from API response (array or object with known keys or fallback scan).
     *
     * @param mixed $data
     * @return array<int, array>
     */
    private function extractItemsArray($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        $knownKeys = [
            'data', 'branches', 'pickupPoints', 'results', 'branchList', 'branch_list',
            'points', 'list', 'items', 'branch', 'pickup_points',
        ];
        foreach ($knownKeys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $candidate = $data[$key];
                if (isset($candidate[0]) && is_array($candidate[0])) {
                    return $candidate;
                }
            }
        }
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }
        foreach ($data as $value) {
            if (is_array($value) && isset($value[0]) && is_array($value[0])) {
                $first = $value[0];
                if (isset($first['id']) || isset($first['branchId']) || isset($first['pointId'])
                    || isset($first['internalId']) || isset($first['name'])) {
                    return $value;
                }
            }
        }
        return [];
    }

    /**
     * @param array $item
     * @return string|null
     */
    private function extractId(array $item): ?string
    {
        foreach (['id', 'branchId', 'pointId', 'internalId', 'branch_id'] as $key) {
            if (isset($item[$key]) && (string) $item[$key] !== '') {
                return (string) $item[$key];
            }
        }
        return null;
    }

    /**
     * @param array $item
     * @return float|null
     */
    private function extractLat(array $item)
    {
        if (isset($item['latitude'])) {
            return (float) $item['latitude'];
        }
        if (isset($item['lat'])) {
            return (float) $item['lat'];
        }
        if (isset($item['gpsLat'])) {
            return (float) $item['gpsLat'];
        }
        if (isset($item['location']) && is_array($item['location'])) {
            $loc = $item['location'];
            return isset($loc['lat']) ? (float) $loc['lat'] : (isset($loc['latitude']) ? (float) $loc['latitude'] : null);
        }
        return null;
    }

    /**
     * @param array $item
     * @return float|null
     */
    private function extractLon(array $item)
    {
        if (isset($item['longitude'])) {
            return (float) $item['longitude'];
        }
        if (isset($item['lng'])) {
            return (float) $item['lng'];
        }
        if (isset($item['lon'])) {
            return (float) $item['lon'];
        }
        if (isset($item['gpsLng'])) {
            return (float) $item['gpsLng'];
        }
        if (isset($item['location']) && is_array($item['location'])) {
            $loc = $item['location'];
            return isset($loc['lon']) ? (float) $loc['lon'] : (isset($loc['lng']) ? (float) $loc['lng'] : (isset($loc['longitude']) ? (float) $loc['longitude'] : null));
        }
        return null;
    }

    /**
     * @param array $item
     * @return string
     */
    private function normalizeType(array $item): string
    {
        $type = isset($item['type']) ? strtolower(trim((string) $item['type'])) : '';
        if ($type === 'locker' || $type === 'parcel_locker') {
            return 'locker';
        }
        if ($type === 'parcel_shop' || $type === 'parcel-shop' || $type === 'shop') {
            return 'parcel_shop';
        }
        if ($type === 'dropoff_point' || $type === 'dropoff') {
            return 'dropoff_point';
        }
        return 'pickup_point';
    }
}
