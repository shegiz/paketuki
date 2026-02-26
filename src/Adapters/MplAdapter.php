<?php

namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

/**
 * Adapter for Magyar Posta (MPL) parcel terminals
 * Feed: http://httpmegosztas.posta.hu/PartnerExtra/Out/PostInfo_CS.xml
 * XML with post elements: ID, name, city, street, gpsData (WGSLat, WGSLon), workingHours, zipCode
 */
class MplAdapter implements VendorAdapterInterface
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
            throw new \RuntimeException("MPL fetch failed: {$message}");
        }

        if ($httpCode !== 200) {
            curl_close($ch);
            throw new \RuntimeException("MPL API returned HTTP {$httpCode}");
        }

        curl_close($ch);

        return (string) $response;
    }

    public function parse(string $raw): array
    {
        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            throw new \RuntimeException("MPL XML parse error");
        }

        $locations = [];
        $posts = $xml->xpath('//post');
        if ($posts === false) {
            $posts = [];
        }

        foreach ($posts as $post) {
            $isPostPoint = (string) ($post['isPostPoint'] ?? '0');
            if ($isPostPoint !== '1') {
                continue;
            }

            $id = trim((string) ($post->ID ?? ''));
            if ($id === '') {
                continue;
            }

            $latStr = trim((string) ($post->gpsData->WGSLat ?? ''));
            $lonStr = trim((string) ($post->gpsData->WGSLon ?? ''));
            if ($latStr === '' || $lonStr === '') {
                $this->logger->warning("Skipping MPL item with missing coordinates", ['id' => $id]);
                continue;
            }

            $lat = (float) str_replace(',', '.', $latStr);
            $lon = (float) str_replace(',', '.', $lonStr);
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $this->logger->warning("Skipping MPL item with invalid coordinates", ['id' => $id]);
                continue;
            }

            $name = trim((string) ($post->name ?? "MPL {$id}"));
            $city = trim((string) ($post->city ?? ''));
            $zipCode = trim((string) ($post['zipCode'] ?? $post->zipCode ?? ''));
            $streetName = trim((string) ($post->street->name ?? ''));
            $streetType = trim((string) ($post->street->type ?? ''));
            $houseNumber = trim((string) ($post->street->houseNumber ?? ''));
            $addressParts = array_filter([$streetName, $streetType, $houseNumber]);
            $addressLine = implode(' ', $addressParts);
            if ($addressLine !== '' && $city !== '') {
                $addressLine = $addressLine . ', ' . $zipCode . ' ' . $city;
            } elseif ($addressLine === '' && $city !== '') {
                $addressLine = $zipCode . ' ' . $city;
            }

            $workingHours = null;
            if (isset($post->workingHours->days)) {
                $hoursArr = [];
                foreach ($post->workingHours->days as $days) {
                    $day = trim((string) ($days->day ?? ''));
                    $from = trim((string) ($days->From1 ?? $days->from ?? ''));
                    $to = trim((string) ($days->To1 ?? $days->to ?? ''));
                    if ($day !== '') {
                        $hoursArr[$day] = $from !== '' || $to !== '' ? $from . '-' . $to : '';
                    }
                }
                $workingHours = $hoursArr !== [] ? json_encode($hoursArr) : null;
            }

            $locations[] = [
                'vendor_location_id' => $id,
                'name' => $name,
                'type' => 'locker',
                'status' => 'active',
                'lat' => $lat,
                'lon' => $lon,
                'address_line' => $addressLine !== '' ? $addressLine : null,
                'city' => $city !== '' ? $city : null,
                'postcode' => $zipCode !== '' ? $zipCode : null,
                'country' => 'HU',
                'services' => ['ServicePointType' => (string) ($post->ServicePointType ?? 'CS')],
                'opening_hours' => $workingHours,
            ];
        }

        $this->logger->info("Parsed " . count($locations) . " MPL locations");

        return $locations;
    }
}
