<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleziona Fermata - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F5F5;
            margin: 0;
            padding: 0;
        }

        /* Header Verde */
        .header-green {
            background: #009E61;
            padding: 2rem 1.5rem 4rem;
            color: white;
            clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
            margin-bottom: -2rem;
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 28px;
            line-height: 1.2;
            margin-top: 1rem;
        }

        .back-button {
            color: white;
            text-decoration: none;
            font-size: 24px;
            display: inline-block;
        }

        /* Main Content */
        .main-content {
            padding: 0 1.5rem 1.5rem;
            position: relative;
            z-index: 2;
        }

        /* Search Bar */
        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-input {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            border: none;
            background: #FFFFFF;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            box-shadow: 0px 2px 12px rgba(0, 158, 97, 0.3);
        }

        /* Section Title */
        .section-title {
            font-family: 'SF Pro', sans-serif;
            font-weight: 600;
            font-size: 18px;
            color: #000000;
            margin: 1.5rem 0 0.75rem;
        }

        /* Stop Card */
        .stop-card {
            background: #FFFFFF;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .stop-card:active {
            transform: scale(0.98);
            box-shadow: 0px 1px 4px rgba(0, 0, 0, 0.15);
        }

        .stop-card-content {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }

        .stop-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #F5F5F5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 20px;
        }

        .stop-info {
            flex-grow: 1;
        }

        .stop-name {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: #000000;
            margin-bottom: 0.25rem;
        }

        .stop-desc {
            font-family: 'SF Pro', sans-serif;
            font-weight: 500;
            font-size: 14px;
            color: #666;
        }

        .line-badge {
            background: #0152BB;
            border-radius: 7px;
            color: #FFFFFF;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            display: inline-block;
            margin-right: 4px;
        }

        .favorite-icon {
            color: #FFD700;
            font-size: 20px;
        }

        .arrow-icon {
            color: #ccc;
            font-size: 20px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding: 0 1.5rem 1.5rem;
        }

        .btn-primary {
            flex: 1;
            background: #0152BB;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary:active {
            transform: scale(0.98);
            background: #013d99;
        }

        .btn-secondary {
            flex: 1;
            background: #FFFFFF;
            color: #0152BB;
            border: 2px solid #0152BB;
            border-radius: 12px;
            padding: 1rem;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:active {
            transform: scale(0.98);
            background: #f0f0f0;
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-family: 'Inter', sans-serif;
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
    <div class="action-buttons" style="position: fixed; bottom: 0; left: 0; right: 0; background: #F5F5F5; border-top: 1px solid #e0e0e0; z-index: 2;">
        <button class="btn-secondary" onclick="cancelSelection()">annulla</button>
        <button class="btn-primary" onclick="confirmSelection()">fatto</button>
    </div>

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
                // Convert to array
                allStops = Object.values(data).map(stop => ({
                    id: stop.id,
                    name: stop.name,
                    lines: [], // GTFS stops.json doesn't have lines yet, we might need to add them or ignore
                    lat: stop.lat,
                    lng: stop.lon
                }));

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
            
            if (recent.length === 0) {
                container.innerHTML = '<div class="no-results">Nessuna fermata recente</div>';
                return;
            }

            container.innerHTML = recent.slice(0, 5).map(stop => createStopCard(stop, false)).join('');
        }

        function createStopCard(stop, isFavorite) {
            const linesHtml = stop.lines && stop.lines.length > 0 
                ? stop.lines.slice(0, 3).map(line => `<span class="line-badge">${line.alias || line.line}</span>`).join('')
                : '';

            return `
                <div class="stop-card" onclick='selectStop(${JSON.stringify(stop)})'>
                    <div class="stop-card-content">
                        <div class="stop-icon">🚏</div>
                        <div class="stop-info">
                            <div class="stop-name">${stop.name}</div>
                            <div class="stop-desc">${linesHtml || 'Nessuna linea'}</div>
                        </div>
                    </div>
                    ${isFavorite ? '<span class="favorite-icon">★</span>' : '<span class="arrow-icon">›</span>'}
                </div>
            `;
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
