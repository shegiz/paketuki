<?php
/**
 * Main entry point for web application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Paketuki\Database;
use Paketuki\Logger;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize
date_default_timezone_set($config['app']['timezone']);
Database::init($config['database']);
$logger = new Logger(__DIR__ . '/../logs/app.log', $config['app']['debug'] ?? false);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcel Locker Map Aggregator</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Leaflet MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        #app {
            display: flex;
            height: 100vh;
        }
        
        #sidebar {
            width: 320px;
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1);
        }
        
        #map {
            flex: 1;
            height: 100vh;
        }
        
        h1 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #212529;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
            font-size: 14px;
        }
        
        .filter-group input[type="text"],
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-group input[type="text"]:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .checkbox-group {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px;
            background: white;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 6px 0;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .checkbox-item label {
            font-weight: normal;
            margin: 0;
            cursor: pointer;
            font-size: 14px;
        }
        
        .status-info {
            margin-top: 20px;
            padding: 12px;
            background: #e7f3ff;
            border-radius: 4px;
            font-size: 12px;
            color: #004085;
        }
        
        .status-info strong {
            display: block;
            margin-bottom: 4px;
        }
        
        .btn {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .popup-content {
            min-width: 200px;
        }
        
        .popup-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
            color: #212529;
        }
        
        .popup-detail {
            margin: 4px 0;
            font-size: 14px;
            color: #495057;
        }
        
        .popup-detail strong {
            color: #212529;
        }
    </style>
</head>
<body>
    <div id="app">
        <div id="sidebar">
            <h1>📍 Parcel Lockers</h1>
            
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" id="search" placeholder="City, postcode, address...">
            </div>
            
            <div class="filter-group">
                <label>Vendors</label>
                <div id="vendor-filters" class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="vendor-all" checked>
                        <label for="vendor-all">All vendors</label>
                    </div>
                </div>
            </div>
            
            <div class="filter-group">
                <label>Types</label>
                <div id="type-filters" class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="type-all" checked>
                        <label for="type-all">All types</label>
                    </div>
                </div>
            </div>
            
            <button class="btn" onclick="refreshMap()">Refresh Map</button>
            
            <div class="status-info">
                <strong>Status</strong>
                <div id="status-text">Loading...</div>
            </div>
        </div>
        
        <div id="map"></div>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Leaflet MarkerCluster JS -->
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    
    <script>
        // Initialize map (centered on Hungary)
        const map = L.map('map').setView([47.4979, 19.0402], 7);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
        
        // Marker cluster group
        let markerCluster = L.markerClusterGroup({
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
        });
        map.addLayer(markerCluster);
        
        let markers = [];
        let vendors = [];
        let types = [];
        
        // Load initial data
        loadVendors();
        loadTypes();
        refreshMap();
        
        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Search input handler
        document.getElementById('search').addEventListener('input', debounce(() => {
            refreshMap();
        }, 500));
        
        // Map move end handler
        map.on('moveend', debounce(() => {
            refreshMap();
        }, 300));
        
        // Load vendors
        async function loadVendors() {
            try {
                const response = await fetch('/api/vendors.php');
                const data = await response.json();
                vendors = data.vendors || [];
                
                const container = document.getElementById('vendor-filters');
                vendors.forEach(vendor => {
                    const div = document.createElement('div');
                    div.className = 'checkbox-item';
                    div.innerHTML = `
                        <input type="checkbox" id="vendor-${vendor.code}" value="${vendor.code}" checked>
                        <label for="vendor-${vendor.code}">${vendor.name} (${vendor.count})</label>
                    `;
                    container.appendChild(div);
                });
                
                // Handle "all" checkbox
                document.getElementById('vendor-all').addEventListener('change', function() {
                    const checked = this.checked;
                    vendors.forEach(vendor => {
                        document.getElementById(`vendor-${vendor.code}`).checked = checked;
                    });
                    refreshMap();
                });
                
                // Handle individual checkboxes
                vendors.forEach(vendor => {
                    document.getElementById(`vendor-${vendor.code}`).addEventListener('change', function() {
                        const allChecked = vendors.every(v => document.getElementById(`vendor-${v.code}`).checked);
                        document.getElementById('vendor-all').checked = allChecked;
                        refreshMap();
                    });
                });
            } catch (error) {
                console.error('Failed to load vendors:', error);
            }
        }
        
        // Load types
        async function loadTypes() {
            try {
                const response = await fetch('/api/types.php');
                const data = await response.json();
                types = data.types || [];
                
                const container = document.getElementById('type-filters');
                types.forEach(type => {
                    const div = document.createElement('div');
                    div.className = 'checkbox-item';
                    div.innerHTML = `
                        <input type="checkbox" id="type-${type.type}" value="${type.type}" checked>
                        <label for="type-${type.type}">${type.type} (${type.count})</label>
                    `;
                    container.appendChild(div);
                });
                
                // Handle "all" checkbox
                document.getElementById('type-all').addEventListener('change', function() {
                    const checked = this.checked;
                    types.forEach(type => {
                        document.getElementById(`type-${type.type}`).checked = checked;
                    });
                    refreshMap();
                });
                
                // Handle individual checkboxes
                types.forEach(type => {
                    document.getElementById(`type-${type.type}`).addEventListener('change', function() {
                        const allChecked = types.every(t => document.getElementById(`type-${t.type}`).checked);
                        document.getElementById('type-all').checked = allChecked;
                        refreshMap();
                    });
                });
            } catch (error) {
                console.error('Failed to load types:', error);
            }
        }
        
        // Refresh map markers
        async function refreshMap() {
            const bounds = map.getBounds();
            const bbox = [
                bounds.getWest(),
                bounds.getSouth(),
                bounds.getEast(),
                bounds.getNorth()
            ].join(',');
            
            // Get selected vendors
            const selectedVendors = vendors
                .filter(v => document.getElementById(`vendor-${v.code}`).checked)
                .map(v => v.code);
            
            // Get selected types
            const selectedTypes = types
                .filter(t => document.getElementById(`type-${t.type}`).checked)
                .map(t => t.type);
            
            // Get search query
            const searchQuery = document.getElementById('search').value.trim();
            
            // Build query params
            const params = new URLSearchParams({
                bbox: bbox,
            });
            
            if (selectedVendors.length > 0 && selectedVendors.length < vendors.length) {
                params.append('vendor', selectedVendors.join(','));
            }
            
            if (selectedTypes.length > 0 && selectedTypes.length < types.length) {
                params.append('type', selectedTypes.join(','));
            }
            
            if (searchQuery) {
                params.append('q', searchQuery);
            }
            
            try {
                document.getElementById('status-text').textContent = 'Loading...';
                
                const response = await fetch(`/api/lockers.php?${params}`);
                const data = await response.json();
                
                // Clear existing markers
                markerCluster.clearLayers();
                markers = [];
                
                // Add new markers
                if (data.items && Array.isArray(data.items)) {
                    data.items.forEach(item => {
                        const marker = L.marker([item.lat, item.lon]);
                        
                        const popupContent = `
                            <div class="popup-content">
                                <div class="popup-title">${escapeHtml(item.name)}</div>
                                <div class="popup-detail"><strong>Vendor:</strong> ${escapeHtml(item.vendor_name)}</div>
                                <div class="popup-detail"><strong>Type:</strong> ${escapeHtml(item.type)}</div>
                                <div class="popup-detail"><strong>Status:</strong> ${escapeHtml(item.status)}</div>
                                ${item.address_line ? `<div class="popup-detail"><strong>Address:</strong> ${escapeHtml(item.address_line)}</div>` : ''}
                                ${item.city ? `<div class="popup-detail"><strong>City:</strong> ${escapeHtml(item.city)}</div>` : ''}
                                ${item.postcode ? `<div class="popup-detail"><strong>Postcode:</strong> ${escapeHtml(item.postcode)}</div>` : ''}
                                ${item.last_updated_at ? `<div class="popup-detail"><strong>Updated:</strong> ${escapeHtml(item.last_updated_at)}</div>` : ''}
                            </div>
                        `;
                        
                        marker.bindPopup(popupContent);
                        markerCluster.addLayer(marker);
                        markers.push(marker);
                    });
                    
                    document.getElementById('status-text').textContent = `Showing ${data.items.length} locations`;
                } else {
                    document.getElementById('status-text').textContent = 'No locations found';
                }
            } catch (error) {
                console.error('Failed to load lockers:', error);
                document.getElementById('status-text').textContent = 'Error loading data';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
