<?php

namespace Paketuki;

/**
 * Geocoding service using Nominatim (OpenStreetMap)
 * Note: Respects usage policy - use caching and reasonable rate limits
 */
class GeocodingService
{
    private Logger $logger;
    private Cache $cache;
    private string $nominatimUrl;
    private string $userAgent;
    private int $cacheTtl;

    public function __construct(Logger $logger, Cache $cache, array $config)
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->nominatimUrl = $config['nominatim_url'] ?? 'https://nominatim.openstreetmap.org/search';
        $this->userAgent = $config['user_agent'] ?? 'ParcelLockerAggregator/1.0';
        $this->cacheTtl = $config['cache_ttl'] ?? 86400; // 24 hours
    }

    /**
     * Geocode an address to coordinates
     * 
     * @param string $query Address, city, postcode, etc.
     * @return array|null ['lat' => float, 'lon' => float] or null if not found
     */
    public function geocode(string $query): ?array
    {
        $query = trim($query);
        
        if (empty($query)) {
            return null;
        }
        
        // Check cache first
        $cacheKey = 'geocode_' . md5($query);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $result = json_decode($cached, true);
            if ($result && isset($result['lat'], $result['lon'])) {
                return $result;
            }
        }
        
        // Geocode via Nominatim
        $params = [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
        ];
        
        $url = $this->nominatimUrl . '?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            $this->logger->warning("Geocoding failed for query: {$query}", [
                'http_code' => $httpCode,
            ]);
            return null;
        }
        
        $results = json_decode($response, true);
        
        if (!is_array($results) || empty($results)) {
            return null;
        }
        
        $first = $results[0];
        
        if (!isset($first['lat'], $first['lon'])) {
            return null;
        }
        
        $result = [
            'lat' => (float) $first['lat'],
            'lon' => (float) $first['lon'],
        ];
        
        // Cache result
        $this->cache->set($cacheKey, json_encode($result), $this->cacheTtl);
        
        return $result;
    }

    /**
     * Reverse geocode coordinates to address
     * 
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @return array|null Address components or null
     */
    public function reverseGeocode(float $lat, float $lon): ?array
    {
        $cacheKey = 'reverse_' . md5("{$lat},{$lon}");
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return json_decode($cached, true);
        }
        
        $params = [
            'lat' => $lat,
            'lon' => $lon,
            'format' => 'json',
            'addressdetails' => 1,
        ];
        
        $url = $this->nominatimUrl . '?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return null;
        }
        
        $result = json_decode($response, true);
        
        if ($result) {
            $this->cache->set($cacheKey, json_encode($result), $this->cacheTtl);
        }
        
        return $result;
    }
}
