/**
 * Gestione della visualizzazione dei risultati di ricerca del percorso.
 * Include la funzionalità di confronto side-by-side tra percorsi.
 */

// Handler globale degli errori per facilitare il debugging in produzione
window.onerror = function (msg, url, line, col, error) {
    console.error("Errore JS:", msg, "a", url, line, col);
    const errorBox = document.createElement('div');
    errorBox.style.cssText = "color: red; padding: 20px; background: white; border: 2px solid red; margin: 20px; border-radius: 8px; font-family: sans-serif;";
    errorBox.innerHTML = `
        <h3 style="margin-top:0">Si è verificato un errore</h3>
        <p>${msg}</p>
        <small>Linea: ${line}, Colonna: ${col}</small>
        ${error ? `<pre style="font-size:11px; margin-top:10px; overflow:auto">${error.stack}</pre>` : ''}
    `;
    document.body.appendChild(errorBox);
    return false;
};

let originData = null;
let destinationData = null;
let departureDate = null;
let departureTime = null;

// Stato confronto percorsi
let compareMode = false;
let selectedRoutes = [];
let allRoutes = [];

// Stato viaggio di ritorno
let returnTripEnabled = false;
let returnTime = null;
let outboundRoutes = [];
let returnRoutes = [];
let currentDirection = 'andata'; // 'andata' | 'ritorno'
let activeDestName = '';
let realtimeVehiclesByTrip = new Map();
const ROUTE_RESULTS_CACHE_KEY = 'actv_route_results_cache_v5';
const ROUTE_RESULTS_CACHE_TTL = 5 * 60 * 1000;

/**
 * Inizializzazione della Pagina
 */
window.addEventListener('DOMContentLoaded', async () => {
    try {
        // Carica dati dal localStorage
        originData = safeParseJSON(localStorage.getItem('route_origin'));
        destinationData = safeParseJSON(localStorage.getItem('route_destination'));

        departureDate = localStorage.getItem('route_departure_date') || formatLocalDate(new Date());
        departureTime = localStorage.getItem('route_departure_time') || new Date().toTimeString().slice(0, 5);

        returnTripEnabled = localStorage.getItem('route_return_trip') === '1';
        returnTime = localStorage.getItem('route_return_time') || departureTime;

        if (!originData || !destinationData) {
            showErrorState('Seleziona una partenza e una destinazione valide.');
            return;
        }

        // Aggiorna intestazione UI
        updateHeaderUI();

        // Avvia la ricerca percorsi
        await performRouteSearch();
    } catch (err) {
        console.error("Errore critico durante l'init:", err);
        showErrorState(`Errore inizializzazione: ${err.message}`);
    }
});

function safeParseJSON(str) {
    try {
        return str ? JSON.parse(str) : null;
    } catch (e) {
        console.error("Errore parsing JSON:", e);
        return null;
    }
}

function formatLocalDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function updateHeaderUI() {
    updateSummary(originData, destinationData, departureTime);
}

function updateSummary(fromData, toData, time) {
    const originEl = document.getElementById('origin-name');
    const destEl = document.getElementById('destination-name');
    const dateEl = document.getElementById('datetime-info');

    if (originEl) originEl.textContent = fromData.name;
    if (destEl) destEl.textContent = toData.name;
    if (dateEl) dateEl.textContent = `Partenza: ${formatItalianDate(departureDate)} alle ${time}`;
}

/**
 * Logica di Ricerca
 */
function getRouteParam(data) {
    return (data.type === 'address') ? `${data.lat},${data.lng}` : data.id;
}

function getOptimizeParam() {
    const optimize = localStorage.getItem('route_optimize');
    return (optimize && ['time', 'transfers', 'walking'].includes(optimize)) ? optimize : null;
}

