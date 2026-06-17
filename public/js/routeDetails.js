/**
 * Logica per la visualizzazione dettagliata di un percorso calcolato.
 * Renderizza una timeline coerente con la pagina dei risultati, mostra (se
 * l'utente è vicino) l'orario consigliato di partenza a piedi, e una mappa.
 */

let CURRENT_ROUTE = null;
let ORIGIN_COORDS = null;
let DEST_COORDS = null;
let USER_COORDS = null;
let mapInstance = null;
let FAV_ORIGIN = null;
let FAV_DESTINATION = null;

window.addEventListener('DOMContentLoaded', () => {
    try {
        const routeData = localStorage.getItem('selected_route');
        const originData = localStorage.getItem('route_origin');
        const destinationData = localStorage.getItem('route_destination');
        const dateStr = localStorage.getItem('route_departure_date');

        if (!routeData) {
            console.warn("Nessun percorso in cache. Ritorno al cercapercorsi.");
            window.location.href = '/route-finder';
            return;
        }

        const route = JSON.parse(routeData);
        const origin = originData ? JSON.parse(originData) : { name: 'Partenza' };
        const destination = destinationData ? JSON.parse(destinationData) : { name: 'Destinazione' };

        CURRENT_ROUTE = route;
        FAV_ORIGIN = origin;
        FAV_DESTINATION = destination;
        ORIGIN_COORDS = parseCoords(origin);
        DEST_COORDS = parseCoords(destination);

        updateSaveRouteBtn();

        const dateEl = document.getElementById('route-date');
        const durationEl = document.getElementById('route-duration');

        if (dateEl) dateEl.textContent = formatItalianDate(dateStr);
        if (durationEl) durationEl.textContent = `\u23F1 ${Math.round(route.duration)} min`;

        renderRouteTimeline(route, origin, destination);

        // Info "parti da qui" basata sulla geolocalizzazione (solo se vicino)
        requestUserLocation();

    } catch (e) {
        console.error("Errore init routeDetails:", e);
        alert('Si è verificato un errore nel caricamento del percorso.');
    }
});

/** Formatta la data per l'intestazione */
function formatItalianDate(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('it-IT', {
            weekday: 'short',
            day: 'numeric',
            month: 'numeric',
            year: 'numeric'
        });
    } catch (e) {
        return dateStr;
    }
}

function formatShortTime(timeStr) {
    return timeStr ? timeStr.substring(0, 5) : '--:--';
}

/** Convenzione colori linee (coerente con la pagina risultati / fermata). */
function getLineBadge(lineRaw) {
    if (!lineRaw) return { name: '?', class: 'badge-red' };
    if (lineRaw === 'Cammina') return { name: '\u{1F6B6}', class: 'badge-walking' };

    const [lineName, lineTag] = String(lineRaw).split('_');

    let badgeClass = 'badge-red';
    // Extraurbano (blu/azzurro): tag US/UN/EN oppure nome che termina con 'E' (es. 5E)
    if (['US', 'UN', 'EN'].includes(lineTag) || /E$/i.test(lineName)) badgeClass = 'badge-blue';
    // Notturne
    if (/^N/i.test(lineName)) badgeClass = 'badge-night';

    return { name: lineName, class: badgeClass };
}

/** Renderizza la timeline completa del percorso a partire dalle sue tratte (legs). */
function renderRouteTimeline(route, origin, destination) {
    const container = document.getElementById('timeline-container');
    if (!container) return;

    const legs = (route.legs && route.legs.length > 0) ? route.legs : [{
        type: 'bus',
        route_short_name: route.route_short_name,
        stops_count: route.stops_count,
        departure_time: route.departure_time,
        arrival_time: route.arrival_time,
        origin: origin.name,
        destination: destination.name
    }];

    const finalDest = destination.name;
    const html = legs.map((leg, i) => renderLeg(leg, i, legs.length, finalDest)).join('');
    container.innerHTML = `<div class="route-timeline">${html}</div>`;
}

