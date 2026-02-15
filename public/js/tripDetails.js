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
let firstIteration = {
    refresh: true,
    scroll: true
};

document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);

    state.tripId = urlParams.get('tripId');
    console.log(sessionStorage.getItem('tripDetails_url'));
    

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
async function init() {
    const dow = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
    state.today = dow[new Date().getDay()];

    // Recupero info contestuali dalla sessione (usati per identificare univocamente la corsa)
    const trackName = sessionStorage.getItem('busTrack');
    const lastStop = sessionStorage.getItem('lastStop');
    const timedStop = sessionStorage.getItem('timedStop');
    const realTime = sessionStorage.getItem('realTime');
    const lineId = sessionStorage.getItem('lineId');

    let initUnpackTripId = async () => {
        console.log("Start Unpacking TripID");
        
        let data = await unpackTripId(state.tripId);
        state.line = data.bus_track;
        state.destination = data.bus_direction;
        state.tag = data.line_tag;
        state.lineFull = state.line + "_" + state.tag;

        console.log("Unpacked TripID");

        let loadingBox = document.querySelector('.loading-state');
        if (loadingBox) loadingBox.innerHTML += '<br>Info corsa caricate';
        updateHeader();
    }
    let initStopsGTFS = async () => {
        console.log("Start Fetching GTFS Stops");
        state.stopsGTFS = await fetchGTFSStops(state.tripId);

        console.log("Fetched GTFS Stops");

        let loadingBox = document.querySelector('.loading-state');
        if (loadingBox) loadingBox.innerHTML += '<br>Percorso caricato';
    }
    
    await Promise.all([initUnpackTripId(), initStopsGTFS()]);
    
    let initStopsJSON = async () => {
        console.log("Start Fetching Real Time Info");
        state.currentStopId = sessionStorage.getItem('tripDetails_selectedStop');
        state.stopsJSON = await fetchRealTimeInfo(state.currentStopId, state.line, state.today);

        console.log("Fetched Real Time Info", "stopsJSON", state.stopsJSON);

        let loadingBox = document.querySelector('.loading-state');
        if (loadingBox) loadingBox.innerHTML += '<br>Fermate caricate';
    }
    await initStopsJSON();
    
    //set stopId from url
    state.currentStopId = sessionStorage.getItem('tripDetails_selectedStop');
    console.log("State", state);

    // Destination e last stop non matchano lancio un warn in console
    if (state.destination != lastStop) {
        console.warn(`REQUESTED USER CONTROL: \n
            Destination e last stop non matchano\n
            dest.   : ${state.destination}\n
            lastStop: ${lastStop}
        `);
    }

    // 1. Identifica il Trip ID univoco nel GTFS
    //state.tripId = await fetchTripId(trackName, lastStop, state.today, realTime, timedStop, lineId);

    if (!state.tripId) {
        console.error("Impossibile identificare il Trip ID.");
    }

    // Inizializza l'intestazione
    updateHeader();

    // 2. Carica il percorso statico (GTFS)
    // state.stopsGTFS = await fetchGTFSStops(state.tripId);

    // 3. Primo rendering e avvio loop di aggiornamento
    if (state.stopsGTFS) {
        await refreshData();
        setInterval(refreshData, 25000);
    }
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
        console.log(data);
        
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
async function fetchTripId(busTrack, busDirection, day, time, stop, lineId, stopId = null) {    
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
        errorPopup("Errore fetchTripId: \""+text+"\"");
        return null;
    }
}

