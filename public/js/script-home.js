/**
 * Logica principale della Home Page.
 * Gestisce i preferiti, la mappa delle fermate vicine e gli avvisi di servizio.
 */

/**
 * Gestione UI Preferiti
 */

function renderFavorites() {
    const favorites = JSON.parse(localStorage.getItem('favorite_stops') || '[]');
    const section = document.getElementById('favorites-section');
    const list = document.getElementById('favorites-list');
    const hr = document.getElementById('hr_favorites');

    if (!section || !list) return;

    if (favorites.length === 0) {
        section.style.display = 'none';
        if (hr) hr.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    if (hr) hr.style.display = 'block';
    
    list.innerHTML = favorites.map(stop => createFavoriteCardHTML(stop)).join('');
}

function createFavoriteCardHTML(stop) {
    const ids = stop.ids || [stop.id];
    const encodedName = encodeURIComponent(stop.name);
    const idBadges = ids.map(id => 
        `<div class="id-badge-small">${id}</div>`
    ).join('');

    return `
        <a href="/aut/stops/stop?id=${ids.join('-')}&name=${encodedName}" class="stop-card">
            <div class="d-flex align-items-center w-100">
                <div class="id-badges-container">
                    ${idBadges}
                </div>
                <div class="stop-info ms-3">
                    <span class="stop-name d-block">${stop.name}</span>
                    <span class="stop-desc">★ Preferita</span>
                </div>
            </div>
            <div class="chevron">›</div>
        </a>
    `;
}

/**
 * Gestione Mappa e Geolocalizzazione
 */

async function initHomeMap() {
    renderFavorites();

    const map = L.map('map', { 
        attributionControl: false, 
        fullscreenControl: { pseudoFullscreen: true } 
    }).setView([45.4384, 12.3359], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    const stations = await fetchAllStations();
    let userMarker = null;

    // Stili icone mappa
    const stopStyle = { color: '#0078A8', fillColor: '#0078A8', fillOpacity: 0.3, radius: 6 };
    const nearStopStyle = { color: '#E60000', fillColor: '#E60000', fillOpacity: 0.7, radius: 6 };

    // Disegna tutte le stazioni
    stations.forEach(s => {
        s.marker = L.circleMarker([s.lat, s.lng], stopStyle)
            .bindPopup(createPopupContent(s))
            .addTo(map);
    });

    // Localizzazione utente
    map.locate({ setView: true, maxZoom: 15 });

    map.on('locationfound', (e) => {
        if (userMarker) map.removeLayer(userMarker);
        
        userMarker = L.circleMarker(e.latlng, {
            color: '#009E61', fillColor: '#009E61', fillOpacity: 0.9, radius: 8
        }).addTo(map).bindPopup("La tua posizione").openPopup();

        // Trova le 3 più vicine
        const near = stations.map(s => ({
            ...s,
            distance: calculateDistance(e.latlng.lat, e.latlng.lng, s.lat, s.lng)
        })).sort((a, b) => a.distance - b.distance).slice(0, 3);

        updateNearbyUI(near, map, e.latlng);
        
        // Evidenzia sulla mappa
        near.forEach(s => s.marker.setStyle(nearStopStyle).setPopupContent(createPopupContent(s, true)));

        // Mostra banner successo temporaneo
        showStatusBanner("Posizione trovata. Ecco le stazioni più vicine.");
    });

    map.on('locationerror', (e) => console.warn("Errore localizzazione:", e.message));
}

/** Carica l'elenco fermate dal backend ACTV */
async function fetchAllStations() {
    try {
        const response = await fetch('https://oraritemporeale.actv.it/aut/backend/page/stops');
        if (!response.ok) return [];
        const data = await response.json();
        return (data || []).map(s => ({
            id: s.name,
            name: s.description.replace(/\[\d+\]/g, '').trim(),
            lat: s.latitude,
            lng: s.longitude
        }));
    } catch (e) {
        return [];
    }
}

/** Aggiorna la lista testuale delle fermate vicine */
function updateNearbyUI(near, map, userPos) {
    const list = document.getElementById('nearby-list');
    const section = document.getElementById('nearby-section');
    const hr = document.getElementById('hr_nearby');

    if (!list || near.length === 0) return;

    section.style.display = 'block';
    if (hr) hr.style.display = 'block';

    list.innerHTML = near.map(stop => {
        const ids = stop.id.split('-web-aut')[0].split('-');
        const idBadges = ids.map(id => `<div class="id-badge-small">${id}</div>`).join('');
        
        return `
            <a href="/aut/stops/stop?id=${ids.join('-')}&name=${encodeURIComponent(stop.name)}" class="stop-card">
                <div class="d-flex align-items-center w-100">
                    <div class="id-badges-container">${idBadges}</div>
                    <div class="stop-info ms-3">
                        <span class="stop-name d-block">${stop.name}</span>
                        <span class="stop-desc">${stop.distance.toFixed(2)} km</span>
                    </div>
                </div>
                <div class="chevron">›</div>
            </a>
        `;
    }).join('');

    // Adatta mappa per mostrare utente e fermate vicine
    const bounds = L.latLngBounds([userPos]);
    near.forEach(s => bounds.extend([s.lat, s.lng]));
    map.fitBounds(bounds, { padding: [40, 40] });
}

function createPopupContent(station, includeDist = false) {
    const cleanId = station.id.split('-web-aut')[0];
    return `
        <b>Stazione:</b> ${station.name}<br>
        ${includeDist ? `Distanza: ${station.distance.toFixed(2)} km<br>` : ''}
        <a href="/aut/stops/stop?id=${cleanId}&name=${encodeURIComponent(station.name)}">Vedi passaggi</a>
    `;
}

function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function showStatusBanner(text) {
    const el = document.getElementById('status');
    if (!el) return;
    el.className = "alert alert-success d-flex align-items-center animate__animated animate__fadeIn m-3";
    el.innerHTML = `<div>${text}</div>`;
    setTimeout(() => el.classList.replace('animate__fadeIn', 'animate__fadeOut'), 3000);
    setTimeout(() => el.style.display = 'none', 3500);
}

/**
 * Gestione Avvisi Importanti
 */

async function checkImportantNotices() {
    try {
        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/page/terminal-cialdini-web`, { cache: 'no-cache' });
        if (!response.ok) return;
        const data = await response.json();
        if (!data.text) return;

        const lines = data.text.split('\n');
        const link = lines[0]?.match(/href="([^"]*)"/)?.[1] || "";
        const title = lines[1] || "Avviso di servizio";
        const content = lines[2]?.split("u>")?.[1]?.slice(0, -2) || lines[2] || "";

        const btn = document.getElementById('important-info-btn');
        if (btn) {
            btn.classList.remove('hidden');
            btn.onclick = () => showNoticeModal(title, content, link);
        }
    } catch (e) {
        console.error("Errore fetch avvisi:", e);
    }
}

function showNoticeModal(title, content, link) {
    const toast = document.getElementById('important-info-toast');
    if (!toast) return;
    toast.style.display = 'flex';
    toast.innerHTML = `
        <div class="card p-3 shadow-lg" style="max-width: 90%;">
            <h5 class="fw-bold">${title}</h5>
            <p>${content} ${link ? `<a href="${link}" target="_blank">clicca qui</a>` : ''}</p>
            <p class="small text-muted">Nota: Servizio soggetto a variazioni.</p>
            <button class="btn btn-primary" onclick="document.getElementById('important-info-toast').style.display='none'">Ho capito</button>
        </div>
    `;
}

// Inizializzazione Completa
window.onload = () => {
    initHomeMap();
    checkImportantNotices();
};