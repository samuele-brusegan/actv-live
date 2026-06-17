/**
 * Test per favoriteRoutes.js
 */
const {
    getFavoriteRoutes, favoriteRouteKey, isFavoriteRoute,
    addFavoriteRoute, removeFavoriteRoute, toggleFavoriteRoute
} = require('../../public/js/favoriteRoutes');

const A = { id: '100', name: 'Piazzale Roma' };
const B = { id: '200', name: 'Mestre Centro' };

beforeEach(() => {
    localStorage.clear();
});

describe('favoriteRouteKey', () => {
    test('chiave stabile origine->destinazione', () => {
        expect(favoriteRouteKey(A, B)).toBe('100=>200');
    });
    test('usa il nome se manca id', () => {
        expect(favoriteRouteKey({ name: 'X' }, { name: 'Y' })).toBe('X=>Y');
    });
});

describe('addFavoriteRoute / isFavoriteRoute', () => {
    test('aggiunge un tragitto e lo riconosce come preferito', () => {
        expect(addFavoriteRoute(A, B, '2')).toBe(true);
        expect(isFavoriteRoute(A, B)).toBe(true);
        expect(getFavoriteRoutes()).toHaveLength(1);
        expect(getFavoriteRoutes()[0].line).toBe('2');
    });

    test('non duplica lo stesso tragitto', () => {
        addFavoriteRoute(A, B);
        expect(addFavoriteRoute(A, B)).toBe(false);
        expect(getFavoriteRoutes()).toHaveLength(1);
    });

    test('non aggiunge se mancano origine o destinazione', () => {
        expect(addFavoriteRoute(null, B)).toBe(false);
        expect(addFavoriteRoute(A, null)).toBe(false);
    });
});

describe('removeFavoriteRoute', () => {
    test('rimuove per chiave', () => {
        addFavoriteRoute(A, B);
        removeFavoriteRoute(favoriteRouteKey(A, B));
        expect(getFavoriteRoutes()).toHaveLength(0);
    });
});

describe('toggleFavoriteRoute', () => {
    test('alterna lo stato di preferito', () => {
        expect(toggleFavoriteRoute(A, B)).toBe(true);   // aggiunto
        expect(isFavoriteRoute(A, B)).toBe(true);
        expect(toggleFavoriteRoute(A, B)).toBe(false);  // rimosso
        expect(isFavoriteRoute(A, B)).toBe(false);
    });
});
