/**
 * Tragitti preferiti: salvataggio locale di coppie partenza -> destinazione
 * per riavviare rapidamente una ricerca percorso.
 */

const FAVORITE_ROUTES_KEY = 'favorite_routes';
const MAX_FAVORITE_ROUTES = 20;

function getFavoriteRoutes() {
    try {
        const data = localStorage.getItem(FAVORITE_ROUTES_KEY);
        const arr = data ? JSON.parse(data) : [];
        return Array.isArray(arr) ? arr : [];
    } catch (e) {
        return [];
    }
}

/** Chiave stabile di un tragitto (origine -> destinazione) */
function favoriteRouteKey(origin, destination) {
    const o = (origin && (origin.id || origin.name)) || '';
    const d = (destination && (destination.id || destination.name)) || '';
    return `${o}=>${d}`;
}

function isFavoriteRoute(origin, destination) {
    const key = favoriteRouteKey(origin, destination);
    return getFavoriteRoutes().some(r => r.key === key);
}

/** Aggiunge un tragitto ai preferiti (se non già presente). Ritorna true se aggiunto. */
function addFavoriteRoute(origin, destination, line) {
    if (!origin || !destination) return false;
    const key = favoriteRouteKey(origin, destination);
    let routes = getFavoriteRoutes();
    if (routes.some(r => r.key === key)) return false;

    routes.unshift({
        key,
        origin,
        destination,
        line: line || null,
        createdAt: Date.now()
    });
    routes = routes.slice(0, MAX_FAVORITE_ROUTES);
    localStorage.setItem(FAVORITE_ROUTES_KEY, JSON.stringify(routes));
    return true;
}

/** Rimuove un tragitto preferito per chiave. */
function removeFavoriteRoute(key) {
    const routes = getFavoriteRoutes().filter(r => r.key !== key);
    localStorage.setItem(FAVORITE_ROUTES_KEY, JSON.stringify(routes));
}

/** Alterna lo stato di preferito per un tragitto. Ritorna true se ora è preferito. */
function toggleFavoriteRoute(origin, destination, line) {
    const key = favoriteRouteKey(origin, destination);
    if (getFavoriteRoutes().some(r => r.key === key)) {
        removeFavoriteRoute(key);
        return false;
    }
    addFavoriteRoute(origin, destination, line);
    return true;
}

// Export per Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        FAVORITE_ROUTES_KEY,
        getFavoriteRoutes, favoriteRouteKey, isFavoriteRoute,
        addFavoriteRoute, removeFavoriteRoute, toggleFavoriteRoute
    };
}
