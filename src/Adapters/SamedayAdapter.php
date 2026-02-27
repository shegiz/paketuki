<?php

namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

/**
 * Adapter for Sameday easybox parcel lockers (RO, HU, BG).
 * Expects JSON: array of lockers, or object with "data" / "lockers" / "items" array.
 * Each item: lockerId or id, name, address, city, county, postalCode,
 * and lat/lng or latitude/longitude or location[lat,lon].
 * See: https://cdn.sameday.ro/locker-plugin/techdoc.html
 */
class SamedayAdapter implements VendorAdapterInterface
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
            throw new \RuntimeException("Sameday fetch failed: {$message}");
        }

        if ($httpCode !== 200) {
            curl_close($ch);
            throw new \RuntimeException("Sameday API returned HTTP {$httpCode}");
        }

        curl_close($ch);

        return (string) $response;
    }

    public function parse(string $raw): array
    {
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Sameday JSON parse error: " . json_last_error_msg());
        }

        $items = [];
        if (is_array($data)) {
            $items = isset($data[0]) && is_array($data[0]) ? $data : (isset($data['data']) ? $data['data'] : (isset($data['lockers']) ? $data['lockers'] : (isset($data['items']) ? $data['items'] : []));
        } elseif (is_array($data['data'] ?? null)) {
            $items = $data['data'];
        } elseif (is_array($data['lockers'] ?? null)) {
            $items = $data['lockers'];
        } elseif (is_array($data['items'] ?? null)) {
            $items = $data['items'];
        }

        if (!is_array($items)) {
            $items = [];
        }

        $locations = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['lockerId']) ? (string) $item['lockerId'] : (isset($item['id']) ? (string) $item['id'] : null);
            if ($id === null || $id === '') {
                continue;
            }

            $lat = null;
            $lon = null;
            if (isset($item['lat']) && isset($item['lng'])) {
                $lat = (float) $item['lat'];
                $lon = (float) $item['lng'];
            } elseif (isset($item['latitude']) && isset($item['longitude'])) {
                $lat = (float) $item['latitude'];
                $lon = (float) $item['longitude'];
            } elseif (isset($item['location']) && is_array($item['location']) && count($item['location']) >= 2) {
                $lat = (float) $item['location'][0];
                $lon = (float) $item['location'][1];
            } elseif (isset($item['geo']) && is_array($item['geo'])) {
                $lat = (float) ($item['geo']['lat'] ?? $item['geo']['latitude'] ?? 0);
                $lon = (float) ($item['geo']['lng'] ?? $item['geo']['lon'] ?? $item['geo']['longitude'] ?? 0);
            }

            if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping Sameday item with missing or invalid coordinates", ['id' => $id]);
                continue;
            }

            $name = trim((string) ($item['name'] ?? "easybox {$id}"));
            $addressLine = isset($item['address']) ? trim((string) $item['address']) : null;
            $city = isset($item['city']) ? trim((string) $item['city']) : null;
            $county = isset($item['county']) ? trim((string) $item['county']) : null;
            $postcode = isset($item['postalCode']) ? trim((string) $item['postalCode']) : (isset($item['postcode']) ? trim((string) $item['postcode']) : null);
            $country = isset($item['countryCode']) ? strtoupper((string) $item['countryCode']) : 'RO';

            $oohType = isset($item['oohType']) ? (int) $item['oohType'] : 0;
            $type = $oohType === 1 ? 'pickup_point' : 'locker';

            $services = [];
            if ($county !== '') {
                $services['county'] = $county;
            }

            $locations[] = [
                'vendor_location_id' => $id,
                'name' => $name,
                'type' => $type,
                'status' => 'active',
                'lat' => $lat,
                'lon' => $lon,
                'address_line' => $addressLine,
                'city' => $city,
                'postcode' => $postcode,
                'country' => $country,
                'services' => $services,
                'opening_hours' => null,
            ];
        }

        $this->logger->info("Parsed " . count($locations) . " Sameday easybox locations");

        return $locations;
    }
}
