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

        stopCard.innerHTML = /*html*/`
            <div class="d-flex align-items-center" style="width: 100%; position: relative;">
                <div style="display: flex; flex-direction: column; gap: 4px; min-width: 60px; align-items: center;">
                    ${idBadgesHtml}
                </div>
                <div class="stop-info ms-3" style="flex-grow: 1;">
                    <span class="stop-name d-block">${stop.name}</span>
                    <span class="stop-desc">★ Preferita</span>
                </div>
            </div>
            <div style="display: flex; align-items: center;">
                <span style="font-size: 20px; color: #ccc;">&rsaquo;</span>
            </div>
        `;
        /*
            <div class="favorite-btn favorited" style="position: absolute; top: 0; right: 0; padding: 5px; font-size: 20px; color: var(--color-gold);">
                <span>★</span>
            </div>
        `; */

        /*
        stopCard.querySelector('.favorite-btn').addEventListener('click', () => {
            let favorites = getFavorites();
            const favoriteBtn = stopCard.querySelector('.favorite-btn');
            let stationId = stop.id;
            let stationName = stop.name;

            if (favoriteBtn.classList.contains('favorited')) {
                // Remove from favorites
                favorites = favorites.filter(fav => {
                    if (fav.ids && Array.isArray(fav.ids)) {
                        return !fav.ids.some(id => stationId.includes(id) || id === stationId.split('-')[0]);
                    }
                    return fav.id !== stationId && !stationId.includes(fav.id);
                });
                favoriteBtn.classList.remove('favorited');
                favoriteBtn.title = 'Aggiungi ai preferiti';
            } else {
                // Add to favorites
                // Parse IDs from stationId (format: "4825" or "4825-4826")
                const ids = stationId.split('-');
                favorites.push({
                    id: ids[0],
                    ids: ids,
                    name: stationName || `Fermata ${stationId}`
                });
                favoriteBtn.classList.add('favorited');
                favoriteBtn.title = 'Rimuovi dai preferiti';
            }

            localStorage.setItem('favorite_stops', JSON.stringify(favorites));
        });*/

        favoritesList.appendChild(stopCard);
    });
}

// Favorite management functions
/*
 function getFavorites() {
    const favorites = localStorage.getItem('favorite_stops');
    return favorites ? JSON.parse(favorites) : [];
}

function isFavorite() {
    const favorites = getFavorites();
    // Check if any favorite has this ID (could be in ids array)
    return favorites.some(fav => {
        if (fav.ids && Array.isArray(fav.ids)) {
            return fav.ids.some(id => stationId.includes(id) || id === stationId.split('-')[0]);
        }
        return fav.id === stationId || stationId.includes(fav.id);
    });
} */

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

            returnArray.push({ id: id, name: name, lat: lat, lng: lng, lines: lines });
        });
        return returnArray;

    } catch (error) {
        console.error(error.message);
        return [];
    }
}

window.onload = async function () {
    // Render favorites first
    renderFavorites();

    const statusElement = document.getElementById('status');

    // Inizializza Mappa
    var map = L.map('map', { attributionControl: false, fullscreenControl: { pseudoFullscreen: true } }).setView([45.4384, 12.3359], 12); // Venezia centro

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Recupera stazioni
    let actvStations = await getStations();

    // Variabile per il marcatore della posizione utente
    var userMarker = null;

    // Imposta il numero di stazioni più vicine da evidenziare
    const N = 3;

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
        radius: 6
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
    if (actvStations) {
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
            statusElement.style.position = 'absolute';
            statusElement.style.visibility = 'hidden';
            console.log("Status hidden???");

        }, 2500);

        if (userMarker) map.removeLayer(userMarker);

        userMarker = L.circleMarker(e.latlng, {
            color: '#009E61',
            fillColor: '#009E61',
            fillOpacity: 0.9,
            radius: 10
        }).addTo(map).bindPopup("Sei Qui").openPopup();

        if (actvStations) {
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
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }

    function onLocationError(e) {
        console.error("Errore geolocalizzazione:", e.message);
    }

    // Request location
    map.locate({ setView: true, maxZoom: 16, maximumAge: 60000, timeout: 5000 });
    map.on('locationfound', onLocationFound);
    map.on('locationerror', onLocationError);
};