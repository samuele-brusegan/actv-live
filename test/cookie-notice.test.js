/**
 * Test per cookie-notice.js
 * Funzioni testate: CookieNotice class
 */

beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = '';
});

const CookieNotice = require('../public/js/cookie-notice');

describe('CookieNotice', () => {
    test('crea un\'istanza con opzioni di default', () => {
        const notice = new CookieNotice();
        expect(notice.options.cookieName).toBe('cookie_consent_accepted');
        expect(notice.options.title).toBe('Cookie & Privacy');
    });

    test('accetta opzioni personalizzate', () => {
        const notice = new CookieNotice({ title: 'Custom Title', moreUrl: '/custom' });
        expect(notice.options.title).toBe('Custom Title');
        expect(notice.options.moreUrl).toBe('/custom');
        // Opzioni di default non sovrascritt
        expect(notice.options.cookieName).toBe('cookie_consent_accepted');
    });

    test('hasConsented ritorna false inizialmente', () => {
        const notice = new CookieNotice();
        expect(notice.hasConsented()).toBe(false);
    });

    test('setConsent salva il consenso', () => {
        const notice = new CookieNotice();
        // render il DOM per setConsent
        notice.render();
        notice.setConsent();
        expect(localStorage.getItem('cookie_consent_accepted')).toBe('true');
        expect(notice.hasConsented()).toBe(true);
    });

    test('non renderizza se già accettato', () => {
        localStorage.setItem('cookie_consent_accepted', 'true');
        new CookieNotice();
        expect(document.getElementById('cookie-notice')).toBeNull();
    });

    test('renderizza il banner nel DOM', () => {
        const notice = new CookieNotice();
        // DOM content loaded already fired, so render was called
        notice.render();
        const banner = document.getElementById('cookie-notice');
        expect(banner).not.toBeNull();
        // Usiamo textContent per evitare problemi con entity escaping (& vs &amp;)
        expect(banner.textContent).toContain('Cookie & Privacy');
        expect(banner.innerHTML).toContain('Accetta');
    });
});
