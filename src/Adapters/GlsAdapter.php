<?php

namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

/**
 * Adapter for GLS Hungary delivery points API
 * Feed: https://map.gls-hungary.com/data/deliveryPoints/hu.json
 * Returns parcel-shop and parcel-locker locations.
 */
class GlsAdapter implements VendorAdapterInterface
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
            throw new \RuntimeException("GLS fetch failed: {$message}");
        }

        if ($httpCode !== 200) {
            curl_close($ch);
            throw new \RuntimeException("GLS API returned HTTP {$httpCode}");
        }

        curl_close($ch);

        return (string) $response;
    }

    public function parse(string $raw): array
    {
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("GLS JSON parse error: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \RuntimeException("GLS data is not an array");
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        $locations = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (string) $item['id'] : null;
            $locationArr = isset($item['location']) && is_array($item['location']) ? $item['location'] : null;

            if ($id === null || $locationArr === null || count($locationArr) < 2) {
                $this->logger->warning("Skipping GLS item with missing id or location", [
                    'id' => $id,
                    'has_location' => $locationArr !== null,
                ]);
                continue;
            }

            $lat = (float) $locationArr[0];
            $lon = (float) $locationArr[1];

            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping GLS item with invalid coordinates", [
                    'id' => $id,
                    'lat' => $lat,
                    'lon' => $lon,
                ]);
                continue;
            }

            $contact = isset($item['contact']) && is_array($item['contact']) ? $item['contact'] : [];
            $addressLine = isset($contact['address']) ? $contact['address'] : null;
            $city = isset($contact['city']) ? $contact['city'] : null;
            $postcode = isset($contact['postalCode']) ? $contact['postalCode'] : null;
            $country = isset($contact['countryCode']) ? strtoupper((string) $contact['countryCode']) : 'HU';

            $type = $this->normalizeType(isset($item['type']) ? $item['type'] : 'parcel-shop');
            $status = 'active';

            $services = [];
            if (isset($item['features']) && is_array($item['features'])) {
                $services['features'] = $item['features'];
            }
            if (isset($item['pickupTime'])) {
                $services['pickup_time'] = $item['pickupTime'];
            }
            if (!empty($item['hasWheelchairAccess'])) {
                $services['wheelchair_accessible'] = true;
            }
            if (isset($item['lockerSaturation'])) {
                $services['locker_saturation'] = $item['lockerSaturation'];
            }

            $openingHours = null;
            if (isset($item['hours']) && is_array($item['hours'])) {
                $openingHours = json_encode($item['hours']);
            }

            $locations[] = [
                'vendor_location_id' => $id,
                'name' => isset($item['name']) ? $item['name'] : "GLS {$id}",
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

        $this->logger->info("Parsed " . count($locations) . " GLS locations");

        return $locations;
    }

    /**
     * Normalize GLS type to standard taxonomy
     */
    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'parcel-locker') {
            return 'locker';
        }
        if ($type === 'parcel-shop' || $type === 'parcel_shop') {
            return 'parcel_shop';
        }
        return 'locker';
    }
}
