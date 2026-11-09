/**
 * Jest global setup.
 * Provides mocks for browser APIs not available in jsdom.
 */

// Mock i18n: actvT(key, fallback, vars) ritorna il fallback con le sostituzioni {var}
global.actvT = (key, fallback, vars) => {
    let s = typeof fallback === 'string' ? fallback : key;
    if (vars && typeof vars === 'object') {
        for (const k in vars) s = String(s).split('{' + k + '}').join(String(vars[k]));
    }
    return s;
};
window.actvT = global.actvT;
window.ACTV_T = {};
window.ACTV_LANG = 'it';

// Mock global fetch
global.fetch = jest.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve([]),
        text: () => Promise.resolve(''),
    })
);

// Mock Leaflet (L) for map-dependent code
global.L = {
    map: jest.fn(() => ({
        setView: jest.fn().mockReturnThis(),
        locate: jest.fn(),
        on: jest.fn(),
        removeLayer: jest.fn(),
        fitBounds: jest.fn(),
    })),
    tileLayer: jest.fn(() => ({
        addTo: jest.fn(),
    })),
    circleMarker: jest.fn(() => ({
        bindPopup: jest.fn().mockReturnThis(),
        addTo: jest.fn().mockReturnThis(),
        setStyle: jest.fn().mockReturnThis(),
        setPopupContent: jest.fn().mockReturnThis(),
    })),
    latLngBounds: jest.fn(() => ({
        extend: jest.fn(),
    })),
    marker: jest.fn(() => ({
        bindPopup: jest.fn().mockReturnThis(),
        addTo: jest.fn().mockReturnThis(),
        getLatLng: jest.fn(),
        getElement: jest.fn(),
    })),
    polyline: jest.fn(() => ({
        addTo: jest.fn().mockReturnThis(),
        getBounds: jest.fn(),
        bringToFront: jest.fn(),
        bindPopup: jest.fn().mockReturnThis(),
        on: jest.fn().mockReturnThis(),
    })),
    divIcon: jest.fn(),
};
