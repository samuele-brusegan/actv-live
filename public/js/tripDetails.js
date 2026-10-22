/**
 * Gestione della visualizzazione dei passaggi per una specifica corsa (trip).
 * Gestisce l'integrazione tra dati statici GTFS e dati real-time ACTV.
 *
 * REFACTORING NOTE:
 * - `stopId` (URL param) è stato rinominato in `currentStopId` nello state per indicare
 *   che è la fermata SELEZIONATA dall'utente, non necessariamente la posizione attuale del bus.
 * - La logica di visualizzazione ora tenta di mostrare i dati real-time anche per le fermate
 *   precedenti a quella selezionata.
 */

let state = {
    lineFull: null,      // Esempio: "5E_..."
    line: null,          // Esempio: "5E"
    tag: null,           // Esempio: "U", "N", etc.
    destination: null,   // Destinazione corsa
    currentStopId: null, // ID della fermata SELEZIONATA dall'utente (la visualizzazione è centrata qui)
    arrivalTime: null,   // Orario di arrivo alla fermata selezionata
    tripId: null,        // ID univoco della corsa nel GTFS
    today: null,         // Giorno della settimana (es. "monday")
    stopsGTFS: [],       // Lista fermate da GTFS (statico)
    stopsJSON: [],       // Lista fermate da Real-Time
    mergedStops: [],     // Lista fermate merge
    tripContext: null    // Contesto originale usato per identificare la corsa
};
let firstIteration = {
    refresh: true,
    scroll: true
};
let tripMap = null;
let tripMapVehicleMarker = null;
let tripMapRefreshTimer = null;
let tripMapShape = null;
let tripMapGeometry = [];
let tripMapRemainingRoute = null;
let tripMapCompletedRoute = null;
let tripMapStopMarkers = new Map();
let tripMapUserMarker = null;
let tripMapUserAccuracy = null;
let tripMapGeoWatchId = null;
let tripMapSelectedStopId = null;
let tripMapStops = [];
let tripMapProgress = null;
let tripMapNextStopScrolled = false;
let tripDetailsRefreshTimer = null;
let tripMapVehiclePosition = null;

document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);

    state.tripId = urlParams.get('tripId');


    /* state.lineFull = urlParams.get('line');
    state.line = state.lineFull?.split('_')[0];
    state.tag = state.lineFull?.split('_')[1];
    state.destination = urlParams.get('dest');

    // "stopId" nell'URL rappresenta la fermata cliccata dall'utente
    state.currentStopId = urlParams.get('stopId');
    state.arrivalTime = urlParams.get('time'); */

    init();
});

/** Inizializzazione della pagina */
function renderAvailableTripData() {
    if (!Array.isArray(state.stopsGTFS) || !state.stopsGTFS.length) return;
    state.mergedStops = mergeStops();
    renderTimeline();
}

async function init() {
    const dow = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
    state.today = dow[new Date().getDay()];
    const urlParams = new URLSearchParams(window.location.search);

    // Recupero info contestuali dalla sessione (usati per identificare univocamente la corsa)
    const contextValue = (urlKey, storageKey) =>
        urlParams.get(urlKey) || sessionStorage.getItem(storageKey) || null;
    const trackName = contextValue('contextLine', 'busTrack');
    const lastStop = contextValue('contextDestination', 'lastStop');
    const timedStop = contextValue('contextStop', 'timedStop');
    const realTime = contextValue('contextTime', 'realTime');
    const lineId = contextValue('contextLineId', 'lineId');
    const isRealtime = contextValue('contextRealtime', 'contextIsRealtime');
    state.tripContext = {
        stop: timedStop,
        time: realTime,
        destination: lastStop,
        lineId,
        // null = provenienza ignota (link vecchi); true/false = la card cliccata
        // mostrava una stima RT o un orario programmato
        realtime: isRealtime == null ? null : isRealtime === '1'
    };

    state.currentStopId = urlParams.get('stopId') || sessionStorage.getItem('tripDetails_selectedStop');

    const infoPromise = (async () => {
        const data = await unpackTripId(state.tripId);
        if (!data || data.error) return null;
        state.line = data.bus_track;
        state.destination = data.bus_direction;
        state.tag = data.line_tag;
        state.lineFull = state.line + "_" + state.tag;
        updateHeader();
        updateTripMapHeader();
        return data;
    })();

    const gtfsPromise = (async () => {
        state.stopsGTFS = await fetchGTFSStops(state.tripId);
        renderAvailableTripData();
        return state.stopsGTFS;
    })();

    const realtimePromise = infoPromise.then(async info => {
        if (!info || !state.currentStopId) return [];
        state.stopsJSON = await fetchRealTimeInfo(state.currentStopId, state.line, state.today, state.tripContext);
        firstIteration.refresh = false;
        await gtfsPromise;
        await refreshData(false);
        return state.stopsJSON;
    }).catch(error => {
        console.error('Caricamento realtime fallito:', error);
        return [];
    });

    if (!state.tripId) {
        console.error("Impossibile identificare il Trip ID.");
    }

    // Nessuna delle richieste blocca le altre. Il refresh periodico parte
    // quando il primo tentativo realtime e terminato, anche se senza dati.
    realtimePromise.finally(() => {
        clearInterval(tripDetailsRefreshTimer);
        tripDetailsRefreshTimer = setInterval(() => refreshData(true), 25000);
    });

    Promise.allSettled([infoPromise, gtfsPromise]).then(() => {
        if (state.destination && lastStop && state.destination !== lastStop) {
            console.warn('Destinazione GTFS diversa dal contesto selezionato', {
                destination: state.destination,
                requestedDestination: lastStop
            });
        }
    });
}

async function unpackTripId(tripId) {
    let text = '';
    try {
        const params = new URLSearchParams({
            return: 'true',
            tripId: tripId
        });
        let url = `/api/gtfs-resolve?${params.toString()}`;
        // console.log("https://actv-live.test"+url);

        const response = await fetch(url);
        text = await response.text();

        if (!response.ok) throw new Error("Error" + text);

        const data = JSON.parse(text);
        if (data.error) {
            console.warn("Errore fetchTripId:", data);
            // errorPopup(data.error);
        }

        return data;

    } catch (e) {
        console.error("Errore fetchTripId:", e);
        errorPopup(text);
        return null;
    }
}

/** Utility per calcolare minuti mancanti */
function formatMinutesRemaining(timeString) {
    if (!timeString || !timeString.includes(':')) return timeString;

    const now = new Date();
    const [h, m] = timeString.split(':').map(Number);

    // Creiamo l'oggetto target per oggi
    const target = new Date(now);
    target.setHours(h, m, 0, 0);

    // --- GESTIONE CAMBIO DATA ---
    // Se la differenza è superiore a 12 ore nel passato, assumiamo sia domani.
    // Se la differenza è superiore a 12 ore nel futuro, assumiamo fosse ieri (opzionale).
    const dodiciOreInMs = 12 * 60 * 60 * 1000;
    const diffMs = target - now;

    if (diffMs < -dodiciOreInMs) {
        // Esempio: sono le 23:00, target è "01:00". Aggiungiamo un giorno.
        target.setDate(target.getDate() + 1);
    } else if (diffMs > dodiciOreInMs) {
        // Esempio: sono le 01:00, target è "23:00". Togliamo un giorno.
        target.setDate(target.getDate() - 1);
    }

    const diffTotalMin = Math.trunc((target - now) / 60000);
    const absMin = Math.abs(diffTotalMin);
    const hours = Math.floor(absMin / 60);
    const mins = absMin % 60;

    // --- FORMATTAZIONE OUTPUT ---
    if (diffTotalMin === 0) return "< 1 min";

    if (diffTotalMin > 0) {
        // Futuro
        return diffTotalMin < 60
            ? `${diffTotalMin} min`
            : `${hours} h ${mins} min`;
    } else {
        // Passato
        return absMin < 60
            ? `Passato ${absMin} min fa`
            : `Passato ${hours} h ${mins} min fa &#128512;`;
    }
}

