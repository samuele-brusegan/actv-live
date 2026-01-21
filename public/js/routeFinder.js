/**
 * Logica per la ricerca dei percorsi.
 * Gestisce i selettori di data/ora personalizzati e lo stato della ricerca.
 */

// Stato globale della ricerca
const searchState = {
    origin: null,
    destination: null,
    selectedDate: new Date(),
    selectedHour: new Date().getHours(),
    selectedMinute: Math.floor(new Date().getMinutes() / 5) * 5 // Arrotonda ai 5 min
};

/**
 * Gestione Modali (Data e Ora)
 */

function openDateModal() {
    document.getElementById('date-modal').style.display = 'flex';
    renderCalendar();
}

function closeDateModal() {
    document.getElementById('date-modal').style.display = 'none';
}

function confirmDate() {
    updateDisplayDateTime();
    closeDateModal();
}

function openTimeModal() {
    document.getElementById('time-modal').style.display = 'flex';
    renderTimePicker();
}

function closeTimeModal() {
    document.getElementById('time-modal').style.display = 'none';
}

function confirmTime() {
    updateDisplayDateTime();
    closeTimeModal();
}

function updateDisplayDateTime() {
    const h = searchState.selectedHour.toString().padStart(2, '0');
    const m = searchState.selectedMinute.toString().padStart(2, '0');
    
    const dateEl = document.getElementById('display-date');
    const timeEl = document.getElementById('display-time');
    
    if (dateEl) dateEl.textContent = searchState.selectedDate.toLocaleDateString('it-IT');
    if (timeEl) timeEl.textContent = `${h}:${m}`;
}

/**
 * Logica Calendario
 */

function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    const monthYearLabel = document.getElementById('calendar-month-year');
    if (!grid || !monthYearLabel) return;
    
    grid.innerHTML = '';
    
    const year = searchState.selectedDate.getFullYear();
    const month = searchState.selectedDate.getMonth();
    
    monthYearLabel.textContent = searchState.selectedDate.toLocaleString('it-IT', { 
        month: 'long', 
        year: 'numeric' 
    });

    const firstDay = new Date(year, month, 1).getDay();
    // Correzione per far iniziare la settimana da Lunedì (getDay: 0=Dom, 1=Lun...)
    const startOffset = (firstDay === 0) ? 6 : firstDay - 1;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Slot vuoti iniziali
    for (let i = 0; i < startOffset; i++) {
        grid.appendChild(document.createElement('div'));
    }

    // Giorni del mese
    for (let d = 1; d <= daysInMonth; d++) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day';
        dayEl.textContent = d;
        
        if (d === searchState.selectedDate.getDate()) {
            dayEl.classList.add('selected');
        }
        
        dayEl.onclick = () => {
            searchState.selectedDate.setDate(d);
            renderCalendar();
        };
        
        grid.appendChild(dayEl);
    }
}

function changeMonth(delta) {
    searchState.selectedDate.setMonth(searchState.selectedDate.getMonth() + delta);
    renderCalendar();
}

/**
 * Logica Time Picker
 */

function renderTimePicker() {
    const hWheel = document.getElementById('hour-wheel');
    const mWheel = document.getElementById('minute-wheel');
    if (!hWheel || !mWheel) return;
    
    hWheel.innerHTML = '';
    mWheel.innerHTML = '';
    
    // Ore (00-23)
    for (let i = 0; i < 24; i++) {
        const el = createTimeItem(i.toString().padStart(2, '0'), i === searchState.selectedHour, () => {
            searchState.selectedHour = i;
            renderTimePicker();
        });
        hWheel.appendChild(el);
    }
    
    // Minuti (intervalli di 5m)
    for (let i = 0; i < 60; i += 5) {
        const el = createTimeItem(i.toString().padStart(2, '0'), i === searchState.selectedMinute, () => {
            searchState.selectedMinute = i;
            renderTimePicker();
        });
        mWheel.appendChild(el);
    }
    
    // Scroll automatico alla posizione corretta
    scrollToSelectedTime();
}

