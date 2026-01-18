<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mappa Linee - ACTV</title>
    <!-- CSS di Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/linesMap.css">
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <a href="/" class="back-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
        </a>
        <div class="header-title">Mappa Linee</div>
        <div style="width: 24px;"></div> <!-- Spacer for centering -->
    </div>

    <div id="loading" class="loading-overlay">
        <div class="spinner"></div>
        <div>Caricamento linee...</div>
    </div>

    <div id="map"></div>

    <!-- JavaScript di Leaflet -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
    <script>
        // Initialize Map
        var map = L.map('map', {attributionControl: false, zoomControl: false}).setView([45.4384, 12.3359], 12); // Venezia centro

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Bright, fixed palette
        const LINE_COLORS = [
            '#FF0000', // Red
            '#0000FF', // Blue
            '#008000', // Green
            '#FFA500', // Orange
            '#800080', // Purple
            '#00FFFF', // Cyan
            '#FF00FF', // Magenta
            '#00FF00', // Lime
            '#FF1493', // DeepPink
            '#008080', // Teal
            '#FFD700', // Gold
            '#4B0082', // Indigo
            '#DC143C', // Crimson
            '#1E90FF'  // DodgerBlue
        ];

        function getLineColor(str, boolVal) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                hash = str.charCodeAt(i) + ((hash << 5) - hash);
            }
            // Ensure positive
            hash = Math.abs(hash);
            return boolVal ? LINE_COLORS[hash % LINE_COLORS.length] : '#AAA';
        }

        async function loadLines() {
            const urlParams = new URLSearchParams(window.location.search);
            const targetLine = urlParams.get('line');
            const targetTripId = urlParams.get('tripId');
            const isFiltered = targetLine || targetTripId;

            try {
                // Passa i parametri GET attuali (line, tripId) all'API
                const response = await fetch('/api/lines-shapes' + window.location.search);
                if (!response.ok) throw new Error('Network response was not ok');
                
                const shapes = await response.json();
                let targetBounds = null;
                
                // Se c'è un filtro attivo, vogliamo che le linee restituite siano "evidenziate" (isTarget = true)
                // Se NON c'è filtro, nessuno è target (isTarget = false), stile standard
                
                shapes.forEach(shape => {
                    if (shape.path && shape.path.length > 0) {
                        const latlngs = shape.path.map(p => [p.lat, p.lng]);
                        
                        // Se abbiamo filtrato lato server, tutto ciò che arriva è il nostro target.
                        // Altrimenti (visualizzazione completa), nessuno è target specifico.
                        const isTarget = isFiltered ? true : false;
                        
                        const color = getLineColor(shape.route_short_name, isTarget || !isFiltered); 
                        // Se !isFiltered (vista completa), passiamo true a getLineColor per avere i colori. 
                        // Se isFiltered, isTarget è true, quindi passiamo true.
                        // Quindi sempre true? No, nel vecchio codice: getLineColor(..., isTarget||!targetLine)
                        // Se c'è un targetLine ma QUESTA non lo è, passava false (grigio).
                        // Qui se c'è isFiltered, abbiamo SOLO le linee giuste, quindi sempre colorato.
                        
                        const weight = isTarget ? 8 : 4;
                        const opacity = isTarget ? 1 : 0.7; // Se non filtrato, opacità standard 0.7
                        const zIndex = isTarget ? 10000 : 1;
                        
                        const polyline = L.polyline(latlngs, {
                            color: color,
                            weight: weight,
                            opacity: opacity,
                            zIndex: zIndex
                        }).addTo(map);
                        
                        if (isTarget) {
                            // Espandi bounds per includere tutte le parti del target (es. se query ha più shape)
                            if (!targetBounds) {
                                targetBounds = polyline.getBounds();
                            } else {
                                targetBounds.extend(polyline.getBounds());
                            }
                            polyline.bringToFront();
                        }
                        
                        polyline.bindPopup(`
                            <div class="line-popup">
                                <div class="line-popup-title">Linea ${shape.route_short_name}</div>
                                <div class="line-popup-desc">${shape.route_long_name}</div>
                            </div>
                        `);
                        
                        // Highlight on hover
                        polyline.on('mouseover', function(e) {
                            var layer = e.target;
                            layer.setStyle({
                                weight: 7,
                                opacity: 1
                            });
                            layer.bringToFront();
                        });

                        polyline.on('mouseout', function(e) {
                            var layer = e.target;
                            layer.setStyle({
                                weight: weight,
                                opacity: opacity
                            });
                            if (!isTarget && isFiltered) {
                                // Should not happen given logic, but safe keep
                            }
                        });
                    }
                });
                
                if (targetBounds) {
                    map.fitBounds(targetBounds, {padding: [50, 50]});
                }
                
                document.getElementById('loading').style.display = 'none';
                
            } catch (error) {
                console.error('Error loading lines:', error);
                document.getElementById('loading').innerHTML = '<div style="color: red;">Errore caricamento linee</div>';
            }
        }

        loadLines();
    </script>
</body>
</html>
