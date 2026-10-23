const {layout, offsetPath} = require('../../public/js/mapRouteLanes');
const p = (x, y) => ({x, y});
const route = (key, points) => ({key, points});

test('separa tre linee condivise simmetricamente e non dipende dall’ordine di caricamento', () => {
    const routes = ['21','5E','6'].map(key => route(key, [p(0,0),p(200,0)]));
    const paths = layout(routes).map(fragments => offsetPath(fragments, 10));
    expect(paths.map(path => path[0].y)).toEqual([-10,0,10]);
    const reversed = layout([...routes].reverse()).map(fragments => offsetPath(fragments, 10));
    expect(reversed.reverse()).toEqual(paths);
});

test('i percorsi con verso opposto restano su lati distinti', () => {
    const paths = layout([
        route('A',[p(0,0),p(200,0)]),
        route('B',[p(200,0),p(0,0)])
    ]).map(fragments => offsetPath(fragments, 10));
    expect(paths[0][0].y).toBe(-5);
    expect(paths[1][0].y).toBe(5);
});

test('separa solo il tratto condiviso con campionamenti e diramazioni diversi', () => {
    const lanes = layout([
        route('A',[p(0,0),p(300,0)]),
        route('B',[p(100,0),p(150,0),p(200,0),p(200,100)])
    ]);
    expect(lanes[0][0].lane).toBe(0);
    expect(lanes[0].at(-1).lane).toBe(0);
    expect(lanes[0].some(fragment => fragment.lane === -0.5)).toBe(true);
    expect(lanes[1].at(-1).lane).toBe(0);
});

test('non sposta incroci, strade distinte o varianti della stessa linea', () => {
    const routes = [
        route('A',[p(0,0),p(200,0)]),
        route('B',[p(100,-100),p(100,100)]),
        route('C',[p(0,40),p(200,40)]),
        route('A',[p(200,0),p(0,0)])
    ];
    const lanes = layout(routes);
    expect(lanes.flat().every(fragment => fragment.lane === 0)).toBe(true);
    lanes.forEach((fragments, i) => expect(offsetPath(fragments,10)).toEqual(routes[i].points));
});

test('riconosce tratti quasi coincidenti e limita gli spigoli stretti', () => {
    const routes = [
        route('A',[p(0,0),p(200,0),p(1,1)]),
        route('B',[p(0,2),p(200,2),p(1,3)])
    ];
    const lanes = layout(routes);
    expect(lanes[0].some(fragment => fragment.lane !== 0)).toBe(true);
    for (const fragments of lanes) for (const point of offsetPath(fragments, 10)) {
        expect(Number.isFinite(point.x) && Number.isFinite(point.y)).toBe(true);
        expect(point.x).toBeGreaterThan(-40);
        expect(point.x).toBeLessThan(240);
    }
});

test('una linea singola e i punti duplicati non generano spostamenti o NaN', () => {
    const lanes=layout([route('A',[p(0,0),p(0,0),p(100,50)])]);
    expect(offsetPath(lanes[0],10)).toEqual([p(0,0),p(100,50)]);
    expect(offsetPath([],10)).toEqual([]);
});

test('mantiene la separazione anche sui piccoli segmenti delle curve', () => {
    const points=[p(0,0),p(2,0),p(3,1),p(3,3)];
    const lanes=layout([route('A',points),route('B',points)]);
    expect(lanes[0].every(fragment => fragment.lane===-0.5)).toBe(true);
    expect(lanes[1].every(fragment => fragment.lane===0.5)).toBe(true);
});

test('contiene i fasci densi senza appiattire più linee sullo stesso offset', () => {
    const routes = Array.from({length:20},(_,i)=>route(String(i).padStart(2,'0'),[p(0,0),p(2000,0)]));
    const paths=layout(routes).map(fragments=>offsetPath(fragments,6,{maxOffset:8}));
    const offsets=paths.map(path=>path[0].y);
    expect(Math.max(...offsets)).toBeLessThanOrEqual(8);
    expect(Math.min(...offsets)).toBeGreaterThanOrEqual(-8);
    expect(new Set(offsets).size).toBe(20);
});

test('raccorda i cambi frequenti di corsia invece di generare denti sul percorso', () => {
    const fragments=Array.from({length:100},(_,i)=>({start:p(i,0),end:p(i+1,0),lane:i%2?-3:3,laneCount:7}));
    const path=offsetPath(fragments,6,{maxOffset:8});
    for(let i=1;i<path.length;i++) {
        expect(path[i].x).toBeGreaterThan(path[i-1].x);
        expect(Math.abs(path[i].y-path[i-1].y)).toBeLessThan(2);
    }
});
