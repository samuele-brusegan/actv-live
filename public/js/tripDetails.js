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
    mergedStops: []      // Lista fermate merge
};

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    state.lineFull = urlParams.get('line');
    state.line = state.lineFull?.split('_')[0];
    state.tag = state.lineFull?.split('_')[1];
    state.destination = urlParams.get('dest');

    // "stopId" nell'URL rappresenta la fermata cliccata dall'utente
    state.currentStopId = urlParams.get('stopId');
    state.arrivalTime = urlParams.get('time');

    init();
});

/** Inizializzazione della pagina */
async function init() {
    const dow = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
    state.today = dow[new Date().getDay()];

    // Recupero info contestuali dalla sessione (usati per identificare univocamente la corsa)
    const trackName = sessionStorage.getItem('busTrack');
    const lastStop = sessionStorage.getItem('lastStop');
    const timedStop = sessionStorage.getItem('timedStop');
    const realTime = sessionStorage.getItem('realTime');
    const lineId = sessionStorage.getItem('lineId');

    // 1. Identifica il Trip ID univoco nel GTFS
    state.tripId = await fetchTripId(trackName, lastStop, state.today, realTime, timedStop, lineId);

    if (!state.tripId) {
        console.error("Impossibile identificare il Trip ID.");
    }

    // Inizializza l'intestazione
    updateHeader();

    // 2. Carica il percorso statico (GTFS)
    state.stopsGTFS = await fetchGTFSStops(state.tripId);

    // 3. Primo rendering e avvio loop di aggiornamento
    if (state.stopsGTFS) {
        await refreshData();
        setInterval(refreshData, 25000);
    }
}

/** Utility per calcolare minuti mancanti */
function formatMinutesRemaining(timeString) {
    if (!timeString || !timeString.includes(':')) return timeString; // Ritorna la stringa originale se non parsabile

    const now = new Date();
    const [h, m] = timeString.split(':').map(Number);
    const target = new Date();
    target.setHours(h, m, 0, 0);

    // Gestione basilare del cambio giorno (se target è ieri, ecc... non gestito profondamente qui)
    const diffMin = Math.trunc((target - now) / 60000);

    if (diffMin < 0) return "Passato";
    if (diffMin === 0) return "< 1 min";
    return `${diffMin} min`;
}

/** Recupera il Trip ID univoco */
async function fetchTripId(busTrack, busDirection, day, time, stop, lineId) {
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
        let url = `/api/gtfs-identify?${params.toString()}`;
        // console.log("https://actv-live.test"+url);
        
        const response = await fetch(url);
        text = await response.text();

        if (!response.ok) return null;
        const data = JSON.parse(text);
        if (data.error) {
            console.warn("Errore fetchTripId:", data);
            // errorPopup(data.error);
        }
        return data.trip_id;

    } catch (e) {
        console.error("Errore fetchTripId:", e);
        errorPopup(text);
        return null;
    }
}

