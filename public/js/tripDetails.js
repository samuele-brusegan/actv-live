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
    stopsJSON: []        // Lista fermate da Real-Time
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
    // console.log(busTrack, busDirection, day, time, stop, lineId);
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
        const response = await fetch(`/api/gtfs-identify?${params.toString()}`);

        if (!response.ok) return null;
        const data = await response.json();
        return data.trip_id;

    } catch (e) {
        console.error("Errore fetchTripId:", e);
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

/** Recupera informazioni real-time per tutte le fermate */
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
        const myTrip = results.find(t => t.calculatedTripId === state.tripId);

        return myTrip ? myTrip.timingPoints : [];
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

    const selectedStopIdx = state.stopsGTFS.findIndex(s =>
        state.currentStopId.split('-').includes(s.stop_id.toString())
    );

    state.stopsGTFS.forEach((stop, index) => {
        const stopEl = document.createElement('div');
        const rtInfo = state.stopsJSON.find(s => s.stop === stop.stop_name);

        const isSelected = (index === selectedStopIdx);
        // Calcoliamo se la fermata è graficamente precedente
        const isGraphicallyPrevious = (selectedStopIdx !== -1 && index < selectedStopIdx);

        let statusClass = '';
        if (isGraphicallyPrevious) statusClass = 'passed';
        else if (isSelected) statusClass = 'current current-stop-item';

        // --- GESTIONE VISUALIZZAZIONE ORARIO ---
        let timeDisplay = "--:--";
        console.log(stop);


        if (isSelected) {
            // Usa l'orario header che è già real-time o fallback
            timeDisplay = state.arrivalTime === 'departure' ? 'ORA' : formatMinutesRemaining(state.arrivalTime);
        } else if (rtInfo) {
            // Usa l'orario real-time convertito in quanti minuti mancano
            timeDisplay = formatMinutesRemaining(rtInfo.time);
        } else {
            // Fallback GTFS (statico) - Visualizziamo l'ora assoluta o "Passato" se è molto vecchio?
            // Per ora mostriamo l'ora assoluta per chiarezza se non c'è real-time
            timeDisplay = stop.arrival_time?.substring(0, 5) || "Info N.D.";
        }

        // Se timeDisplay è "Passato", aggiungiamo la classe passed graficamente anche se non lo era
        if (timeDisplay === "Passato") {
            statusClass = 'passed';
        }

        const stopIdShort = stop.data_url.split("-").slice(0, -2).join("-");
        const stopNameEscaped = encodeURIComponent(stop.stop_name);

        stopEl.className = `stop-item ${statusClass}`;
        stopEl.style.cursor = 'pointer';
        stopEl.onclick = () => {
            window.location.href = `/aut/stops/stop?id=${stopIdShort}&name=${stopNameEscaped}`;
        };

        stopEl.innerHTML = `
            <div class="stop-line ${isGraphicallyPrevious ? 'passed' : ''}"></div>
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