<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettagli Viaggio - ACTV</title>
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

        .back-button {
            color: white;
            text-decoration: none;
            font-size: 24px;
            display: inline-block;
            border: 2px solid white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            text-align: center;
            line-height: 28px;
        }

        .main-content {
            padding: 0 1.5rem 1.5rem;
            position: relative;
            z-index: 2;
        }

        .details-card {
            background: #FFFFFF;
            box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            min-height: 400px;
        }

        /* Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 10px;
            margin-top: 1rem;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            position: relative;
            margin-bottom: 0;
        }

        .timeline-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            z-index: 2;
            background: white;
        }
        
        .icon-circle {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #009E61;
            background: white;
        }
        
        .icon-walk {
            font-size: 20px;
            color: #000;
        }
        
        .icon-location {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #009E61;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #009E61;
            font-size: 12px;
            font-weight: bold;
        }

        .timeline-content {
            flex: 1;
            padding-bottom: 20px;
        }

        .timeline-title {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #000;
            margin-bottom: 5px;
        }

        .timeline-desc {
            font-family: 'SF Pro', sans-serif;
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }

        .line-badge {
            background: #E60000; /* Red for line number */
            color: white;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 5px;
        }

        /* Connector Lines */
        .timeline-connector {
            position: absolute;
            left: 21px; /* Center of icon (24px width -> center 12px + 10px padding-left? No, relative to timeline div) */
            /* Actually, icon is 24px wide. Center is 12px. */
            /* Let's adjust based on visual check */
            left: 12px; 
            top: 24px;
            bottom: -10px;
            width: 0;
            border-left: 3px solid #009E61; /* Green solid line */
            z-index: 1;
        }
        
        .timeline-connector.dashed {
            border-left-style: dashed;
            border-color: #999;
        }

        /* Map Button */
        .btn-map {
            background: #0152BB;
            color: white;
            border: none;
            border-radius: 25px;
            padding: 15px;
            width: 100%;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            margin-top: 2rem;
            box-shadow: 0 4px 10px rgba(1, 82, 187, 0.3);
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
