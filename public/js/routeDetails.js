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
        if (durationEl) durationEl.innerHTML = `${getClockIcon()} ${formatRouteDuration(route)}`;

        renderRouteTimeline(route, origin, destination);

        // Info "parti da qui" basata sulla geolocalizzazione (solo se vicino)
        requestUserLocation();

    } catch (e) {
        console.error("Errore init routeDetails:", e);
        if (window.actvAlert) actvAlert('Si è verificato un errore nel caricamento del percorso.', 'Impossibile caricare il percorso');
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

function routeTransitLegs(route) {
    return (route?.legs || []).filter(leg => leg.type !== 'walking');
}

function routeTimeMinutes(value) {
    const parts = String(value || '').split(':').map(Number);
    return Number.isFinite(parts[0]) && Number.isFinite(parts[1]) ? parts[0] * 60 + parts[1] : 0;
}

function routeDurationText(minutes) {
    minutes = Math.max(0, Math.round(minutes || 0));
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const rest = minutes % 60;
    const parts = [];
    if (days) parts.push(`${days} ${days === 1 ? 'giorno' : 'giorni'}`);
    if (hours) parts.push(`${hours} h`);
    if (rest || !parts.length) parts.push(`${rest} min`);
    return parts.join(' ');
}

function formatRouteDuration(route) {
    const legs = routeTransitLegs(route);
    if (!legs.length) return routeDurationText(route?.duration || 0);
    const start = routeTimeMinutes(legs[0].departure_time);
    let end = routeTimeMinutes(legs[legs.length - 1].arrival_time);
    while (end < start) end += 1440;
    const requested = routeTimeMinutes(localStorage.getItem('route_departure_time'));
    let waiting = start - requested + (Number(route.day_offset) || 0) * 1440;
    if (waiting < 0) waiting += 1440;
    return `${routeDurationText(end - start)} (${routeDurationText(waiting)} da ora)`;
}

/** Convenzione colori linee (coerente con la pagina risultati / fermata). */
function getLineBadge(lineRaw) {
    if (!lineRaw) return { name: '?', class: 'badge-red' };
    if (lineRaw === 'Cammina') return { name: getWalkingIcon(), class: 'badge-walking' };

    const [lineName, lineTag] = String(lineRaw).split('_');

    let badgeClass = 'badge-red';
    // Extraurbano (blu/azzurro): tag US/UN/EN oppure nome che termina con 'E' (es. 5E)
    if (['US', 'UN', 'EN'].includes(lineTag) || /E$/i.test(lineName)) badgeClass = 'badge-blue';
    // Notturne
    if (/^N/i.test(lineName)) badgeClass = 'badge-night';

    return { name: lineName, class: badgeClass };
}

function getWalkingIcon() {
    return '<svg class="route-icon walking-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">' +
        '<path fill-rule="evenodd" clip-rule="evenodd" d="M13 6C14.1046 6 15 5.10457 15 4C15 2.89543 14.1046 2 13 2C11.8955 2 11 2.89543 11 4C11 5.10457 11.8955 6 13 6ZM11.0528 6.60557C11.3841 6.43992 11.7799 6.47097 12.0813 6.68627L13.0813 7.40056C13.3994 7.6278 13.5559 8.01959 13.482 8.40348L12.4332 13.847L16.8321 20.4453C17.1384 20.9048 17.0143 21.5257 16.5547 21.8321C16.0952 22.1384 15.4743 22.0142 15.168 21.5547L10.5416 14.6152L9.72611 13.3919C9.58336 13.1778 9.52866 12.9169 9.57338 12.6634L10.1699 9.28309L8.38464 10.1757L7.81282 13.0334C7.70445 13.575 7.17759 13.9261 6.63604 13.8178C6.09449 13.7095 5.74333 13.1825 5.85169 12.641L6.51947 9.30379C6.58001 9.00123 6.77684 8.74356 7.05282 8.60557L11.0528 6.60557ZM16.6838 12.9487L13.8093 11.9905L14.1909 10.0096L17.3163 11.0513C17.8402 11.226 18.1234 11.7923 17.9487 12.3162C17.7741 12.8402 17.2078 13.1234 16.6838 12.9487ZM6.12844 20.5097L9.39637 14.7001L9.70958 15.1699L10.641 16.5669L7.87159 21.4903C7.60083 21.9716 6.99111 22.1423 6.50976 21.8716C6.0284 21.6008 5.85768 20.9911 6.12844 20.5097Z" fill="currentColor"/>' +
        '</svg>';
}

function getClockIcon() {
    return '<svg class="route-icon clock-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">' +
        '<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="2"/>' +
        '<path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
        '</svg>';
}

function getLineBadgeStyle(leg) {
    const color = String(leg?.route_color || '').trim();
    const text = String(leg?.route_text_color || '').trim();
    const styles = [];
    const normalizeColor = value => /^#[0-9a-f]{6}$/i.test(value)
        ? value : (/^[0-9a-f]{6}$/i.test(value) ? `#${value}` : '');
    const background = normalizeColor(color);
    const foreground = normalizeColor(text);
    if (background) {
        styles.push(`background-color:${background}`);
        styles.push(`color:${foreground || '#FFFFFF'}`);
    }
    return styles.length ? ` style="${styles.join(';')}"` : '';
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
            <div class="line-badge ${badge.class}"${getLineBadgeStyle(leg)}>${badge.name}</div>
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
            <span class="leave-info-icon">${getWalkingIcon()}</span>
            <span><strong>~${info.walkMin} min</strong> a piedi fino alla fermata (${distStr})</span>
        </div>
        <div class="leave-info-row">
            <span class="leave-info-icon">${getClockIcon()}</span>
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

async function initMap() {
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

    const geometries = await fetchRouteGeometries(CURRENT_ROUTE);
    if (geometries.length) {
        const fallbackColors = ['#00D4FF', '#FFB000', '#FF4F81', '#B9FF3D', '#A78BFA', '#F97316'];
        let previousColor = '';
        let fallbackIndex = 0;
        geometries.forEach(geometry => {
            let color = normalizeMapColor(geometry.color) || '#009E61';
            if (color.toUpperCase() === previousColor.toUpperCase()) {
                do {
                    color = fallbackColors[fallbackIndex++ % fallbackColors.length];
                } while (color.toUpperCase() === previousColor.toUpperCase());
            }
            previousColor = color;
            const service = geometry.service === 'navigation' ? 'Navigazione' : 'Automobilistico';
            const mode = geometry.mode === 'water' ? 'Vaporetto' : 'Bus';
            const popup = `<strong>${escapeMapHtml(geometry.route_short_name || mode)}</strong><br>` +
                `${service} · ${mode}<br>` +
                `${escapeMapHtml(geometry.origin || '')} → ${escapeMapHtml(geometry.destination || '')}<br>` +
                `${formatShortTime(geometry.departure_time)} – ${formatShortTime(geometry.arrival_time)}` +
                (geometry.trip_id ? `<br><small>Corsa: ${escapeMapHtml(geometry.trip_id)}</small>` : '');
            L.polyline(geometry.points, {
                color, weight: 5, opacity: 0.9
            }).addTo(mapInstance).bindPopup(popup);

            // Hit area più ampia della linea visibile: rende il tap affidabile
            // anche su schermi piccoli senza modificare la grafica della mappa.
            L.polyline(geometry.points, {
                color: '#000000',
                weight: 22,
                opacity: 0,
                interactive: true
            }).addTo(mapInstance).bindPopup(popup);
            points.push(...geometry.points);
        });
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

function normalizeMapColor(value) {
    const color = String(value || '').trim();
    if (/^#[0-9a-f]{6}$/i.test(color)) return color;
    return /^[0-9a-f]{6}$/i.test(color) ? `#${color}` : '';
}

function escapeMapHtml(value) {
    return String(value || '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
}

async function fetchRouteGeometries(route) {
    const legs = (route?.legs || []).filter(leg => leg.type !== 'walking');
    const tripIds = [...new Set(legs.map(leg => String(leg.trip_id || '')).filter(Boolean))];
    if (!tripIds.length) return [];

    try {
        const groups = new Map();
        legs.forEach(leg => {
            const service = leg.service === 'navigation' || leg.mode === 'water' ? 'navigation' : 'automobilistico';
            if (!groups.has(service)) groups.set(service, []);
            groups.get(service).push(String(leg.trip_id));
        });
        const responses = await Promise.all([...groups.entries()].map(async ([service, ids]) => {
            const params = new URLSearchParams({ tripIds: ids.join(',') });
            if (service === 'navigation') params.set('service', 'navigation');
            const response = await fetch(`/api/lines-shapes?${params.toString()}`, { cache: 'no-store' });
            let data = [];
            if (response.ok) {
                try {
                    const responseText = await response.text();
                    const parsed = JSON.parse(responseText);
                    data = Array.isArray(parsed) ? parsed : [];
                } catch (parseError) {
                    console.warn('[ACTV] Risposta geometrie non JSON, uso la cache:', parseError);
                }
            }
            data = Array.isArray(data) ? data : [];

            // Il database può non avere ancora la corsa selezionata, mentre
            // la cache GTFS sì: completa solo le corse mancanti dalla cache.
            const found = new Set(data.map(shape => String(shape.trip_id || '')));
            if (ids.some(id => !found.has(String(id)))) {
                const cacheParams = new URLSearchParams({ tripIds: ids.join(','), cache: '1' });
                if (service === 'navigation') cacheParams.set('service', 'navigation');
                const cacheResponse = await fetch(`/api/lines-shapes?${cacheParams.toString()}`, { cache: 'no-store' });
                if (cacheResponse.ok) {
                    try {
                        const cached = JSON.parse(await cacheResponse.text());
                        if (Array.isArray(cached)) {
                            data = data.concat(cached.filter(shape => !found.has(String(shape.trip_id || ''))));
                        }
                    } catch (parseError) {
                        console.warn('[ACTV] Cache geometrie non JSON:', parseError);
                    }
                }
            }
            return data;
        }));
        const shapes = responses.flat();

        const colors = ['#00D4FF', '#FFB000', '#FF4F81', '#B9FF3D'];
        const legByTrip = new Map(legs.map(leg => [String(leg.trip_id), leg]));
        return shapes
            .filter(shape => tripIds.includes(String(shape.trip_id)))
            .map((shape, shapeIndex) => {
                const rawPoints = Array.isArray(shape.path) && shape.path.length > 1
                    ? shape.path : (shape.shape || []);
                let points = rawPoints
                    .map(point => [Number(point.lat), Number(point.lng)])
                    .filter(point => point.every(Number.isFinite));
                const leg = legByTrip.get(String(shape.trip_id));

                // La risposta path contiene le fermate dell'intera corsa:
                // limita il disegno alla tratta effettivamente utilizzata.
                if (Array.isArray(shape.path) && leg && shape.path.length > 1) {
                    const normalize = value => String(value || '').trim().toLowerCase();
                    const origin = normalize(leg.origin);
                    const destination = normalize(leg.destination);
                    const originId = String(leg.origin_id || '');
                    const destinationId = String(leg.destination_id || '');
                    const startIndexById = originId
                        ? shape.path.findIndex(stop => String(stop.stop_id || '') === originId) : -1;
                    const endIndexById = destinationId
                        ? shape.path.findIndex((stop, index) => index >= Math.max(0, startIndexById) && String(stop.stop_id || '') === destinationId) : -1;
                    const startIndex = startIndexById >= 0
                        ? startIndexById : shape.path.findIndex(stop => normalize(stop.name) === origin);
                    const endIndex = endIndexById >= 0
                        ? endIndexById : shape.path.findIndex((stop, index) =>
                            index >= Math.max(0, startIndex) && normalize(stop.name) === destination
                        );
                    if (startIndex >= 0 && endIndex > startIndex) {
                        const selectedStops = shape.path.slice(startIndex, endIndex + 1);
                        const refined = Array.isArray(shape.shape) ? shape.shape : [];
                        if (refined.length > 1) {
                            const distance = (a, b) => (Number(a.lat) - Number(b.lat)) ** 2 + (Number(a.lng) - Number(b.lng)) ** 2;
                            const nearest = stop => refined.reduce((best, point, index) =>
                                distance(point, stop) < distance(refined[best], stop) ? index : best, 0);
                            const refinedStart = nearest(selectedStops[0]);
                            const refinedEnd = nearest(selectedStops[selectedStops.length - 1]);
                            const from = Math.min(refinedStart, refinedEnd);
                            const to = Math.max(refinedStart, refinedEnd);
                            points = refined.slice(from, to + 1)
                                .map(point => [Number(point.lat), Number(point.lng)])
                                .filter(point => point.every(Number.isFinite));
                        } else {
                            points = selectedStops
                                .map(point => [Number(point.lat), Number(point.lng)])
                                .filter(point => point.every(Number.isFinite));
                        }
                    }
                }
                return {
                    points,
                    color: normalizeMapColor(shape.route_color) || normalizeMapColor(leg?.route_color) || colors[shapeIndex % colors.length],
                    route_short_name: shape.route_short_name || leg?.route_short_name,
                    service: leg?.service || shape.service,
                    mode: leg?.mode || shape.mode,
                    trip_id: shape.trip_id || leg?.trip_id,
                    origin: leg?.origin,
                    destination: leg?.destination,
                    departure_time: leg?.departure_time,
                    arrival_time: leg?.arrival_time
                };
            })
            .filter(geometry => geometry.points.length > 1);
    } catch (error) {
        console.warn('[ACTV] Geometria percorso non disponibile:', error);
        return [];
    }
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatItalianDate, formatShortTime, getLineBadge, renderLeg,
        renderRouteTimeline, parseCoords, haversineMeters, getBoardingTime, computeLeaveInfo
    };
}
