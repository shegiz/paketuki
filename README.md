# Parcel Locker Map Aggregator

A web application that aggregates parcel locker / parcel automata locations from multiple vendors/providers, stores normalized data in MySQL, and displays them on an interactive map using OpenStreetMap tiles.

## Features

- 📍 Interactive map with OpenStreetMap tiles (Leaflet)
- 🔍 Filter by vendor, type, location, and search
- 📊 Marker clustering for large datasets
- 🔄 Daily automatic sync from vendor APIs
- 🗄️ Normalized data storage in MySQL
- 🚀 RESTful API endpoints
- 🎨 Clean, modern UI

## Requirements

- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx) with PHP support
- Composer (for autoloading)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd paketuki
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure database**
   - Copy `config/config.example.php` to `config/config.php`
   - Edit `config/config.php` with your database credentials

4. **Create database and run migrations**
   ```bash
   mysql -u root -p < migrations/001_create_schema.sql
   ```

5. **Set up web server**
   - Point document root to `public/` directory
   - Ensure PHP can write to `logs/` directory

6. **Run initial sync**
   ```bash
   php scripts/sync_all.php
   ```

## Configuration

### Database

Edit `config/config.php`:

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'parcel_lockers',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
],
```

### Cron Job

Set up a daily sync job (runs at 5:00 AM):

```bash
# Edit crontab
crontab -e

# Add this line (adjust path)
0 5 * * * cd /path/to/paketuki && php scripts/sync_all.php >> logs/sync.log 2>&1
```

## Project Structure

```
paketuki/
├── config/              # Configuration files
│   ├── config.example.php
│   └── config.php       # (create from example)
├── migrations/          # Database migrations
│   └── 001_create_schema.sql
├── public/              # Web entry points
│   ├── index.php        # Main map page
│   └── api/             # API endpoints
│       ├── lockers.php
│       ├── vendors.php
│       └── types.php
├── scripts/             # CLI scripts
│   └── sync_all.php     # Daily sync script
├── src/                 # PHP source code
│   ├── Database.php
│   ├── Logger.php
│   ├── LocationRepository.php
│   ├── VendorRepository.php
│   ├── SyncService.php
│   ├── VendorAdapterInterface.php
│   └── Adapters/
│       └── FoxpostAdapter.php
├── logs/                # Log files (created automatically)
├── vendor/              # Composer dependencies
├── composer.json
└── README.md
```

## Adding a New Vendor

1. **Create an adapter class** in `src/Adapters/`:

```php
<?php
namespace Paketuki\Adapters;

use Paketuki\VendorAdapterInterface;
use Paketuki\Logger;

class YourVendorAdapter implements VendorAdapterInterface
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function fetch(string $apiUrl): string
    {
        // Fetch data from vendor API
        // Return raw JSON string
    }

    public function parse(string $raw): array
    {
        // Parse vendor-specific format
        // Return normalized location array
    }
}
```

2. **Register the adapter** in `scripts/sync_all.php`:

```php
$syncService->registerAdapter('your_vendor', new YourVendorAdapter($logger));
```

3. **Add vendor to database**:

```sql
INSERT INTO vendors (code, name, api_url, active) 
VALUES ('your_vendor', 'Your Vendor Name', 'https://api.example.com/lockers.json', TRUE);
```

4. **Run sync**:

```bash
php scripts/sync_all.php
```

## API Endpoints

### GET /api/lockers

Returns locations filtered by bounding box and other criteria.

**Query Parameters:**
- `bbox` (required): `minLon,minLat,maxLon,maxLat`
- `vendor` (optional): Comma-separated vendor codes
- `type` (optional): Comma-separated types
- `status` (optional): Comma-separated statuses
- `q` (optional): Text search (name/address/city/postcode)
- `limit` (optional): Maximum results (default: 1000, max: 5000)

**Response:**
```json
{
    "items": [
        {
            "id": 1,
            "vendor_location_id": "123",
            "name": "Location Name",
            "type": "locker",
            "status": "active",
            "lat": 47.4979,
            "lon": 19.0402,
            "address_line": "Street Address",
            "city": "Budapest",
            "postcode": "1011",
            "vendor_code": "foxpost",
            "vendor_name": "Foxpost",
            ...
        }
    ],
    "meta": {
        "count": 1,
        "limit": 1000
    }
}
```

### GET /api/vendors

Returns list of vendors with location counts.

**Response:**
```json
{
    "vendors": [
        {
            "id": 1,
            "code": "foxpost",
            "name": "Foxpost",
            "count": 1500
        }
    ]
}
```

### GET /api/types

Returns list of location types with counts.

**Response:**
```json
{
    "types": [
        {
            "type": "locker",
            "count": 1200
        },
        {
            "type": "parcel_shop",
            "count": 300
        }
    ]
}
```

## Development

### Running Locally

1. Start PHP built-in server:
   ```bash
   php -S localhost:8000 -t public
   ```

2. Open browser: http://localhost:8000

### Logging

Logs are written to:
- `logs/app.log` - Application logs
- `logs/sync.log` - Sync job logs

## Security Considerations

- All database queries use prepared statements
- Input validation on all API endpoints
- CORS headers configured (adjust as needed)
- File permissions should restrict access to config files
- Consider rate limiting for production use

## Performance

- Bounding box queries use spatial/composite indexes
- Marker clustering reduces client-side rendering load
- Consider adding caching layer for frequently accessed data
- API responses are limited to prevent large payloads

## License

[Your License Here]

## Contributing

[Contributing Guidelines]
