<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mappa Stazioni ACTV</title>
    <!-- CSS di Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php require COMMON_HTML_HEAD; ?>
    <style>
        body {
            font-size: 28px;
            line-height: 1.2;
            /*margin-top: 1rem;*/
        }

        /* Header Verde */
        .header-green {
            background: #009E61;
            padding: 2rem 1.5rem 6rem;
            color: white;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
            margin-bottom: -4rem;
        }

        .header-title {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 28px;
            line-height: 1.2;
            margin-top: 1rem;
        }

        /* Sezioni Titoli */
        .section-title {
            font-family: 'SF Pro', sans-serif; /* Fallback se SF Pro non c'è */
            font-weight: 590; /* 600 approx */
            font-size: 20px;
            color: #000000;
            margin: 1.5rem 1.5rem 0.5rem;
        }

        /* Card Fermata */
        .stop-card {
            background: #FFFFFF;
            box-shadow: 2px 0px 9.7px -4px rgba(0, 0, 0, 0.24);
            border-radius: 15px;
            padding: 1rem;
            margin: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: inherit;
            position: relative;
            transition: transform 0.1s;
        }
        
        .stop-card:active {
            transform: scale(0.98);
        }

        .stop-info {
            display: flex;
            flex-direction: column;
        }

        .stop-name {
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 20px;
            color: #000000;
            margin-bottom: 0.2rem;
        }

        .stop-desc {
            font-family: 'SF Pro', sans-serif;
            font-weight: 510; /* 500 approx */
            font-size: 14px;
            color: #666; /* Slightly lighter than black for description */
        }

        /* Badge Linea */
        .line-badge {
            background: #0152BB;
            border-radius: 7px;
            color: #FFFFFF;
            font-family: 'SF Pro', sans-serif;
            font-weight: 700;
            font-size: 14px;
            padding: 2px 8px;
            min-width: 36px;
            text-align: center;
            display: inline-block;
            margin-right: 5px;
        }

        /* Quick Action (Arrow/Icon placeholder) */
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 24px;
            color: #333;
        }

        /* Mappa */
        #map-container {
            padding: 1.5rem;
        }
        #map {
            height: 300px;
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* Status bar */
        #status {
            margin: 0 1.5rem 1rem;
            border-radius: 15px;
        }

    </style>
