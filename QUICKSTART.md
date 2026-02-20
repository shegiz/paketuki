# Quick Start Guide

## 5-Minute Setup

### 1. Install Dependencies
```bash
composer install
```

### 2. Configure Database
```bash
cp config/config.example.php config/config.php
# Edit config/config.php with your database credentials
```

### 3. Create Database
```bash
mysql -u root -p -e "CREATE DATABASE parcel_lockers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p parcel_lockers < migrations/001_create_schema.sql
```

### 4. Run Initial Sync
```bash
php scripts/sync_all.php
```

### 5. Start Web Server
```bash
php -S localhost:8000 -t public
```

### 6. Open Browser
```
http://localhost:8000
```

## Common Commands

```bash
# Verify setup
php scripts/verify_setup.php

# Test Foxpost adapter
php scripts/test_foxpost.php

# Run sync manually
php scripts/sync_all.php

# Check logs
tail -f logs/sync.log
tail -f logs/app.log

# Database queries
mysql -u root -p parcel_lockers -e "SELECT COUNT(*) FROM locations;"
mysql -u root -p parcel_lockers -e "SELECT * FROM vendors;"
```

## Troubleshooting

**Database connection fails:**
- Check credentials in `config/config.php`
- Verify MySQL is running: `sudo systemctl status mysql`

**No locations on map:**
- Run sync: `php scripts/sync_all.php`
- Check sync logs: `tail -f logs/sync.log`
- Verify vendors exist: `SELECT * FROM vendors;`

**Map not loading:**
- Check browser console for errors
- Verify API endpoints work: `curl http://localhost:8000/api/vendors.php`
- Check PHP error log

**Sync fails:**
- Check internet connectivity
- Verify vendor API URLs are accessible
- Check curl extension: `php -m | grep curl`

## Next Steps

1. Set up cron job for daily sync (see SETUP.md)
2. Configure web server (Apache/Nginx) for production
3. Add more vendor adapters (see CONTRIBUTING.md)
4. Customize map appearance and filters
5. Set up monitoring and alerts
