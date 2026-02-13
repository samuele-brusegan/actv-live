/**
 * Gestione della visualizzazione dei passaggi per una specifica fermata.
 */

// Estrazione parametri dall'URL
const urlParams = new URLSearchParams(window.location.search);
const stationId = urlParams.get('id');
const rawStationName = urlParams.get('name');
const stationName = rawStationName ? decodeURIComponent(rawStationName) : null;

/**
 * Gestione dei Preferiti
 */

/** @returns {Array} Lista delle fermate preferite dal localStorage */
function getFavorites() {
    try {
        const favorites = localStorage.getItem('favorite_stops');
        return favorites ? JSON.parse(favorites) : [];
    } catch (e) {
        console.error("Errore nel parsing dei preferiti:", e);
        return [];
    }
}

/** @returns {boolean} True se la fermata corrente è tra i preferiti */
function isFavorite() {
    const favorites = getFavorites();
    if (!stationId) return false;

    // Supporta ID singoli (es. "4825") o multipli (es. "4825-4826")
    const currentIds = stationId.split('-');

    return favorites.some(fav => {
        if (fav.ids && Array.isArray(fav.ids)) {
            return fav.ids.some(id => currentIds.includes(id));
        }
        return fav.id === stationId || currentIds.includes(fav.id);
    });
}

/** Alterna lo stato di preferito per la fermata corrente */
function toggleFavorite() {
    let favorites = getFavorites();
    const favoriteBtn = document.getElementById('favorite-btn');

    if (isFavorite()) {
        const currentIds = stationId.split('-');
        favorites = favorites.filter(fav => {
            if (fav.ids && Array.isArray(fav.ids)) {
                return !fav.ids.some(id => currentIds.includes(id));
            }
            return fav.id !== stationId && !currentIds.includes(fav.id);
        });
    } else {
        favorites.push({
            id: stationId.split('-')[0],
            ids: stationId.split('-'),
            name: stationName || `Fermata ${stationId}`
        });
    }

    localStorage.setItem('favorite_stops', JSON.stringify(favorites));
    updateFavoriteButton();
}

/** Aggiorna l'aspetto visivo del pulsante preferiti */
function updateFavoriteButton() {
    const favoriteBtn = document.getElementById('favorite-btn');
    if (!favoriteBtn) return;

    const favorited = isFavorite();
    favoriteBtn.classList.toggle('favorited', favorited);
    favoriteBtn.title = favorited ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti';
}

/**
 * Recupero Dati API
 */

/** Recupera i passaggi in tempo reale dal backend ACTV */
async function fetchPassages() {
    if (!stationId) return [];

    try {
        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stationId}-web-aut`, {
            cache: 'no-cache'
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        return data || [];
    } catch (error) {
        console.error("Errore fetch passaggi:", error);
        return null;
    }
}

/** Recupera eventuali avvisi/comunicazioni per la fermata */
async function fetchNoticeboard() {
    if (!stationId) return null;

    try {
        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/page/${stationId}-web-aut`);
        if (!response.ok) return null;

        const data = await response.json();
        return data && data.text ? data.text : null;
    } catch (error) {
        return null;
    }
}

/**
 * Rendering e UI
 */

/** Carica e visualizza i passaggi */
async function loadPassages() {
    if (!stationId) return;

    const passages = await fetchPassages();
    const loadingEl = document.getElementById('loading');
    const listContainer = document.getElementById('passages-list');

    if (loadingEl) loadingEl.style.display = 'none';
    if (!listContainer) return;

    if (passages === null) {
        listContainer.innerHTML = "<p class='text-center text-danger'>Errore nel caricamento dei dati.</p>";
        return;
    }

    if (passages.length === 0) {
        const message = passages.message || "Nessun passaggio previsto.";
        listContainer.innerHTML = `<p class='text-center text-muted'>${message}</p>`;
        return;
    }

    listContainer.innerHTML = "";
    passages.forEach(p => {
        const card = createPassageCard(p);
        listContainer.appendChild(card);
    });

    updateFilter();
}

