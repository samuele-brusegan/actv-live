/**
 * Time Machine Frontend Logic
 * Intercepts ACTV fetch calls and overrides global time when simulation is active.
 */

const TimeMachine = {
    isEnabled: () => localStorage.getItem('tm_enabled') === 'true',
    getSimTime: () => localStorage.getItem('tm_time'),

    init() {
        this.interceptFetch();
        this.interceptDate();
    },

    interceptFetch() {
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            const url = args[0];
            
            // Check if it's an ACTV passage request and simulation is on
            if (this.isEnabled() && typeof url === 'string' && url.includes('oraritemporeale.actv.it/aut/backend/passages/')) {
                const stopIdMatch = url.match(/passages\/(.+)-web-aut/);
                if (stopIdMatch) {
                    const stopId = stopIdMatch[1];
                    const simTime = this.getSimTime();
                    
                    console.log(`[TimeMachine] Intercepting fetch for stop ${stopId} at simulated time ${simTime}`);
                    
                    // Fetch from our local simulation endpoint instead
                    return originalFetch(`/api/tm/simulated-data?stopId=${stopId}&time=${encodeURIComponent(simTime)}`);
                }
            }
            
            return originalFetch(...args);
        };
    },

    interceptDate() {
        const self = this;
        // Optional: Override Date.now() if needed by UI, but careful with side effects
        // For now, we manually provide a helper that pages should use
    },

    now() {
        if (this.isEnabled() && this.getSimTime()) {
            return new Date(this.getSimTime());
        }
        return new Date();
    }
};

// Auto-init
TimeMachine.init();
window.TimeMachine = TimeMachine;

// Send errors to backend
window.onerror = function(message, source, lineno, colno, error) {
    fetch('/api/log-js-error', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: message,
            url: source,
            line: lineno,
            stack: error ? error.stack : null
        })
    });
};