</head>
<body>

    <!-- Header -->
    <div class="header-green">
        <!-- Logo o Icona Menu (Placeholder basato su spazio vuoto nel design) -->
        <div style="height: 20px;"></div> 
        <div class="header-title">Dove vuoi<br>andare?</div>
    </div>

    <!-- Contenuto Principale -->
    <div class="main-content pb-5">
        
        <!-- Sezione Fermate Vicine (Dinamica) -->
        <div class="section-title">Fermate più vicine</div>
        
        <!-- Status Geolocation -->
        <div id="status" class="alert alert-info d-flex align-items-center" role="alert">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:"><use xlink:href="#info-fill"/></svg>
            <div>
                In attesa di geolocalizzazione...
            </div>
        </div>
    
        <!-- Mappa -->
        <div id="map-container">
            <div id="map"></div>
        </div>
        
        <!-- Pulsante Lista Completa -->
        <div class="text-center mt-4 mb-5">
            <button class="btn btn-outline-primary rounded-pill px-4 py-2" onclick="window.location.href='/stopList'">
                Vedi tutte le stazioni
            </button>
        </div>

		<!-- Pulsante Trova Percorso -->
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-primary rounded-pill px-4 py-2" onclick="window.location.href='/route-finder'">
                Trova percorso
            </button>
        </div>

    </div>

    <!-- JavaScript di Leaflet -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
    <script>
        // Funzione per recuperare le stazioni
        async function getStations() {
            try {
                let response = await fetch('https://oraritemporeale.actv.it/aut/backend/page/stops');
                if (!response.ok) {
                    throw new Error(`Response status: ${response.status}`);
                }
                let rs = await response.json();
                if (rs === null) return [];
                
                let returnArray = [];
                rs.forEach(element => {
                    let id = element.name; // ID is in 'name' field
                    let name = element.description; // Name is in 'description' field
                    let lat = element.latitude;
                    let lng = element.longitude;
                    let lines = element.lines || []; 
					//Remove ids from name
					name = name.replace(/\[\d+\]/g, '').trim(); 
					
                    returnArray.push({id: id, name: name, lat: lat, lng: lng, lines: lines});
                });
                return returnArray;

            } catch (error) {
                console.error(error.message);
                return [];
            }
        }

        window.onload = async function() {
            const statusElement = document.getElementById('status');
            
            // Inizializza Mappa
            var map = L.map('map').setView([45.4384, 12.3359], 12); // Venezia centro

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Recupera stazioni
            let actvStations = await getStations();

            // Variabile per il marcatore della posizione utente
            var userMarker = null;

            // Imposta il numero di stazioni più vicine da evidenziare
            const N = 5;

            // Stili per i marcatori
            const defaultCircleStyle = {
                color: '#0078A8',    // Blu ACTV
                fillColor: '#0078A8',
                fillOpacity: 0.3,
                radius: 6
            };

            const highlightedCircleStyle = {
                color: '#E60000',    // Rosso Evidenziato
                fillColor: '#E60000',
                fillOpacity: 0.7,
                radius: 10
            };

            // Funzione distanza
            function getDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; 
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                          Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return R * c;
            }

            // Aggiungi tutte le stazioni
            if(actvStations) {
                actvStations.forEach(station => {
                    station.marker = L.circleMarker([station.lat, station.lng], defaultCircleStyle)
                        .bindPopup(`**Stazione:** ${station.name}`)
                        .addTo(map);
                });
            }

            // Gestione Posizione Trovata
            function onLocationFound(e) {
                const userLat = e.latlng.lat;
                const userLng = e.latlng.lng;

                statusElement.className = "alert alert-success d-flex align-items-center mx-3 mb-3";
                statusElement.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill me-2" viewBox="0 0 16 16">
                      <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                    </svg>
                    <div>Posizione trovata. Ecco le stazioni più vicine.</div>`;

                if (userMarker) map.removeLayer(userMarker);

                userMarker = L.circleMarker(e.latlng, {
                    color: '#3399FF',
                    fillColor: '#3399FF',
                    fillOpacity: 0.9,
                    radius: 12
                }).addTo(map).bindPopup("Sei Qui").openPopup();

                if(actvStations) {
                    // Calcola distanze
                    actvStations.forEach(station => {
                        station.distance = getDistance(userLat, userLng, station.lat, station.lng);
                        if (station.marker) map.removeLayer(station.marker);
                    });

                    // Trova le più vicine
                    const closestStations = actvStations
                        .sort((a, b) => a.distance - b.distance)
                        .slice(0, N);
                    
                    // Ridisegna stazioni
                    actvStations.forEach(station => {
                        const isClosest = closestStations.includes(station);
                        const style = isClosest ? highlightedCircleStyle : defaultCircleStyle;
                        const popupText = isClosest
                            ? `**Stazione:** ${station.name}<br>Distanza: ${station.distance.toFixed(2)} km`
                            : `**Stazione:** ${station.name}`;

                        station.marker = L.circleMarker([station.lat, station.lng], style)
                            .bindPopup(popupText)
                            .addTo(map);
                    });

                    // Aggiorna lista visuale
                    renderClosestStationsList(closestStations);

                    // Centra mappa
                    const bounds = L.latLngBounds([e.latlng]);
                    closestStations.forEach(s => bounds.extend([s.lat, s.lng]));
                    map.fitBounds(bounds, {padding: [50, 50]});
                }
            }

            function onLocationError(e) {
                console.error("Errore geolocalizzazione:", e.message);
        };
    </script>

</body>
</html>