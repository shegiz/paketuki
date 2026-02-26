# Setup Instructions

## Quick Start

### 1. Prerequisites

- PHP 7.4+ with extensions: `pdo`, `pdo_mysql`, `curl`, `json`
- MySQL 8.0+
- Composer
- Web server (Apache/Nginx) or PHP built-in server

### 2. Installation Steps

```bash
# Clone repository
git clone <repository-url>
cd paketuki

# Install Composer dependencies
composer install

# Copy configuration
cp config/config.example.php config/config.php
cp config/secrets.example.php config/secrets.php

# Edit config/config.php for host, dbname, username, etc.
# Put the database password only in config/secrets.php (this file stays on the server, do not commit)
# nano config/config.php
# nano config/secrets.php   # set 'database' => ['password' => 'your_password']

# Create database
mysql -u root -p -e "CREATE DATABASE paketuki CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
mysql -u root -p paketuki < migrations/001_create_schema.sql
mysql -u root -p paketuki < migrations/002_add_vendor_logo.sql
mysql -u root -p paketuki < migrations/003_add_gls_vendor.sql
mysql -u root -p paketuki < migrations/004_add_gls_cz_sk_ro.sql
mysql -u root -p paketuki < migrations/005_merge_gls_vendors.sql
mysql -u root -p paketuki < migrations/006_add_mpl_vendor.sql

# Create logs directory
mkdir -p logs
chmod 755 logs

# Run initial sync
php scripts/sync_all.php
```

### 3. Web Server Configuration

#### Option A: PHP Built-in Server (Development)

```bash
cd /path/to/paketuki
php -S localhost:8000 -t public
```

Then open: http://localhost:8000

#### Option B: Apache

1. Point document root to `public/` directory
2. Ensure `.htaccess` is enabled (mod_rewrite)
3. Example VirtualHost:

```apache
<VirtualHost *:80>
    ServerName parcel-lockers.local
    DocumentRoot /path/to/paketuki/public
    
    <Directory /path/to/paketuki/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Option C: Nginx

```nginx
server {
    listen 80;
    server_name parcel-lockers.local;
    root /path/to/paketuki/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Adjust PHP-FPM socket or host:port for your PHP 7.4 setup
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 4. Cron Job Setup

Set up daily sync at 5:00 AM:

```bash
# Edit crontab
crontab -e

# Add this line (adjust path to your project)
0 5 * * * cd /path/to/paketuki && php scripts/sync_all.php >> logs/sync.log 2>&1
```

Or use systemd timer (Linux):

Create `/etc/systemd/system/parcel-lockers-sync.service`:
```ini
[Unit]
Description=Parcel Lockers Daily Sync
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/path/to/paketuki
ExecStart=/usr/bin/php scripts/sync_all.php
StandardOutput=append:/path/to/paketuki/logs/sync.log
StandardError=append:/path/to/paketuki/logs/sync.log
```

Create `/etc/systemd/system/parcel-lockers-sync.timer`:
```ini
[Unit]
Description=Daily sync timer for Parcel Lockers

[Timer]
OnCalendar=05:00
Timezone=Europe/Vienna
Persistent=true

[Install]
WantedBy=timers.target
```

Enable timer:
```bash
sudo systemctl enable parcel-lockers-sync.timer
sudo systemctl start parcel-lockers-sync.timer
```

### 5. Verify Installation

1. Check database:
   ```bash
   mysql -u root -p paketuki -e "SELECT COUNT(*) FROM locations;"
   ```

2. Check logs:
   ```bash
   tail -f logs/sync.log
   tail -f logs/app.log
   ```

3. Test API endpoints:
   ```bash
   curl http://localhost:8000/api/vendors.php
   curl http://localhost:8000/api/types.php
   ```

## Troubleshooting

### Database Connection Issues

- Verify credentials in `config/config.php`
- Check MySQL is running: `sudo systemctl status mysql`
- Test connection: `mysql -u username -p -h hostname database_name`

### Permission Issues

- Ensure web server can read files: `chmod -R 755 /path/to/paketuki`
- Ensure logs directory is writable: `chmod 777 logs` (or set proper ownership)
- Check PHP error log: `tail -f /var/log/php/error.log`

### Sync Issues

- Check vendor API URLs are accessible
- Verify curl extension is enabled: `php -m | grep curl`
- Check network connectivity: `curl https://cdn.foxpost.hu/foxplus.json`
- Review sync logs: `tail -f logs/sync.log`

### Map Not Loading

- Check browser console for errors
- Verify Leaflet CDN is accessible
- Check API endpoints return valid JSON
- Verify CORS headers if accessing from different domain

## Adding a New Vendor

See `README.md` for detailed instructions on adding new vendor adapters.

## Production Deployment

1. Set `debug` to `false` in `config/config.php`
2. Set proper file permissions
3. Use environment variables for sensitive data
4. Enable HTTPS
5. Set up proper backup strategy for database
6. Configure monitoring/alerting for sync jobs
7. Consider adding rate limiting for API endpoints
8. Set up log rotation