/** Recupera il Trip ID univoco */
async function fetchTripId(busTrack, busDirection, day, time, stop, lineId, stopId = null, excludeTripIds = []) {
    let text = '';
    try {
        const params = new URLSearchParams({
            return: 'true',
            time: time,
            busTrack: busTrack,
            busDirection: busDirection,
            day: day,
            stop: stop,
            lineId: lineId
        });
        if (stopId && stopId !== 'null') {
            params.append('stopId', stopId);
        }
        if (excludeTripIds.length) params.append('excludeTripIds', excludeTripIds.join(','));
        let url = `/api/gtfs-identify?${params.toString()}`;
        // console.log("https://actv-live.test"+url);

        const response = await fetch(url);
        text = await response.text();
        if (!response.ok) throw new Error("Error" + text);

        const data = JSON.parse(text);
        if (data.error) {
            console.warn("Errore fetchTripId:", data);
            errorPopup(`${data.error}, <br> ${JSON.stringify(data.params)} <br> <a href="${data.link}" target="_blank">Link</a>`);
            return null;
        }
        return data.trip_id;

    } catch (e) {
        console.error("Errore fetchTripId:", e);
        errorPopup("Errore fetchTripId: \"" + text + "\"");
        return null;
    }
}

/** Aggiorna i dati in tempo reale e ridisegna la lista */
async function refreshData(fetchRealtime = true) {
    try {
        if (fetchRealtime && state.currentStopId && state.line) {
            state.stopsJSON = await fetchRealTimeInfo(
                state.currentStopId,
                state.line,
                state.today,
                state.tripContext
            );
            firstIteration.refresh = false;
        }

        // Cerca se la fermata SELEZIONATA è nella lista GTFS
        const selectedIds = String(state.currentStopId || '').split('-').filter(Boolean);
        const selectedStopInGTFS = state.stopsGTFS.find(s =>
            selectedIds.includes(s.stop_id.toString())
        );

        state.arrivalTime = null;
        if (selectedStopInGTFS) {
            const selectedStopKey = normalizeStopName(selectedStopInGTFS.stop_name);
            const rtStop = state.stopsJSON.find(s => normalizeStopName(s.stop) === selectedStopKey);
            if (rtStop) {
                state.arrivalTime = rtStop.time;
            }
        }

        updateHeader();

        state.mergedStops = mergeStops();

        const unmatchedRealtimeStops = state.mergedStops
            .filter(stop => stop.hasRealTime && !stop.hasGTFS)
            .map(stop => ({ stop: stop.stop, time: stop.arrival_time }));
        if (unmatchedRealtimeStops.length) {
            console.warn('Realtime stops without GTFS match', {
                tripId: state.tripId,
                line: state.line,
                destination: state.destination,
                currentStopId: state.currentStopId,
                gtfsStopCount: state.stopsGTFS.length,
                realtimeStopCount: state.stopsJSON.length,
                gtfsLastStop: state.stopsGTFS[state.stopsGTFS.length - 1]?.stop_name ?? null,
                realtimeLastStop: state.stopsJSON[state.stopsJSON.length - 1]?.stop ?? null,
                stops: unmatchedRealtimeStops
            });
        }

        // Try to get real time for previous stops
        getPreviousStopsRealTime();

        // console.log(state.mergedStops);


        renderTimeline();
    } catch (error) {
        console.error("Refresh fallito:", error);
    }
}

/** Inizializza o aggiorna l'elemento Header */
function updateHeader() {
    const lineEl = document.getElementById('line-number');
    const destEl = document.getElementById('direction-name');
    const timeTextEl = document.getElementById('time-text');
    const timeContainer = document.getElementById('time-container');

    if (lineEl) {
        lineEl.innerText = state.line || '--';
        let badgeClass = 'badge-red';
        if (state.line?.includes('N')) badgeClass = 'badge-night';
        else if (["US", "UN", "EN"].includes(state.tag)) badgeClass = 'badge-blue';
        lineEl.className = `line-box line-badge ${badgeClass}`;
    }

    if (destEl) {
        destEl.innerText = state.destination?.replace(/\\/g, '') || 'Sconosciuta';
    }

    if (state.arrivalTime && timeTextEl) {
        if (timeContainer) timeContainer.style.display = 'flex';

        if (state.arrivalTime === 'departure') {
            timeTextEl.innerText = 'In partenza';
        } else {
            timeTextEl.innerText = formatMinutesRemaining(state.arrivalTime);
        }
    } else {
        if (timeContainer) timeContainer.style.display = 'none';
        if (timeTextEl) timeTextEl.innerText = '';
    }
    updateScheduleInfo();
}

/** Sotto i minuti mancanti: orario di arrivo previsto e scostamento
 *  rispetto all'orario di linea GTFS alla fermata selezionata. */
function updateScheduleInfo() {
    const delayEl = document.getElementById('time-delay');
    if (!delayEl) return;
    delayEl.innerHTML = '';
    delayEl.className = 'time-delay';
    delayEl.style.display = 'none';

    const arrival = state.arrivalTime;
    if (!arrival || !arrival.includes(':')) return;

    // Scostamento vs orario programmato della fermata selezionata
    const selectedIds = String(state.currentStopId || '').split('-').filter(Boolean);
    const gtfsStop = (state.stopsGTFS || []).find(s => selectedIds.includes(String(s.stop_id)));
    const schedTime = String(gtfsStop?.arrival_time || gtfsStop?.departure_time || '');
    if (schedTime.includes(':')) {
        const toMin = t => { const [h, m] = t.split(':').map(Number); return (h * 60 + m) % 1440; };
        const delay = ((toMin(arrival) - toMin(schedTime) + 720) % 1440 + 1440) % 1440 - 720;
        delayEl.style.display = '';
        if (delay > 0) {
            delayEl.classList.add('is-late');
            delayEl.innerText = `+${delay} min`;
        } else if (delay < 0) {
            delayEl.classList.add('is-early');
            delayEl.innerText = `−${-delay} min`;
        } else {
            delayEl.classList.add('is-ontime');
            delayEl.innerText = 'in orario';
        }
    }
}

/** Carica la lista fermate dal GTFS Builder */
async function fetchGTFSStops(tripId) {
    if (!tripId) return null;
    try {
        const response = await fetch(`/api/gtfs-builder?trip_id=${encodeURIComponent(tripId)}`);
        if (!response.ok) throw new Error("Errore API GTFS");
        return await response.json();
    } catch (e) {
        console.error(e);
        const container = document.getElementById('stops-container');
        if (container) container.innerHTML = '<div class="text-center text-danger">Errore caricamento percorso</div>';
        return null;
    }
}

