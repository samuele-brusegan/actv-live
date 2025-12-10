<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risultati Percorso - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/routeResults.css">
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
            <a href="/route-finder" class="back-button">&larr;</a>
        </div>
        <div class="header-title">Risultati<br>Percorso</div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Route Summary -->
        <div class="route-summary">
            <div class="route-stops">
                <div class="route-stop">
                    <div class="route-stop-label">da</div>
                    <div class="route-stop-name" id="origin-name">-</div>
                </div>
                <div class="route-arrow">→</div>
                <div class="route-stop">
                    <div class="route-stop-label">a</div>
                    <div class="route-stop-name" id="destination-name">-</div>
                </div>
            </div>
            <div class="route-datetime" id="datetime-info">-</div>
        </div>

        <!-- Loading State -->
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <div>Ricerca percorsi in corso...</div>
        </div>

        <!-- Routes List -->
        <div id="routes-container" style="display: none;">
            <div class="section-title">percorsi disponibili</div>
            <div id="routes-list"></div>
        </div>

        <!-- No Routes -->
        <div id="no-routes" style="display: none;">
            <div class="no-routes">
                <div class="no-routes-icon">🚫</div>
                <div class="no-routes-text">Nessun percorso trovato</div>
                <div class="no-routes-subtext">Prova a selezionare fermate diverse</div>
                <button class="btn-back" onclick="window.location.href='/route-finder'">Torna indietro</button>
            </div>
        </div>

    </div>

    <!-- Modal -->
    <div id="details-modal" class="modal-overlay" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-title">Dettagli Viaggio</div>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modal-body">
                <!-- Content injected by JS -->
            </div>
            <button class="btn-back" style="margin-top: 1rem;" onclick="closeModal()">Chiudi</button>
        </div>
    </div>

    <script>
        // Global Error Handler
        window.onerror = function(msg, url, line, col, error) {
            document.body.innerHTML += `<div style="color: red; padding: 20px; background: white; border: 1px solid red; margin: 20px;">
                <h3>JavaScript Error</h3>
                <p>${msg}</p>
                <p>Line: ${line}, Col: ${col}</p>
                <pre>${error ? error.stack : ''}</pre>
            </div>`;
            return false;
        };

        let origin = null;
        let destination = null;
        let departureDate = null;
        let departureTime = null;

        // Load route parameters
        window.addEventListener('DOMContentLoaded', async () => {
            try {
                // Get from localStorage
                try {
                    origin = JSON.parse(localStorage.getItem('route_origin'));
                    destination = JSON.parse(localStorage.getItem('route_destination'));
                } catch (e) {
                    console.error("Error parsing localStorage:", e);
                    // Reset invalid data
                    localStorage.removeItem('route_origin');
                    localStorage.removeItem('route_destination');
                }

                departureDate = localStorage.getItem('route_departure_date') || new Date().toISOString().split('T')[0];
                departureTime = localStorage.getItem('route_departure_time') || new Date().toTimeString().slice(0, 5);

                if (!origin || !destination) {
                    showNoRoutes('Seleziona partenza e destinazione');
                    return;
                }

                // Update UI
                const originEl = document.getElementById('origin-name');
                const destEl = document.getElementById('destination-name');
                const dateEl = document.getElementById('datetime-info');

                if (originEl) originEl.textContent = origin.name;
                if (destEl) destEl.textContent = destination.name;
                if (dateEl) dateEl.textContent = `Partenza: ${formatDate(departureDate)} alle ${departureTime}`;

                // Search routes
                await searchRoutes();
            } catch (err) {
                console.error("Critical initialization error:", err);
                document.getElementById('loading').style.display = 'none';
                document.body.innerHTML += `<div style="color: red; padding: 20px;">Init Error: ${err.message}</div>`;
            }
        });

        async function searchRoutes() {
            try {
                // Determine params
                const fromParam = (origin.type === 'address') ? `${origin.lat},${origin.lng}` : origin.id;
                const toParam = (destination.type === 'address') ? `${destination.lat},${destination.lng}` : destination.id;

                // Call our internal API
                const url = `/api/plan-route?from=${encodeURIComponent(fromParam)}&to=${encodeURIComponent(toParam)}&time=${departureTime}`;
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success && data.routes && data.routes.length > 0) {
                    displayRoutes(data.routes);
                } else {
                    showNoRoutes(data.error || 'Nessun percorso trovato');
                }
            } catch (error) {
                console.error('Error searching routes:', error);
                showNoRoutes('Errore durante la ricerca: ' + error.message);
            }
        }

        function getLineBadgeInfo(lineNameRaw) {
            if (!lineNameRaw) return { name: '?', class: 'badge-red' };
            
            if (lineNameRaw === 'Cammina') {
                return { name: '🚶', class: 'badge-walking' }; // Custom class needed? Or just handle in render
            }

            let lineNameParts = lineNameRaw.split("_");
            let lineName = lineNameParts[0];
            let lineTag = lineNameParts[1];

            let badgeColor = "badge-red";
            if(lineTag === "US" || lineTag === "UN" || lineTag === "EN") {
                badgeColor = "badge-blue";
            }
            if(lineName.startsWith("N")) {
                badgeColor = "badge-night";
            }

            return { name: lineName, class: badgeColor };
        }

        function displayRoutes(routes) {
            const loadingEl = document.getElementById('loading');
            const containerEl = document.getElementById('routes-container');
            const listEl = document.getElementById('routes-list');

            if (loadingEl) loadingEl.style.display = 'none';
            if (containerEl) containerEl.style.display = 'block';

            if (listEl) {
                listEl.innerHTML = routes.map(route => {
                    // Build legs HTML dynamically
                    let legsHtml = '';
                    
                    route.legs.forEach((leg, index) => {
                        const isWalking = leg.type === 'walking';
                        const badge = getLineBadgeInfo(leg.route_short_name);
                        const isStart = index === 0;
                        const isEnd = index === route.legs.length - 1;

                        // Start Marker (only for first leg)
                        if (isStart) {
                             legsHtml += `
                                <div class="timeline-item">
                                    <div class="timeline-marker start"></div>
                                    <div class="timeline-content">
                                        <div class="stop-name">${leg.origin || 'Partenza'}</div>
                                        <div class="stop-time">${formatTime(leg.departure_time)}</div>
                                    </div>
                                </div>`;
                        }

                        // Connector (The Trip/Walk itself)
                        let connectorContent = '';
                        if (isWalking) {
                             connectorContent = `
                                <div class="line-badge" style="background: #ccc; color: #333;">🚶</div>
                                <div class="connector-info">cammina per ${Math.round(leg.duration)} min (${leg.distance}m)</div>
                            `;
                        } else {
                            connectorContent = `
                                <div class="line-badge ${badge.class}">${badge.name}</div>
                                <div class="connector-info">per ${leg.stops_count} fermate</div>
                            `;
                        }

                        legsHtml += `
                            <div class="timeline-connector">
                                ${connectorContent}
                            </div>
                        `;

                        // End Node of this leg (Transfer or Arrival)
                        let markerClass = isEnd ? 'end' : 'transfer';
                        let stopName = isWalking ? leg.destination : (isEnd ? 'Arrivo' : `Cambio a ${route.transfer_stop || 'fermata'}`);
                         // If it's a walking leg destination, show name
                        if (!isWalking && isEnd) {
                             // Logic to get destination name if avail
                             stopName = destination.name; 
                        }
                        
                        // Refinement: If next leg is walking, this node is where we start walking
                        // Using rendered leg destination
                        
                        legsHtml += `
                            <div class="timeline-item">
                                <div class="timeline-marker ${markerClass}"></div>
                                <div class="timeline-content">
                                    <!-- Using leg destination for intermediate stops is safer if available, but for now logic is ok -->
                                    <div class="stop-name">${isWalking ? leg.destination : (isEnd ? destination.name : route.transfer_stop)}</div>
                                    <div class="stop-time">${formatTime(leg.arrival_time)}</div>
                                </div>
                            </div>
                        `;
                    });

                    return `
                    <div class="route-card" onclick='showRouteDetails(${JSON.stringify(route)})'>
                        <div class="route-header-row">
                            <div class="route-date">${formatDate(departureDate)}</div>
                            <div class="route-total-duration">⏱ ${Math.round(route.duration)} min</div>
                        </div>
                        <div class="route-timeline">
                            ${legsHtml}
                        </div>
                        <div class="route-footer">
                            <button class="btn-select" onclick='selectRoute(${JSON.stringify(route)}, event)'>seleziona &rarr;</button>
                        </div>
                    </div>
                `}).join('');
            }
        }

        function showNoRoutes(message = null) {
            const loadingEl = document.getElementById('loading');
            const noRoutesEl = document.getElementById('no-routes');
            
            if (loadingEl) loadingEl.style.display = 'none';
            if (noRoutesEl) noRoutesEl.style.display = 'block';
            
            if (message) {
                const textEl = document.querySelector('.no-routes-text');
                if (textEl) textEl.textContent = message;
            }
        }

        function selectRoute(route, event) {
            if (event) event.stopPropagation();
            // Save to localStorage for the details page
            localStorage.setItem('selected_route', JSON.stringify(route));
            window.location.href = '/route-details';
        }

        // Keep this for backward compatibility if needed, or remove
        function showRouteDetails(route) {
            selectRoute(route);
        }
        
        function closeModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('details-modal').classList.remove('active');
        }

        function formatDate(dateStr) {
            try {
                const date = new Date(dateStr);
                const options = { weekday: 'short', day: 'numeric', month: 'short' };
                return date.toLocaleDateString('it-IT', options);
            } catch (e) {
                return dateStr;
            }
        }
        
        function formatTime(timeStr) {
            return timeStr ? timeStr.substring(0, 5) : '--:--';
        }
    </script>

</body>
</html>
