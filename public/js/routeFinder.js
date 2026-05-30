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
    selectedMinute: Math.floor(new Date().getMinutes() / 5) * 5,
    optimize: 'time', // 'time' | 'transfers' | 'walking'
    returnTrip: false,
    returnHour: (new Date().getHours() + 1) % 24,
    returnMinute: Math.floor(new Date().getMinutes() / 5) * 5
};

// Quale orario sta modificando il time-picker: 'departure' | 'return'
let timeModalTarget = 'departure';

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

function openTimeModal(target = 'departure') {
    timeModalTarget = target;
    document.getElementById('time-modal').style.display = 'flex';
    renderTimePicker();
}

function closeTimeModal() {
    document.getElementById('time-modal').style.display = 'none';
}

function confirmTime() {
    // Salva il valore attualmente centrato nelle ruote (utile se l'utente
    // conferma prima che lo scroll si sia stabilizzato)
    commitWheelValue(document.getElementById('hour-wheel'));
    commitWheelValue(document.getElementById('minute-wheel'));
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

    const returnEl = document.getElementById('display-return-time');
    if (returnEl) {
        const rh = searchState.returnHour.toString().padStart(2, '0');
        const rm = searchState.returnMinute.toString().padStart(2, '0');
        returnEl.textContent = `${rh}:${rm}`;
    }
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

// Altezza di un singolo elemento della ruota (deve combaciare con .time-item in CSS)
const ITEM_HEIGHT = 40;
// La lista viene ripetuta più volte per simulare lo scorrimento "a riporto"
// (es. minuti: ... 50 -> 55 -> 00 -> 05 ...). Si resta sempre nella copia centrale.
const WHEEL_REPEATS = 9;
const WHEEL_MIDDLE = 4; // Math.floor(WHEEL_REPEATS / 2)

/** Restituisce step e chiave di stato per la ruota indicata */
function wheelConfig(wheel) {
    const isHour = wheel.id === 'hour-wheel';
    const step = isHour ? 1 : 5;
    const key = isHour
        ? (timeModalTarget === 'return' ? 'returnHour' : 'selectedHour')
        : (timeModalTarget === 'return' ? 'returnMinute' : 'selectedMinute');
    return { step, key };
}

function renderTimePicker() {
    const hWheel = document.getElementById('hour-wheel');
    const mWheel = document.getElementById('minute-wheel');
    if (!hWheel || !mWheel) return;

    buildWheel(hWheel, 24, 1);   // Ore 00-23
    buildWheel(mWheel, 60, 5);   // Minuti 00-55 (intervalli di 5m)

    scrollToSelectedTime();
}

function buildWheel(wheel, count, step) {
    wheel.innerHTML = '';
    const valuesCount = count / step;
    wheel.dataset.valuesCount = valuesCount;
    wheel.dataset.step = step;

    // Ripetiamo la lista WHEEL_REPEATS volte per ottenere lo scorrimento circolare
    for (let c = 0; c < WHEEL_REPEATS; c++) {
        for (let i = 0; i < valuesCount; i++) {
            const value = i * step;
            const domIndex = c * valuesCount + i;
            const el = document.createElement('div');
            el.className = 'time-item';
            el.dataset.value = value;
            el.textContent = value.toString().padStart(2, '0');
            // Il tap su un valore lo porta al centro (lo scroll aggiorna lo stato)
            el.addEventListener('click', () => {
                wheel.scrollTo({ top: domIndex * ITEM_HEIGHT, behavior: 'smooth' });
            });
            wheel.appendChild(el);
        }
    }
    updateActiveItem(wheel);
}

/** Evidenzia l'elemento attualmente al centro della ruota */
function updateActiveItem(wheel) {
    if (!wheel) return;
    const index = Math.round(wheel.scrollTop / ITEM_HEIGHT);
    wheel.querySelectorAll('.time-item').forEach((el, idx) => {
        el.classList.toggle('active', idx === index);
    });
}

/** Legge il valore centrato nella ruota e lo salva nello stato (con riporto) */
function commitWheelValue(wheel) {
    if (!wheel) return;
    const { step, key } = wheelConfig(wheel);
    const valuesCount = parseInt(wheel.dataset.valuesCount, 10) || 1;
    const domIndex = Math.round(wheel.scrollTop / ITEM_HEIGHT);
    const valueIndex = ((domIndex % valuesCount) + valuesCount) % valuesCount;
    searchState[key] = valueIndex * step;
}

/** Riporta lo scroll nella copia centrale mantenendo il valore (loop infinito). */
function recenterWheel(wheel) {
    if (!wheel) return;
    const valuesCount = parseInt(wheel.dataset.valuesCount, 10) || 1;
    const domIndex = Math.round(wheel.scrollTop / ITEM_HEIGHT);
    const valueIndex = ((domIndex % valuesCount) + valuesCount) % valuesCount;
    const target = (WHEEL_MIDDLE * valuesCount + valueIndex) * ITEM_HEIGHT;
    if (Math.abs(target - wheel.scrollTop) > 1) {
        wheel.scrollTop = target; // reposizionamento istantaneo e invisibile
        updateActiveItem(wheel);
    }
}

function scrollToSelectedTime() {
    const hWheel = document.getElementById('hour-wheel');
    const mWheel = document.getElementById('minute-wheel');

    const hour = timeModalTarget === 'return' ? searchState.returnHour : searchState.selectedHour;
    const minute = timeModalTarget === 'return' ? searchState.returnMinute : searchState.selectedMinute;

    setTimeout(() => {
        if (hWheel) {
            const vc = parseInt(hWheel.dataset.valuesCount, 10) || 24;
            hWheel.scrollTop = (WHEEL_MIDDLE * vc + hour) * ITEM_HEIGHT;
            updateActiveItem(hWheel);
        }
        if (mWheel) {
            const vc = parseInt(mWheel.dataset.valuesCount, 10) || 12;
            mWheel.scrollTop = (WHEEL_MIDDLE * vc + (minute / 5)) * ITEM_HEIGHT;
            updateActiveItem(mWheel);
        }
    }, 50);
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

function updateOptimizeUI() {
    document.querySelectorAll('.optimize-pill').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.value === searchState.optimize);
    });
}

