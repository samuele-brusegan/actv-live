/**
 * Gestione della visualizzazione dei risultati di ricerca del percorso.
 */

// Handler globale degli errori per facilitare il debugging in produzione
window.onerror = function(msg, url, line, col, error) {
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
    const originEl = document.getElementById('origin-name');
    const destEl = document.getElementById('destination-name');
    const dateEl = document.getElementById('datetime-info');

    if (originEl) originEl.textContent = originData.name;
    if (destEl) destEl.textContent = destinationData.name;
    if (dateEl) dateEl.textContent = `Partenza: ${formatItalianDate(departureDate)} alle ${departureTime}`;
}

/**
 * Logica di Ricerca
 */
async function performRouteSearch() {
    try {
        // Parametri: se è un indirizzo usiamo le coordinate, altrimenti l'ID fermata
        const from = (originData.type === 'address') ? `${originData.lat},${originData.lng}` : originData.id;
        const to = (destinationData.type === 'address') ? `${destinationData.lat},${destinationData.lng}` : destinationData.id;

        const params = new URLSearchParams({ from, to, time: departureTime });
        const response = await fetch(`/api/plan-route?${params.toString()}`);
        
        if (!response.ok) throw new Error(`Status HTTP: ${response.status}`);

        const data = await response.json();

        if (data.success && data.routes?.length > 0) {
            renderRouteResults(data.routes);
        } else {
            showErrorState(data.error || 'Nessun percorso trovato per i parametri specificati.');
        }
    } catch (error) {
        console.error('Errore ricerca percorsi:', error);
        showErrorState(`Errore durante la ricerca: ${error.message}`);
    }
}

/**
 * Rendering Risultati
 */

function getLineBadgeDetails(lineRaw) {
    if (!lineRaw) return { name: '?', class: 'badge-red' };
    if (lineRaw === 'Cammina') return { name: '🚶', class: 'badge-walking' };

    const [lineName, lineTag] = lineRaw.split("_");
    
    let badgeClass = "badge-red";
    if (["US", "UN", "EN"].includes(lineTag)) badgeClass = "badge-blue";
    if (lineName.startsWith("N")) badgeClass = "badge-night";

    return { name: lineName, class: badgeClass };
}

function renderRouteResults(routes) {
    const loadingEl = document.getElementById('loading');
    const containerEl = document.getElementById('routes-container');
    const listEl = document.getElementById('routes-list');

    if (loadingEl) loadingEl.style.display = 'none';
    if (containerEl) containerEl.style.display = 'block';
    if (!listEl) return;

    listEl.innerHTML = routes.map(route => {
        const legsHtml = route.legs.map((leg, index) => renderLegHTML(leg, route, index)).join('');
        const routeJson = JSON.stringify(route).replace(/'/g, "&#39;");

        return `
            <div class="route-card" onclick='viewRouteDetails(${routeJson})'>
                <div class="route-header-row">
                    <div class="route-date">${formatItalianDate(departureDate)}</div>
                    <div class="route-total-duration">⏱ ${Math.round(route.duration)} min</div>
                </div>
                <div class="route-timeline">
                    ${legsHtml}
                </div>
                <div class="route-footer">
                    <button class="btn-select" onclick='confirmRouteSelection(${routeJson}, event)'>
                        Seleziona &rarr;
                    </button>
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

    // Punto di partenza della tratta (solo se è la prima o un cambio)
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

    // Connettore (Il tragitto in bus o a piedi)
    const connectorContent = isWalking 
        ? `<div class="line-badge badge-walking">🚶</div>
           <div class="connector-info">Cammina per ${Math.round(leg.duration)} min (${leg.distance}m)</div>`
        : `<div class="line-badge ${badge.class}">${badge.name}</div>
           <div class="connector-info">per ${leg.stops_count} fermate</div>`;

    html += `<div class="timeline-connector">${connectorContent}</div>`;

    // Punto di arrivo della tratta
    const markerClass = isLast ? 'end' : 'transfer';
    const arrivalName = isWalking 
        ? leg.destination 
        : (isLast ? destinationData.name : (route.transfer_stop || 'Cambio'));

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