/** Aggiorna i dati in tempo reale e ridisegna la lista */
async function refreshData() {
    try {
        state.stopsJSON = await fetchRealTimeInfo();

        // Cerca se la fermata SELEZIONATA è nella lista GTFS
        const selectedStopInGTFS = state.stopsGTFS.find(s =>
            state.currentStopId.split('-').includes(s.stop_id.toString())
        );

        if (selectedStopInGTFS) {
            const rtStop = state.stopsJSON.find(s => s.stop === selectedStopInGTFS.stop_name);
            if (rtStop) {
                state.arrivalTime = rtStop.time;
            }
        }

        updateHeader();

        state.mergedStops = mergeStops();

        // Try to get real time for previous stops
        getPreviousStopsRealTime();

        console.log(state.mergedStops);
        

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
async function fetchRealTimeInfo() {
    try {
        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${state.currentStopId}-web-aut`, {
            cache: 'no-cache'
        });
        if (!response.ok) throw new Error("Errore RealTime");
        const trips = await response.json();

        // OPTIMIZATION: Filtra solo i trip della linea corrente
        const plausibleTrips = trips.filter(trip => {
            const tripLine = trip.line?.split('_')[0];
            return tripLine === state.line;
        });

        const matchPromises = plausibleTrips.map(async trip => {
            const stop = trip.timingPoints[trip.timingPoints.length - 1];
            const tid = await fetchTripId(
                trip.line.split('_')[0],
                trip.destination,
                state.today,
                stop.time,
                stop.stop,
                trip.lineId
            );
            return { ...trip, calculatedTripId: tid };
        });

        const results = await Promise.all(matchPromises);
        const interestingTrip = results.find(t => t.calculatedTripId === state.tripId);

        return interestingTrip ? interestingTrip.timingPoints : [];
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

    state.mergedStops.forEach((stop, index) => {
        const stopEl = document.createElement('div');
        // const rtInfo = state.stopsJSON.find(s => s.stop === stop.stop_name);

        const isSelected = (index === selectedStopIdx);
        // Calcoliamo se la fermata è graficamente precedente
        const isGraphicallyPrevious = (selectedStopIdx !== -1 && index < selectedStopIdx);
        const isPrevious = !stop.hasRealTime;
        
        let statusClass = '';
        if      (isPrevious) statusClass = 'passed';
        else if (isSelected) statusClass = 'current current-stop-item';

        // --- GESTIONE VISUALIZZAZIONE ORARIO ---
        let timeDisplay = "--:--";
        
        if (stop.arrival_time && stop.arrival_time.includes('\'')) {
            stop.arrival_time = stop.arrival_time.replace('\'', ' min');
        }

        if (stop.hasRealTime) {
            timeDisplay = formatMinutesRemaining(stop.arrival_time);
        } else {
            timeDisplay = ("Passato (" + stop.arrival_time?.substring(0, 5) + ")*") || "Info N.D.";
        }
        
        if (stop.arrival_time === 'departure') {
            timeDisplay = '< 1 min';
        }

        if (!stop.hasGTFS) {
            console.warn("Stop without GTFS:", stop);
            console.error("Ergo: tripID sbagliato", state);
            return;
        }
        
        const stopIdShort = stop.data_url.split("-").slice(0, -2).join("-");
        const stopNameEscaped = encodeURIComponent(stop.stop_name);

        stopEl.className = `stop-item ${statusClass}`;
        stopEl.style.cursor = 'pointer';
        stopEl.onclick = () => {
            window.location.href = `/aut/stops/stop?id=${stopIdShort}&name=${stopNameEscaped}`;
        };

        stopEl.innerHTML = `
            <div class="stop-line ${isPrevious ? 'passed' : ''}"></div>
            <div class="stop-marker ${statusClass}"></div>
            <div class="stop-content">
                <div class="stop-name">${stop.stop_name}</div>
                <div class="stop-time">${timeDisplay}</div>
            </div>
        `;
        timeline.appendChild(stopEl);
    });

    container.innerHTML = '';
    container.appendChild(timeline);

    // Auto-scroll
    setTimeout(() => {
        const current = document.querySelector('.current-stop-item');
        current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 500);
}

/** Naviga alla mappa delle linee */
function openMap() {
    const url = state.tripId
        ? `/lines-map?tripId=${encodeURIComponent(state.tripId)}`
        : `/lines-map?line=${encodeURIComponent(state.line)}`;
    window.location.href = url;
}

function errorPopup(message) {
    const popup = document.createElement('div');
    popup.className = 'error-popup';
    popup.innerHTML = `
    <div class="error-popup">
        <div class="error-popup-content">
            <div class="error-popup-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="#EF4444"/>
                </svg>
            </div>
            <div class="error-popup-message">
                ${message}
            </div>
            <button class="error-popup-close" onclick="errorPopupClose()">Chiudi</button>
        </div>
    </div>
    `;
    document.body.appendChild(popup);
}

function errorPopupClose() {
    const popup = document.querySelector('.error-popup');
    popup.remove();
}

function mergeStops() {
    const merged = [];
    const jsonMap = new Map();

    // 1. Popola la mappa con i dati JSON (Real-Time)
    state.stopsJSON.forEach(item => {
        jsonMap.set(item.stop, item);
    });

    // 2. Itera sulle fermate GTFS e fonde i dati
    state.stopsGTFS.forEach(gtfsStop => {
        const stopName = gtfsStop.stop_name;
        const jsonItem = jsonMap.get(stopName);

        const mergedItem = {
            ...gtfsStop,
            // Se esiste JSON, prendi i dati da lì, altrimenti usa GTFS
            arrival_time: jsonItem ? jsonItem.time : gtfsStop.arrival_time,
            departure_time: jsonItem ? jsonItem.time : gtfsStop.departure_time,
            // Aggiungi flag per sapere da dove provengono i dati
            hasRealTime: !!jsonItem,
            hasGTFS: true
        };

        merged.push(mergedItem);

        // Rimuovi l'elemento dalla mappa per marcare come "usato"
        jsonMap.delete(stopName);
    });

    // 3. Aggiungi eventuali fermate JSON che non erano nel GTFS (raro ma possibile)
    jsonMap.forEach((jsonItem, stopName) => {
        merged.push({
            ...jsonItem,
            hasRealTime: true,
            hasGTFS: false
        });
    });

    return merged;
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
    
    console.log("Ho finito");

    // 5. Ora puoi renderizzare in sicurezza
    renderTimeline();
}

function getPreviousStopsRealTime() {
    // Find the index of the current stop
    let currentStopIdSplitted = state.currentStopId.split("-");
    const currentStopIndex = state.mergedStops.findIndex( stop => currentStopIdSplitted.find(id => stop.stop_id == id));
    
    
    // Get all stops before the current stop
    let previousStops = state.mergedStops.slice(0, currentStopIndex);
    
    // For Each previous stop, fetch data url
    //     For Each SIMILAR trip in tripList, find trip Id
    //         Per quei che metcha setta il time alla fermata

    previousStops.forEach(async stop => {
        const dataUrl = stop.data_url;
        const stopName = stop.stop_name;
        const stopId = stop.stop_id;
       
        // console.log(stopName, stopId, dataUrl);
        
        let tripList = await returnTripList(dataUrl);
        
        tripList.forEach(async trip => {
            // console.log(state, trip);
            
            /*def Similar: 
                - Same line
                - Same direction
            */
            let guard = true;
            if (state.lineFull != trip.line)           guard = false;
            if (state.destination != trip.destination) guard = false;
            
            if (guard) {
                // Calcolo il trip ID
                let offset = 2;
                if (trip.timingPoints.length < offset) offset = trip.timingPoints.length;
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

                
                
                // Se c'è il MATCH
                if (tid == state.tripId) {
                    console.log(stopName, tid, state.tripId, trip.time);
                    
                    stop.arrival_time = trip.time;
                    stop.departure_time = trip.time;
                    stop.hasRealTime = true;
                    renderTimeline();
                }
                
            }
            
        });
        
    });
}

async function returnTripList(dataUrl) {
    
    let apiBase = "https://oraritemporeale.actv.it/aut/backend/passages/"
    let response = await fetch(apiBase + dataUrl);
    let data = await response.json();
    return data;
}