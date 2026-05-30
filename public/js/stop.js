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

/** Recupera i passaggi in tempo reale dal backend ACTV.
 *  Ritorna un array (anche vuoto) oppure null in caso di errore di rete. */
async function fetchRealtimePassages() {
    if (!stationId) return [];

    try {
        const ctrl = new AbortController();
        const timeoutId = setTimeout(() => ctrl.abort(), 2500);
        const response = await fetch(`https://oraritemporeale.actv.it/aut/backend/passages/${stationId}-web-aut`, {
            cache: 'no-cache',
            signal: ctrl.signal
        });

        clearTimeout(timeoutId);

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        return Array.isArray(data) ? data : [];
    } catch (error) {
        console.warn("Errore fetch passaggi real-time:", error);
        return null;
    }
}

/** Recupera i passaggi PREVISTI (orari GTFS dal DB). Ritorna sempre un array. */
async function fetchScheduledPassages() {
    if (!stationId) return [];

    try {
        const response = await fetch(`/api/gtfs-passages?stop=${encodeURIComponent(stationId)}&return=true`, {
            cache: 'no-cache',
        });
        if (!response.ok) return [];
        const data = await response.json();
        return Array.isArray(data) ? data : [];
    } catch (error) {
        console.warn("Errore fetch passaggi previsti:", error);
        return [];
    }
}

/** Chiave linea+destinazione per individuare i passaggi già coperti dal real-time */
function lineDestKey(p) {
    const line = (p.line || '').split('_')[0];
    const dest = (p.destination || '').trim().toLowerCase();
    return `${line}|${dest}`;
}

/** Unisce real-time e previsti: aggiunge i previsti per le combinazioni
 *  linea+destinazione NON già presenti nel real-time (dati mancanti). */
function mergePassages(realtime, scheduled) {
    const result = Array.isArray(realtime) ? realtime.slice() : [];
    const covered = new Set(result.map(lineDestKey));
    const added = new Set();

    (Array.isArray(scheduled) ? scheduled : []).forEach(p => {
        const ld = lineDestKey(p);
        if (covered.has(ld)) return; // già coperto dal real-time
        const full = ld + '|' + (p.time || '');
        if (added.has(full)) return; // evita duplicati tra i previsti
        added.add(full);
        result.push(p);
    });

    return result;
}

