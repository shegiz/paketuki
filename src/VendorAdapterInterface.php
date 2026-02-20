<?php

namespace Paketuki;

/**
 * Interface for vendor adapters
 * Each vendor adapter normalizes vendor-specific data to a common format
 */
interface VendorAdapterInterface
{
    /**
     * Fetch raw data from vendor API
     * 
     * @param string $apiUrl Vendor API URL
     * @return string Raw JSON response
     * @throws \Exception On fetch failure
     */
    public function fetch(string $apiUrl): string;

    /**
     * Parse raw vendor data into normalized location DTOs
     * 
     * @param string $raw Raw JSON string
     * @return array Array of normalized location arrays with keys:
     *   - vendor_location_id (string)
     *   - name (string)
     *   - type (string: locker, parcel_shop, dropoff_point, pickup_point)
     *   - status (string: active, inactive, out_of_service)
     *   - lat (float)
     *   - lon (float)
     *   - address_line (string|null)
     *   - city (string|null)
     *   - postcode (string|null)
     *   - country (string|null)
     *   - services (array)
     *   - opening_hours (string|null)
     */
    public function parse(string $raw): array;
}