/** Crea l'elemento DOM per un singolo passaggio */
function createPassageCard(p) {
    const lineNameRaw = p.line || "";
    const destination = p.destination || "Destinazione ignota";
    const time = p.time;
    const isReal = p.real;
    const stop = p.stop || stationId;
    const lineId = p.lineId;

    // Parsing nome linea (es. "7E_US" -> "7E")
    const [lineName, lineTag] = lineNameRaw.split("_");

    // Determina colore badge
    let badgeColor = "badge-red";
    if (["US", "UN", "EN"].includes(lineTag)) badgeColor = "badge-blue";
    if (lineName.startsWith("N")) badgeColor = "badge-night";

    // HTML del tempo (Real-time vs Programmato)
    const timeDisplay = time === "departure" ? "Ora" : time;
    const timeHtml = isReal
        ? `<div class="d-flex align-items-center">
             <div class="real-time-indicator"></div>
             <span class="time-badge real-time">${timeDisplay}</span>
           </div>`
        : `<span class="time-badge scheduled">${timeDisplay}</span>`;

    const div = document.createElement('div');
    div.className = 'passage-card';
    div.style.cursor = 'pointer';

    div.onclick = async () => {
        const timingPoint = p.timingPoints[p.timingPoints.length - 1];
        const timeStr = isReal ? `${time} min` : time;

        // Persistenza dati per la pagina dettagli
        sessionStorage.setItem('timedStop', timingPoint.stop);
        sessionStorage.setItem('busTrack', lineName);
        sessionStorage.setItem('realTime', timingPoint.time);
        sessionStorage.setItem('lastStop', destination);
        sessionStorage.setItem('lineId', lineId);

        // Fetch tripId
        const tripId = await fetchTripId(lineName, lineTag, day, time, stop, lineId);

        const params = new URLSearchParams({
            /* line: `${lineName}_${lineTag}`,
            dest: destination,
            stopId: stationId,
            time: timeStr */
            tripId: tripId
        });

        window.location.href = `/trip-details?${params.toString()}`;
    };

    div.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="line-badge ${badgeColor}">${lineName}</div>
            <div class="passage-info">
                <span class="passage-dest"><b>${destination}</b></span><br/>
                <span class="passage-meta">Presso <b>${stop}</b></span>
            </div>
        </div>
        <div>${timeHtml}</div>
    `;

    return div;
}

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

        if (!response.ok) throw new Error("Error" + text);

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

/** Gestione filtri per linea */
function updateFilter() {
    const activeFilter = sessionStorage.getItem('filter');
    const passageCards = document.querySelectorAll('.passage-card');
    const filterContainer = document.getElementById('filter-container');

    if (!filterContainer) return;

    // Raccoglie le linee uniche presenti
    const linesFound = [];
    passageCards.forEach(card => {
        const name = card.querySelector('.line-badge').innerText;
        const colorClass = Array.from(card.querySelector('.line-badge').classList)
            .find(c => c.startsWith('badge-'))?.split('-')[1] || 'red';

        if (!linesFound.find(l => l.name === name)) {
            linesFound.push({ name, color: colorClass });
        }
    });

    // Se c'è solo una linea, nasconde i filtri
    if (linesFound.length <= 1) {
        filterContainer.innerHTML = '';
        passageCards.forEach(c => c.classList.remove('hidden'));
        return;
    }

    // Renderizza i pulsanti filtro
    filterContainer.innerHTML = '';
    linesFound.forEach(line => {
        const btn = document.createElement('div');
        btn.className = `filter-box box-${line.color} ${activeFilter === line.name ? 'selected' : ''}`;
        btn.innerText = line.name;

        btn.onclick = () => {
            const newFilter = activeFilter === line.name ? null : line.name;
            sessionStorage.setItem('filter', newFilter || "");
            updateFilter();
        };

        filterContainer.appendChild(btn);
    });

    // Applica il filtro alla lista
    let hasMatch = false;
    passageCards.forEach(card => {
        const name = card.querySelector('.line-badge').innerText;
        const isVisible = !activeFilter || activeFilter === "" || name === activeFilter;
        card.classList.toggle('hidden', !isVisible);
        if (isVisible) hasMatch = true;
    });

    if (!hasMatch && activeFilter) {
        sessionStorage.removeItem('filter');
        updateFilter();
    }
}

/** Aggiorna il banner del Time Machine */
function updateTimeMachineUI() {
    const banner = document.getElementById('tm-banner');
    const timeSpan = document.getElementById('tm-current-time');

    if (window.TimeMachine && TimeMachine.isEnabled()) {
        banner?.classList.remove('d-none');
        if (timeSpan) timeSpan.innerText = TimeMachine.getSimTime();
    } else {
        banner?.classList.add('d-none');
    }
}

/** Inizializzazione pagina */
async function init() {
    if (!stationId) {
        const nameEl = document.getElementById('station-name');
        if (nameEl) nameEl.innerText = "Errore: ID mancante";
        const loadingEl = document.getElementById('loading');
        if (loadingEl) loadingEl.style.display = 'none';
        return;
    }

    // Imposta info intestazione
    document.getElementById('station-name').innerText = stationName || `Fermata ${stationId}`;
    document.getElementById('station-id').innerText = stationId;

    updateFavoriteButton();
    updateTimeMachineUI();

    // Primo caricamento
    await loadPassages();

    // Refresh automatico ogni 15 secondi
    setInterval(loadPassages, 15000);
}

window.onload = init;