/** Sciopero/servizio non monitorato: real-time vuoto ma corse previste esistono. */
function isLikelyStrike(realtime, scheduled) {
    const rtEmpty = Array.isArray(realtime) && realtime.length === 0;
    const schedHas = Array.isArray(scheduled) && scheduled.length > 0;
    return rtEmpty && schedHas;
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

/** Carica e visualizza i passaggi (real-time + previsti uniti) e la bacheca */
async function loadPassages() {
    if (!stationId) return;

    // Real-time + previsti + avvisi in parallelo
    const [realtime, scheduled, noticeText] = await Promise.all([
        fetchRealtimePassages(),
        fetchScheduledPassages(),
        fetchNoticeboard()
    ]);

    const loadingEl = document.getElementById('loading');
    const listContainer = document.getElementById('passages-list');

    if (loadingEl) loadingEl.style.display = 'none';

    const strike = isLikelyStrike(realtime, scheduled);

    // ── Bacheca (avvisi + eventuale news sciopero) ──
    const notes = [];
    if (strike) {
        notes.push({
            type: 'strike',
            html: '<strong>&#9888;&#65039; Possibile sciopero o servizio non monitorato.</strong><br>' +
                  'I passaggi in tempo reale non sono disponibili: gli orari mostrati sono quelli <em>previsti</em> ' +
                  'e i bus potrebbero non passare.'
        });
    }
    if (noticeText) {
        notes.push({ type: 'notice', html: noticeText });
    }
    renderBoard(notes);

    if (!listContainer) return;

    // Errore di rete reale (real-time null) e nessun dato previsto
    if (realtime === null && (!scheduled || scheduled.length === 0)) {
        listContainer.innerHTML = "<p class='text-center text-danger'>Errore nel caricamento dei dati.</p>";
        return;
    }

    const passages = mergePassages(realtime, scheduled);

    if (passages.length === 0) {
        const message = strike
            ? "Nessun passaggio in tempo reale (possibile sciopero). Vedi la bacheca."
            : "Nessun passaggio previsto.";
        listContainer.innerHTML = `<p class='text-center text-muted'>${message}</p>`;
        return;
    }

    listContainer.innerHTML = "";
    passages.forEach(p => {
        const card = createPassageCard(p);
        listContainer.appendChild(card);
    });

    // Registra ritardi nello storico (solo i passaggi real-time: real:false vengono ignorati)
    if (typeof recordPassageDelays !== 'undefined') {
        recordPassageDelays(passages, stationId, stationName);
    }

    updateFilter();
}

/**
 * Bacheca: avvisi della fermata + eventuale news sciopero.
 * Collassabile in una bolla in basso a destra. Se non ci sono note, non
 * compare né la bacheca né la bolla.
 */
function ensureBoardBubble() {
    let bubble = document.getElementById('board-bubble');
    if (!bubble) {
        bubble = document.createElement('div');
        bubble.id = 'board-bubble';
        bubble.className = 'board-bubble';
        bubble.style.display = 'none';
        bubble.setAttribute('title', 'Apri bacheca');
        bubble.onclick = expandBoard;
        bubble.innerHTML = '<span class="board-bubble-icon">&#128203;</span><span class="board-bubble-count"></span>';
        document.body.appendChild(bubble);
    }
    return bubble;
}

function renderBoard(notes) {
    const container = document.getElementById('noticeboard');
    const bubble = ensureBoardBubble();
    if (!container) return;

    if (!notes || notes.length === 0) {
        container.innerHTML = '';
        bubble.style.display = 'none';
        return;
    }

    const notesHtml = notes.map(n =>
        `<div class="board-note board-note-${n.type || 'notice'}">${n.html}</div>`
    ).join('');

    container.innerHTML = `
        <div class="board-card" id="board-card">
            <div class="board-header">
                <span class="board-title">&#128203; Bacheca</span>
                <button class="board-collapse" onclick="collapseBoard()" title="Riduci">&minus;</button>
            </div>
            <div class="board-body">${notesHtml}</div>
        </div>`;

    const countEl = bubble.querySelector('.board-bubble-count');
    if (countEl) countEl.textContent = notes.length;

    // Rispetta lo stato collassato scelto dall'utente (persiste tra i refresh)
    const collapsed = sessionStorage.getItem('board_collapsed') === '1';
    const card = document.getElementById('board-card');
    if (collapsed) {
        if (card) card.style.display = 'none';
        bubble.style.display = 'flex';
    } else {
        if (card) card.style.display = '';
        bubble.style.display = 'none';
    }
}

function collapseBoard() {
    sessionStorage.setItem('board_collapsed', '1');
    const card = document.getElementById('board-card');
    const bubble = ensureBoardBubble();
    if (card) card.style.display = 'none';
    bubble.style.display = 'flex';
}

function expandBoard() {
    sessionStorage.setItem('board_collapsed', '0');
    const card = document.getElementById('board-card');
    const bubble = document.getElementById('board-bubble');
    if (card) card.style.display = '';
    if (bubble) bubble.style.display = 'none';
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
        sessionStorage.setItem('tripDetails_selectedStop', stationId);

        // Fetch tripId
        const dow = ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
        let day = dow[new Date().getDay()];

        let myParams = new URLSearchParams({
            rtable: 'true',
            time: timingPoint.time,
            busTrack: lineName,
            busDirection: destination,
            day: day,
            stop: timingPoint.stop,
            lineId: lineId,
            limit: 50
        });
        let url = `https://actv-live.test/api/gtfs-identify?${myParams.toString()}`;
        sessionStorage.setItem('tripDetails_url', url);


        const tripId = await fetchTripId(lineName, destination, day, timingPoint.time, timingPoint.stop, lineId);

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

/**
 * Notifiche Ritardi per questa fermata
 */

function toggleStopNotifications() {
    if (typeof isStopMonitored === 'undefined') return;

    const monitored = isStopMonitored(stationId);
    const panel = document.getElementById('notification-panel');

    if (monitored) {
        removeMonitoredStop(stationId);
        if (panel) panel.style.display = 'none';
        updateNotifyButton();
    } else {
        if (panel) panel.style.display = 'block';
        enableStopNotifications();
    }
}

async function enableStopNotifications() {
    if (typeof toggleNotifications === 'undefined') return;

    const enabled = await toggleNotifications();
    if (!enabled && !areNotificationsEnabled()) {
        const panel = document.getElementById('notification-panel');
        if (panel) panel.style.display = 'none';
        return;
    }

    addMonitoredStop(stationId, stationName || `Fermata ${stationId}`);
    updateNotifyButton();
    updateThresholdButtons();
}

function closeNotificationPanel() {
    const panel = document.getElementById('notification-panel');
    if (panel) panel.style.display = 'none';
}

function setThreshold(minutes) {
    if (typeof setDelayThreshold !== 'undefined') {
        setDelayThreshold(minutes);
    }
    updateThresholdButtons();
}

function updateNotifyButton() {
    const btn = document.getElementById('notify-btn');
    if (!btn) return;

    const monitored = typeof isStopMonitored !== 'undefined' && isStopMonitored(stationId);
    btn.classList.toggle('notify-active', monitored);
    btn.title = monitored ? 'Disattiva notifiche' : 'Attiva notifiche ritardi';
}

function updateThresholdButtons() {
    const current = typeof getDelayThreshold !== 'undefined' ? getDelayThreshold() : 5;
    document.querySelectorAll('.threshold-btn').forEach(btn => {
        const val = parseInt(btn.textContent, 10);
        btn.classList.toggle('active', val === current);
    });
}

/**
 * Tab Switching
 */
let linesLoaded = false;

function switchTab(tabName) {
    document.querySelectorAll('.stop-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.toggle('active', tab.id === `tab-${tabName}`);
    });

    if (tabName === 'lines' && !linesLoaded) {
        linesLoaded = true;
        loadStopLines();
    }
}

/**
 * Linee e Orari
 */
async function loadStopLines() {
    if (!stationId) return;

    const loadingEl = document.getElementById('lines-loading');
    const listEl = document.getElementById('lines-list');
    const emptyEl = document.getElementById('lines-empty');

    try {
        const now = new Date().toTimeString().slice(0, 5);
        const params = new URLSearchParams({ stop: stationId.split('-')[0], time: now });
        const response = await fetch(`/api/stop-lines?${params.toString()}`);

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();

        if (loadingEl) loadingEl.style.display = 'none';

        if (data.success && data.lines && data.lines.length > 0) {
            renderStopLines(data.lines, listEl);
        } else {
            if (emptyEl) emptyEl.style.display = 'block';
        }
    } catch (error) {
        console.error('Errore caricamento linee:', error);
        if (loadingEl) loadingEl.style.display = 'none';
        if (listEl) listEl.innerHTML = "<p class='text-center text-danger'>Errore nel caricamento delle linee.</p>";
    }
}

function renderStopLines(lines, container) {
    if (!container) return;

    container.innerHTML = lines.map(line => {
        const [lineName, lineTag] = (line.route_short_name || '').split('_');

        let badgeClass = 'badge-red';
        if (['US', 'UN', 'EN'].includes(lineTag)) badgeClass = 'badge-blue';
        if (lineName && lineName.startsWith('N')) badgeClass = 'badge-night';

        const departuresHtml = line.departures.map(dep =>
            `<div class="line-departure">
                <span class="dep-time">${dep.time}</span>
                <span class="dep-dest">${dep.destination}</span>
            </div>`
        ).join('');

        return `
            <div class="line-card">
                <div class="line-card-header">
                    <div class="line-badge ${badgeClass}">${lineName || line.route_short_name}</div>
                    <div class="line-card-name">${line.route_long_name || ''}</div>
                </div>
                <div class="line-departures">
                    <div class="departures-header">
                        <span>Orario</span>
                        <span>Direzione</span>
                    </div>
                    ${departuresHtml}
                </div>
            </div>
        `;
    }).join('');
}

/** Condividi widget */
function shareWidget() {
    if (typeof showWidgetDialog !== 'undefined') {
        showWidgetDialog(stationId, stationName);
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
    updateNotifyButton();

    // Primo caricamento
    await loadPassages();

    // Refresh automatico ogni 15 secondi
    setInterval(loadPassages, 15000);
}

window.onload = init;

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { getFavorites, isFavorite, toggleFavorite, updateFavoriteButton, createPassageCard, updateNotifyButton, switchTab, renderStopLines, mergePassages, isLikelyStrike, lineDestKey };
}