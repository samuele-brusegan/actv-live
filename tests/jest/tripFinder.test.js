const { uniqueOrigins, destinationsForOrigin } = require('../../public/js/tripFinder');

describe('tripFinder selectors', () => {
    const variants = [
        {
            stops: [
                { id: 'a', name: 'Partenza' },
                { id: 'b', name: 'Fermata comune' },
                { id: 'c', name: 'Destinazione A' }
            ]
        },
        {
            stops: [
                { id: 'a', name: 'Partenza' },
                { id: 'd', name: 'Destinazione B' }
            ]
        }
    ];

    test('popola la partenza solo con le prime fermate delle varianti', () => {
        expect(uniqueOrigins(variants)).toEqual([
            { id: 'a', name: 'Partenza' },
        ]);
    });

    test('propone solo destinazioni successive alla partenza', () => {
        expect(destinationsForOrigin(variants, 'a')).toEqual([
            { id: 'c', name: 'Destinazione A' },
            { id: 'd', name: 'Destinazione B' }
        ]);
        expect(destinationsForOrigin(variants, 'b')).toEqual([
            { id: 'c', name: 'Destinazione A' }
        ]);
        expect(destinationsForOrigin(variants, 'c')).toEqual([]);
    });
});
