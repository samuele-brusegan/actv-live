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
                // Call our internal API
                const url = `/api/plan-route?from=${origin.id}&to=${destination.id}&time=${departureTime}`;
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
                    const isTransfer = route.type === 'transfer';
                    
                    // Prepare legs for display
                    let legsHtml = '';
                    
                    if (isTransfer) {
                        // Transfer Logic
                        const leg1 = route.legs[0];
                        const leg2 = route.legs[1];
                        
                        const badge1 = getLineBadgeInfo(leg1.route_short_name);
                        const badge2 = getLineBadgeInfo(leg2.route_short_name);

                        legsHtml = `
                            <div class="timeline-item">
                                <div class="timeline-marker start"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">Partenza</div>
                                    <div class="stop-time">${formatTime(leg1.departure_time)}</div>
                                </div>
                            </div>
                            <div class="timeline-connector">
                                <div class="line-badge ${badge1.class}">${badge1.name}</div>
                                <div class="connector-info">per ${leg1.stops_count} fermate</div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker transfer"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">Cambio a ${route.transfer_stop}</div>
                                    <div class="stop-time">${formatTime(leg1.arrival_time)}</div>
                                </div>
                            </div>
                             <div class="timeline-connector">
                                <div class="line-badge ${badge2.class}">${badge2.name}</div>
                                 <div class="connector-info">per ${leg2.stops_count} fermate</div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker end"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">Arrivo</div>
                                    <div class="stop-time">${formatTime(leg2.arrival_time)}</div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Direct Logic
                        const badge = getLineBadgeInfo(route.route_short_name);

                        legsHtml = `
                            <div class="timeline-item">
                                <div class="timeline-marker start"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">${origin.name}</div>
                                    <div class="stop-time">${formatTime(route.departure_time)}</div>
                                </div>
                            </div>
                            <div class="timeline-connector">
                                <div class="line-badge ${badge.class}">${badge.name}</div>
                                <div class="connector-info">viaggia per ${route.stops_count} fermate</div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker end"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">${destination.name}</div>
                                    <div class="stop-time">${formatTime(route.arrival_time)}</div>
                                </div>
                            </div>
                        `;
                    }

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
