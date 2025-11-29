<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleziona Fermata - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>

    <style>
        /* Page specific overrides - matching stopList.php style */
        .stop-ids-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 60px;
            align-items: center;
        }
        
        .stop-id-badge {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            min-width: 50px;
        }
        
        .stop-card-content {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .stop-card-action {
            display: flex;
            align-items: center;
        }
    </style>
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
            <div id="all-stops-list"></div>
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

        // Load stops from API
        async function loadStops() {
            try {
                const response = await fetch('/api/stops');
                if (!response.ok) throw new Error('Network error');
                const data = await response.json();
                
                // GTFS stops.json format is object {stop_id: {id, name, lat, lon}}
                // Convert to array and merge stops with same name
                const stopsMap = new Map();
                Object.values(data).forEach(stop => {
                    // Normalize name: lowercase, trim, remove extra spaces
                    const normalizedName = stop.name.trim().toLowerCase().replace(/\s+/g, ' ');
                    const cleanName = stop.name.trim(); // Keep original case for display
                    
                    if (stopsMap.has(normalizedName)) {
                        // Add this stop ID to existing entry
                        stopsMap.get(normalizedName).ids.push(stop.id);
                    } else {
                        // Create new entry
                        stopsMap.set(normalizedName, {
                            ids: [stop.id],
                            name: cleanName,
                            lines: [],
                            lat: stop.lat,
                            lng: stop.lon
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
                    lng: stop.lon
                }));
                
                // Debug: Log merged stops
                console.log(`Total stops after merge: ${allStops.length}`);
                const mergedStops = allStops.filter(s => s.ids.length > 1);
                console.log(`Stops with multiple IDs: ${mergedStops.length}`);
                if (mergedStops.length > 0) {
                    console.log('First 5 merged stops:', mergedStops.slice(0, 5));
                }

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
                //container.innerHTML = '<div class="no-results">Nessuna fermata preferita</div>';
                return;
            }

            container.innerHTML = favorites.map(stop => createStopCard(stop, true)).join('');
        }

        function renderRecent() {
            const recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');
            const container = document.getElementById('recent-list');
            
            if (recent.length === 0) {
                //container.innerHTML = '<div class="no-results">Nessuna fermata recente</div>';
                return;
            }

            container.innerHTML = recent.slice(0, 5).map(stop => createStopCard(stop, false)).join('');
        }

        function createStopCard(stop, isFavorite) {
            return StopCard.create(stop, {
                isFavorite: isFavorite,
                onClick: selectStop,
                showIds: true
            });
        }

        function selectStop(stop) {
            selectedStop = stop;
            
            // Visual feedback
            document.querySelectorAll('.stop-card').forEach(card => {
                card.style.background = '#FFFFFF';
            });
            event.currentTarget.style.background = '#E8F5E9';

            // Add to recent
            addToRecent(stop);
        }

        function addToRecent(stop) {
            let recent = JSON.parse(localStorage.getItem('recent_stops') || '[]');
            recent = recent.filter(s => s.id !== stop.id);
            recent.unshift(stop);
            recent = recent.slice(0, 10);
            localStorage.setItem('recent_stops', JSON.stringify(recent));
        }

        function confirmSelection() {
            if (!selectedStop) {
                alert('Seleziona una fermata');
                return;
            }

            localStorage.setItem(`route_${selectionType}`, JSON.stringify(selectedStop));
            window.location.href = '/route-finder';
        }

        function cancelSelection() {
            window.location.href = '/route-finder';
        }

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

            const filtered = allStops.filter(stop => 
                stop.name.toLowerCase().includes(query)
            );

            const container = document.getElementById('all-stops-list');
            if (filtered.length === 0) {
                container.innerHTML = '<div class="no-results">Nessuna fermata trovata</div>';
            } else {
                container.innerHTML = filtered.slice(0, 20).map(stop => createStopCard(stop, false)).join('');
            }
        }

        // Initialize
        window.addEventListener('DOMContentLoaded', () => {
            loadStops();
        });
    </script>

</body>
</html>
