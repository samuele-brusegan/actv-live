/**
 * Test per widget.js
 * Funzioni testate: getWidgetUrl, getEmbedCode
 */

const { getWidgetUrl, getEmbedCode, showQrDialog, closeQrDialog } = require('../../public/js/widget');

describe('getWidgetUrl', () => {
    test('genera URL con stop e name', () => {
        const url = getWidgetUrl('4825', 'Piazzale Roma', { baseUrl: 'https://example.com' });
        expect(url).toContain('/widget?');
        expect(url).toContain('stop=4825');
        expect(url).toContain('name=Piazzale+Roma');
    });

    test('include parametro max', () => {
        const url = getWidgetUrl('611', 'Test', { baseUrl: 'https://example.com', max: 5 });
        expect(url).toContain('max=5');
    });

    test('include parametro theme', () => {
        const url = getWidgetUrl('611', 'Test', { baseUrl: 'https://example.com', theme: 'dark' });
        expect(url).toContain('theme=dark');
    });

    test('funziona senza nome', () => {
        const url = getWidgetUrl('4825', '', { baseUrl: 'https://example.com' });
        expect(url).toContain('stop=4825');
    });
});

describe('getEmbedCode', () => {
    test('genera codice iframe corretto', () => {
        const code = getEmbedCode('4825', 'Piazzale Roma', { baseUrl: 'https://example.com' });
        expect(code).toContain('<iframe');
        expect(code).toContain('src="https://example.com/widget?');
        expect(code).toContain('stop=4825');
        expect(code).toContain('frameborder="0"');
        expect(code).toContain('title="ACTV Live - Piazzale Roma"');
    });

    test('usa dimensioni personalizzate', () => {
        const code = getEmbedCode('4825', 'Test', {
            baseUrl: 'https://example.com',
            width: '300',
            height: '500'
        });
        expect(code).toContain('width="300"');
        expect(code).toContain('height="500"');
    });

    test('dimensioni default', () => {
        const code = getEmbedCode('4825', 'Test', { baseUrl: 'https://example.com' });
        expect(code).toContain('width="100%"');
        expect(code).toContain('height="400"');
    });
});

describe('showQrDialog', () => {
    afterEach(() => {
        const modal = document.getElementById('qr-share-modal');
        if (modal) modal.remove();
    });

    test('crea il modal con l\'URL', () => {
        showQrDialog('https://example.com/stop?id=1');
        expect(document.getElementById('qr-share-modal')).not.toBeNull();
        expect(document.getElementById('qr-url').value).toBe('https://example.com/stop?id=1');
    });

    test('sostituisce un modal esistente', () => {
        showQrDialog('https://a.example');
        showQrDialog('https://b.example');
        expect(document.querySelectorAll('#qr-share-modal').length).toBe(1);
        expect(document.getElementById('qr-url').value).toBe('https://b.example');
    });

    test('escapa caratteri speciali nell\'attributo value', () => {
        showQrDialog('https://example.com/?a="x"');
        expect(document.getElementById('qr-url').value).toBe('https://example.com/?a="x"');
    });

    test('mostra fallback senza libreria qrcode', () => {
        showQrDialog('https://example.com');
        expect(document.getElementById('qr-code-container').textContent).toContain('non disponibile');
    });
});

describe('closeQrDialog', () => {
    test('rimuove il modal', () => {
        showQrDialog('https://example.com');
        closeQrDialog();
        expect(document.getElementById('qr-share-modal')).toBeNull();
    });

    test('non fallisce se il modal non esiste', () => {
        expect(() => closeQrDialog()).not.toThrow();
    });
});
