<?php

namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

/**
 * Adapter for Packeta (Zásilkovna) pickup points.
 * Expects JSON: array of branches, or object with "data" / "branches" / "pickupPoints" array.
 * Each item: id or branchId/pointId, name or branchName, address/street, city, zip/postalCode,
 * latitude/longitude or lat/lng.
 * API typically requires credentials; set api_url (and optional API key in request) when available.
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

        $items = [];
        if (is_array($data)) {
            if (isset($data[0]) && is_array($data[0])) {
                $items = $data;
            } elseif (isset($data['data']) && is_array($data['data'])) {
                $items = $data['data'];
            } elseif (isset($data['branches']) && is_array($data['branches'])) {
                $items = $data['branches'];
            } elseif (isset($data['pickupPoints']) && is_array($data['pickupPoints'])) {
                $items = $data['pickupPoints'];
            } elseif (isset($data['results']) && is_array($data['results'])) {
                $items = $data['results'];
            }
        }

        $locations = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (string) $item['id'] : (isset($item['branchId']) ? (string) $item['branchId'] : (isset($item['pointId']) ? (string) $item['pointId'] : null));
            if ($id === null || $id === '') {
                continue;
            }

            $lat = $this->extractLat($item);
            $lon = $this->extractLon($item);
            if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping Packeta item with missing or invalid coordinates", ['id' => $id]);
                continue;
            }

            $name = isset($item['name']) ? (string) $item['name'] : (isset($item['branchName']) ? (string) $item['branchName'] : "Packeta {$id}");
            $addressLine = isset($item['address']) ? (string) $item['address'] : (isset($item['street']) ? (string) $item['street'] : null);
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
