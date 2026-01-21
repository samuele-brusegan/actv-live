/**
 * Logica per la visualizzazione dettagliata di un percorso calcolato.
 * Gestisce la timeline passo-passo e le informazioni di interscambio.
 */

window.addEventListener('DOMContentLoaded', () => {
    try {
        // Recupero parametri dai dati di navigazione
        const routeData = localStorage.getItem('selected_route');
        const originData = localStorage.getItem('route_origin');
        const destinationData = localStorage.getItem('route_destination');
        const dateStr = localStorage.getItem('route_departure_date');
        
        if (!routeData) {
            console.warn("Nessun percorso in cache. Ritorno al cercapercorsi.");
            window.location.href = '/route-finder';
            return;
        }

        const route = JSON.parse(routeData);
        const origin = originData ? JSON.parse(originData) : { name: 'Partenza' };
        const destination = destinationData ? JSON.parse(destinationData) : { name: 'Destinazione' };
        
        // Imposta info intestazione
        const dateEl = document.getElementById('route-date');
        const durationEl = document.getElementById('route-duration');
        
        if (dateEl) dateEl.textContent = formatItalianDate(dateStr);
        if (durationEl) durationEl.textContent = `⏱ ${Math.round(route.duration)} min`;
        
        renderRouteTimeline(route, origin, destination);
        
    } catch (e) {
        console.error("Errore init routeDetails:", e);
        alert('Si è verificato un errore nel caricamento del percorso.');
    }
});

/** Formatta la data per l'intestazione */
function formatItalianDate(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('it-IT', { 
            weekday: 'short', 
            day: 'numeric', 
            month: 'numeric', 
            year: 'numeric' 
        });
    } catch (e) {
        return dateStr;
    }
}

/** Renderizza la timeline completa del percorso */
function renderRouteTimeline(route, origin, destination) {
    const container = document.getElementById('timeline-container');
    if (!container) return;

    let html = '';
    
    // Inizio: Posizione attuale -> Camminata iniziale
    html += renderTimelineStep({
        icon: 'location',
        title: 'Posizione attuale',
        connector: 'dashed'
    });
    
    html += renderTimelineStep({
        icon: 'walk',
        title: 'Cammina',
        subtitle: 'carca 800 metri',
        connector: 'dashed'
    });
    
    // Tratte del percorso (Legs)
    if (route.legs && route.legs.length > 0) {
        route.legs.forEach((leg, index) => {
            const isLastLeg = (index === route.legs.length - 1);
            
            // Punto di imbarco/partenza tratta
            html += renderStopStep(leg.origin || origin.name, leg.route_short_name, leg.stops_count, leg.duration);
            
            // Se è l'ultima tratta, aggiungiamo il punto di arrivo finale del bus
            if (isLastLeg) {
                const arrivalName = leg.destination || destination.name;
                html += renderTimelineStep({
                    icon: 'circle',
                    title: arrivalName,
                    connector: 'dashed' // Tratteggiato perché dopo ci sarà la camminata finale
                });
            }
        });
    } else {
        // Fallback per vecchi formati se necessario
        html += renderStopStep(origin.name, route.route_short_name, route.stops_count, route.duration);
        html += renderTimelineStep({ icon: 'circle', title: destination.name, connector: 'dashed' });
    }
    
    // Fine: Camminata finale -> Destinazione
    html += renderTimelineStep({
        icon: 'walk',
        title: 'Cammina',
        subtitle: 'circa 800 metri',
        connector: 'dashed'
    });
    
    html += renderTimelineStep({
        icon: 'location_end',
        title: destination.name
    });
    
    container.innerHTML = html;
}

/** Genera HTML per un punto di fermata con info sulla linea */
function renderStopStep(stopName, lineName, stopsCount, duration) {
    let lineInfo = '';
    if (lineName) {
        lineInfo = `
            <div class="line-badge">${lineName}</div>
            <div class="timeline-desc">
                Prendi la linea <strong>${lineName}</strong><br>
                Scendi dopo <strong>${stopsCount}</strong> fermate<br>
                <small>(circa ${Math.round(duration)} min)</small>
            </div>
        `;
    }
    
    return `
        <div class="timeline-item">
            <div class="timeline-icon"><div class="icon-circle"></div></div>
            <div class="timeline-connector"></div>
            <div class="timeline-content">
                <div class="timeline-title">${stopName}</div>
                ${lineInfo}
            </div>
        </div>
    `;
}

/** Genera HTML per uno step generico della timeline (camminata, posizione, etc) */
function renderTimelineStep({ icon, title, subtitle, connector }) {
    const iconContent = {
        'location': '↘',
        'location_end': '↖',
        'walk': '🚶',
        'circle': '<div class="icon-circle"></div>'
    }[icon] || '';

    const connectorHtml = connector 
        ? `<div class="timeline-connector ${connector}"></div>` 
        : '';

    return `
        <div class="timeline-item">
            <div class="timeline-icon ${icon.startsWith('location') ? 'icon-location' : (icon === 'walk' ? 'icon-walk' : '')}">
                ${iconContent}
            </div>
            ${connectorHtml}
            <div class="timeline-content">
                <div class="timeline-title">
                    ${title}${subtitle ? `<br><small>${subtitle}</small>` : ''}
                </div>
            </div>
        </div>
    `;
}

/** Mock per apertura mappa */
function showMap() {
    alert('Funzionalità mappa in arrivo in una prossima versione!');
}