function createTimeItem(text, isActive, onClick) {
    const el = document.createElement('div');
    el.className = `time-item ${isActive ? 'active' : ''}`;
    el.textContent = text;
    el.onclick = onClick;
    return el;
}

function scrollToSelectedTime() {
    const hWheel = document.getElementById('hour-wheel');
    const mWheel = document.getElementById('minute-wheel');
    const itemHeight = 40;

    setTimeout(() => {
        if (hWheel) hWheel.scrollTop = (searchState.selectedHour * itemHeight) - (hWheel.clientHeight / 2) + (itemHeight / 2);
        if (mWheel) mWheel.scrollTop = (searchState.selectedMinute / 5 * itemHeight) - (mWheel.clientHeight / 2) + (itemHeight / 2);
    }, 10);
}

/**
 * Gesture: Pull-to-dismiss per i modali
 */

function initPullToCancel(modalId, closeFunc) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    const content = modal.querySelector('.modal-content');
    const handle = modal.querySelector('.modal-handle');
    if (!content || !handle) return;
    
    let startY = 0;
    let isDragging = false;
    
    handle.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
        isDragging = true;
        content.style.transition = 'none';
    }, { passive: true });
    
    window.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        const diff = e.touches[0].clientY - startY;
        if (diff > 0) {
            content.style.transform = `translateY(${diff}px)`;
        }
    }, { passive: false });
    
    window.addEventListener('touchend', (e) => {
        if (!isDragging) return;
        isDragging = false;
        const diff = e.changedTouches[0].clientY - startY;
        
        content.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        
        if (diff > 120) {
            content.style.transform = 'translateY(100%)';
            setTimeout(() => {
                closeFunc();
                content.style.transform = '';
            }, 300);
        } else {
            content.style.transform = '';
        }
    });
}

/**
 * Azioni Principali
 */

function selectStation(type) {
    localStorage.setItem('route_selection_mode', type);
    window.location.href = `/station-selector?type=${type}`;
}

function swapStations() {
    const temp = searchState.origin;
    searchState.origin = searchState.destination;
    searchState.destination = temp;
    
    updateUI();
    persistState();
}

function updateUI() {
    const originLabel = document.getElementById('origin-value');
    const destLabel = document.getElementById('destination-value');
    
    if (originLabel) originLabel.textContent = searchState.origin?.name || 'Seleziona partenza';
    if (destLabel) destLabel.textContent = searchState.destination?.name || 'Seleziona destinazione';
}

function persistState() {
    if (searchState.origin) localStorage.setItem('route_origin', JSON.stringify(searchState.origin));
    else localStorage.removeItem('route_origin');
    
    if (searchState.destination) localStorage.setItem('route_destination', JSON.stringify(searchState.destination));
    else localStorage.removeItem('route_destination');
}

/** Avvia la ricerca percorsi */
function searchRoutes() {
    if (!searchState.origin || !searchState.destination) {
        alert('Inserisci sia la partenza che la destinazione.');
        return;
    }

    const dateStr = searchState.selectedDate.toISOString().split('T')[0];
    const timeStr = `${searchState.selectedHour.toString().padStart(2, '0')}:${searchState.selectedMinute.toString().padStart(2, '0')}`;
    
    localStorage.setItem('route_departure_date', dateStr);
    localStorage.setItem('route_departure_time', timeStr);

    window.location.href = '/route-results';
}

function toggleReturn() {
    // Funzionalità per il viaggio di ritorno (non ancora implementata)
    setTimeout(() => {
        const toggle = document.getElementById('return-toggle');
        if (toggle) toggle.checked = false;
    }, 400);
}

// Inizializzazione al caricamento
window.addEventListener('DOMContentLoaded', () => {
    // Carica dati salvati
    const savedOrigin = localStorage.getItem('route_origin');
    const savedDest = localStorage.getItem('route_destination');
    
    if (savedOrigin) searchState.origin = JSON.parse(savedOrigin);
    if (savedDest) searchState.destination = JSON.parse(savedDest);
    
    updateUI();
    updateDisplayDateTime();
    
    initPullToCancel('date-modal', closeDateModal);
    initPullToCancel('time-modal', closeTimeModal);
});