/** Recupera informazioni real-time per la fermata selezionata */
async function fetchRealTimeInfo(currentStopId, line, today, context = null) {
    try {
        let url = `https://oraritemporeale.actv.it/aut/backend/passages/${currentStopId}-web-aut`;
        const response = await fetch(url, {
            cache: 'no-cache'
        });
        if (!response.ok) throw new Error("Errore RealTime");
        const trips = await response.json();

        // OPTIMIZATION: Filtra solo i trip della linea corrente
        const plausibleTrips = trips.filter(trip => {
            const tripLine = trip.line?.split('_')[0];
            return tripLine === line;
        });
        // Il contesto seleziona un passaggio, ma non prova l'identita GTFS:
        // risolvere sempre prima di associare stime e orari programmati.
        const results = [];
        const assignedTripIds = [];
        // Risoluzione intenzionalmente sequenziale: ogni trip_id assegnato
        // viene escluso dal candidato successivo, quindi due corse ravvicinate
        // non possono collassare sullo stesso viaggio GTFS.
        for (const trip of plausibleTrips) {
            // L'ID del passaggio appartiene al primo punto, gli altri timing
            // point del feed hanno soltanto il nome: non estenderlo a tutti.
            const timingPoints = Array.isArray(trip.timingPoints) ? trip.timingPoints.map((point, index) =>
                index === 0 && trip.stop != null && String(trip.stop).trim()
                    ? { ...point, stop_id: String(trip.stop).trim() } : point) : [];
            const stop = timingPoints[0];
            if (!stop) {
                results.push({ ...trip, calculatedTripId: null });
                continue;
            }

            const tid = await fetchTripId(
                trip.line.split('_')[0],
                trip.destination,
                today,
                stop.time,
                stop.stop,
                trip.lineId,
                trip.stop || currentStopId,
                assignedTripIds
            );
            if (tid) assignedTripIds.push(String(tid));
            results.push({ ...trip, timingPoints, calculatedTripId: tid });
        }
        return selectMatchingTripTimingPoints(results, state.tripId, {
            ...context,
            expectedDestination: state.destination
        });

    } catch (e) {
        console.error("fetchRealTimeInfo:", e);
        return [];
    }
}

/** Renderizza la timeline delle fermate */
function renderTimeline() {
    const container = document.getElementById('stops-container');
    if (!container) return;

    const timeline = document.createElement('div');
    timeline.className = 'timeline';

    const selectedStopIdx = state.mergedStops.findIndex(s =>
        state.currentStopId.split('-').includes(s.stop_id.toString())
    );

    // console.log("Merged: ", state.mergedStops);


    state.mergedStops.forEach((stop, index) => {
        const stopEl = document.createElement('div');
        stopEl.dataset.stopId = stop.stop_id;
        stopEl.dataset.index = index;

        timeline.appendChild(stopEl);
        updateSingleStopInTimeline(stop, selectedStopIdx, stopEl);
    });

    container.innerHTML = '';
    container.appendChild(timeline);

    // Auto-scroll
    if (firstIteration.scroll) {
        setTimeout(() => {
            const current = document.querySelector('.current-stop-item');
            current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 500);
        firstIteration.scroll = false;
    }
}

/** Apre la mappa della corsa senza perdere il dettaglio corrente. */
async function openMap() {
    const dialog = document.getElementById('trip-map-dialog');
    if (!dialog || !state.tripId) return;
    if (typeof L === 'undefined') {
        errorPopup('Mappa non disponibile. Verifica la connessione e riprova.');
        return;
    }
    dialog.hidden = false;
    document.body.classList.add('trip-map-open');
    updateTripMapHeader();

    if (!tripMap) {
        tripMap = L.map('trip-map', { attributionControl: false }).setView([45.4384, 12.3359], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(tripMap);
        // Geometria/fermate e posizione live sono indipendenti: il marker puo
        // apparire subito anche se la shape e ancora in download.
        void loadTripMap();
    }
    startTripMapUserLocation();
    setTimeout(() => tripMap.invalidateSize(), 0);
    void refreshTripVehicle();
    clearInterval(tripMapRefreshTimer);
    tripMapRefreshTimer = setInterval(refreshTripVehicle, 10000);
}

function updateTripMapHeader() {
    const lineBadge = document.getElementById('trip-map-line');
    const direction = document.getElementById('trip-map-direction');
    if (lineBadge) lineBadge.textContent = state.line || '--';
    if (direction) direction.textContent = state.destination?.replace(/\\/g, '') || 'Corsa ACTV';
}

function closeMap() {
    const dialog = document.getElementById('trip-map-dialog');
    if (dialog) dialog.hidden = true;
    document.body.classList.remove('trip-map-open');
    clearInterval(tripMapRefreshTimer);
    tripMapRefreshTimer = null;
    if (tripMapGeoWatchId !== null && navigator.geolocation?.clearWatch) {
        navigator.geolocation.clearWatch(tripMapGeoWatchId);
        tripMapGeoWatchId = null;
    }
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !document.getElementById('trip-map-dialog')?.hidden) closeMap();
});

function toggleMapStops() {
    const panel = document.getElementById('trip-map-stops');
    const button = panel?.querySelector('.trip-map-stops-header');
    if (!panel || !button) return;
    const collapsed = panel.classList.toggle('collapsed');
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
}

function mapStopTime(stop) {
    return String(stop.arrival_time || stop.departure_time || '').substring(0, 5) || '--:--';
}

function escapeMapHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
}

function mapStopId(stop) {
    return String(stop?.stop_id ?? stop?.id ?? '');
}

function normalizeMapColor(value) {
    let color = String(value ?? '').trim().replace(/^#/, '');
    if (/^[0-9a-f]{3}$/i.test(color)) color = color.split('').map(char => char + char).join('');
    return /^[0-9a-f]{6}$/i.test(color) ? `#${color.toUpperCase()}` : null;
}

function getContrastTextColor(background) {
    const color = normalizeMapColor(background);
    if (!color) return '#fff';
    const channels = [1, 3, 5].map(index => parseInt(color.slice(index, index + 2), 16) / 255);
    const linear = channels.map(channel => channel <= 0.03928
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4);
    const luminance = (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
    return luminance > 0.179 ? '#111827' : '#fff';
}

function findMergedMapStop(stop) {
    const id = mapStopId(stop);
    return state.mergedStops.find(item => String(item.stop_id) === id)
        || state.mergedStops.find(item => normalizeStopName(item.stop_name) === normalizeStopName(stop?.name || stop?.stop_name));
}

function mapStopPassageLabel(stop) {
    const merged = findMergedMapStop(stop);
    const time = String(merged?.arrival_time || merged?.departure_time || stop?.arrival_time || stop?.departure_time || '');
    if (time === 'departure') return 'Il bus è in partenza';
    if (!time.includes(':')) return 'Orario non disponibile';
    const remaining = formatMinutesRemaining(time);
    return merged?.hasRealTime ? `Passaggio realtime: tra ${remaining}` : `Passaggio previsto: tra ${remaining}`;
}

function createMapStopPopup(stop, index) {
    const name = stop?.name || stop?.stop_name || 'Fermata';
    return `<strong>${index + 1}. ${escapeMapHtml(name)}</strong><br><span>${escapeMapHtml(mapStopPassageLabel(stop))}</span>`;
}

function isSelectedMapStop(stop) {
    return tripMapSelectedStopId !== null && mapStopId(stop) === String(tripMapSelectedStopId);
}

function isVisitedMapStop(stop) {
    if (!tripMapProgress || !tripMapGeometry.length) return false;
    const position = findMapProgress({
        lat: Number(stop?.lat ?? stop?.stop_lat),
        lng: Number(stop?.lng ?? stop?.stop_lon)
    });
    if (!position) return false;
    return position.index + position.ratio < tripMapProgress.index + tripMapProgress.ratio - 0.01;
}

function mapStopBearing(stops, index) {
    const current = stops[index];
    const currentLat = Number(current?.lat ?? current?.stop_lat);
    const currentLng = Number(current?.lng ?? current?.stop_lon);
    if (!Number.isFinite(currentLat) || !Number.isFinite(currentLng)) return 0;

    let target = null;
    let forward = true;
    for (let next = index + 1; next < stops.length; next++) {
        const lat = Number(stops[next]?.lat ?? stops[next]?.stop_lat);
        const lng = Number(stops[next]?.lng ?? stops[next]?.stop_lon);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            target = [lat, lng];
            break;
        }
    }
    if (!target) {
        forward = false;
        for (let previous = index - 1; previous >= 0; previous--) {
            const lat = Number(stops[previous]?.lat ?? stops[previous]?.stop_lat);
            const lng = Number(stops[previous]?.lng ?? stops[previous]?.stop_lon);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                target = [lat, lng];
                break;
            }
        }
    }
    if (!target) return 0;

    const from = forward ? [currentLat, currentLng] : target;
    const to = forward ? target : [currentLat, currentLng];
    const latitude1 = from[0] * Math.PI / 180;
    const latitude2 = to[0] * Math.PI / 180;
    const deltaLongitude = (to[1] - from[1]) * Math.PI / 180;
    const bearing = Math.atan2(
        Math.sin(deltaLongitude) * Math.cos(latitude2),
        Math.cos(latitude1) * Math.sin(latitude2)
            - Math.sin(latitude1) * Math.cos(latitude2) * Math.cos(deltaLongitude)
    ) * 180 / Math.PI;
    return (bearing + 360) % 360;
}

