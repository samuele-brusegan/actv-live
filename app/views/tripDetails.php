<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Corsa - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/style.css">
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
                <div class="line-box line-badge" id="line-number">--</div>
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
        const lineFull = urlParams.get('line');
        const line = lineFull.split('_')[0];
        const tag = lineFull.split('_')[1];
        
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
            
            // 1. Find current stop index
            let currentStopIndex = -1;
            
            // Debug logs
            console.log('Target stopId:', stopId);
            
            // Try to find by ID first
            if (stopId) {
                currentStopIndex = stops.findIndex(s => s.id == stopId || stopId.includes(s.id));
            }
            
            // Fallback: Try to find by Name if ID failed (and we have a way to know which one... 
            // actually if we don't have ID match, maybe we are just viewing the trip without a specific target?
            // But the user said "passed" issue implies we HAVE a target but it's not matching.
            // Let's try matching by name if ID fails, assuming 'dest' might be the target? 
            // No, 'stopId' param is the user's selected stop.
            
            if (currentStopIndex === -1 && stopId) {
                console.warn('Stop ID match failed. Trying loose match or name match...');
                // Logic to try to match by name if we had the name passed, but we only have stopId from URL.
                // If the API returned stops with different IDs than what we have in URL (e.g. 123 vs 123_1), try partial match
                currentStopIndex = stops.findIndex(s => String(s.id).includes(String(stopId)) || String(stopId).includes(String(s.id)));
            }

            console.log('Found current stop index:', currentStopIndex);

            // If still -1, it means we are either at the start or the stop wasn't found in this trip.
            // To be safe and avoid "PASSATO" everywhere, we treat -1 as "Start of trip" (all future) 
            // UNLESS we want to show error. But "All Future" is safer than "All Passed".
            
            // Helper for badge color (same as stop.php)
            if (line.includes('N')) {
                lineBadgeClass = 'badge-night';
            } else if ( tag === "US" || tag === "UN" || tag === "EN" ) {
                lineBadgeClass = 'badge-blue';
            } else {
                lineBadgeClass = 'badge-red';
            }

            document.getElementById('line-number').className += ' ' + lineBadgeClass;
            
            stops.forEach((stop, index) => {
                let isCurrent = false;
                let isPassed = false;
                
                if (currentStopIndex !== -1) {
                    if (index < currentStopIndex) {
                        isPassed = true;
                    } else if (index === currentStopIndex) {
                        isCurrent = true;
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
                    timeDisplay = stop.time ? stop.time : 'PASSATO';
                } else if (isCurrent) {
                    // Show the time passed in URL if available
                    timeDisplay = time ? time : 'ORA';
                } else {
                    // Future stops
                    if (time && time.includes('min')) {
                        const currentMin = parseInt(time);
                        // If we know current stop index, we can estimate. 
                        // If we don't (currentStopIndex == -1), we assume we are at start (index 0 effectively for time calc?)
                        // actually if currentStopIndex is -1, we can't estimate relative time easily.
                        const baseIndex = currentStopIndex === -1 ? 0 : currentStopIndex;
                        const diff = index - baseIndex;
                        const estMin = currentMin + (diff * 2); // Rough estimate
                        timeDisplay = `${estMin} min`;
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
                }
            }, 500);
        }
        
        function openMap() {
            // Open lines map, maybe passing the line to highlight
            window.location.href = `/lines-map?line=${encodeURIComponent(line)}`;
        }
        
        loadStops();
    </script>
</body>
</html>
