<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mappa Stazioni ACTV</title>
    <!-- CSS di Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php require COMMON_HTML_HEAD; ?>
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
        
        <!-- Sezione Fermate Preferite -->
        <div id="favorites-section" style="display: none;">
            <div class="section-title">Fermate Preferite</div>
            <div id="favorites-list"></div>
        </div>
        
        <hr>
        
        <!-- Sezione Fermate Vicine (Dinamica) -->
        <div id="nearby-section">
            <div class="section-title">Fermate più vicine</div>
            <div id="nearby-list"></div>
        </div>

        <hr>
        
        <div class="section-title">Mappa</div>
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
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-outline-primary rounded-pill px-4 py-2" onclick="window.location.href='/stopList'" style="width: 100%;">
                Vedi tutte le stazioni
            </button>
        </div>

		<!-- Pulsante Trova Percorso -->
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-primary rounded-pill px-4 py-2" onclick="window.location.href='/route-finder'">
                Trova percorso
            </button>
        </div>

        <!-- Pulsante Mappa Linee -->
        <div class="text-center mt-4 mb-3">
            <button class="btn btn-secondary rounded-pill px-4 py-2" onclick="window.location.href='/lines-map'">
                Mappa linee (NON definitivo)
            </button>
        </div>

    </div>

    <!-- JavaScript di Leaflet -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="/components/StopCard.js"></script>
    
    <script>
        // Render favorites
        function renderFavorites() {
            const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
            const favoritesSection = document.getElementById('favorites-section');
            const favoritesList = document.getElementById('favorites-list');
            
            if (favorites.length === 0) {
                favoritesSection.style.display = 'none';
                return;
            }
            
            favoritesSection.style.display = 'block';
            favoritesList.innerHTML = '';
            
            favorites.forEach(stop => {
                const stopCard = document.createElement('a');
                stopCard.href = `/aut/stops/stop?id=${stop.ids.join('-')}&name=${encodeURIComponent(stop.name)}`;
                stopCard.className = 'stop-card';
                stopCard.style.textDecoration = 'none';
                stopCard.style.color = 'inherit';
                
                // Create ID badges HTML
                const idBadgesHtml = stop.ids.map(id => 
                    `<div style="background: #007bff; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; text-align: center; min-width: 50px; line-height: 1.2;">${id}</div>`
                ).join('');
                
                stopCard.innerHTML = `
                    <div class="d-flex align-items-center" style="width: 100%;">
                        <div style="display: flex; flex-direction: column; gap: 4px; min-width: 60px; align-items: center;">
                            ${idBadgesHtml}
                        </div>
                        <div class="stop-info ms-3" style="flex-grow: 1;">
                            <span class="stop-name d-block">${stop.name}</span>
                            <span class="stop-desc">★ Preferita</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <span style="font-size: 20px; color: #ccc;">›</span>
                    </div>
                `;
                
                favoritesList.appendChild(stopCard);
            });
        }
    
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
            // Render favorites first
            renderFavorites();
            
            const statusElement = document.getElementById('status');
            
            // Inizializza Mappa
            var map = L.map('map', {attributionControl: false}).setView([45.4384, 12.3359], 12); // Venezia centro

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
                        .bindPopup(`<b>Stazione:</b> ${station.name}`)
                        .addTo(map);
                });
            }

            // Gestione Posizione Trovata
            function onLocationFound(e) {
                const userLat = e.latlng.lat;
                const userLng = e.latlng.lng;

                statusElement.className = "alert alert-success d-flex align-items-center mx-3 mb-3";
                statusElement.innerHTML = /*html*/`
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill me-2" viewBox="0 0 16 16">
                      <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                    </svg>
                    <div>Posizione trovata. Ecco le stazioni più vicine.</div>`;

                setTimeout(() => {
                    statusElement.style.display = 'none';
                }, 5000);

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
                    
                    // Aggiorna lista visuale con StopListItem
                    const closestStationsList = document.getElementById('nearby-list');
                    if (closestStationsList) {
                        console.log(closestStations);
                        
                        closestStationsList.innerHTML = '';
                        closestStations.forEach(stop => {
                            console.log(stop);
                            
                            let stopIds = stop.id.split('-web-aut')[0].split('-');

                            const stopCard = document.createElement('a');
                            stopCard.href = `/aut/stops/stop?id=${stopIds.join('-')}&name=${encodeURIComponent(stop.name)}`;
                            stopCard.className = 'stop-card';
                            stopCard.style.textDecoration = 'none';
                            stopCard.style.color = 'inherit';
                            
                            // Create ID badges HTML
                            const idBadgesHtml = stopIds.map(id => 
                                `<div style="background: #007bff; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; text-align: center; min-width: 50px; line-height: 1.2;">${id}</div>`
                            ).join('');
                            
                            stopCard.innerHTML = `
                                <div class="d-flex align-items-center" style="width: 100%;">
                                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 60px; align-items: center;">
                                        ${idBadgesHtml}
                                    </div>
                                    <div class="stop-info ms-3" style="flex-grow: 1;">
                                        <span class="stop-name d-block">${stop.name}</span>
                                        <span class="stop-desc">${stop.distance.toFixed(2)} km</span>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <span style="font-size: 20px; color: #ccc;">›</span>
                                </div>
                            `;
                            
                            closestStationsList.appendChild(stopCard);
                            
                        });
                    }

                    if (closestStations != null && closestStations.length <= 0) {
                        document.getElementById('nearby-section').style.display = 'none';
                    }

                    // Ridisegna stazioni
                    actvStations.forEach(station => {
                        const isClosest = closestStations.includes(station);
                        const style = isClosest ? highlightedCircleStyle : defaultCircleStyle;
                        const popupText = isClosest
                            ? `<b>Stazione:</b> ${station.name}<br>Distanza: ${station.distance.toFixed(2)} km<br>Link: <a href="/aut/stops/stop?id=${station.id.split('-web-aut')[0]}&name=${encodeURIComponent(station.name)}">${station.id.split('-web-aut')[0]}</a>`
                            : `<b>Stazione:</b> ${station.name}<br>Link: <a href="/aut/stops/stop?id=${station.id.split('-web-aut')[0]}&name=${encodeURIComponent(station.name)}">${station.id.split('-web-aut')[0]}</a>`;

                        station.marker = L.circleMarker([station.lat, station.lng], style)
                            .bindPopup(popupText)
                            .addTo(map);
                    });

                    // Aggiorna lista visuale
                    if (typeof renderClosestStationsList === 'function') {
                        renderClosestStationsList(closestStations);
                    }

                    // Centra mappa
                    const bounds = L.latLngBounds([e.latlng]);
                    closestStations.forEach(s => bounds.extend([s.lat, s.lng]));
                    map.fitBounds(bounds, {padding: [50, 50]});
                }
            }

            function onLocationError(e) {
                console.error("Errore geolocalizzazione:", e.message);
            }
            
            // Request location
            map.locate({setView: true, maxZoom: 16, maximumAge: 60000, timeout: 5000});
            map.on('locationfound', onLocationFound);
            map.on('locationerror', onLocationError);
        };
    </script>

</body>
</html>