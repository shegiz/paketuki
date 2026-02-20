# Contributing Guide

## Adding a New Vendor Adapter

### Step 1: Create Adapter Class

Create a new file in `src/Adapters/` following the naming convention: `{VendorName}Adapter.php`

Example: `src/Adapters/DHLAdapter.php`

```php
<?php
namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

class DHLAdapter implements VendorAdapterInterface
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function fetch(string $apiUrl): string
    {
        // Implement fetch logic
        // Return raw JSON string
    }

    public function parse(string $raw): array
    {
        // Parse vendor-specific format
        // Return normalized array of locations
        // Each location must have:
        // - vendor_location_id (string)
        // - name (string)
        // - type (string: locker, parcel_shop, dropoff_point, pickup_point)
        // - status (string: active, inactive, out_of_service)
        // - lat (float)
        // - lon (float)
        // - address_line, city, postcode, country (optional)
        // - services (array, optional)
        // - opening_hours (string, optional)
    }
}
```

### Step 2: Register Adapter

Edit `scripts/sync_all.php`:

```php
use Paketuki\Adapters\DHLAdapter;

// In the sync script:
$syncService->registerAdapter('dhl', new DHLAdapter($logger));
```

### Step 3: Add Vendor to Database

```sql
INSERT INTO vendors (code, name, api_url, active) 
VALUES ('dhl', 'DHL', 'https://api.dhl.com/lockers.json', TRUE);
```

### Step 4: Test

```bash
# Test adapter
php scripts/test_foxpost.php  # Create similar test for your adapter

# Run sync
php scripts/sync_all.php

# Verify in database
mysql -u root -p parcel_lockers -e "SELECT COUNT(*) FROM locations WHERE vendor_id = (SELECT id FROM vendors WHERE code = 'dhl');"
```

## Code Style

- Follow PSR-12 coding standards
- Use type hints for all parameters and return types
- Add PHPDoc comments for classes and public methods
- Keep methods focused and single-purpose
- Use meaningful variable and method names

## Testing

Before submitting changes:

1. Run verification script: `php scripts/verify_setup.php`
2. Test sync: `php scripts/sync_all.php`
3. Check logs: `tail -f logs/sync.log`
4. Test API endpoints manually or with curl
5. Verify map displays correctly

## Database Changes

If you need to modify the database schema:

1. Create a new migration file: `migrations/002_add_new_feature.sql`
2. Document the changes
3. Test migration on a copy of production data
4. Update README.md with migration instructions

## Pull Request Process

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-vendor-adapter`
3. Make your changes
4. Test thoroughly
5. Update documentation if needed
6. Submit pull request with clear description

## Questions?

Open an issue or contact the maintainers.
