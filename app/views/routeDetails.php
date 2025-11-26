<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettagli Viaggio - ACTV</title>
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        /* Page specific overrides */
        .timeline-connector {
            /* Override for routeDetails specific connector positioning if needed */
            /* In style.css I used generic connector styles. 
               routeDetails had: left: 12px; top: 24px; bottom: -10px; width: 0; border-left: 3px solid #009E61;
               style.css has: margin-left: 7px; border-left: 2px solid... 
               The structure in routeDetails seems slightly different or I merged them.
               Let's check the HTML structure in routeDetails.
            */
             position: absolute;
             left: 12px; 
             top: 24px;
             bottom: -10px;
             width: 0;
             border-left: 3px solid #009E61;
             z-index: 1;
             margin-left: 0; /* Reset style.css margin */
             padding: 0; /* Reset style.css padding */
             display: block; /* Reset flex */
        }
        
        .timeline-connector.dashed {
            border-left-style: dashed;
            border-color: #999;
        }

        .timeline-item {
            margin-bottom: 0;
        }
        
        .timeline {
            margin-top: 1rem;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .header-date {
            font-weight: 700;
            font-size: 18px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <div style="height: 20px;">
            <a href="javascript:history.back()" class="back-button">&larr;</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <div class="details-card">
            
            <!-- Header Info inside card -->
            <div class="header-info">
                <div class="header-date" id="route-date"></div>
                <div class="header-time" id="route-duration"></div>
            </div>
            
            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

            <div id="timeline-container" class="timeline">
                <!-- Injected via JS -->
            </div>

        </div>

        <button class="btn-map" onclick="showMap()">visualizza sulla mappa</button>

    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            try {
                const routeJson = localStorage.getItem('selected_route');
                const originJson = localStorage.getItem('route_origin');
                const destJson = localStorage.getItem('route_destination');
                const dateStr = localStorage.getItem('route_departure_date');
                
                if (!routeJson) {
                    alert('Nessun percorso selezionato');
                    window.location.href = '/route-finder';
                    return;
                }

                const route = JSON.parse(routeJson);
                const origin = originJson ? JSON.parse(originJson) : {name: 'Partenza'};
                const destination = destJson ? JSON.parse(destJson) : {name: 'Destinazione'};
                
                // Set Header Info
                document.getElementById('route-date').textContent = formatDate(dateStr);
                document.getElementById('route-duration').textContent = `⏱ ${Math.round(route.duration)} min`;
                
                renderTimeline(route, origin, destination);
                
            } catch (e) {
                console.error(e);
                alert('Errore nel caricamento dei dati');
            }
        });

        function renderTimeline(route, origin, destination) {
            const container = document.getElementById('timeline-container');
            let html = '';
            
            // 1. Start Walking (Mocked)
            html += `
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <div class="icon-location">↘</div>
                    </div>
                    <div class="timeline-connector dashed"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">posizione attuale<br>città</div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <div class="icon-walk">🚶</div>
                    </div>
                    <div class="timeline-connector dashed"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">percorrere<br>circa 800 m</div>
                    </div>
                </div>
            `;
            
            // 2. Route Legs
            if (route.type === 'transfer') {
                const leg1 = route.legs[0];
                const leg2 = route.legs[1];
                
                // Leg 1 Start
                html += createStopItem(origin.name, leg1.route_short_name, leg1.stops_count, leg1.duration, false);
                
                // Transfer Stop
                html += createStopItem(route.transfer_stop, leg2.route_short_name, leg2.stops_count, leg2.duration, false);
                
                // End Stop
                html += createStopItem(destination.name, null, 0, 0, true);
                
            } else {
                // Direct Route
                html += createStopItem(origin.name, route.route_short_name, route.stops_count, route.duration, false);
                html += createStopItem(destination.name, null, 0, 0, true);
            }
            
            // 3. End Walking (Mocked)
            html += `
                <div class="timeline-item">
                    <div class="timeline-connector dashed" style="top: -10px; height: 40px; bottom: auto;"></div>
                    <div class="timeline-icon">
                        <div class="icon-walk">🚶</div>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">percorrere<br>circa 800 m</div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <div class="icon-location">↖</div>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">${destination.name}</div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function createStopItem(stopName, routeName, stopsCount, duration, isLast) {
            let lineInfo = '';
            let connectorClass = 'timeline-connector';
            
            if (routeName) {
                lineInfo = `
                    <div class="line-badge">${routeName}</div>
                    <div class="timeline-desc">
                        prendere la linea ${routeName} verso<br>
                        DESTINAZIONE (mock)<br>
                        scendere dopo ${stopsCount} fermate<br>
                        (circa ${Math.round(duration)} min)
                    </div>
                `;
            } else {
                // Last stop, no line info, connector is dashed for walking after
                connectorClass = 'timeline-connector dashed';
            }
            
            // If it's the very last stop of the bus journey, we switch to dashed line for walking
            // But the connector logic is tricky in a loop. 
            // The connector belongs to the PREVIOUS item visually connecting to THIS item? 
            // No, in my CSS connector is absolute in the item, going DOWN.
            
            return `
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <div class="icon-circle"></div>
                    </div>
                    <div class="${connectorClass}"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">${stopName}</div>
                        ${lineInfo}
                    </div>
                </div>
            `;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const options = { weekday: 'short', day: 'numeric', month: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('it-IT', options);
        }
        
        function showMap() {
            alert('Funzionalità mappa in arrivo!');
        }
    </script>
</body>
</html>
