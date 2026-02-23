# Project Structure

## Directory Layout

```
paketuki/
├── config/                      # Configuration files
│   ├── config.example.php      # Example configuration (copy to config.php)
│   ├── config.php              # Actual configuration (gitignored)
│   ├── secrets.example.php     # Example secrets (copy to secrets.php on server)
│   └── secrets.php              # Passwords only – server-only (gitignored)
│
├── migrations/                  # Database migrations
│   └── 001_create_schema.sql    # Initial database schema
│
├── public/                      # Web-accessible files (document root)
│   ├── .htaccess               # Apache configuration
│   ├── index.php               # Main map page
│   └── api/                    # API endpoints
│       ├── lockers.php         # GET /api/lockers - location search
│       ├── vendors.php         # GET /api/vendors - vendor list
│       └── types.php           # GET /api/types - type list
│
├── scripts/                    # CLI scripts
│   ├── sync_all.php           # Daily sync job
│   ├── verify_setup.php       # Setup verification
│   └── test_foxpost.php       # Test Foxpost adapter
│
├── src/                        # PHP source code
│   ├── Database.php            # PDO database wrapper
│   ├── Logger.php              # File-based logger
│   ├── Cache.php               # File-based cache
│   ├── LocationRepository.php  # Location data access
│   ├── VendorRepository.php   # Vendor data access
│   ├── SyncService.php        # Sync orchestration
│   ├── GeocodingService.php   # Nominatim geocoding
│   ├── VendorAdapterInterface.php  # Adapter interface
│   └── Adapters/              # Vendor adapters
│       └── FoxpostAdapter.php # Foxpost implementation
│
├── logs/                       # Log files (created automatically)
│   ├── app.log                # Application logs
│   └── sync.log               # Sync job logs
│
├── cache/                      # Cache files (created automatically)
│
├── vendor/                     # Composer dependencies (gitignored)
│
├── .gitignore                  # Git ignore rules
├── composer.json              # Composer configuration
├── README.md                   # Main documentation
├── SETUP.md                    # Setup instructions
├── CONTRIBUTING.md            # Contribution guide
└── PROJECT_STRUCTURE.md       # This file
```

## Key Components

### Backend Architecture

1. **Database Layer** (`Database.php`)
   - Singleton PDO connection
   - Prepared statements for security
   - Error handling

2. **Repositories**
   - `LocationRepository`: Location CRUD and queries
   - `VendorRepository`: Vendor data access

3. **Services**
   - `SyncService`: Orchestrates vendor data synchronization
   - `GeocodingService`: Address geocoding via Nominatim

4. **Adapters**
   - `VendorAdapterInterface`: Contract for vendor adapters
   - Each vendor has its own adapter implementing the interface
   - Normalizes vendor-specific data to common format

5. **Utilities**
   - `Logger`: File-based logging
   - `Cache`: File-based caching

### Frontend Architecture

1. **Main Page** (`public/index.php`)
   - Single-page application
   - Leaflet map with OpenStreetMap tiles
   - Marker clustering for performance
   - Sidebar with filters

2. **API Endpoints**
   - REST-like endpoints
   - JSON responses
   - Bounding box queries for map integration

### Data Flow

1. **Sync Process** (Daily cron job)
   ```
   SyncService → VendorAdapter → Fetch API → Parse → Normalize → Upsert DB
   ```

2. **Map Display**
   ```
   User Interaction → AJAX Request → API Endpoint → LocationRepository → MySQL → JSON → Map Markers
   ```

## Database Schema

### Tables

1. **vendors**
   - Vendor/provider information
   - API URLs and configuration

2. **locations**
   - Normalized location data
   - Geo-indexed for fast queries
   - Vendor-specific IDs preserved

3. **vendor_payload_snapshots**
   - Raw API responses for debugging
   - Hash-based deduplication

4. **sync_runs**
   - Sync job execution logs
   - Metrics (created/updated/inactivated)

## Security Considerations

- Prepared statements prevent SQL injection
- Input validation on all API endpoints
- File permissions restrict config access
- CORS headers configured
- Security headers in .htaccess

## Performance Optimizations

- Composite index on (lat, lon) for bounding box queries
- Marker clustering reduces client-side rendering
- API result limits prevent large payloads
- Caching layer for geocoding and API responses
- Efficient bbox queries using indexed columns

## Extension Points

1. **Adding Vendors**: Create adapter class, register in sync script
2. **Custom Filters**: Extend LocationRepository::findByBboxAndFilters
3. **Geocoding**: Replace GeocodingService with different provider
4. **Caching**: Replace Cache class with Redis/Memcached
5. **Logging**: Replace Logger with Monolog or similar