async function fetchRoutes(from, to, date, time, optimize, mode = 'all', fromService = null, toService = null) {
    const params = new URLSearchParams({ from, to, date, time });
    if (optimize) params.set('optimize', optimize);
    params.set('mode', mode);
    if (fromService) params.set('from_service', fromService);
    if (toService) params.set('to_service', toService);
    const cacheKey = params.toString();

    try {
        const cache = JSON.parse(localStorage.getItem(ROUTE_RESULTS_CACHE_KEY) || '{}');
        const cached = cache[cacheKey];
        if (cached && Date.now() - cached.timestamp < ROUTE_RESULTS_CACHE_TTL && Array.isArray(cached.routes) && cached.routes.length > 0) {
            console.log('[ACTV] Risultati percorsi dalla cache:', cacheKey);
            return cached.routes;
        }
    } catch (cacheError) {
        console.warn('[ACTV] Cache percorsi non disponibile:', cacheError);
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    let response;
    try {
        response = await fetch(`/api/plan-route?${params.toString()}`, {
            cache: 'no-store',
            signal: controller.signal
        });
    } catch (error) {
        if (error?.name === 'AbortError') {
            throw new Error('La ricerca ha superato il tempo massimo. Riprova tra poco.');
        }
        throw new Error(navigator.onLine
            ? 'Il servizio percorsi non è raggiungibile. Riprova tra poco.'
            : 'Connessione assente. Controlla la rete e riprova.');
    } finally {
        clearTimeout(timeout);
    }
    const responseText = await response.text();
    console.log('[ACTV] Risposta ricerca percorsi:', {
        url: response.url,
        status: response.status,
        contentType: response.headers.get('content-type'),
        text: responseText
    });
    let data = null;

    try {
        data = responseText.trim() ? JSON.parse(responseText) : null;
    } catch (parseError) {
        // Tolleranza per eventuali warning PHP emessi prima del payload JSON.
        // La risposta server viene comunque corretta lato PHP, ma questo evita
        // di perdere il risultato durante un deploy con codice misto in cache.
        const jsonStart = responseText.search(/[\[{]/);
        if (jsonStart >= 0) {
            try { data = JSON.parse(responseText.slice(jsonStart)); } catch (ignored) { data = null; }
        }
        if (data) {
            if (!data.success) throw new Error(data.error || 'Errore durante la ricerca.');
            return data.routes || [];
        }
        throw new Error(response.ok
            ? 'Il server ha restituito una risposta non valida.'
            : `HTTP ${response.status}: risposta non valida dal server.`);
    }

    if (!response.ok) {
        throw new Error(data?.error || `HTTP ${response.status}: errore durante la ricerca.`);
    }
    if (!data || typeof data !== 'object') {
        throw new Error('Il server ha restituito una risposta vuota.');
    }
    if (!data.success) throw new Error(data.error || 'Errore durante la ricerca.');
    const routes = data.routes || [];
    try {
        const cache = JSON.parse(localStorage.getItem(ROUTE_RESULTS_CACHE_KEY) || '{}');
        if (routes.length > 0) cache[cacheKey] = { timestamp: Date.now(), routes };
        const entries = Object.entries(cache)
            .sort((a, b) => b[1].timestamp - a[1].timestamp)
            .slice(0, 8);
        localStorage.setItem(ROUTE_RESULTS_CACHE_KEY, JSON.stringify(Object.fromEntries(entries)));
    } catch (cacheError) {
        console.warn('[ACTV] Impossibile salvare i risultati in cache:', cacheError);
    }
    return routes;
}

async function performRouteSearch() {
    try {
        const optimize = getOptimizeParam();
        const mode = 'all';
        const fromParam = getRouteParam(originData);
        const toParam = getRouteParam(destinationData);

        outboundRoutes = await fetchRoutes(
            fromParam, toParam, departureDate, departureTime, optimize, mode,
            originData.service || null, destinationData.service || null
        );
        const realtimeTasks = [enrichRoutesRealtime(outboundRoutes)];

        if (returnTripEnabled) {
            returnRoutes = await fetchRoutes(
                toParam, fromParam, departureDate, returnTime, optimize, mode,
                destinationData.service || null, originData.service || null
            );
            realtimeTasks.push(enrichRoutesRealtime(returnRoutes));
            setupDirectionTabs();
        }

        if (outboundRoutes.length > 0) {
            switchDirection('andata');
        } else if (returnTripEnabled && returnRoutes.length > 0) {
            switchDirection('ritorno');
        } else {
            showErrorState('Nessun percorso trovato per i parametri specificati.');
        }

        // Il realtime è un arricchimento: non deve bloccare la visualizzazione
        // dell'orario programmato quando il feed ACTV è lento o indisponibile.
        Promise.allSettled(realtimeTasks).then(() => {
            if (allRoutes.length > 0) renderRouteResults(allRoutes);
        });
    } catch (error) {
        console.error('Errore ricerca percorsi:', error);
        showErrorState(`Errore durante la ricerca: ${error.message}`);
    }
}

function distanceMeters(aLat, aLon, bLat, bLon) {
    const values = [aLat, aLon, bLat, bLon].map(Number);
    if (!values.every(Number.isFinite)) return null;
    const [lat1, lon1, lat2, lon2] = values.map(value => value * Math.PI / 180);
    const h = Math.sin((lat2 - lat1) / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin((lon2 - lon1) / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function formatVehicleDistance(meters) {
    if (!Number.isFinite(meters)) return '';
    if (meters < 150) return 'in prossimità della fermata';
    if (meters < 1000) return `a circa ${Math.max(100, Math.round(meters / 100) * 100)} m dalla fermata`;
    return `a circa ${(meters / 1000).toFixed(meters < 10000 ? 1 : 0).replace('.', ',')} km dalla fermata`;
}

function computeVehicleProgress(position, shape, boardingStopId) {
    const stops = Array.isArray(shape?.stops) ? shape.stops : [];
    if (!position || !stops.length) return null;
    const boardingIndex = stops.findIndex(stop => String(stop.id ?? stop.stop_id) === String(boardingStopId));
    if (boardingIndex < 0) return null;
    let nearestIndex = -1;
    let nearestDistance = Infinity;
    stops.forEach((stop, index) => {
        const distance = distanceMeters(position.lat, position.lon, stop.lat ?? stop.stop_lat, stop.lng ?? stop.lon ?? stop.stop_lon);
        if (distance != null && distance < nearestDistance) { nearestDistance = distance; nearestIndex = index; }
    });
    if (nearestIndex < 0) return null;
    const boardingDistance = distanceMeters(position.lat, position.lon,
        stops[boardingIndex].lat ?? stops[boardingIndex].stop_lat,
        stops[boardingIndex].lng ?? stops[boardingIndex].lon ?? stops[boardingIndex].stop_lon);
    return {
        nearestStop: stops[nearestIndex].name ?? stops[nearestIndex].stop_name ?? '',
        stopsToBoarding: boardingIndex - nearestIndex,
        boardingPassed: nearestIndex > boardingIndex && boardingDistance > 150
    };
}

async function fetchRealtimeShapesForRoutes(routes) {
    const groups = new Map();
    (routes || []).flatMap(route => route.legs || []).forEach(leg => {
        if (leg.type === 'walking' || !leg.trip_id) return;
        const service = leg.service === 'navigation' || leg.mode === 'water' ? 'navigation' : 'automobilistico';
        if (!groups.has(service)) groups.set(service, new Set());
        groups.get(service).add(String(leg.trip_id));
    });
    const responses = await Promise.allSettled([...groups.entries()].map(async ([service, ids]) => {
        const params = new URLSearchParams({ tripIds: [...ids].join(','), cache: '1' });
        if (service === 'navigation') params.set('service', 'navigation');
        const response = await fetch(`/api/lines-shapes?${params.toString()}`, { cache: 'no-store' });
        return response.ok ? response.json() : [];
    }));
    return responses.flatMap(result => result.status === 'fulfilled' && Array.isArray(result.value) ? result.value : []);
}

async function fetchRealtimeVehiclesForRoutes(routes) {
    const services = new Set((routes || []).flatMap(route => route.legs || [])
        .filter(leg => leg.type !== 'walking' && leg.trip_id)
        .map(leg => leg.service === 'navigation' || leg.mode === 'water' ? 'navigation' : 'automobilistico'));
    const results = await Promise.allSettled([...services].map(async service => {
        const url = service === 'navigation' ? '/api/navigation/vehicles' : '/api/realtime/vehicles?service=automobilistico';
        const response = await fetch(url, { cache: 'no-store' });
        if (!response.ok) return [];
        const payload = await response.json();
        return Array.isArray(payload) ? payload : (Array.isArray(payload?.vehicles) ? payload.vehicles : []);
    }));
    return results.flatMap(result => result.status === 'fulfilled' ? result.value : []);
}

async function enrichRoutesRealtime(routes) {
    if (!Array.isArray(routes) || !routes.some(route => (route.legs || []).some(leg => leg.type !== 'walking' && leg.trip_id))) return;
    try {
        const [vehicles, stopsResponse, shapes] = await Promise.all([
            fetchRealtimeVehiclesForRoutes(routes),
            fetch('/api/stops', { cache: 'no-store' }).catch(() => null),
            fetchRealtimeShapesForRoutes(routes)
        ]);
        const stops = stopsResponse?.ok ? await stopsResponse.json() : [];
        const stopPositions = new Map((Array.isArray(stops) ? stops : []).map(stop => [`${stop.service || 'automobilistico'}|${String(stop.stop_id)}`, {
            lat: Number(stop.stop_lat), lon: Number(stop.stop_lon)
        }]));
        const updates = new Map(vehicles.filter(item => item.trip_id).map(item => [String(item.trip_id), item]));
        const shapesByTrip = new Map(shapes.map(shape => [String(shape.trip_id || ''), shape]));
        updates.forEach((vehicle, tripId) => realtimeVehiclesByTrip.set(tripId, vehicle));
        routes.forEach(route => (route.legs || []).forEach(leg => {
            const update = updates.get(String(leg.trip_id || ''));
            if (!update) return;
            leg.status = update.status;
            leg.delay_seconds = update.delay_seconds || 0;
            leg.realtime_vehicle = update.vehicle_position || null;
            const service = leg.service === 'navigation' || leg.mode === 'water' ? 'navigation' : 'automobilistico';
            const stop = stopPositions.get(`${service}|${String(leg.origin_id || '')}`);
            leg.vehicle_distance_m = stop && leg.realtime_vehicle
                ? distanceMeters(leg.realtime_vehicle.lat, leg.realtime_vehicle.lon, stop.lat, stop.lon)
                : null;
            leg.vehicle_progress = computeVehicleProgress(leg.realtime_vehicle, shapesByTrip.get(String(leg.trip_id)), leg.origin_id);
            if (update.status === 'cancelled') route.status = 'cancelled';
        }));
    } catch (error) {
        console.warn('Realtime Navigazione non disponibile:', error);
    }
}

/**
 * Gestione direzione (Andata / Ritorno)
 */

function setupDirectionTabs() {
    const tabs = document.getElementById('direction-tabs');
    if (tabs) tabs.style.display = 'flex';
}

function switchDirection(direction) {
    currentDirection = direction;

    const tabAndata = document.getElementById('tab-andata');
    const tabRitorno = document.getElementById('tab-ritorno');
    if (tabAndata) tabAndata.classList.toggle('active', direction === 'andata');
    if (tabRitorno) tabRitorno.classList.toggle('active', direction === 'ritorno');

    // Reset stato confronto al cambio direzione
    compareMode = false;
    selectedRoutes = [];
    const toggleBtn = document.getElementById('btn-compare-toggle');
    if (toggleBtn) {
        toggleBtn.classList.remove('active');
        toggleBtn.textContent = 'Confronta';
    }
    const compareBar = document.getElementById('compare-bar');
    if (compareBar) compareBar.style.display = 'none';

    if (direction === 'ritorno') {
        allRoutes = returnRoutes;
        activeDestName = originData.name;
        updateSummary(destinationData, originData, returnTime);
    } else {
        allRoutes = outboundRoutes;
        activeDestName = destinationData.name;
        updateSummary(originData, destinationData, departureTime);
    }

    const loadingEl = document.getElementById('loading');
    const containerEl = document.getElementById('routes-container');
    if (loadingEl) loadingEl.style.display = 'none';
    if (containerEl) containerEl.style.display = 'block';

    if (!allRoutes || allRoutes.length === 0) {
        const listEl = document.getElementById('routes-list');
        if (listEl) {
            listEl.innerHTML = `<div class="no-routes-text" style="text-align:center; padding:1.5rem;">Nessun percorso trovato per questa direzione.</div>`;
        }
        return;
    }

    renderRouteResults(allRoutes);
}

/**
 * Rendering Risultati
 */

function getLineBadgeDetails(lineRaw) {
    if (!lineRaw) return { name: '?', class: 'badge-red' };
    if (lineRaw === 'Cammina') return { name: getWalkingIcon(), class: 'badge-walking' };

    const [lineName, lineTag] = String(lineRaw).split("_");

    let badgeClass = "badge-red";
    // Extraurbano (blu/azzurro): tag US/UN/EN oppure nome che termina con 'E' (es. 5E)
    if (["US", "UN", "EN"].includes(lineTag) || /E$/i.test(lineName)) badgeClass = "badge-blue";
    // Notturne
    if (/^N/i.test(lineName)) badgeClass = "badge-night";

    return { name: lineName, class: badgeClass };
}

function getWalkingIcon() {
    return '<svg class="walking-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">' +
        '<path fill-rule="evenodd" clip-rule="evenodd" d="M13 6C14.1046 6 15 5.10457 15 4C15 2.89543 14.1046 2 13 2C11.8955 2 11 2.89543 11 4C11 5.10457 11.8955 6 13 6ZM11.0528 6.60557C11.3841 6.43992 11.7799 6.47097 12.0813 6.68627L13.0813 7.40056C13.3994 7.6278 13.5559 8.01959 13.482 8.40348L12.4332 13.847L16.8321 20.4453C17.1384 20.9048 17.0143 21.5257 16.5547 21.8321C16.0952 22.1384 15.4743 22.0142 15.168 21.5547L10.5416 14.6152L9.72611 13.3919C9.58336 13.1778 9.52866 12.9169 9.57338 12.6634L10.1699 9.28309L8.38464 10.1757L7.81282 13.0334C7.70445 13.575 7.17759 13.9261 6.63604 13.8178C6.09449 13.7095 5.74333 13.1825 5.85169 12.641L6.51947 9.30379C6.58001 9.00123 6.77684 8.74356 7.05282 8.60557L11.0528 6.60557ZM16.6838 12.9487L13.8093 11.9905L14.1909 10.0096L17.3163 11.0513C17.8402 11.226 18.1234 11.7923 17.9487 12.3162C17.7741 12.8402 17.2078 13.1234 16.6838 12.9487ZM6.12844 20.5097L9.39637 14.7001L9.70958 15.1699L10.641 16.5669L7.87159 21.4903C7.60083 21.9716 6.99111 22.1423 6.50976 21.8716C6.0284 21.6008 5.85768 20.9911 6.12844 20.5097Z" fill="currentColor"/>' +
        '</svg>';
}

function getModeLabel(leg) {
    if (leg?.type === 'walking') return 'Cammina';
    return leg?.mode === 'water' || leg?.service === 'navigation' ? 'Navigazione' : 'Bus';
}

function getBadgeStyle(leg) {
    const color = String(leg?.route_color || '').trim();
    const text = String(leg?.route_text_color || '').trim();
    const styles = [];
    const normalizeColor = value => /^#[0-9a-f]{6}$/i.test(value)
        ? value
        : (/^[0-9a-f]{6}$/i.test(value) ? `#${value}` : '');
    const background = normalizeColor(color);
    const foreground = normalizeColor(text);
    if (background) {
        styles.push(`background-color:${background}`);
        // GTFS spesso pubblica route_text_color senza il carattere '#'.
        // Se manca anche quel valore, bianco garantisce contrasto sui badge
        // colorati, in particolare sulle linee extraurbane come 9E.
        styles.push(`color:${foreground || '#FFFFFF'}`);
    }
    return styles.length ? ` style="${styles.join(';')}"` : '';
}

function timeToMinutes(value) {
    const parts = String(value || '').split(':').map(Number);
    return Number.isFinite(parts[0]) && Number.isFinite(parts[1]) ? parts[0] * 60 + parts[1] : 0;
}

function formatDuration(minutes) {
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

function getTransitLegs(route) {
    return (route.legs || []).filter(leg => leg.type !== 'walking');
}

function getRideDuration(route) {
    const legs = getTransitLegs(route);
    if (!legs.length) return Math.max(0, Number(route.duration) || 0);
    const start = timeToMinutes(legs[0].departure_time);
    let end = timeToMinutes(legs[legs.length - 1].arrival_time);
    while (end < start) end += 1440;
    return end - start;
}

function getWaitingDuration(route) {
    const departure = timeToMinutes(getRouteDepartureTime(route));
    const requested = timeToMinutes(departureTime);
    let wait = departure - requested + (Number(route.day_offset) || 0) * 1440;
    if (wait < 0) wait += 1440;
    return wait;
}

function formatRouteDuration(route) {
    return `${formatDuration(getRideDuration(route))} <span class="route-wait-time">(${formatDuration(getWaitingDuration(route))} da ora)</span>`;
}

function renderRouteResults(routes) {
    const loadingEl = document.getElementById('loading');
    const containerEl = document.getElementById('routes-container');
    const listEl = document.getElementById('routes-list');

    if (loadingEl) loadingEl.style.display = 'none';
    if (containerEl) containerEl.style.display = 'block';
    if (!listEl) return;

    listEl.innerHTML = routes.map((route, idx) => {
        const legsHtml = route.legs.map((leg, index) => renderLegHTML(leg, route, index)).join('');
        const routeJson = JSON.stringify(route).replace(/'/g, "&#39;");
        const isSelected = selectedRoutes.includes(idx);

        return `
            <div class="route-card ${compareMode ? 'compare-mode' : ''} ${isSelected ? 'compare-selected' : ''}"
                 onclick='${compareMode ? `toggleRouteSelection(${idx})` : `viewRouteDetails(${routeJson})`}'
                 data-route-index="${idx}">
                ${compareMode ? `<div class="compare-checkbox ${isSelected ? 'checked' : ''}"><span>${isSelected ? '\u2713' : ''}</span></div>` : ''}
                <div class="route-card-body">
                    <div class="route-header-row">
                        <div class="route-date">${formatItalianDate(departureDate)}</div>
                        <div class="route-total-duration">\u23F1 ${formatRouteDuration(route)}</div>
                    </div>
                    <div class="route-timeline">
                        ${legsHtml}
                    </div>
                    <div class="route-footer">
                        ${compareMode ? '' : `<button class="btn-select" onclick='confirmRouteSelection(${routeJson}, event)'>Seleziona &rarr;</button>`}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderLegHTML(leg, route, index) {
    const isFirst = index === 0;
    const isLast = (index === route.legs.length - 1);
    const isWalking = leg.type === 'walking';
    const badge = getLineBadgeDetails(leg.route_short_name);

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

    const connectorContent = isWalking
        ? `<div class="line-badge badge-walking">${getWalkingIcon()}</div>
           <div class="connector-info">Cammina per ${Math.round(leg.duration)} min${Number.isFinite(Number(leg.distance)) ? ` (${Math.round(Number(leg.distance))}m)` : ''}</div>`
        : `<div class="line-badge ${badge.class}" title="${getModeLabel(leg)}"${getBadgeStyle(leg)}>${leg.mode === 'water' ? '⛴ ' : ''}${badge.name}</div>
           <div class="connector-info">per ${leg.stops_count} fermate${leg.status === 'cancelled' ? ' · Corsa cancellata' : leg.status === 'delayed' ? ` · +${Math.round((leg.delay_seconds || 0) / 60)} min` : ''}${leg.realtime_vehicle ? `<span class="vehicle-live-info"><span class="vehicle-live-dot"></span>${leg.vehicle_progress?.boardingPassed ? 'Il mezzo ha già superato la fermata di salita' : `Mezzo rilevato${leg.vehicle_distance_m != null ? ` ${formatVehicleDistance(leg.vehicle_distance_m)}` : ''}${leg.vehicle_progress?.stopsToBoarding > 0 ? ` · ${leg.vehicle_progress.stopsToBoarding} fermat${leg.vehicle_progress.stopsToBoarding === 1 ? 'a' : 'e'} prima della salita` : ''}${leg.vehicle_progress?.nearestStop ? ` · vicino a ${leg.vehicle_progress.nearestStop}` : ''}`}</span>` : ''}</div>`;

    html += `<div class="timeline-connector">${connectorContent}</div>`;

    const markerClass = isLast ? 'end' : 'transfer';
    // Ogni tratta conosce la propria fermata di arrivo. Non usare
    // route.transfer_stop per le tratte intermedie: nei percorsi composti da
    // più corse della stessa linea rappresenta solo l'ultimo cambio e
    // produrrebbe una timeline con la stessa fermata ripetuta.
    const arrivalName = leg.destination || (isLast ? (activeDestName || destinationData.name) : (route.transfer_stop || 'Cambio'));

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

/**
 * Confronto Percorsi
 */

function toggleCompareMode() {
    compareMode = !compareMode;
    selectedRoutes = [];

    const toggleBtn = document.getElementById('btn-compare-toggle');
    const compareBar = document.getElementById('compare-bar');

    if (toggleBtn) {
        toggleBtn.classList.toggle('active', compareMode);
        toggleBtn.textContent = compareMode ? 'Annulla' : 'Confronta';
    }

    if (compareBar) compareBar.style.display = compareMode ? 'flex' : 'none';

    renderRouteResults(allRoutes);
    updateCompareBar();
}

function toggleRouteSelection(index) {
    const pos = selectedRoutes.indexOf(index);
    if (pos >= 0) {
        selectedRoutes.splice(pos, 1);
    } else if (selectedRoutes.length < 3) {
        selectedRoutes.push(index);
    }

    renderRouteResults(allRoutes);
    updateCompareBar();
}

function updateCompareBar() {
    const countEl = document.getElementById('compare-count');
    const compareBtn = document.querySelector('.btn-compare');

    if (countEl) {
        countEl.textContent = `${selectedRoutes.length} selezionat${selectedRoutes.length === 1 ? 'o' : 'i'}`;
    }
    if (compareBtn) {
        compareBtn.disabled = selectedRoutes.length < 2;
    }
}

function openCompareModal() {
    if (selectedRoutes.length < 2) return;

    const modal = document.getElementById('compare-modal');
    const body = document.getElementById('compare-body');
    if (!modal || !body) return;

    const routes = selectedRoutes.map(idx => allRoutes[idx]);

    body.innerHTML = renderComparisonView(routes);
    modal.classList.add('active');
    loadComparisonMap(routes);
}

function closeCompareModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('compare-modal');
    if (modal) modal.classList.remove('active');
}

function renderComparisonView(routes) {
    const best = findBestValues(routes);

    // Table header
    let html = `<div class="compare-table">`;

    // Route labels
    html += `<div class="compare-row compare-header-row">
        <div class="compare-label"></div>
        ${routes.map((_, i) => `<div class="compare-cell compare-route-label">Percorso ${i + 1}</div>`).join('')}
    </div>`;

    // Lines
    html += `<div class="compare-row">
        <div class="compare-label">Linee</div>
        ${routes.map(r => {
            const badges = getRouteBadges(r);
            return `<div class="compare-cell">${badges}</div>`;
        }).join('')}
    </div>`;

    // Departure time
    html += `<div class="compare-row">
        <div class="compare-label">Partenza</div>
        ${routes.map(r => {
            const dep = getRouteDepartureTime(r);
            return `<div class="compare-cell">${formatShortTime(dep)}</div>`;
        }).join('')}
    </div>`;

    // Arrival time
    html += `<div class="compare-row">
        <div class="compare-label">Arrivo</div>
        ${routes.map(r => {
            const arr = getRouteArrivalTime(r);
            return `<div class="compare-cell">${formatShortTime(arr)}</div>`;
        }).join('')}
    </div>`;

    // Duration
    html += `<div class="compare-row">
        <div class="compare-label">Durata</div>
        ${routes.map(r => {
            const dur = Math.round(getRideDuration(r));
            const isBest = dur === best.duration;
            return `<div class="compare-cell ${isBest ? 'best-value' : ''}">${formatDuration(dur)} <small class="compare-wait-time">(${formatDuration(getWaitingDuration(r))} da ora)</small></div>`;
        }).join('')}
    </div>`;

    // Stops count
    html += `<div class="compare-row">
        <div class="compare-label">Fermate</div>
        ${routes.map(r => {
            const stops = r.stops_count || (r.legs || []).reduce((sum, l) => sum + (l.stops_count || 0), 0);
            const isBest = stops === best.stops;
            return `<div class="compare-cell ${isBest ? 'best-value' : ''}">${stops}</div>`;
        }).join('')}
    </div>`;

    // Transfers
    html += `<div class="compare-row">
        <div class="compare-label">Cambi</div>
        ${routes.map(r => {
            const transfers = getTransferCount(r);
            const isBest = transfers === best.transfers;
            return `<div class="compare-cell ${isBest ? 'best-value' : ''}">${transfers}</div>`;
        }).join('')}
    </div>`;

    // Walking
    html += `<div class="compare-row">
        <div class="compare-label">A piedi</div>
        ${routes.map(r => {
            const walkMin = getWalkingMinutes(r);
            const isBest = walkMin === best.walking;
            return `<div class="compare-cell ${isBest ? 'best-value' : ''}">${walkMin} min</div>`;
        }).join('')}
    </div>`;

    html += `</div>`;

    // Action buttons
    html += `<div class="compare-actions">
        ${routes.map((r, i) => {
            const routeJson = JSON.stringify(r).replace(/'/g, "&#39;");
            return `<button class="btn-select-compare" onclick='confirmRouteSelection(${routeJson}, event)'>
                Seleziona Percorso ${i + 1}
            </button>`;
        }).join('')}
    </div>`;

    return html;
}

function escapeComparisonHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

let comparisonMap = null;

function toggleComparisonMap() {
    const shell = document.getElementById('compare-map-shell');
    if (!shell) return;
    const expanded = shell.classList.toggle('is-expanded');
    const button = shell.querySelector('.compare-map-expand');
    if (button) {
        button.textContent = expanded ? '×' : '⛶';
        button.setAttribute('aria-label', expanded ? 'Riduci mappa' : 'Espandi mappa');
    }
    setTimeout(() => comparisonMap?.invalidateSize(), 100);
}

function comparisonMapTime(value, delaySeconds = 0) {
    const parts = String(value || '').split(':').map(Number);
    if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) return value || '--:--';
    const total = parts[0] * 3600 + parts[1] * 60 + (parts[2] || 0) + Number(delaySeconds || 0);
    const normalized = ((total % 86400) + 86400) % 86400;
    return `${String(Math.floor(normalized / 3600)).padStart(2, '0')}:${String(Math.floor(normalized / 60) % 60).padStart(2, '0')}`;
}

function comparisonMapService(leg) {
    return leg?.service === 'navigation' || leg?.mode === 'water' ? 'navigation' : 'automobilistico';
}

function comparisonMapSliceShape(shape, leg) {
    const path = Array.isArray(shape?.path) ? shape.path : [];
    if (path.length < 2) return { stops: path, points: shape?.shape || path };

    const id = value => String(value || '');
    const start = path.findIndex(stop => id(stop.stop_id) === id(leg?.origin_id));
    const end = path.findIndex((stop, index) => index >= Math.max(0, start) && id(stop.stop_id) === id(leg?.destination_id));
    const fallbackStart = start >= 0 ? start : path.findIndex(stop => String(stop.name || '').trim().toLowerCase() === String(leg?.origin || '').trim().toLowerCase());
    const fallbackEnd = end >= 0 ? end : path.findIndex((stop, index) => index >= Math.max(0, fallbackStart) && String(stop.name || '').trim().toLowerCase() === String(leg?.destination || '').trim().toLowerCase());
    const from = fallbackStart >= 0 ? fallbackStart : 0;
    const to = fallbackEnd > from ? fallbackEnd : path.length - 1;
    const stops = path.slice(from, to + 1);
    const refined = Array.isArray(shape.shape) ? shape.shape : [];
    if (refined.length < 2) return { stops, points: stops };

    const distance = (a, b) => (Number(a.lat) - Number(b.lat)) ** 2 + (Number(a.lng) - Number(b.lng)) ** 2;
    const nearest = stop => refined.reduce((best, point, index) => distance(point, stop) < distance(refined[best], stop) ? index : best, 0);
    const refinedStart = nearest(stops[0]);
    const refinedEnd = nearest(stops[stops.length - 1]);
    const refinedFrom = Math.min(refinedStart, refinedEnd);
    const refinedTo = Math.max(refinedStart, refinedEnd);
    return { stops, points: refined.slice(refinedFrom, refinedTo + 1) };
}

function comparisonMapDistanceToSegment(point, start, end) {
    const latitudeScale = 110540;
    const longitudeScale = 111320 * Math.cos(Number(point.lat) * Math.PI / 180);
    const p = { x: Number(point.lng) * longitudeScale, y: Number(point.lat) * latitudeScale };
    const a = { x: Number(start.lng) * longitudeScale, y: Number(start.lat) * latitudeScale };
    const b = { x: Number(end.lng) * longitudeScale, y: Number(end.lat) * latitudeScale };
    const dx = b.x - a.x, dy = b.y - a.y;
    const lengthSquared = dx * dx + dy * dy;
    const t = lengthSquared ? Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / lengthSquared)) : 0;
    const nearest = { x: a.x + t * dx, y: a.y + t * dy };
    return { distance: Math.hypot(p.x - nearest.x, p.y - nearest.y), longitudeScale };
}

function comparisonMapOffsetOverlaps(geometries, zoom = 15) {
    const overlapRadius = 11;
    // A zoom basso basta un piccolo distacco; aumentando lo zoom il distacco
    // cresce così le linee restano distinguibili anche sui tratti condivisi.
    const offsetMeters = Math.max(1.5, Math.min(9, 0.8 * (Number(zoom) - 10)));
    return geometries.map((geometry, geometryIndex) => {
        const points = geometry.points;
        const shifted = points.map((point, pointIndex) => {
            let direction = 0;
            let overlapping = false;
            geometries.forEach((other, otherIndex) => {
                if (otherIndex === geometryIndex || other.routeIndex === geometry.routeIndex) return;
                for (let i = 0; i < other.points.length - 1; i++) {
                    if (comparisonMapDistanceToSegment(point, other.points[i], other.points[i + 1]).distance <= overlapRadius) {
                        overlapping = true;
                        direction += geometry.routeIndex < other.routeIndex ? -1 : 1;
                        break;
                    }
                }
            });
            if (!overlapping || direction === 0) return point;
            const previous = points[Math.max(0, pointIndex - 1)];
            const next = points[Math.min(points.length - 1, pointIndex + 1)];
            const latitudeScale = 110540;
            const longitudeScale = 111320 * Math.cos(Number(point.lat) * Math.PI / 180);
            const dx = (Number(next.lng) - Number(previous.lng)) * longitudeScale;
            const dy = (Number(next.lat) - Number(previous.lat)) * latitudeScale;
            const length = Math.hypot(dx, dy);
            if (!length) return point;
            const amount = Math.sign(direction) * offsetMeters;
            return {
                lat: Number(point.lat) + (-dx / length) * amount / latitudeScale,
                lng: Number(point.lng) + (dy / length) * amount / longitudeScale
            };
        });
        return { ...geometry, points: shifted };
    });
}

function comparisonMapPopup(records) {
    const first = records[0] || {};
    const sameStop = records.length > 1;
    const rows = records.map(record => `<div class="compare-stop-line">
        <span class="compare-stop-route-badge">Percorso ${record.routeNumber}</span>
        <span>${record.direction === 'down' ? '<span class="compare-stop-direction compare-stop-direction-down" title="Scendi qui">↘</span>' : record.direction === 'up' ? '<span class="compare-stop-direction compare-stop-direction-up" title="Sali qui">↗</span>' : ''}<strong>Linea ${escapeComparisonHtml(record.line || '?')}</strong> · ${escapeComparisonHtml(record.mode || 'Servizio')}<br><span class="compare-stop-time">Arrivo ${escapeComparisonHtml(record.arrivalTime || '--:--')}</span></span>
    </div>`).join(sameStop ? '<hr>' : '');
    return `<div class="compare-stop-popup">
        <strong class="compare-stop-name">${escapeComparisonHtml(first.name || 'Fermata')}</strong>
        ${rows}
    </div>`;
}

async function loadComparisonMap(routes) {
    const mapEl = document.getElementById('compare-map');
    const statusEl = document.getElementById('compare-map-status');
    if (!mapEl) return;

    if (typeof L === 'undefined') {
        if (statusEl) statusEl.textContent = 'Mappa non disponibile in questo momento.';
        return;
    }

    if (comparisonMap) {
        comparisonMap.remove();
        comparisonMap = null;
    }

    try {
        // Aggiorna entrambe le modalità prima di disegnare: se il feed non
        // risponde, geometrie e orari programmati restano comunque visibili.
        const realtime = await fetchRealtimeVehiclesForRoutes(routes);
        realtime.filter(item => item.trip_id).forEach(item => realtimeVehiclesByTrip.set(String(item.trip_id), item));

        const groups = new Map();
        routes.forEach(route => (route.legs || []).forEach(leg => {
            if (leg.type === 'walking' || !leg.trip_id) return;
            const service = comparisonMapService(leg);
            if (!groups.has(service)) groups.set(service, new Set());
            groups.get(service).add(String(leg.trip_id));
        }));

        const shapeSets = await Promise.all([...groups.entries()].map(async ([service, ids]) => {
            const tripIds = [...ids];
            const params = new URLSearchParams({ tripIds: tripIds.join(','), tripGroups: tripIds.map(id => `1:${id}`).join('|') });
            if (service === 'navigation') params.set('service', 'navigation');
            let data = [];
            const response = await fetch(`/api/lines-shapes?${params.toString()}`, { cache: 'no-store' });
            if (response.ok) {
                try { data = JSON.parse(await response.text()); } catch (error) { data = []; }
            }
            if (!Array.isArray(data) || data.length < tripIds.length) {
                const fallback = new URLSearchParams({ tripIds: tripIds.join(','), cache: '1' });
                if (service === 'navigation') fallback.set('service', 'navigation');
                const fallbackResponse = await fetch(`/api/lines-shapes?${fallback.toString()}`, { cache: 'no-store' });
                if (fallbackResponse.ok) {
                    try {
                        const cached = JSON.parse(await fallbackResponse.text());
                        if (Array.isArray(cached)) {
                            const found = new Set(data.map(shape => String(shape.trip_id || '')));
                            data = data.concat(cached.filter(shape => !found.has(String(shape.trip_id || ''))));
                        }
                    } catch (error) { /* la risposta DB resta utilizzabile */ }
                }
            }
            return Array.isArray(data) ? data : [];
        }));
        const shapes = shapeSets.flat();
        const shapeByTrip = new Map(shapes.map(shape => [String(shape.trip_id || ''), shape]));
        if (!shapes.length) throw new Error('Geometria non disponibile');

        comparisonMap = L.map(mapEl, { zoomControl: true, attributionControl: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
        }).addTo(comparisonMap);

        const colors = ['#087df5', '#ef7d32', '#9b59b6'];
        const bounds = [];
        const markerRecords = new Map();
        const routeGeometries = [];
        let transitLayers = [];
        const addStop = (stop, record, important) => {
            const lat = Number(stop.lat), lng = Number(stop.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            // Bus e navigazione possono avere ID GTFS diversi per la stessa
            // fermata fisica: posizione e nome permettono di sovrapporre il
            // marker e mostrare entrambe le linee nel popup.
            const stopName = String(stop.name || '').trim().toLowerCase();
            const key = `${stopName}|${lat.toFixed(4)}|${lng.toFixed(4)}`;
            if (!markerRecords.has(key)) markerRecords.set(key, { lat, lng, name: stop.name, important: false, records: [] });
            const marker = markerRecords.get(key);
            marker.important ||= important;
            if (!marker.records.some(item => item.routeNumber === record.routeNumber && item.line === record.line)) marker.records.push(record);
        };

        routes.forEach((route, routeIndex) => (route.legs || []).forEach((leg, legIndex) => {
            if (leg.type === 'walking' || !leg.trip_id) return;
            const shape = shapeByTrip.get(String(leg.trip_id));
            if (!shape) return;
            const sliced = comparisonMapSliceShape(shape, leg);
            const points = (sliced.points || [])
                .map(point => ({ lat: Number(point.lat), lng: Number(point.lng) }))
                .filter(point => Number.isFinite(point.lat) && Number.isFinite(point.lng));
            if (points.length < 2) return;
            const color = colors[routeIndex % colors.length];
            routeGeometries.push({ routeIndex, color, points, leg });

            const slicedStops = sliced.stops || [];
            slicedStops.forEach((stop, stopIndex) => {
                const isFirst = stopIndex === 0;
                const isLast = stopIndex === slicedStops.length - 1;
                const isChange = isLast && legIndex < route.legs.length - 1 && route.legs.slice(legIndex + 1).some(next => next.type !== 'walking');
                addStop(stop, {
                    routeNumber: routeIndex + 1,
                    line: leg.route_short_name,
                    mode: getModeLabel(leg),
                    arrivalTime: comparisonMapTime(stop.arrival_time || stop.departure_time, leg.delay_seconds || 0),
                    direction: isLast ? 'down' : isFirst && route.legs.slice(0, legIndex).some(previous => previous.type !== 'walking') ? 'up' : ''
                }, isFirst || isLast || isChange);
            });
        }));

        // Quando due corse condividono la stessa strada, sposta solo i punti
        // della porzione comune. Le deviazioni e le parti indipendenti restano
        // esattamente sulla geometria reale. Il ridisegno su zoom mantiene la
        // separazione proporzionata al livello di dettaglio visualizzato.
        routeGeometries.forEach(geometry => bounds.push(...geometry.points.map(point => [point.lat, point.lng])));
        const drawTransitLayers = () => {
            transitLayers.forEach(layer => layer.remove());
            transitLayers = comparisonMapOffsetOverlaps(routeGeometries, comparisonMap.getZoom()).map(geometry => {
                const layer = L.polyline(geometry.points.map(point => [point.lat, point.lng]), {
                    color: geometry.color, weight: 6, opacity: .9
                }).addTo(comparisonMap);
                layer.bindPopup(`Percorso ${geometry.routeIndex + 1}: ${escapeComparisonHtml(geometry.leg.route_short_name || '')}`);
                return layer;
            });
        };
        drawTransitLayers();
        comparisonMap.on('zoomend', drawTransitLayers);

        // I tratti a piedi collegano le coordinate delle fermate già caricate.
        routes.forEach((route, routeIndex) => (route.legs || []).forEach(leg => {
            if (leg.type !== 'walking') return;
            const from = [...markerRecords.values()].find(marker => String(marker.name) === String(leg.origin));
            const to = [...markerRecords.values()].find(marker => String(marker.name) === String(leg.destination));
            if (from && to) L.polyline([[from.lat, from.lng], [to.lat, to.lng]], { color: colors[routeIndex % colors.length], weight: 4, opacity: .8, dashArray: '7 8' }).addTo(comparisonMap);
        }));

        markerRecords.forEach(marker => {
            L.circleMarker([marker.lat, marker.lng], {
                radius: marker.important ? 8 : 4,
                color: colors[marker.records[0]?.routeNumber - 1 || 0],
                weight: marker.important ? 3 : 2,
                fillColor: '#fff', fillOpacity: 1
            }).addTo(comparisonMap).bindPopup(comparisonMapPopup(marker.records));
        });

        let realtimeCount = 0;
        routes.forEach((route, routeIndex) => (route.legs || []).forEach(leg => {
            if (leg.type === 'walking' || !leg.trip_id) return;
            const vehicle = realtimeVehiclesByTrip.get(String(leg.trip_id));
            const position = vehicle?.vehicle_position;
            const lat = Number(position?.lat), lng = Number(position?.lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            const color = colors[routeIndex % colors.length];
            const line = getLineBadgeDetails(leg.route_short_name).name;
            const icon = L.divIcon({
                className: 'compare-vehicle-marker-shell',
                html: `<span class="compare-vehicle-marker" style="--vehicle-color:${color}">${leg.mode === 'water' ? '⛴' : '🚌'}<b>${escapeComparisonHtml(line)}</b></span>`,
                iconSize: [48, 34], iconAnchor: [24, 17]
            });
            L.marker([lat, lng], { icon, zIndexOffset: 800 })
                .addTo(comparisonMap)
                .bindPopup(`<strong>Mezzo reale · ${escapeComparisonHtml(line)}</strong><br>Percorso ${routeIndex + 1}<br>Corsa ${escapeComparisonHtml(leg.trip_id)}`);
            bounds.push([lat, lng]);
            realtimeCount++;
        }));

        if (!bounds.length) throw new Error('Nessun punto disponibile');
        comparisonMap.fitBounds(bounds, { padding: [18, 18] });
        if (statusEl) {
            statusEl.innerHTML = routes.map((_, i) =>
                `<span><i style="background:${colors[i % colors.length]}"></i>Percorso ${i + 1}</span>`
            ).join('') + (realtimeCount ? `<span class="compare-live-count"><span class="vehicle-live-dot"></span>${realtimeCount} ${realtimeCount === 1 ? 'mezzo rilevato' : 'mezzi rilevati'}</span>` : '');
        }
        setTimeout(() => comparisonMap?.invalidateSize(), 80);
    } catch (error) {
        console.error('[ACTV] Errore mappa confronto:', error);
        if (statusEl) statusEl.textContent = 'Impossibile caricare la mappa dei percorsi.';
    }
}

function findBestValues(routes) {
    const durations = routes.map(r => Math.round(getRideDuration(r)));
    const stops = routes.map(r => r.stops_count || (r.legs || []).reduce((sum, l) => sum + (l.stops_count || 0), 0));
    const transfers = routes.map(r => getTransferCount(r));
    const walking = routes.map(r => getWalkingMinutes(r));

    return {
        duration: Math.min(...durations),
        stops: Math.min(...stops),
        transfers: Math.min(...transfers),
        walking: Math.min(...walking)
    };
}

function getRouteBadges(route) {
    if (!route.legs) return '';
    return route.legs
        .filter(l => l.type !== 'walking' && l.route_short_name)
        .map(l => {
            const badge = getLineBadgeDetails(l.route_short_name);
            return `<span class="line-badge ${badge.class}"${getBadgeStyle(l)}>${badge.name}</span>`;
        })
        .join(' ');
}

function getRouteDepartureTime(route) {
    const legs = getTransitLegs(route);
    if (legs.length > 0) return legs[0].departure_time;
    return route.departure_time;
}

function getRouteArrivalTime(route) {
    const legs = getTransitLegs(route);
    if (legs.length > 0) return legs[legs.length - 1].arrival_time;
    return route.arrival_time;
}

function getTransferCount(route) {
    if (!route.legs) return 0;
    return Math.max(0, route.legs.filter(l => l.type !== 'walking').length - 1);
}

function getWalkingMinutes(route) {
    if (!route.legs) return 0;
    return Math.round(route.legs
        .filter(l => l.type === 'walking')
        .reduce((sum, l) => sum + (l.duration || 0), 0));
}

/**
 * Utility UI e Formattazione
 */

function showErrorState(message) {
    const loadingEl = document.getElementById('loading');
    const noRoutesEl = document.getElementById('no-routes');
    if (loadingEl) loadingEl.style.display = 'none';
    if (noRoutesEl) {
        noRoutesEl.style.display = 'block';
        const textEl = noRoutesEl.querySelector('.no-routes-text');
        if (textEl) textEl.textContent = message;
    }
}

function confirmRouteSelection(route, event) {
    if (event) event.stopPropagation();
    localStorage.setItem('selected_route', JSON.stringify(route));
    window.location.href = '/route-details';
}

function viewRouteDetails(route) {
    confirmRouteSelection(route);
}

function formatItalianDate(dateStr) {
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'short' });
    } catch (e) {
        return dateStr;
    }
}

function formatShortTime(timeStr) {
    return timeStr ? timeStr.substring(0, 5) : '--:--';
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { safeParseJSON, getLineBadgeDetails, formatItalianDate, formatShortTime, getTransferCount, getWalkingMinutes, findBestValues, distanceMeters, formatVehicleDistance, computeVehicleProgress };
}
