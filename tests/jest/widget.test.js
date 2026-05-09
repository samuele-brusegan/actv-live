/**
 * Test per widget.js
 * Funzioni testate: getWidgetUrl, getEmbedCode
 */

const { getWidgetUrl, getEmbedCode } = require('../../public/js/widget');

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
