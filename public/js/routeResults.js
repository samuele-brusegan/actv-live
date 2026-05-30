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

/**
 * Inizializzazione della Pagina
 */
window.addEventListener('DOMContentLoaded', async () => {
    try {
        // Carica dati dal localStorage
        originData = safeParseJSON(localStorage.getItem('route_origin'));
        destinationData = safeParseJSON(localStorage.getItem('route_destination'));

        departureDate = localStorage.getItem('route_departure_date') || new Date().toISOString().split('T')[0];
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

async function fetchRoutes(from, to, time, optimize) {
    const params = new URLSearchParams({ from, to, time });
    if (optimize) params.set('optimize', optimize);

    const response = await fetch(`/api/plan-route?${params.toString()}`);
    if (!response.ok) throw new Error(`Status HTTP: ${response.status}`);

    const data = await response.json();
    if (!data.success) throw new Error(data.error || 'Errore durante la ricerca.');
    return data.routes || [];
}

async function performRouteSearch() {
    try {
        const optimize = getOptimizeParam();
        const fromParam = getRouteParam(originData);
        const toParam = getRouteParam(destinationData);

        outboundRoutes = await fetchRoutes(fromParam, toParam, departureTime, optimize);

        if (returnTripEnabled) {
            returnRoutes = await fetchRoutes(toParam, fromParam, returnTime, optimize);
            setupDirectionTabs();
        }

        if (outboundRoutes.length > 0) {
            switchDirection('andata');
        } else if (returnTripEnabled && returnRoutes.length > 0) {
            switchDirection('ritorno');
        } else {
            showErrorState('Nessun percorso trovato per i parametri specificati.');
        }
    } catch (error) {
        console.error('Errore ricerca percorsi:', error);
        showErrorState(`Errore durante la ricerca: ${error.message}`);
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
    if (lineRaw === 'Cammina') return { name: '\u{1F6B6}', class: 'badge-walking' };

    const [lineName, lineTag] = String(lineRaw).split("_");

    let badgeClass = "badge-red";
    // Extraurbano (blu/azzurro): tag US/UN/EN oppure nome che termina con 'E' (es. 5E)
    if (["US", "UN", "EN"].includes(lineTag) || /E$/i.test(lineName)) badgeClass = "badge-blue";
    // Notturne
    if (/^N/i.test(lineName)) badgeClass = "badge-night";

    return { name: lineName, class: badgeClass };
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
                        <div class="route-total-duration">\u23F1 ${Math.round(route.duration)} min</div>
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
        ? `<div class="line-badge badge-walking">\u{1F6B6}</div>
           <div class="connector-info">Cammina per ${Math.round(leg.duration)} min (${leg.distance}m)</div>`
        : `<div class="line-badge ${badge.class}">${badge.name}</div>
           <div class="connector-info">per ${leg.stops_count} fermate</div>`;

    html += `<div class="timeline-connector">${connectorContent}</div>`;

    const markerClass = isLast ? 'end' : 'transfer';
    const arrivalName = isWalking
        ? leg.destination
        : (isLast ? (activeDestName || destinationData.name) : (route.transfer_stop || 'Cambio'));

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
            const dur = Math.round(r.duration);
            const isBest = dur === best.duration;
            return `<div class="compare-cell ${isBest ? 'best-value' : ''}">${dur} min</div>`;
        }).join('')}
    </div>`;

    // Stops count
    html += `<div class="compare-row">
        <div class="compare-label">Fermate</div>
        ${routes.map(r => {
            const stops = r.stops_count || r.legs.reduce((sum, l) => sum + (l.stops_count || 0), 0);
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

function findBestValues(routes) {
    const durations = routes.map(r => Math.round(r.duration));
    const stops = routes.map(r => r.stops_count || r.legs.reduce((sum, l) => sum + (l.stops_count || 0), 0));
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
            return `<span class="line-badge ${badge.class}">${badge.name}</span>`;
        })
        .join(' ');
}

function getRouteDepartureTime(route) {
    if (route.legs && route.legs.length > 0) return route.legs[0].departure_time;
    return route.departure_time;
}

function getRouteArrivalTime(route) {
    if (route.legs && route.legs.length > 0) return route.legs[route.legs.length - 1].arrival_time;
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
    module.exports = { safeParseJSON, getLineBadgeDetails, formatItalianDate, formatShortTime, getTransferCount, getWalkingMinutes, findBestValues };
}
