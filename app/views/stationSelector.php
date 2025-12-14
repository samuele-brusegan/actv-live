<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Seleziona Fermata - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>

        <link rel="stylesheet" href="/css/stopList.css">
        <link rel="stylesheet" href="/css/stationSelector.css">
    </head>
    <body>

        <!-- Header -->
        <div class="header-green">
            <div style="height: 20px;">
                <a href="/route-finder" class="back-button">&larr;</a>
            </div>
            <div class="header-title">
                <?php 
                $type = $_GET['type'] ?? 'origin';
                echo $type === 'origin' ? 'Dove vuoi<br>andare?' : 'Seleziona<br>destinazione';
                ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Search Bar -->
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="🔍 Cerca fermata..." oninput="filterStops()">
            </div>

            <!-- Favorites Section -->
            <div id="favorites-section">
                <div class="section-title">fermate preferite</div>
                <div id="favorites-list"></div>
            </div>

            <!-- Recent Section -->
            <div id="recent-section">
                <div class="section-title">recenti</div>
                <div id="recent-list"></div>
            </div>

            <!-- All Stops Section -->
            <div id="all-stops-section" style="display: none;">
                <div class="section-title">tutte le fermate</div>
                <div id="all-stops-list" style="margin-bottom: 10px;"></div>
            </div>

        </div>

        <!-- Action Buttons -->
        <div class="action-buttons" style="position: fixed; bottom: 0; left: 0; right: 0; background: #F5F5F5; border-top: 1px solid #e0e0e0; z-index: 2; padding: 10px;">
            <button class="mb-1 btn-secondary" onclick="cancelSelection()">annulla</button>
            <button class="mt-1 btn-primary" onclick="confirmSelection()">fatto</button>
        </div>

        <!-- StopCard Component -->
        <script src="/components/StopCard.js"></script>
        
        <script>
            let selectedStop = null;
            const selectionType = '<?php echo $_GET['type'] ?? 'origin'; ?>';
            let allStops = [];
            let addressResults = [];

            // Load stops from API
            async function loadStops() {
                try {
                    const response = await fetch('/api/stops');
                    if (!response.ok) throw new Error('Network error');
                    const data = await response.json();
                    
                    // GTFS stops.json format is object {stop_id: {id, name, lat, lon}}
                    // Convert to array and merge stops with same name
                    const stopsMap = new Map();

                    //console.log(data);

                    Object.values(data).forEach(stop => {
                        
                        // Normalize name: lowercase, trim, remove extra spaces
                        const normalizedName = stop.stop_name.trim().toLowerCase().replace(/\s+/g, ' ');
                        const cleanName = stop.stop_name.trim(); // Keep original case for display
                        


                        if (stopsMap.has(normalizedName)) {
                            // Add this stop ID to existing entry
                            stopsMap.get(normalizedName).ids.push(stop.stop_id);
                        } else {
                            // Create new entry
                            stopsMap.set(normalizedName, {
                                ids: [stop.stop_id],
                                name: cleanName,
                                lines: [],
                                lat: stop.stop_lat,
                                lng: stop.stop_lon
                            });
                        }
                    });
                    
                    // Convert map to array, using first ID as primary
                    allStops = Array.from(stopsMap.values()).map(stop => ({
                        id: stop.ids[0], // Primary ID
                        ids: stop.ids,   // All IDs for this stop
                        name: stop.name,
                        lines: stop.lines,
                        lat: stop.lat,
                        lng: stop.lng
                    }));
                    
                    // Debug: Log merged stops
                    console.log(`Total stops after merge: ${allStops.length}`);

                    renderFavorites();
                    renderRecent();
                } catch (error) {
                    console.error('Error loading stops:', error);
                }
            }

            function renderFavorites() {
                const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
                const container = document.getElementById('favorites-list');
                
                if (favorites.length === 0) {
                    container.innerHTML = '<div class="no-results">Nessuna fermata preferita</div>';
                    return;
                }

                container.innerHTML = favorites.map(stop => createStopCard(stop, true)).join('');
            }

            function renderRecent() {
                const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');
                const container = document.getElementById('recent-list');
                
                if (recent.length === 0 || recent[0] == null) {
                    container.innerHTML = '<div class="no-results">Nessuna fermata recente</div>';
                    return;
                }
                console.log(recent);
                container.innerHTML = recent.slice(0, Math.min(recent.length, 5)).map(stop => createStopCard(stop, false)).join('');
            }

            function createStopCard(stop, isFavorite) {
                if (stop == undefined || stop == null) {
                    console.warn('createStopCard: Stop is undefined', stop, isFavorite);
                    return '';
                }
                // Check if it's an address (custom type we just added)
                if (stop.type === 'address') {
                    const stopName = stop.parsedName;
                    return `
                    <div class="stop-card" onclick='selectAddress(${JSON.stringify(stop).replace(/'/g, "&#39;")}, this)'>
                        <div class="stop-icon" style="background: #E0E0E0; color: #555;">📍</div>
                        <div class="stop-content">
                            <div class="stop-name">${stopName}</div>
                            <div class="stop-lines" style="color: #666;">Indirizzo</div>
                        </div>
                    </div>`;
                }
                
                if ( stop.name.includes("web") ){
                    stop.name = stop.name.replace("web-aut", "");
                    stop.name = stop.name.replace("web", "");
                }

                // If it's a stop
                return StopCard.create(stop, {
                    isFavorite: isFavorite,
                    onClick: selectStop,
                    showIds: true
                });
            }

            function selectStop(stop, event) {
                selectedStop = stop;
                selectedStop.type = 'stop'; // Ensure type is set
                
                // Visual feedback
                updateSelectionVisuals(event ? event.currentTarget : null);
                
                // Add to recent
                addToRecent(stop);
            }

            function selectAddress(address, element) {
                selectedStop = address;
                selectedStop.type = 'address';
                
                updateSelectionVisuals(element);
            }

            function updateSelectionVisuals(selectedElement) {
                document.querySelectorAll('.stop-card').forEach(card => {
                    card.style.background = '#FFFFFF';
                });
                
                // Handle event if passed directly or from window.event
                const e = event || window.event;
                if (e && e.currentTarget) {
                    e.currentTarget.style.background = '#E8F5E9';
                }

                // Add to recent
                addToRecent(stop);
            }

            function addToRecent(stop) {
                let recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');
                
                // Remove duplicates
                if (!(recent.length === 0 || recent[0] === null)) {
                    recent = recent.filter(s => {
                        if (stop.type === 'address') return s.name !== stop.name;
                        return s.stop_id !== stop.stop_id;
                    });
                }
                if (recent.length === 0 && stop != null) recent = [stop];
                if (stop != null && recent.length > 1) recent.unshift(stop);
                recent = recent.slice(0, 10);
                localStorage.setItem('recent_stops', JSON.stringify(recent));
            }

            function confirmSelection() {
                if (!selectedStop) {
                    alert('Seleziona una fermata o un indirizzo');
                    return;
                }

                localStorage.setItem(`route_${selectionType}`, JSON.stringify(selectedStop));
                window.location.href = '/route-finder';
            }

            function cancelSelection() {
                window.location.href = '/route-finder';
            }

            let debounceTimer;
            function filterStops() {
                const query = document.getElementById('search-input').value.toLowerCase();
                
                if (query.length === 0) {
                    document.getElementById('favorites-section').style.display = 'block';
                    document.getElementById('recent-section').style.display = 'block';
                    document.getElementById('all-stops-section').style.display = 'none';
                    return;
                }

                // Hide favorites and recent, show all stops filtered
                document.getElementById('favorites-section').style.display = 'none';
                document.getElementById('recent-section').style.display = 'none';
                document.getElementById('all-stops-section').style.display = 'block';

                // 1. Filter local stops
                const filteredStops = allStops.filter(stop => 
                    stop.name.toLowerCase().includes(query)
                );

                // 2. Filter Favorites & Recents (Suggestions)
                const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
                const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');
                
                let arr = [...favorites, ...recent];
                let suggestions = [];
                if (!(arr.length === 0 || arr[0] === null)) {
                    suggestions = arr.filter(item => 
                        item != null && (
                        (item.name && item.name.toLowerCase().includes(query)) ||
                        (item.type === 'address' && item.name.toLowerCase().includes(query)))
                    );
                }
                
                // Deduplicate suggestions based on ID or Name
                const uniqueSuggestions = [];
                const seenIds = new Set();
                suggestions.forEach(item => {
                    const uniqueKey = item.type === 'address' ? item.name : item.id;
                    if (!seenIds.has(uniqueKey)) {
                        seenIds.add(uniqueKey);
                        uniqueSuggestions.push(item);
                    }
                });

                // 3. Search addresses (Debounced)
                if (query.length > 2) {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => searchAddresses(query), 500);
                } else {
                    addressResults = []; // clear old address results if query too short
                    renderResults(filteredStops, [], uniqueSuggestions);
                }
                
                // Initial render with just stops & suggestions while waiting for addresses
                renderResults(filteredStops, addressResults, uniqueSuggestions); 
            }

            async function searchAddresses(query) {
                try {
                    // Bounds for Venice area roughly (optional, but good for relevance)
                    const viewbox = '12.1,45.3,12.6,45.5'; // lon1,lat1,lon2,lat2
                    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&viewbox=${viewbox}&bounded=1&limit=5`;
                    
                    const response = await fetch(url);
                    const data = await response.json();
                    
                    addressResults = data.map(item => {
                        let dNameSplitted = item.display_name.split(',');
                        let parsedName = dNameSplitted.slice(0, -3).join(',');
                        let address = {
                            type: 'address',
                            id: 'addr_' + item.place_id,
                            name: dNameSplitted[0], // Take first part
                            fullName: item.display_name,
                            parsedName: parsedName,
                            lat: item.lat,
                            lng: item.lon,
                            lines: [] // dummy
                        };
                        return address;
                    });
                    
                    // Re-render
                    // Re-calculate suggestions/stops to ensure consistency with current query
                    const currentQuery = document.getElementById('search-input').value.toLowerCase();
                    const filteredStops = allStops.filter(stop => 
                        stop.name.toLowerCase().includes(currentQuery)
                    );
                    
                    const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
                    const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');

                    let arr = [...favorites, ...recent];
                    let suggestions = [];
                    if (!(arr.length === 0 || arr[0] === null)) {
                        suggestions = arr.filter(item => 
                            (item.name && item.name.toLowerCase().includes(currentQuery)) ||
                            (item.type === 'address' && item.name.toLowerCase().includes(currentQuery))
                        );
                    }

                    const uniqueSuggestions = [];
                    const seenIds = new Set();
                    suggestions.forEach(item => {
                        const uniqueKey = item.type === 'address' ? item.name : item.id;
                        if (!seenIds.has(uniqueKey)) {
                            seenIds.add(uniqueKey);
                            uniqueSuggestions.push(item);
                        }
                    });
                    
                    renderResults(filteredStops, addressResults, uniqueSuggestions);

                } catch (e) {
                    console.error("Address search failed", e);
                }
            }

            function renderResults(stops, addresses, suggestions = []) {
                const container = document.getElementById('all-stops-list');
                let html = '';

                // Render Suggestions first (History/Favorites)
                if (suggestions.length > 0) {
                    html += '<div class="subsection-title" style="font-size:12px; color:#888; margin: 10px 0;">CRONOLOGIA</div>';
                    html += suggestions.map(item => createStopCard(item, false)).join('');
                }

                // Render Address API Results
                if (addresses.length > 0) {
                    html += '<div class="subsection-title" style="font-size:12px; color:#888; margin: 10px 0;">SUGGERIMENTI INDIRIZZO</div>';
                    html += addresses.map(addr => createStopCard(addr, false)).join('');
                }

                // Render Stops
                if (stops.length > 0) {
                    html += '<div class="subsection-title" style="font-size:12px; color:#888; margin: 10px 0;">FERMATE</div>';
                    html += stops.slice(0, 20).map(stop => createStopCard(stop, false)).join('');
                }

                if (stops.length === 0 && addresses.length === 0 && suggestions.length === 0) {
                    html = '<div class="no-results">Nessun risultato trovato</div>';
                }

                container.innerHTML = html;
            }

            // Initialize
            window.addEventListener('DOMContentLoaded', () => {
                loadStops();
            });
        </script>

    </body>
</html>
