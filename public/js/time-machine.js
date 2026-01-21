/**
 * Time Machine: Logica per la simulazione del tempo ed intercettazione dati.
 * Permette di dirottare le richieste API ACTV verso dati registrati localmente.
 */

const TimeMachine = {
    /** @returns {boolean} Se la simulazione è attiva */
    isEnabled() {
        return localStorage.getItem('tm_enabled') === 'true';
    },

    /** @returns {string|null} L'orario simulato memorizzato */
    getSimTime() {
        return localStorage.getItem('tm_time');
    },

    /** Inizializza gli interceptor */
    init() {
        this._setupFetchInterceptor();
        this._setupErrorLogging();
    },

    /** 
     * Intercetta le chiamate fetch dirette ad ACTV.
     * Se la Time Machine è attiva, reindirizza la chiamata al nostro database locale.
     */
    _setupFetchInterceptor() {
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            const url = args[0];
            
            if (this.isEnabled() && typeof url === 'string' && url.includes('oraritemporeale.actv.it/aut/backend/passages/')) {
                const match = url.match(/passages\/(.+)-web-aut/);
                if (match) {
                    const stopId = match[1];
                    const simTime = this.getSimTime();
                    
                    console.info(`[TimeMachine] Intercettata richiesta per fermata ${stopId} alle ${simTime}`);
                    
                    // Modifica URL per puntare al nostro endpoint di simulazione
                    return originalFetch(`/api/tm/simulated-data?stopId=${stopId}&time=${encodeURIComponent(simTime)}`);
                }
            }
            
            return originalFetch(...args);
        };
    },

    /** Invia gli errori JavaScript al backend per il logging centralizzato */
    _setupErrorLogging() {
        const originalOnError = window.onerror;
        window.onerror = (message, source, lineno, colno, error) => {
            // Log locale
            console.error("JS Error Logged:", message);

            // Log remoto
            fetch('/api/log-js-error', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: message,
                    url: source,
                    line: lineno,
                    stack: error?.stack || null,
                    context: window.location.href
                })
            }).catch(() => {/* fail silently if logging fails */});

            if (originalOnError) return originalOnError(message, source, lineno, colno, error);
            return false;
        };
    },

    /** 
     * Restituisce un oggetto Date basato sul tempo simulato o reale.
     * Da usare ovunque serva gestire date sensibili alla simulazione.
     * @returns {Date}
     */
    now() {
        const simTime = this.getSimTime();
        if (this.isEnabled() && simTime) {
            return new Date(simTime);
        }
        return new Date();
    }
};

// Inizializzazione automatica e pubblicazione globale
TimeMachine.init();
window.TimeMachine = TimeMachine;