function getMapStopArrowRotation(bearing) {
    const normalizedBearing = Number(bearing);
    return Number.isFinite(normalizedBearing) ? normalizedBearing - 90 : -90;
}

function isMapTerminalStop(stops, index) {
    return stops.length > 0 && (index === 0 || index === stops.length - 1);
}

function createMapStopIcon(selected, bearing, visited = false, terminal = false) {
    const className = `${selected ? ' trip-map-stop-icon-selected' : ''}${visited ? ' trip-map-stop-icon-visited' : ''}${terminal ? ' trip-map-stop-icon-terminal' : ''}`;
    const color = visited ? '#8b949e' : (selected ? '#075bbb' : '#087f5b');
    const symbol = terminal
        ? '<circle cx="12" cy="12" r="4"/>'
        : `<path d="M8 5l7 7-7 7" style="transform:rotate(${getMapStopArrowRotation(bearing)}deg);transform-origin:12px 12px"/>`;
    return L.divIcon({
        className: 'trip-map-stop-icon',
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        html: `<span class="trip-map-stop-badge${className}" style="--stop-color:${color}"><svg viewBox="0 0 24 24" aria-hidden="true">${symbol}</svg></span>`
    });
}

function updateMapStopMarkers() {
    tripMapStopMarkers.forEach((marker, index) => {
        const stop = tripMapStops[index];
        if (!stop) return;
        marker.setIcon(createMapStopIcon(
            isSelectedMapStop(stop),
            mapStopBearing(tripMapStops, index),
            isVisitedMapStop(stop),
            isMapTerminalStop(tripMapStops, index)
        ));
    });
}

function scrollMapToNextStopOnce(stops) {
    if (tripMapNextStopScrolled || !tripMapProgress) return;
    const list = document.getElementById('trip-map-stops-list');
    if (!list) return;
    const nextIndex = stops.findIndex(stop => !isVisitedMapStop(stop));
    if (nextIndex < 0) return;
    const next = list.querySelector(`[data-stop-index="${nextIndex}"]`);
    if (!next) return;

    tripMapNextStopScrolled = true;
    const targetScrollTop = next.offsetTop - (list.clientHeight - next.offsetHeight) / 2;
    const scrollTop = Math.max(0, targetScrollTop);
    if (typeof list.scrollTo === 'function') list.scrollTo({ top: scrollTop, behavior: 'smooth' });
    else list.scrollTop = scrollTop;
}

function renderMapStops(stops) {
    const list = document.getElementById('trip-map-stops-list');
    if (!list) return;
    const selectedIds = String(state.currentStopId || '').split('-');
    list.innerHTML = stops.map((stop, index) => {
        const id = String(stop.stop_id || stop.id || '');
        const current = selectedIds.includes(id) || isSelectedMapStop(stop) ? ' current' : '';
        const visited = isVisitedMapStop(stop) ? ' visited' : '';
        const nextVisited = stops[index + 1] ? isVisitedMapStop(stops[index + 1]) : false;
        const passedSegment = visited && nextVisited ? ' passed-segment' : '';
        const name = escapeMapHtml(stop.name || stop.stop_name || '');
        const status = visited ? 'Già passata' : 'Da raggiungere';
        return `<div class="trip-map-stop${current}${visited}${passedSegment}" data-stop-index="${index}" role="button" tabindex="0" aria-label="${escapeMapHtml(name)} · ${status}"><span class="trip-map-stop-marker"></span><span class="trip-map-stop-name">${name}</span><time>${escapeMapHtml(mapStopPassageLabel(stop))}</time></div>`;
    }).join('');

    list.querySelectorAll('.trip-map-stop').forEach(item => {
        const index = Number(item.dataset.stopIndex);
        const marker = tripMapStopMarkers.get(index);
        if (!marker) return;
        const open = () => marker.openPopup();
        item.addEventListener('click', open);
        item.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    });
    scrollMapToNextStopOnce(stops);
}

