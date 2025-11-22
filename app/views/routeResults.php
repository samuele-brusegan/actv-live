<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risultati Percorso - ACTV</title>
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

        /* Route Summary */
        .route-summary {
            background: #FFFFFF;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .route-stops {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .route-stop {
            flex: 1;
        }

        .route-stop-label {
            font-family: 'SF Pro', sans-serif;
            font-size: 12px;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .route-stop-name {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: #000;
        }

        .route-arrow {
            font-size: 24px;
            color: #009E61;
        }

        .route-datetime {
            font-family: 'SF Pro', sans-serif;
            font-size: 14px;
            color: #666;
            margin-top: 0.5rem;
        }

        /* Section Title */
        .section-title {
            font-family: 'SF Pro', sans-serif;
            font-weight: 600;
            font-size: 18px;
            color: #000000;
            margin: 1.5rem 0 0.75rem;
        }

        /* Route Card */
        .route-card {
            background: #FFFFFF;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            padding: 1.25rem 1.25rem 0 1.25rem; /* Remove bottom padding for flush button */
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            overflow: hidden; /* Clip button corners */
        }

        .route-card:active {
            transform: scale(0.98);
            box-shadow: 0px 1px 4px rgba(0, 0, 0, 0.15);
        }

        .route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .route-time {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 20px;
            color: #000;
        }

        .route-duration {
            font-family: 'SF Pro', sans-serif;
            font-size: 14px;
            color: #666;
        }

        .route-details {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .line-badge {
            background: #0152BB;
            border-radius: 7px;
            color: #FFFFFF;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 14px;
            padding: 6px 12px;
            display: inline-block;
        }

        .route-info {
            font-family: 'SF Pro', sans-serif;
            font-size: 14px;
            color: #666;
        }

        .no-routes {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .no-routes-icon {
            font-size: 48px;
            margin-bottom: 1rem;
        }

        .no-routes-text {
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            margin-bottom: 0.5rem;
        }

        .no-routes-subtext {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #999;
        }

        .loading {
            text-align: center;
            padding: 3rem 1rem;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #009E61;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-back {
            background: #0152BB;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            width: 100%;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: all 0.2s ease;
        }

        .btn-back:active {
            transform: scale(0.98);
            background: #013d99;
        }

        /* New Timeline Styles */
        .route-header-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-family: 'SF Pro', sans-serif;
            font-size: 14px;
            color: #000;
            font-weight: 600;
        }

        .route-timeline {
            position: relative;
            padding-left: 10px;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            position: relative;
            z-index: 2;
        }

        .timeline-marker {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #009E61; /* Green */
            background: #fff;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .timeline-marker.start { border-color: #0152BB; } /* Blue */
        .timeline-marker.transfer { border-color: #FF9800; } /* Orange */
        .timeline-marker.end { border-color: #009E61; } /* Green */

        .timeline-content {
            padding-bottom: 0;
        }

        .stop-name {
            font-weight: 700;
            font-size: 16px;
            color: #000;
        }

        .stop-time {
            font-size: 14px;
            color: #666;
        }

        .timeline-connector {
            margin-left: 7px; /* Center with marker (16px width -> center at 8px) */
            border-left: 2px solid #009E61;
            padding-left: 22px; /* 15px margin + 7px offset */
            padding-top: 10px;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .connector-info {
            font-size: 13px;
            color: #666;
        }

        .route-footer {
            padding: 0;
            margin-top: 1rem;
            border-top: none;
            margin-left: -1.25rem;
            margin-right: -1.25rem;
        }

        .walk-info {
            font-size: 14px;
            color: #000;
            font-weight: 500;
        }

        .btn-select {
            background: #009E61;
            color: white;
            border: none;
            border-radius: 0; /* Full width, no radius */
            padding: 15px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .btn-select:hover {
            background: #008552;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: flex-end; /* Bottom sheet on mobile */
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 600px;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            max-height: 80vh;
            overflow-y: auto;
        }

        @media (min-width: 600px) {
            .modal-overlay {
                align-items: center;
            }
            .modal-content {
                border-radius: 20px;
                transform: translateY(20px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            }
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 20px;
        }

        .modal-close {
            background: #f0f0f0;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #333;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .detail-time {
            width: 60px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
            flex-shrink: 0;
        }
        
        .detail-content {
            flex: 1;
            padding-left: 15px;
            border-left: 2px solid #eee;
            padding-bottom: 15px;
        }
        
        .detail-row:last-child .detail-content {
            border-left: none;
        }
        
        .detail-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .detail-subtitle {
            font-size: 14px;
            color: #666;
        }
        
        .badge-line {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
            font-weight: 700;
            font-size: 12px;
            margin-right: 5px;
            background: #0152BB;
        }
    </style>
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
                        
                        legsHtml = `
                            <div class="timeline-item">
                                <div class="timeline-marker start"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">Partenza</div>
                                    <div class="stop-time">${formatTime(leg1.departure_time)}</div>
                                </div>
                            </div>
                            <div class="timeline-connector">
                                <div class="line-badge" style="background-color: #E60000;">${leg1.route_short_name}</div>
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
                                <div class="line-badge" style="background-color: #E60000;">${leg2.route_short_name}</div>
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
                        legsHtml = `
                            <div class="timeline-item">
                                <div class="timeline-marker start"></div>
                                <div class="timeline-content">
                                    <div class="stop-name">${origin.name}</div>
                                    <div class="stop-time">${formatTime(route.departure_time)}</div>
                                </div>
                            </div>
                            <div class="timeline-connector">
                                <div class="line-badge" style="background-color: #E60000;">${route.route_short_name}</div>
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