/** Genera l'HTML di una singola tratta (eventuale partenza + connettore + arrivo). */
function renderLeg(leg, index, total, finalDest) {
    const isFirst = index === 0;
    const isLast = index === total - 1;
    const isWalking = leg.type === 'walking';
    const badge = getLineBadge(leg.route_short_name);

    let html = '';

    if (isFirst) {
        html += `
            <div class="timeline-item">
                <div class="timeline-marker start"></div>
                <div class="timeline-content">
                    <div class="stop-name">${leg.origin || 'Partenza'}</div>
                    <div class="stop-time">${formatShortTime(leg.departure_time)}</div>
                </div>
            </div>`;
    }

    const connectorInfo = isWalking
        ? `Cammina per ${Math.round(leg.duration || 0)} min${leg.distance ? ` (${leg.distance} m)` : ''}`
        : `Linea <strong>${badge.name}</strong> &middot; ${leg.stops_count} fermate`;

    html += `
        <div class="timeline-connector">
            <div class="line-badge ${badge.class}">${badge.name}</div>
            <div class="connector-info">${connectorInfo}</div>
        </div>`;

    const markerClass = isLast ? 'end' : 'transfer';
    const arrivalName = leg.destination || (isLast ? finalDest : 'Cambio');

    html += `
        <div class="timeline-item">
            <div class="timeline-marker ${markerClass}"></div>
            <div class="timeline-content">
                <div class="stop-name">${arrivalName}</div>
                <div class="stop-time">${formatShortTime(leg.arrival_time)}</div>
            </div>
        </div>`;

    return html;
}

/* ====================  Geolocalizzazione / "parti da qui"  ==================== */

function parseCoords(d) {
    if (!d) return null;
    const lat = parseFloat(d.lat);
    const lng = parseFloat(d.lng ?? d.lon);
    if (isNaN(lat) || isNaN(lng)) return null;
    return { lat, lng };
}

function haversineMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function timeToSec(t) {
    if (!t) return 0;
    const p = String(t).split(':');
    return (+p[0]) * 3600 + (+p[1]) * 60 + (p[2] ? +p[2] : 0);
}

