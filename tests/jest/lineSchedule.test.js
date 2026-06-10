const { getDistinctiveStops, groupVariantsByRoute } = require('../../public/js/lineSchedule');

describe('getDistinctiveStops', () => {
    const variantA = {
        stops: [
            { id: 'origin', name: 'MESTRE CENTRO B3' },
            { id: 'common', name: 'Olmo' },
            { id: 'a1', name: 'Zelarino Parolari' },
            { id: 'end', name: 'Noale' }
        ]
    };
    const variantB = {
        stops: [
            { id: 'origin', name: 'MESTRE CENTRO B3' },
            { id: 'common', name: 'Olmo' },
            { id: 'b1', name: 'Tito Castellana' },
            { id: 'end', name: 'Noale' }
        ]
    };

    test('mostra solo le fermate non condivise tra varianti comparabili', () => {
        expect(getDistinctiveStops(variantA, [variantA, variantB])).toEqual(['Zelarino Parolari']);
        expect(getDistinctiveStops(variantB, [variantA, variantB])).toEqual(['Tito Castellana']);
    });

    test('non mostra differenze senza almeno due varianti comparabili', () => {
        expect(getDistinctiveStops(variantA, [variantA])).toEqual([]);
    });
});

describe('groupVariantsByRoute', () => {
    const stops = names => names.map(name => ({ name }));
    const common = ['San Rocco', 'Castellana', 'Maerne', 'Noale'];

    test('unisce deviazioni ed estensioni collegate dello stesso percorso', () => {
        const variants = [
            { trip_id: 'a-long', origin: 'VENEZIA', terminus: 'Noale', stops: stops(['VENEZIA', 'MESTRE B4', 'Zelarino', ...common]) },
            { trip_id: 'a-short', origin: 'MESTRE B3', terminus: 'Noale', stops: stops(['MESTRE B3', 'Zelarino', ...common]) },
            { trip_id: 'b-long', origin: 'VENEZIA', terminus: 'Noale', stops: stops(['VENEZIA', 'MESTRE B4', 'Tito', ...common]) },
            { trip_id: 'b-short', origin: 'MESTRE B3', terminus: 'Noale', stops: stops(['MESTRE B3', 'Tito', ...common]) }
        ];

        const groups = groupVariantsByRoute(variants).map(group => group.map(v => v.trip_id).sort());
        expect(groups).toEqual([
            ['a-long', 'a-short', 'b-long', 'b-short']
        ]);
    });

    test('unisce deviazioni intermedie con gli stessi capolinea', () => {
        const variants = [
            { trip_id: 'direct', origin: 'Noale', terminus: 'VENEZIA', stops: stops(['Noale', 'Maerne', 'Mestre', 'VENEZIA']) },
            { trip_id: 'short-detour', origin: 'Noale', terminus: 'VENEZIA', stops: stops(['Noale', 'Robegano', 'Maerne', 'Mestre', 'VENEZIA']) },
            { trip_id: 'long-detour', origin: 'Noale', terminus: 'VENEZIA', stops: stops(['Noale', 'Martellago', 'Olmo', 'Mestre', 'VENEZIA']) }
        ];

        expect(groupVariantsByRoute(variants).map(group => group.map(v => v.trip_id))).toEqual([
            ['direct', 'short-detour', 'long-detour']
        ]);
    });

    test('aggrega transitivamente una tratta accorciata alla famiglia', () => {
        const variants = [
            { trip_id: 'noale-a', origin: 'Noale', terminus: 'Mestre', stops: stops(['Noale', 'Robegano', 'Maerne', 'Olmo', 'Mestre']) },
            { trip_id: 'noale-b', origin: 'Noale', terminus: 'Mestre', stops: stops(['Noale', 'Robegano', 'Maerne', 'Tito', 'Mestre']) },
            { trip_id: 'maerne', origin: 'Maerne FS', terminus: 'Mestre', stops: stops(['Maerne FS', 'Maerne', 'Olmo', 'Mestre']) }
        ];

        expect(groupVariantsByRoute(variants).map(group => group.map(v => v.trip_id).sort())).toEqual([
            ['maerne', 'noale-a', 'noale-b']
        ]);
    });
});
