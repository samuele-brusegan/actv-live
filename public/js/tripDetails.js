/**
 * Gestione della visualizzazione dei passaggi per una specifica corsa (trip).
 * Gestisce l'integrazione tra dati statici GTFS e dati real-time ACTV.
 */

let state = {
    lineFull: null,
    line: null,
    tag: null,
    destination: null,
    stopId: null,
    arrivalTime: null,
    tripId: null,
    today: null,
    stopsGTFS: [],
    stopsJSON: []
};

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    state.lineFull = urlParams.get('line');
    state.line = state.lineFull?.split('_')[0];
    state.tag = state.lineFull?.split('_')[1];
    state.destination = urlParams.get('dest');
    state.stopId = urlParams.get('stopId');
    state.arrivalTime = urlParams.get('time');

    init();
});

/** Inizializzazione della pagina */
async function init() {
    const dow = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
    state.today = dow[new Date().getDay()];

    // Recupero info contestuali dalla sessione
    const trackName = sessionStorage.getItem('busTrack');
    const lastStop = sessionStorage.getItem('lastStop');
    const timedStop = sessionStorage.getItem('timedStop');
    const realTime = sessionStorage.getItem('realTime');
    const lineId = sessionStorage.getItem('lineId');

    // Identifica il Trip ID univoco nel GTFS
    state.tripId = await fetchTripId(trackName, lastStop, state.today, realTime, timedStop, lineId);
    if (!state.tripId) {
        console.error("Impossibile identificare il Trip ID.");
    }

    // Inizializza l'intestazione
    updateHeader();

    // Carica il percorso statico (GTFS)
    state.stopsGTFS = await fetchGTFSStops(state.tripId);
    
    // Primo rendering e avvio loop di aggiornamento
    if (state.stopsGTFS) {
        await refreshData();
        setInterval(refreshData, 25000); // Ogni 25 secondi
    }
}

/** Recupera il Trip ID univoco tramite API di identificazione */
async function fetchTripId(busTrack, busDirection, day, time, stop, lineId) {
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
        return null;
    }
}

/** Aggiorna i dati in tempo reale e ridisegna la lista */
async function refreshData() {
    try {
        state.stopsJSON = await fetchRealTimeInfo();
        
        // Verifica se siamo ancora "agganciati" alla fermata corrente
        const currentStopInGTFS = state.stopsGTFS.find(s => 
            state.stopId.split('-').includes(s.stop_id.toString())
        );

        if (currentStopInGTFS) {
            const rtStop = state.stopsJSON.find(s => s.stop === currentStopInGTFS.stop_name);
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
        lineEl.className = `line-badge ${badgeClass}`;
    }

    if (destEl) {
        destEl.innerText = state.destination?.replace(/\\/g, '') || 'Sconosciuta';
    }

    if (state.arrivalTime && timeTextEl) {
        if (timeContainer) timeContainer.style.display = 'flex';
        
        // Calcola minuti rimanenti se possibile
        if (state.arrivalTime.includes(':')) {
            const now = new Date();
            const [h, m] = state.arrivalTime.split(':').map(Number);
            const target = new Date();
            target.setHours(h, m, 0, 0);
            
            const diffMin = Math.trunc((target - now) / 60000);
            timeTextEl.innerText = diffMin < 0 ? "Passato" : `${diffMin} min`;
        } else {
            timeTextEl.innerText = state.arrivalTime.replace(/\\/g, '');
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

/** Recupera informazioni real-time per tutte le fermate della corsa */
async function fetchRealTimeInfo() {
    try {
        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${state.stopId}-web-aut`, { 
            cache: 'no-cache' 
        });
        if (!response.ok) throw new Error("Errore RealTime");
        const trips = await response.json();
        
        // Trova il trip corrispondente nella risposta real-time
        // Dobbiamo identificare quale dei trip restituiti è il nostro
        const matchPromises = trips.map(async trip => {
            const stop = trip.timingPoints[trip.timingPoints.length - 1];
            const tid = await fetchTripId(
                trip.line.split('_')[0],
                trip.destination,
                state.today,
                stop.time,
                stop.stop,
                trip.lineId
            );
            return { ...trip, tripId: tid };
        });

        const results = await Promise.all(matchPromises);
        const myTrip = results.find(t => t.tripId === state.tripId);
        
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

    const currentIdx = state.stopsGTFS.findIndex(s => 
        state.stopId.split('-').includes(s.stop_id.toString())
    );

    state.stopsGTFS.forEach((stop, index) => {
        const stopEl = document.createElement('div');
        const rtInfo = state.stopsJSON.find(s => s.stop === stop.stop_name);
        
        const isCurrent = (index === currentIdx);
        const isPassed = (currentIdx !== -1 && index < currentIdx);
        
        let statusClass = '';
        if (isPassed) statusClass = 'passed';
        else if (isCurrent) statusClass = 'current current-stop-item';

        // Calcola visualizzazione del tempo
        let timeDisplay = "--:--";
        if (isPassed) {
            timeDisplay = "Passato";
        } else if (isCurrent) {
            timeDisplay = state.arrivalTime === 'departure' ? 'ORA' : state.arrivalTime || 'In arrivo';
        } else if (rtInfo) {
            timeDisplay = rtInfo.time;
        } else {
            timeDisplay = stop.arrival_time?.substring(0, 5) || "Info N.D.";
        }

        const stopIdShort = stop.data_url.split("-").slice(0, -2).join("-");
        const stopNameEscaped = encodeURIComponent(stop.stop_name);
        
        stopEl.className = `stop-item ${statusClass}`;
        stopEl.style.cursor = 'pointer';
        stopEl.onclick = () => {
            window.location.href = `/aut/stops/stop?id=${stopIdShort}&name=${stopNameEscaped}`;
        };

        stopEl.innerHTML = `
            <div class="stop-line ${isPassed ? 'passed' : ''}"></div>
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

    // Auto-scroll alla fermata corrente
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