async function loadTripMap() {
    const status = document.getElementById('trip-map-status');
    try {
        // Prima chiediamo la geometria DB: per una corsa filtrata contiene la
        // shape_refined snapped alla strada. La cache JSON resta il fallback
        // per installazioni senza DB o durante un aggiornamento.
        const params = new URLSearchParams({ tripId: state.tripId, service: 'automobilistico' });
        let response = await fetch(`/api/lines-shapes?${params.toString()}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        let shapes = await response.json();
        if (!Array.isArray(shapes) || !shapes.length) {
            params.set('cache', '1');
            response = await fetch(`/api/lines-shapes?${params.toString()}`, { cache: 'no-store' });
            shapes = response.ok ? await response.json() : [];
        }
        tripMapShape = Array.isArray(shapes) ? shapes.find(item => String(item.trip_id) === String(state.tripId)) || shapes[0] : null;
        if (!tripMapShape) throw new Error('Percorso non disponibile');
        const lineBadge = document.getElementById('trip-map-line');
        if (lineBadge) {
            const routeColor = normalizeMapColor(tripMapShape.route_color);
            lineBadge.style.backgroundColor = routeColor || '';
            lineBadge.style.color = getContrastTextColor(routeColor);
            lineBadge.style.borderColor = getContrastTextColor(routeColor);
        }
        const geometry = Array.isArray(tripMapShape.shape) && tripMapShape.shape.length > 1 ? tripMapShape.shape : tripMapShape.path;
        const points = (geometry || []).map(point => [Number(point.lat), Number(point.lng)]).filter(point => point.every(Number.isFinite));
        if (points.length < 2) throw new Error('Traccia non disponibile');
        tripMapGeometry = points;
        tripMapRemainingRoute = L.polyline(points, { color: '#087f5b', weight: 7, opacity: 0.9 }).addTo(tripMap);
        tripMapCompletedRoute = null;
        tripMap.fitBounds(tripMapRemainingRoute.getBounds(), {
            paddingTopLeft: [40, 125],
            paddingBottomRight: [40, 220]
        });
        const stops = Array.isArray(tripMapShape.path) ? tripMapShape.path : state.stopsGTFS;
        tripMapStops = stops || [];
        tripMapProgress = null;
        tripMapNextStopScrolled = false;
        const selectedStop = state.stopsGTFS.find(stop =>
            String(state.currentStopId || '').split('-').includes(String(stop.stop_id))
        );
        tripMapSelectedStopId = selectedStop?.stop_id ?? null;
        tripMapStopMarkers.clear();
        (stops || []).forEach((stop, index) => {
            const lat = Number(stop.lat ?? stop.stop_lat), lng = Number(stop.lng ?? stop.stop_lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            const selected = isSelectedMapStop(stop);
            const marker = L.marker([lat, lng], {
                icon: createMapStopIcon(selected, mapStopBearing(stops, index), false, isMapTerminalStop(stops, index)),
                zIndexOffset: selected ? 500 : 100
            }).addTo(tripMap).bindPopup(createMapStopPopup(stop, index));
            marker.on('click', () => marker.setPopupContent(createMapStopPopup(stop, index)));
            tripMapStopMarkers.set(index, marker);
        });
        renderMapStops(stops || []);
        if (tripMapVehiclePosition) updateTripMapProgress(tripMapVehiclePosition);
        if (status && !tripMapVehiclePosition) status.textContent = 'Ricerca posizione del mezzo...';
    } catch (error) {
        if (status) status.textContent = error.message;
    }
}

/**
 * Seleziona i passaggi realtime solo quando l'identificazione GTFS è esatta.
 *
 * Il vecchio fallback restituiva results[0] quando nessun ID coincideva. In
 * questo modo una corsa plausibile, ma diversa, veniva fusa con il GTFS della
 * corsa richiesta e produceva fermate "senza GTFS". Un dato realtime assente è
 * preferibile a una timeline attribuita alla corsa sbagliata.
 */
/** Suffisso numerico del trip_id: i feed RT usano prefissi diversi dal GTFS
 *  statico (AUT_LL_793_* vs AUT_ACTV_794_*) ma il numero identifica la corsa. */
function tripIdSuffix(tripId) {
    const m = String(tripId ?? '').match(/_(\d+)$/);
    return m ? m[1] : null;
}

/** Le timingPoints realtime devono formare una sottosequenza (in ordine)
 *  delle fermate GTFS della corsa visualizzata. Se la variante di percorso è
 *  diversa (es. linea 6 con più varianti), i minuti risulterebbero sparsi e
 *  alcune fermate risulterebbero erroneamente "passate": meglio rifiutare.
 *  Ritorna il rapporto di match (1 = perfetto/non valutabile). */
function timingPointsFitRatio(timingPoints) {
    const gtfsNames = (state.stopsGTFS || []).map(s => normalizeStopName(s.stop_name));
    if (!gtfsNames.length || !Array.isArray(timingPoints) || !timingPoints.length) return 1;
    let cursor = 0, matched = 0, anyOverlap = 0;
    timingPoints.forEach(point => {
        const name = normalizeStopName(point.stop);
        if (!name) return;
        if (gtfsNames.includes(name)) anyOverlap++;
        for (let i = cursor; i < gtfsNames.length; i++) {
            if (gtfsNames[i] === name) { cursor = i + 1; matched++; return; }
        }
    });
    // Nessun nome in comune: la lista GTFS non e' confrontabile (es. dati di
    // test o corsa diversa) — non si puo' validare, si lascia passare.
    if (anyOverlap === 0) return 1;
    return matched / timingPoints.length;
}

function timingPointsFitGtfs(timingPoints, minRatio = 0.6) {
    return timingPointsFitRatio(timingPoints) >= minRatio;
}

function selectMatchingTripTimingPoints(results, requestedTripId, context = null) {
    const requested = String(requestedTripId ?? '');
    const exact = results.find(trip => String(trip.calculatedTripId ?? '') === requested);
    // Match esatto di trip_id: e' la corsa richiesta, il feed realtime puo'
    // legittimamente differire dal GTFS (fermate saltate, deviazioni). Il
    // fit-check serve solo a scartare candidati euristici sbagliati.
    if (exact) {
        return Array.isArray(exact.timingPoints) ? exact.timingPoints : [];
    }

    // Fallback per suffisso: AUT_LL_793_29893 -> AUT_ACTV_794_29893
    const requestedSuffix = tripIdSuffix(requestedTripId);
    if (requestedSuffix) {
        const bySuffix = results.filter(trip =>
            tripIdSuffix(trip.calculatedTripId) === requestedSuffix);
        if (bySuffix.length === 1) {
            return Array.isArray(bySuffix[0].timingPoints) ? bySuffix[0].timingPoints : [];
        }
    }

    const contextStop = normalizeStopName(context?.stop);
    const contextTime = String(context?.time ?? '').slice(0, 5);
    const expectedDestination = normalizeStopName(context?.expectedDestination);
    // Orario di contesto programmato (card schedulata): non confrontabile con
    // le stime RT — una corsa in ritardo puo' coincidere per puro caso con lo
    // schedulato della successiva. Solo il match per identita' e' affidabile.
    const contextIsScheduled = context?.realtime === false;
    if (contextStop && contextTime && !contextIsScheduled) {
        // Il campo destination del realtime ACTV (es. "27 OTTOBRE") non
        // coincide col headsign GTFS (es. "Cavergnago Taliercio"): non si può
        // filtrare per uguaglianza. Si filtra su fermata+orario e si usa la
        // destinazione solo per ordinare i candidati.
        const toMin = t => {
            const [h, m] = String(t ?? '').slice(0, 5).split(':').map(Number);
            return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
        };
        const contextMin = toMin(contextTime);
        // Match per fermata: l'orario RT del contesto e' una stima che si
        // aggiorna tra il click e il reload (e puo' includere il ritardo):
        // tolleranza ±10 min, vince il piu' vicino se univoco.
        const CONTEXT_TOLERANCE_MIN = 10;
        const contextMatches = [];
        results.forEach(trip => {
            // Un ID risolto diverso non puo essere scavalcato dal contesto.
            if (trip.calculatedTripId) return;
            if (!timingPointsFitGtfs(trip.timingPoints)) return;
            if (!Array.isArray(trip.timingPoints)) return;
            let bestDiff = null;
            trip.timingPoints.forEach(point => {
                if (normalizeStopName(point.stop) !== contextStop) return;
                const diff = toMin(point.time) != null && contextMin != null
                    ? Math.abs(toMin(point.time) - contextMin) : (contextMin == null ? 0 : null);
                if (diff !== null && diff <= CONTEXT_TOLERANCE_MIN
                    && (bestDiff === null || diff < bestDiff)) bestDiff = diff;
            });
            if (bestDiff !== null) contextMatches.push({ trip, diff: bestDiff });
        });
        contextMatches.sort((a, b) => a.diff - b.diff);
        if (contextMatches.length === 1
            || (contextMatches.length > 1 && contextMatches[0].diff < contextMatches[1].diff)) {
            const best = contextMatches[0].trip;
            console.info('Realtime trip selected from original context', {
                requestedTripId,
                matchedDestination: best.destination,
                contextStop: context.stop,
                contextTime: context.time,
                timeDiffMin: contextMatches[0].diff
            });
            return best.timingPoints;
        }
        // Piu' candidati a pari distanza: sicuro solo se condividono la stessa
        // sequenza fermata+orario (stessa corsa riportata due volte dal feed).
        // In caso contrario il dato realtime resta assente piuttosto che
        // attribuito alla corsa sbagliata.
        if (contextMatches.length > 1) {
            const signature = entry => entry.trip.timingPoints
                .map(point => normalizeStopName(point.stop) + '@' + String(point.time ?? '').slice(0, 5))
                .join('|');
            const first = signature(contextMatches[0]);
            if (contextMatches.every(entry => signature(entry) === first)) {
                console.info('Realtime trip selected among identical candidates', {
                    requestedTripId,
                    contextStop: context.stop,
                    contextTime: context.time
                });
                return contextMatches[0].trip.timingPoints;
            }
        }
    }

    console.warn('No matching trip found for tripId', {
        requestedTripId,
        context,
        candidates: results.map(trip => {
            const tps = Array.isArray(trip.timingPoints) ? trip.timingPoints : [];
            let bestContextDiff = null;
            if (contextStop) {
                tps.forEach(point => {
                    if (normalizeStopName(point.stop) !== contextStop) return;
                    const pm = toMinutesLocal(point.time);
                    const diff = pm != null && toMinutesLocal(contextTime) != null
                        ? Math.abs(pm - toMinutesLocal(contextTime)) : null;
                    if (diff !== null && (bestContextDiff === null || diff < bestContextDiff)) {
                        bestContextDiff = diff;
                    }
                });
            }
            return {
                tripId: trip.calculatedTripId ?? null,
                line: trip.line ?? null,
                destination: trip.destination ?? null,
                timingPoints: tps.length,
                lastStop: tps.length ? tps[tps.length - 1].stop : null,
                gtfsFitRatio: Math.round(timingPointsFitRatio(tps) * 100) / 100,
                contextStopDiffMin: bestContextDiff
            };
        })
    });
    return [];
}

function busMarkerSvg() {
    return `<svg class="trip-map-bus-svg" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
        <path d="M12 7h24c4.4 0 8 3.6 8 8v18a4 4 0 0 1-4 4h-2v4h-5v-4H15v4h-5v-4H8a4 4 0 0 1-4-4V15c0-4.4 3.6-8 8-8Z"/>
        <path class="trip-map-bus-window" d="M9 14h30v11H9z"/>
        <circle cx="14" cy="32" r="3"/><circle cx="34" cy="32" r="3"/>
        <path class="trip-map-bus-light" d="M7 18h3M38 18h3"/>
    </svg>`;
}

function projectMapPointToSegment(point, start, end) {
    const scaleX = 111320 * Math.cos((point[0] * Math.PI) / 180);
    const scaleY = 111320;
    const ax = start[1] * scaleX, ay = start[0] * scaleY;
    const bx = end[1] * scaleX, by = end[0] * scaleY;
    const px = point[1] * scaleX, py = point[0] * scaleY;
    const dx = bx - ax, dy = by - ay;
    const lengthSquared = dx * dx + dy * dy;
    const ratio = lengthSquared ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lengthSquared)) : 0;
    const projected = [start[0] + (end[0] - start[0]) * ratio, start[1] + (end[1] - start[1]) * ratio];
    return {
        ratio,
        point: projected,
        distanceSquared: ((projected[0] - point[0]) * scaleY) ** 2 + ((projected[1] - point[1]) * scaleX) ** 2
    };
}

function findMapProgress(position) {
    if (!tripMapGeometry.length || !position) return null;
    const point = [Number(position.lat), Number(position.lng)];
    if (!point.every(Number.isFinite)) return null;
    let best = null;
    for (let index = 0; index < tripMapGeometry.length - 1; index++) {
        const candidate = projectMapPointToSegment(point, tripMapGeometry[index], tripMapGeometry[index + 1]);
        if (!best || candidate.distanceSquared < best.distanceSquared) best = { ...candidate, index };
    }
    return best;
}

function updateTripMapProgress(position) {
    const progress = findMapProgress(position);
    if (!progress || !tripMap) return;
    tripMapProgress = { index: progress.index, ratio: progress.ratio };
    const passed = tripMapGeometry.slice(0, progress.index + 1);
    passed.push(progress.point);
    const remaining = [progress.point, ...tripMapGeometry.slice(progress.index + 1)];

    if (tripMapCompletedRoute) tripMapCompletedRoute.setLatLngs(passed);
    else tripMapCompletedRoute = L.polyline(passed, { color: '#8b949e', weight: 7, opacity: 0.95 }).addTo(tripMap);
    if (tripMapRemainingRoute) tripMapRemainingRoute.setLatLngs(remaining);
    updateMapStopMarkers();
    renderMapStops(tripMapStops);
}

function startTripMapUserLocation() {
    if (!tripMap || typeof navigator === 'undefined' || !navigator.geolocation || tripMapGeoWatchId !== null) return;

    const update = position => {
        const lat = Number(position.coords.latitude), lng = Number(position.coords.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        if (!tripMapUserMarker) {
            tripMapUserMarker = L.circleMarker([lat, lng], {
                radius: 8, color: '#fff', weight: 3, fillColor: '#1769e0', fillOpacity: 1, pane: 'markerPane'
            }).addTo(tripMap).bindPopup('La tua posizione');
        } else {
            tripMapUserMarker.setLatLng([lat, lng]);
        }
        const accuracy = Number(position.coords.accuracy);
        if (Number.isFinite(accuracy) && typeof L.circle === 'function') {
            if (!tripMapUserAccuracy) {
                tripMapUserAccuracy = L.circle([lat, lng], {
                    radius: accuracy, color: '#1769e0', weight: 1, fillColor: '#1769e0', fillOpacity: 0.12, interactive: false
                }).addTo(tripMap);
            } else {
                tripMapUserAccuracy.setLatLng([lat, lng]).setRadius(accuracy);
            }
        }
    };
    const fail = error => console.info('Posizione utente non disponibile sulla mappa', error?.message || error);
    if (typeof navigator.geolocation.watchPosition === 'function') {
        tripMapGeoWatchId = navigator.geolocation.watchPosition(update, fail, {
            enableHighAccuracy: true, maximumAge: 15000, timeout: 10000
        });
    } else {
        navigator.geolocation.getCurrentPosition(update, fail, { enableHighAccuracy: true, maximumAge: 15000, timeout: 10000 });
    }
}

async function refreshTripVehicle() {
    if (!tripMap || !state.tripId) return;
    const status = document.getElementById('trip-map-status');
    if (status) status.innerHTML = '<span class="trip-map-live-dot"></span>Ricerca del mezzo in corso…';
    try {
        const params = new URLSearchParams({ service: 'automobilistico', tripId: state.tripId });
        if (tripMapShape?.route_id) params.set('routeId', tripMapShape.route_id);
        const response = await fetch(`/api/realtime/vehicles?${params.toString()}`);
        if (!response.ok) throw new Error('Posizione non disponibile');
        const payload = await response.json();
        const vehicles = Array.isArray(payload) ? payload : payload.vehicles || [];
        const exactVehicle = vehicles.find(item => String(item.trip_id || '') === String(state.tripId));
        const routeVehicles = vehicles.filter(item =>
            tripMapShape?.route_id && String(item.route_id || '') === String(tripMapShape.route_id)
        );
        const selectedIds = String(state.currentStopId || '').split('-');
        const selectedStop = (tripMapShape?.path || []).find(stop => selectedIds.includes(String(stop.stop_id || stop.id || '')));
        const referenceLat = Number(selectedStop?.lat ?? selectedStop?.stop_lat);
        const referenceLng = Number(selectedStop?.lng ?? selectedStop?.stop_lon);
        const distanceSquared = item => {
            const lat = Number(item?.vehicle_position?.lat), lng = Number(item?.vehicle_position?.lon);
            return Number.isFinite(referenceLat) && Number.isFinite(referenceLng) && Number.isFinite(lat) && Number.isFinite(lng)
                ? (lat - referenceLat) ** 2 + (lng - referenceLng) ** 2
                : Number.MAX_VALUE;
        };
        const fallbackVehicle = routeVehicles.slice().sort((a, b) => distanceSquared(a) - distanceSquared(b))[0];
        const vehicle = exactVehicle || fallbackVehicle;
        const lat = Number(vehicle?.vehicle_position?.lat), lng = Number(vehicle?.vehicle_position?.lon);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            if (tripMapVehicleMarker) {
                tripMap.removeLayer(tripMapVehicleMarker);
                tripMapVehicleMarker = null;
            }
            if (status) status.innerHTML = '<span class="trip-map-live-dot offline"></span>Nessun mezzo attivo rilevato su questa corsa';
            return;
        }
        tripMapVehiclePosition = { lat, lng };
        updateTripMapProgress(tripMapVehiclePosition);
        if (!tripMapVehicleMarker) {
            const icon = L.divIcon({ className: 'trip-map-bus-icon', html: busMarkerSvg(), iconSize: [42, 42], iconAnchor: [21, 21] });
            tripMapVehicleMarker = L.marker([lat, lng], { icon, zIndexOffset: 1000 }).addTo(tripMap).bindPopup(`<strong>Linea ${state.line || ''}</strong><br>Posizione rilevata in tempo reale`);
        } else {
            tripMapVehicleMarker.setLatLng([lat, lng]);
        }
        if (status) status.innerHTML = `<span class="trip-map-live-dot"></span>${exactVehicle ? 'Posizione live' : 'Mezzo della linea'} · ${new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
    } catch (error) {
        if (status) status.textContent = error.message;
    }
}

function errorPopup(message) {
    if (!document.querySelector('.error-container')) {
        const container = document.createElement('div');
        container.className = 'error-container';
        document.body.appendChild(container);
    }
    const popup = document.createElement('div');
    popup.className = 'error-popup';
    popup.innerHTML = `
    <div class="error-popup">
        <div class="error-popup-content">
            <div class="error-popup-header">
                <div class="error-popup-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="#EF4444"/>
                    </svg>
                </div>
                <div class="error-popup-title">Errore</div>
                <button class="btn btn-danger btn-close" onclick="errorPopupClose()"></button>
            </div>
            <div class="error-popup-message">
                ${message}
            </div>
        </div>
    </div>
    <hr>
    `;
    document.querySelector('.error-container').appendChild(popup);
}

function errorPopupClose() {

    // Rimuovi il container se è l'ultimo elemento
    if (document.querySelector('.error-container').children.length === 1) {
        document.querySelector('.error-container').remove();
        return;
    }

    const popup = document.querySelector('.error-popup');
    popup.remove();
}

/** Allinea l'intera sequenza RT al GTFS: prima l'ordine, poi la distanza oraria. */
function alignRealtimeStops(gtfsStops, realtimeStops) {
    const rows = gtfsStops.length;
    const cols = realtimeStops.length;
    const scores = Array.from({ length: rows + 1 }, () => Array(cols + 1));
    const better = (a, b) => a.matches > b.matches
        || (a.matches === b.matches && a.distance < b.distance);
    for (let i = rows; i >= 0; i--) {
        for (let j = cols; j >= 0; j--) {
            if (i === rows && j === cols) {
                scores[i][j] = { matches: 0, distance: 0 };
                continue;
            }
            let best = i < rows ? { ...scores[i + 1][j], action: 'skipGTFS' }
                : { ...scores[i][j + 1], action: 'skipRT' };
            if (j < cols) {
                const skip = { ...scores[i][j + 1], action: 'skipRT' };
                if (better(skip, best)) best = skip;
            }
            if (i < rows && j < cols) {
                const name = normalizeStopName(gtfsStops[i].stop_name);
                const realtimeId = realtimeStops[j].stop_id;
                const sameStop = realtimeId != null
                    ? String(gtfsStops[i].stop_id) === String(realtimeId)
                    : name && name === normalizeStopName(realtimeStops[j].stop);
                if (sameStop) {
                    const scheduled = toAbsMinutes(gtfsStops[i].arrival_time || gtfsStops[i].departure_time);
                    const realtime = toAbsMinutes(realtimeStops[j].time);
                    const diff = scheduled == null || realtime == null ? 0 : Math.abs(scheduled - realtime);
                    const next = scores[i + 1][j + 1];
                    const match = { matches: next.matches + 1,
                        distance: next.distance + Math.min(diff, 1440 - diff), action: 'match' };
                    if (better(match, best)) best = match;
                }
            }
            scores[i][j] = best;
        }
    }
    const aligned = new Map();
    let i = 0, j = 0;
    while (i < rows || j < cols) {
        const action = scores[i][j].action;
        if (action === 'match') { aligned.set(i++, j++); }
        else if (action === 'skipGTFS') i++;
        else j++;
    }
    return aligned;
}

function mergeStops() {
    const merged = [];
    const aligned = alignRealtimeStops(state.stopsGTFS, state.stopsJSON);
    const usedRealtime = new Set(aligned.values());

    state.stopsGTFS.forEach((gtfsStop, index) => {
        const jsonItem = aligned.has(index) ? state.stopsJSON[aligned.get(index)] : null;

        const mergedItem = {
            ...gtfsStop,
            // Se esiste JSON, prendi i dati da lì, altrimenti usa GTFS
            arrival_time: jsonItem ? jsonItem.time : gtfsStop.arrival_time,
            departure_time: jsonItem ? jsonItem.time : gtfsStop.departure_time,
            // Orario di linea preservato: il realtime sovrascrive arrival_time
            scheduled_arrival: gtfsStop.arrival_time,
            // RT dalla timeline principale (stopsJSON), distinto dall'arricchimento
            // delle fermate precedenti fatto da getPreviousStopsRealTime
            rtTimeline: !!jsonItem,
            // Aggiungi flag per sapere da dove provengono i dati
            hasRealTime: !!jsonItem,
            hasGTFS: true
        };

        merged.push(mergedItem);

    });

    // Mantieni visibili eventuali punti RT assenti dal percorso GTFS.
    state.stopsJSON.forEach((jsonItem, index) => {
        if (!usedRealtime.has(index)) {
            merged.push({ ...jsonItem, hasRealTime: true, hasGTFS: false });
        }
    });

    return merged;
}

/** "HH:MM[:SS]" → minuti del giorno; accetta anche formati "N'" relativi. */
function toMinutesLocal(t) {
    if (t == null) return null;
    const s = String(t);
    if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(s)) {
        const [h, m] = s.split(':').map(Number);
        return h * 60 + m;
    }
    return null;
}

/** Come toMinutesLocal, ma converte anche i countdown del feed ("4'") in
 *  minuti assoluti del giorno (now + N). */
function toAbsMinutes(t) {
    const abs = toMinutesLocal(t);
    if (abs != null) return abs % 1440;
    const rel = String(t ?? '').trim().match(/^(\d+)['’]?$/);
    if (!rel) return null;
    const now = new Date();
    return (now.getHours() * 60 + now.getMinutes() + parseInt(rel[1], 10)) % 1440;
}

function normalizeStopName(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[’`´]/g, "'")
        .replace(/[^a-zA-Z0-9]+/g, ' ')
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

async function getPreviousStopsRealTime_block() {
    // 1. Trova l'indice (corretto con .some per sicurezza)
    let currentStopIdSplitted = state.currentStopId.split("-");
    const currentStopIndex = state.mergedStops.findIndex(stop =>
        currentStopIdSplitted.some(id => stop.stop_id == id)
    );

    let previousStops = state.mergedStops.slice(0, currentStopIndex);

    // 2. Trasformiamo il forEach in una lista di Promesse usando .map()
    const stopPromises = previousStops.map(async (stop) => {
        const dataUrl = stop.data_url;
        let tripList = await returnTripList(dataUrl);

        // 3. Anche qui usiamo .map per gestire i trip interni
        const tripPromises = tripList.map(async (trip) => {
            let guard = (state.lineFull === trip.line && state.destination === trip.destination);

            if (guard) {
                let offset = Math.min(2, trip.timingPoints.length);
                let penumtimoTP = trip.timingPoints[trip.timingPoints.length - offset];
                if (!penumtimoTP) return;

                let tid = await fetchTripId(
                    trip.line.split('_')[0],
                    trip.destination,
                    state.today,
                    penumtimoTP.time,
                    penumtimoTP.stop,
                    trip.lineId
                );

                if (tid == state.tripId) {
                    stop.arrival_time = trip.time;
                    stop.departure_time = trip.time;
                    stop.hasRealTime = true;
                }
            }
        });

        // Aspettiamo che tutti i trip di questa fermata siano processati
        await Promise.all(tripPromises);
    });

    // 4. Aspettiamo che TUTTE le fermate siano state elaborate
    await Promise.all(stopPromises);


    // 5. Ora puoi renderizzare in sicurezza
    renderTimeline();
}

/** Match conservativo sul seguito della corsa, senza risolvere di nuovo il trip_id. */
function selectPreviousStopTrip(trips, lineFull, timeline, expectedStop = null) {
    if (!timeline.length) return null;
    const first = timeline[0];
    const firstName = normalizeStopName(first.stop);
    const firstMin = toAbsMinutes(first.time);
    if (!firstName || firstMin == null) return null;

    const candidates = trips.filter(trip => {
        if (trip.line !== lineFull || trip.real !== true) return false;
        if (expectedStop) {
            const passageId = String(trip.stop ?? '').trim();
            const expectedId = String(expectedStop.stop_id);
            if (passageId) {
                if (passageId !== expectedId) return false;
            } else {
                // Senza ID nel passaggio e con un feed a piu direzioni non
                // si puo scegliere la banchina in base al solo nome.
                const feedIds = String(expectedStop.data_url || '').replace(/-web-aut$/, '').split('-');
                if (feedIds.length !== 1 || feedIds[0] !== expectedId) return false;
            }
        }
        const points = Array.isArray(trip.timingPoints) ? trip.timingPoints : [];
        const anchors = points.filter(point => normalizeStopName(point.stop) === firstName);
        // Un'ancora ripetuta non identifica un'occorrenza della fermata.
        if (anchors.length !== 1 || toAbsMinutes(anchors[0].time) !== firstMin) return false;
        const start = points.indexOf(anchors[0]);
        const continuation = points.slice(start);
        if (continuation.length !== timeline.length) return false;
        if (!continuation.every((point, index) =>
            normalizeStopName(point.stop) === normalizeStopName(timeline[index].stop)
            && toAbsMinutes(point.time) === toAbsMinutes(timeline[index].time))) return false;

        const candidateMin = toAbsMinutes(trip.time);
        if (candidateMin == null) return false;
        const ahead = ((candidateMin - firstMin + 720) % 1440 + 1440) % 1440 - 720;
        return ahead <= 0;
    });
    // Anche due candidati con lo stesso seguito restano ambigui a monte.
    return candidates.length === 1 ? candidates[0] : null;
}

async function getPreviousStopsRealTime() {

    const mergedStops = state.mergedStops;
    const timeline = state.stopsJSON;
    const currentIds = String(state.currentStopId || '').split('-');
    const currentStopIndex = mergedStops.findIndex(stop => currentIds.includes(String(stop.stop_id)));
    if (currentStopIndex < 0 || !timeline.length) return;
    const previousStops = mergedStops.slice(0, currentStopIndex);

    await Promise.all(previousStops.map(async stop => {
        // I punti della timeline principale sono gia' associati alla corsa.
        if (stop.rtTimeline || !stop.data_url) return;
        try {
            const trips = await returnTripList(stop.data_url);
            // Un refresh puo' aver sostituito la timeline durante la richiesta.
            if (state.mergedStops !== mergedStops || state.stopsJSON !== timeline) return;
            const trip = selectPreviousStopTrip(trips, state.lineFull, timeline, stop);
            if (!trip) return;
            stop.arrival_time = trip.time;
            stop.departure_time = trip.time;
            stop.hasRealTime = true;
            updateSingleStopInTimeline(stop, currentStopIndex);
        } catch (error) {
            console.warn('Realtime fermata precedente non disponibile', { stopId: stop.stop_id, error });
        }
    }));
}

async function returnTripList(dataUrl) {

    let apiBase = "https://oraritemporeale.actv.it/aut/backend/passages/"
    let response = await fetch(apiBase + dataUrl);
    let data = await response.json();
    return data;
}

function updateSingleStopInTimeline(stop, selectedStopIdx, domEl = null) {
    let stopEl;
    if (!domEl) {
        const index = state.mergedStops.indexOf(stop);
        stopEl = document.querySelector(`.stop-item[data-index="${index}"][data-stop-id="${stop.stop_id}"]`);

    } else {
        stopEl = domEl;
    }

    if (!stopEl) return;
    let index = parseInt(stopEl.dataset.index);

    // const rtInfo = state.stopsJSON.find(s => s.stop === stop.stop_name);

    const isSelected = (index === selectedStopIdx);
    // "Passata" non coincide con "senza realtime": il feed RT parte dalla
    // fermata interrogata e NON indica la posizione del bus — le fermate
    // precedenti possono benissimo essere ancora da servire (bus in anticipo
    // o non ancora partito). Una fermata e' passata solo se il suo orario
    // (RT o programmato) e' gia' trascorso.
    const nowMin = new Date().getHours() * 60 + new Date().getMinutes();
    const arrivalMin = toMinutesLocal(stop.arrival_time);
    // Distanza circolare mod 24h: il GTFS usa ore >24 per le corse dopo
    // mezzanotte ("24:15" = 00:15), un confronto lineare sbaglierebbe il segno.
    const circDiff = arrivalMin == null ? null
        : ((arrivalMin % 1440) - nowMin + 1440) % 1440;
    const isPrevious = circDiff != null && circDiff > 720;

    let statusClass = '';
    if (isPrevious) statusClass = 'passed';
    else if (isSelected) statusClass = 'current current-stop-item';
    else if (!stop.hasRealTime) statusClass = 'no-rt';

    // --- GESTIONE VISUALIZZAZIONE ORARIO ---
    let timeDisplay = "--:--";

    if (stop.arrival_time && stop.arrival_time.includes('\'')) {
        stop.arrival_time = stop.arrival_time.replace('\'', ' min');
    }

    if (isPrevious) {
        const sched = stop.scheduled_arrival || stop.arrival_time;
        timeDisplay = "Passato (" + String(sched).substring(0, 5) + ")*";
    } else if (stop.hasRealTime) {
        timeDisplay = formatMinutesRemaining(stop.arrival_time);
    } else if (arrivalMin == null) {
        timeDisplay = "Info N.D.";
    } else {
        // Orario programmato futuro senza conferma realtime
        timeDisplay = stop.arrival_time.substring(0, 5) + "*";
    }

    if (stop.arrival_time === 'departure') {
        timeDisplay = '< 1 min';
    }

    if (!stop.hasGTFS) {
        return;
    }

    // console.log(stop);

    const stopNameEscaped = encodeURIComponent(stop.stop_name);

    stopEl.className = `stop-item ${statusClass}`;
    try {
        stopEl.style.cursor = 'pointer';
        // data_url manca per le fermate non mappate nel feed realtime ACTV
        // (es. navigazione): si ripiega sullo stop_id GTFS.
        const stopIdShort = stop.data_url
            ? stop.data_url.split("-").slice(0, -2).join("-")
            : String(stop.stop_id);
        stopEl.onclick = () => {
            window.location.href = `/aut/stops/stop?id=${stopIdShort}&name=${stopNameEscaped}`;
        };
    } catch (e) {
        console.warn(e);
        stopEl.style.cursor = 'default';
        stopEl.style.backgroundColor = '#2222';
        stopEl.style.borderRadius = '5px';
        stopEl.onclick = () => {
            errorPopup("URL Fermata non disponibile");
        };
    }

    stopEl.innerHTML = `
        <div class="stop-line ${isPrevious ? 'passed' : (!stop.hasRealTime ? 'no-rt' : '')}"></div>
        <div class="stop-marker ${statusClass}"></div>
        <div class="stop-content">
            <div class="stop-name">${stop.stop_name}</div>
            <div class="stop-time">${timeDisplay}</div>
        </div>
    `;
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatMinutesRemaining,
        getContrastTextColor,
        getMapStopArrowRotation,
        isMapTerminalStop,
        mergeStops,
        normalizeMapColor,
        normalizeStopName,
        selectMatchingTripTimingPoints,
        fetchRealTimeInfo,
        updateHeader,
        selectPreviousStopTrip,
        getPreviousStopsRealTime,
        state
    };
}
