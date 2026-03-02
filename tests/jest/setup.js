/**
 * Jest global setup.
 * Provides mocks for browser APIs not available in jsdom.
 */

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
