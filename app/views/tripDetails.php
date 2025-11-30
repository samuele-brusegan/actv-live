<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Corsa - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/tripDetails.css">
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
             <a href="javascript:history.back()" style="color: white; text-decoration: none; font-size: 24px;">&larr;</a>
        </div> 
        
        <div class="trip-header-info">
            <div style="display: flex; flex-direction: column; align-items: center; margin-right: 15px;">
                <div class="line-box" id="line-number">--</div>
                <div id="delay-info" style="font-size: 11px; font-weight: bold; margin-top: 4px; background: white; color: #333; padding: 2px 6px; border-radius: 4px; display: none;"></div>
            </div>
            <div class="direction-info">
                <div class="direction-label">DIREZIONE</div>
                <div class="direction-name" id="direction-name">Caricamento...</div>
            </div>
        </div>
        
        <div class="time-remaining" id="time-container" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
            </svg>
            <span id="time-text">-- min</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-card">
        <div id="stops-container">
            <div class="loading-state">
                Caricamento fermate...
            </div>
        </div>
    </div>
    
    <button class="map-fab" onclick="openMap()" title="Vedi sulla mappa">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
        </svg>
    </button>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const line = urlParams.get('line');
        const dest = urlParams.get('dest');
        const stopId = urlParams.get('stopId');
        const time = urlParams.get('time');
        const delay = urlParams.get('delay'); // New param
        
        // Init Header
        document.getElementById('line-number').innerText = line || '--';
        document.getElementById('direction-name').innerText = dest || 'Sconosciuta';
        if (time) {
            document.getElementById('time-container').style.display = 'flex';
            document.getElementById('time-text').innerText = time; 
        }
        
        if (delay) {
            const delayEl = document.getElementById('delay-info');
            delayEl.style.display = 'block';
            delayEl.innerText = delay;
            
            // Style based on content (simple heuristic)
            if (delay.includes('ardo') || delay.includes('+')) {
                delayEl.style.color = '#E30613'; // Red for delay
            } else if (delay.includes('nticipo') || delay.includes('-')) {
                delayEl.style.color = '#009E61'; // Green for early
            }
        }
        
        async function loadStops() {
            if (!line) return;
            
            try {
                const response = await fetch(`/api/trip-stops?line=${encodeURIComponent(line)}&dest=${encodeURIComponent(dest)}`);
                if (!response.ok) throw new Error('Network error');
                
                const stops = await response.json();
                renderStops(stops);
                
            } catch (error) {
                console.error(error);
                document.getElementById('stops-container').innerHTML = '<div class="text-center text-danger">Errore caricamento percorso</div>';
            }
        }
        
        function renderStops(stops) {
            const container = document.getElementById('stops-container');
            let html = '<div class="timeline">';
            
            let foundCurrent = false;
            let currentStopIndex = -1;
            
            // Helper for badge color (same as stop.php)
            const getBadgeColor = (lineName) => {
                if (lineName.includes('N')) return 'bg-dark'; // Night
                if (['2', '6', '6L', '7', '7L', '5E'].includes(lineName)) return 'bg-primary'; // Blue
                return 'bg-danger'; // Default Red
            };
            
            const badgeClass = getBadgeColor(line);
            const lineBadge = document.getElementById('line-number');
            lineBadge.className = `line-box ${badgeClass}`;
            
            // Override styles based on class logic (since we don't have bootstrap loaded fully or want custom colors)
            if (line.includes('N')) lineBadge.style.backgroundColor = '#000';
            else if (['2', '6', '6L', '7', '7L', '5E'].includes(line)) lineBadge.style.backgroundColor = '#0056b3';
            else lineBadge.style.backgroundColor = '#E30613';

            
            stops.forEach((stop, index) => {
                let isCurrent = false;
                let isPassed = false;
                
                if (!foundCurrent) {
                    if (stopId && (stop.id == stopId || stopId.includes(stop.id))) {
                        isCurrent = true;
                        foundCurrent = true;
                        currentStopIndex = index;
                    } else {
                        isPassed = true;
                    }
                }
                
                // Visual states
                let markerClass = 'stop-marker';
                let lineClass = 'stop-line';
                let itemClass = 'stop-item';
                
                if (isPassed) {
                    markerClass += ' passed';
                    lineClass += ' passed';
                    itemClass += ' passed';
                } else if (isCurrent) {
                    markerClass += ' current';
                    itemClass += ' current-stop-item'; // For scrolling
                }
                
                // Time Logic
                let timeDisplay = '';
                if (isPassed) {
                    timeDisplay = 'PASSATO';
                } else if (isCurrent) {
                    // Show the time passed in URL if available
                    timeDisplay = time ? time : 'ORA';
                } else {
                    // Future stops
                    // We don't have real schedule, so we can't show exact time easily without more data.
                    // But the user asked "Actual value". 
                    // If we have the current time (e.g. 10 min), we could estimate +2 min per stop?
                    // Or just leave empty or "IN ARRIVO".
                    // Let's try to be smart: if 'time' is "X min", we can add X + (diff * 2) min.
                    if (time && time.includes('min')) {
                        const currentMin = parseInt(time);
                        const diff = index - currentStopIndex;
                        const estMin = currentMin + (diff * 2); // Rough estimate
                        timeDisplay = `${estMin} min`;
                    } else if (time && time.includes(':')) {
                         // It's absolute time like 14:30. Hard to add minutes without date obj.
                         timeDisplay = 'IN ARRIVO';
                    } else {
                        timeDisplay = 'IN ARRIVO';
                    }
                }
                
                html += `
                    <div class="${itemClass}" onclick="window.location.href='/aut/stops/stop?id=${stop.id}&name=${encodeURIComponent(stop.name)}'" style="cursor: pointer;">
                        <div class="${lineClass}"></div>
                        <div class="${markerClass}"></div>
                        <div class="stop-content">
                            <div class="stop-name">${stop.name}</div>
                            <div class="stop-time">${timeDisplay}</div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
            
            // Auto-scroll to current
            setTimeout(() => {
                const currentEl = document.querySelector('.current-stop-item');
                if (currentEl) {
                    currentEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    // Fallback: try to find by class 'current' in marker
                    const currentMarker = document.querySelector('.stop-marker.current');
                    if (currentMarker) {
                         currentMarker.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }, 500); // Increased timeout to ensure rendering is done
        }
        
        function openMap() {
            // Open lines map, maybe passing the line to highlight
            window.location.href = `/lines-map?line=${encodeURIComponent(line)}`;
        }
        
        loadStops();
    </script>
</body>
</html>