function returnTimeString() {
    return `${searchState.returnHour.toString().padStart(2, '0')}:${searchState.returnMinute.toString().padStart(2, '0')}`;
}

function persistState() {
    if (searchState.origin) localStorage.setItem('route_origin', JSON.stringify(searchState.origin));
    else localStorage.removeItem('route_origin');

    if (searchState.destination) localStorage.setItem('route_destination', JSON.stringify(searchState.destination));
    else localStorage.removeItem('route_destination');

    localStorage.setItem('route_optimize', searchState.optimize);
    localStorage.setItem('route_return_trip', searchState.returnTrip ? '1' : '0');
    localStorage.setItem('route_return_time', returnTimeString());
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
    localStorage.setItem('route_optimize', searchState.optimize);
    localStorage.setItem('route_return_trip', searchState.returnTrip ? '1' : '0');
    localStorage.setItem('route_return_time', returnTimeString());

    window.location.href = '/route-results';
}

function toggleReturn() {
    const toggle = document.getElementById('return-toggle');
    searchState.returnTrip = toggle ? toggle.checked : false;

    const section = document.getElementById('return-section');
    if (section) section.style.display = searchState.returnTrip ? 'block' : 'none';

    persistState();
}

// Inizializzazione al caricamento
window.addEventListener('DOMContentLoaded', () => {
    // Carica dati salvati
    const savedOrigin = localStorage.getItem('route_origin');
    const savedDest = localStorage.getItem('route_destination');
    const savedOptimize = localStorage.getItem('route_optimize');

    if (savedOrigin) searchState.origin = JSON.parse(savedOrigin);
    if (savedDest) searchState.destination = JSON.parse(savedDest);
    if (savedOptimize && ['time', 'transfers', 'walking'].includes(savedOptimize)) {
        searchState.optimize = savedOptimize;
    }

    // Ripristina stato "ritorno"
    if (localStorage.getItem('route_return_trip') === '1') {
        searchState.returnTrip = true;
    }
    const savedReturnTime = localStorage.getItem('route_return_time');
    if (savedReturnTime && /^\d{2}:\d{2}$/.test(savedReturnTime)) {
        const [rh, rm] = savedReturnTime.split(':').map(Number);
        searchState.returnHour = rh;
        searchState.returnMinute = rm;
    }

    updateUI();
    updateDisplayDateTime();
    updateOptimizeUI();

    // Sincronizza UI del toggle ritorno
    const returnToggle = document.getElementById('return-toggle');
    if (returnToggle) returnToggle.checked = searchState.returnTrip;
    const returnSection = document.getElementById('return-section');
    if (returnSection) returnSection.style.display = searchState.returnTrip ? 'block' : 'none';

    // Optimization pills
    document.querySelectorAll('.optimize-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            searchState.optimize = btn.dataset.value;
            updateOptimizeUI();
            persistState();
        });
    });

    initPullToCancel('date-modal', closeDateModal);
    initPullToCancel('time-modal', closeTimeModal);

    // Selezione dell'orario tramite scroll della ruota (aggiorna lo stato
    // quando lo scroll si stabilizza, evidenziando l'elemento al centro)
    ['hour-wheel', 'minute-wheel'].forEach(id => {
        const wheel = document.getElementById(id);
        if (!wheel) return;
        let settleTimer = null;
        wheel.addEventListener('scroll', () => {
            updateActiveItem(wheel);
            clearTimeout(settleTimer);
            settleTimer = setTimeout(() => {
                commitWheelValue(wheel);
                recenterWheel(wheel);
            }, 120);
        });
        // Su desktop un singolo scatto della rotellina copre più elementi:
        // lo normalizziamo a un solo elemento per scatto.
        wheel.addEventListener('wheel', (e) => {
            e.preventDefault();
            const dir = e.deltaY > 0 ? 1 : -1;
            const maxIndex = wheel.children.length - 1;
            const currentIndex = Math.round(wheel.scrollTop / ITEM_HEIGHT);
            const targetIndex = Math.min(Math.max(currentIndex + dir, 0), maxIndex);
            wheel.scrollTo({ top: targetIndex * ITEM_HEIGHT, behavior: 'smooth' });
        }, { passive: false });
    });
});

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { searchState, swapStations, updateDisplayDateTime, searchRoutes, updateOptimizeUI, toggleReturn };
}