/** Aggiorna i dati in tempo reale e ridisegna la lista */
async function refreshData() {
    try {
        if (firstIteration.refresh) {
            state.stopsJSON = await fetchRealTimeInfo(state.currentStopId, state.line, state.today);
            firstIteration.refresh = false;
        }

        // Cerca se la fermata SELEZIONATA è nella lista GTFS
        const selectedStopInGTFS = state.stopsGTFS.find(s =>
            state.currentStopId.split('-').includes(s.stop_id.toString())
        );
        console.log("selectedStopInGTFS", selectedStopInGTFS);

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
async function fetchRealTimeInfo(currentStopId, line, today) {
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
        console.log(plausibleTrips);
        const matchPromises = plausibleTrips.map(async trip => {
            const stop = trip.timingPoints[trip.timingPoints.length - 1];
            const tid = await fetchTripId(
                trip.line.split('_')[0],
                trip.destination,
                today,
                stop.time,
                stop.stop,
                trip.lineId,
                currentStopId // Pass data_url as stopId
            );
            return { ...trip, calculatedTripId: tid };
        });
        
        const results = await Promise.all(matchPromises);
        console.log("Fetched Real Time Info", "results", results);
        const interestingTrip = results.find(t => t.calculatedTripId == state.tripId);
        if (interestingTrip) return interestingTrip.timingPoints;
        
        console.warn("No matching trip found for tripId Using loose matching", state.tripId);
                
        return results[0] ? results[0].timingPoints : [];

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

/** Naviga alla mappa delle linee */
function openMap() {
    const url = state.tripId
        ? `/lines-map?tripId=${encodeURIComponent(state.tripId)}`
        : `/lines-map?line=${encodeURIComponent(state.line)}`;
    window.location.href = url;
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
    console.log("Previous Stop Matching");
    
    // Find the index of the current stop
    let currentStopIdSplitted = state.currentStopId.split("-");
    const currentStopIndex = state.mergedStops.findIndex(stop => currentStopIdSplitted.find(id => stop.stop_id == id));

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
            let isPlausible = true;
            if (state.lineFull != trip.line) isPlausible = false;
            //if (state.destination != trip.destination) isPlausible = false;            

            if (isPlausible) {
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

                // console.log(tid, state.tripId);
                

                // Se c'è il MATCH
                if (tid == state.tripId) {
                    console.log("Previous Stop Mached", stopName, tid, state.tripId, trip.time);

                    stop.arrival_time = trip.time;
                    stop.departure_time = trip.time;
                    stop.hasRealTime = true;
                    // renderTimeline();
                    updateSingleStopInTimeline(stop, currentStopIndex);
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

function updateSingleStopInTimeline(stop, selectedStopIdx, domEl=null) {
    let stopEl;
    if (!domEl) {
        stopEl = document.querySelector(`.stop-item[data-stop-id="${stop.stop_id}"]`);
    } else {
        stopEl = domEl;
    }
    
    if (!stopEl) return;
    let index = parseInt(stopEl.dataset.index);

    // const rtInfo = state.stopsJSON.find(s => s.stop === stop.stop_name);

    const isSelected = (index === selectedStopIdx);
    // Calcoliamo se la fermata è graficamente precedente
    const isGraphicallyPrevious = (selectedStopIdx !== -1 && index < selectedStopIdx);
    const isPrevious = !stop.hasRealTime;

    let statusClass = '';
    if (isPrevious) statusClass = 'passed';
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

    // console.log(stop);
    
    const stopNameEscaped = encodeURIComponent(stop.stop_name);
    
    stopEl.className = `stop-item ${statusClass}`;
    try{
        stopEl.style.cursor = 'pointer';
        const stopIdShort = stop.data_url.split("-").slice(0, -2).join("-");
        stopEl.onclick = () => {
            window.location.href = `/aut/stops/stop?id=${stopIdShort}&name=${stopNameEscaped}`;
        };
    }catch(e){
        console.warn(e);
        stopEl.style.cursor = 'default';
        stopEl.style.backgroundColor = '#2222';
        stopEl.style.borderRadius = '5px';
        stopEl.onclick = () => {
            errorPopup("URL Fermata non disponibile");
        };
    }

    stopEl.innerHTML = `
        <div class="stop-line ${isPrevious ? 'passed' : ''}"></div>
        <div class="stop-marker ${statusClass}"></div>
        <div class="stop-content">
            <div class="stop-name">${stop.stop_name}</div>
            <div class="stop-time">${timeDisplay}</div>
        </div>
    `;
}