function secToHHMM(s) {
    s = ((s % 86400) + 86400) % 86400;
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/** Orario di salita sul primo mezzo (prima tratta non a piedi). */
function getBoardingTime(route) {
    if (route && route.legs && route.legs.length) {
        const bus = route.legs.find(l => l.type !== 'walking');
        return (bus || route.legs[0]).departure_time;
    }
    return route ? route.departure_time : null;
}

/**
 * Calcola tempo a piedi e orario di partenza consigliato, solo se la fermata
 * è "relativamente vicina" (entro maxMeters).
 */
function computeLeaveInfo(route, originCoords, userCoords, opts = {}) {
    const { maxMeters = 2000, walkSpeed = 83, bufferMin = 2 } = opts;
    if (!originCoords || !userCoords) return null;

    const dist = Math.round(haversineMeters(userCoords.lat, userCoords.lng, originCoords.lat, originCoords.lng));
    if (dist > maxMeters) return null;

    const walkMin = Math.max(1, Math.ceil(dist / walkSpeed));
    const boarding = getBoardingTime(route);
    const leaveBySec = timeToSec(boarding) - walkMin * 60 - bufferMin * 60;

    return {
        dist,
        walkMin,
        bufferMin,
        leaveBy: secToHHMM(leaveBySec),
        boarding: (boarding || '').substring(0, 5)
    };
}

function requestUserLocation() {
    if (typeof navigator === 'undefined' || !navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        pos => {
            USER_COORDS = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            renderLeaveInfo(computeLeaveInfo(CURRENT_ROUTE, ORIGIN_COORDS, USER_COORDS));
        },
        () => { /* permesso negato o non disponibile: nessun pannello */ },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
    );
}

function renderLeaveInfo(info) {
    const el = document.getElementById('leave-info');
    if (!el) return;
    if (!info) { el.style.display = 'none'; return; }

    const distStr = info.dist >= 1000 ? `${(info.dist / 1000).toFixed(1)} km` : `${info.dist} m`;
    el.innerHTML = `
        <div class="leave-info-title">Dalla tua posizione</div>
        <div class="leave-info-row">
            <span class="leave-info-icon">\u{1F6B6}</span>
            <span><strong>~${info.walkMin} min</strong> a piedi fino alla fermata (${distStr})</span>
        </div>
        <div class="leave-info-row">
            <span class="leave-info-icon">\u23F0</span>
            <span>Parti entro le <strong>${info.leaveBy}</strong> per arrivare con ~${info.bufferMin} min di anticipo <small>(bus alle ${info.boarding})</small></span>
        </div>`;
    el.style.display = 'block';
}

/* ====================  Tragitto preferito  ==================== */

/** Linea principale del percorso (prima tratta non a piedi). */
function routeMainLine(route) {
    if (route && Array.isArray(route.legs)) {
        const bus = route.legs.find(l => l.type !== 'walking');
        if (bus) return bus.route_short_name || null;
    }
    return route ? (route.route_short_name || null) : null;
}

function toggleSaveRoute() {
    if (typeof toggleFavoriteRoute === 'undefined' || !FAV_ORIGIN || !FAV_DESTINATION) return;
    toggleFavoriteRoute(FAV_ORIGIN, FAV_DESTINATION, routeMainLine(CURRENT_ROUTE));
    updateSaveRouteBtn();
}

function updateSaveRouteBtn() {
    const btn = document.getElementById('save-route-btn');
    if (!btn || typeof isFavoriteRoute === 'undefined') return;
    const saved = isFavoriteRoute(FAV_ORIGIN, FAV_DESTINATION);
    btn.classList.toggle('saved', saved);
    btn.innerHTML = saved ? '\u2605 Tragitto salvato' : '\u2606 Salva tragitto';
}

/* ============================  Mappa  ============================ */

function showMap() {
    const modal = document.getElementById('map-modal');
    if (!modal) return;
    modal.classList.add('active');
    // Attende che il contenitore sia visibile/dimensionato prima di inizializzare
    setTimeout(initMap, 60);
}

function closeMap(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('map-modal');
    if (modal) modal.classList.remove('active');
}

function initMap() {
    if (typeof L === 'undefined') return;
    const container = document.getElementById('route-map');
    if (!container) return;

    const points = [];

    if (!mapInstance) {
        mapInstance = L.map('route-map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(mapInstance);
    } else {
        mapInstance.eachLayer(l => { if (!(l instanceof L.TileLayer)) mapInstance.removeLayer(l); });
    }

    if (ORIGIN_COORDS) {
        L.circleMarker([ORIGIN_COORDS.lat, ORIGIN_COORDS.lng], {
            radius: 8, color: '#009E61', fillColor: '#009E61', fillOpacity: 1
        }).addTo(mapInstance).bindPopup('Partenza');
        points.push([ORIGIN_COORDS.lat, ORIGIN_COORDS.lng]);
    }

    if (DEST_COORDS) {
        L.circleMarker([DEST_COORDS.lat, DEST_COORDS.lng], {
            radius: 8, color: '#E30613', fillColor: '#E30613', fillOpacity: 1
        }).addTo(mapInstance).bindPopup('Arrivo');
        points.push([DEST_COORDS.lat, DEST_COORDS.lng]);
    }

    if (ORIGIN_COORDS && DEST_COORDS) {
        L.polyline([[ORIGIN_COORDS.lat, ORIGIN_COORDS.lng], [DEST_COORDS.lat, DEST_COORDS.lng]], {
            color: '#009E61', weight: 4, opacity: 0.8
        }).addTo(mapInstance);
    }

    if (USER_COORDS) {
        L.circleMarker([USER_COORDS.lat, USER_COORDS.lng], {
            radius: 7, color: '#0152BB', fillColor: '#0152BB', fillOpacity: 1
        }).addTo(mapInstance).bindPopup('La tua posizione');
        points.push([USER_COORDS.lat, USER_COORDS.lng]);

        if (ORIGIN_COORDS) {
            L.polyline([[USER_COORDS.lat, USER_COORDS.lng], [ORIGIN_COORDS.lat, ORIGIN_COORDS.lng]], {
                color: '#0152BB', weight: 3, dashArray: '6, 8'
            }).addTo(mapInstance);
        }
    }

    mapInstance.invalidateSize();

    if (points.length > 1) {
        mapInstance.fitBounds(points, { padding: [40, 40] });
    } else if (points.length === 1) {
        mapInstance.setView(points[0], 15);
    } else {
        mapInstance.setView([45.49, 12.24], 12); // Fallback: Venezia
    }
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatItalianDate, formatShortTime, getLineBadge, renderLeg,
        renderRouteTimeline, parseCoords, haversineMeters, getBoardingTime, computeLeaveInfo
    };
}
