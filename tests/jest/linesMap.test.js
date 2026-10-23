const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(require.resolve('../../public/js/linesMap'), 'utf8');
const view = fs.readFileSync(require.resolve('../../app/views/linesMap.php'), 'utf8');
const lanes = require('../../public/js/mapRouteLanes');
let initialize, context, active, listeners;
const route = name => ({ service:'automobilistico',route_short_name:name,route_long_name:'Percorso '+name,shape:[{lat:45.4,lng:12.3},{lat:45.5,lng:12.4}],path:[] });
const response = data => ({ok:true,text:async()=>JSON.stringify(data)});
const settle = async () => {for(let i=0;i<40;i++)await Promise.resolve();};

beforeEach(() => {
    window.history.replaceState({}, '', '/lines-map');
    localStorage.clear();
    document.documentElement.innerHTML = view.replace(/<\?php[\s\S]*?\?>/g,'').replace(/<script[\s\S]*?<\/script>/g,'');
    active = new Set(); listeners = new Set();
    const map = {
        setView(){return this;}, on(event,fn){listeners.add(fn);}, off(event,fn){listeners.delete(fn);},
        removeLayer(layer){active.delete(layer);},hasLayer:layer=>active.has(layer),
        project:point=>({x:point[1],y:point[0]}),unproject:point=>[point.y,point.x],
        getZoom:()=>12,getZoomScale:()=>1,fitBounds:jest.fn()
    };
    const layer = () => ({
        addTo(target){ if(target.items)target.items.add(this); active.add(this);return this;},
        bindPopup(){return this;},on(){return this;},setLatLngs:jest.fn(),getBounds:()=>({extend(){}}),bringToFront(){},setStyle(){}
    });
    context=vm.createContext({
        document:new Proxy(document,{get(target,key){
            if(key==='addEventListener')return(event,callback)=>{if(event==='DOMContentLoaded')initialize=callback;else target.addEventListener(event,callback);};
            const value=target[key];return typeof value==='function'?value.bind(target):value;
        }}),window,localStorage,URLSearchParams,AbortController,console,ACTVRouteLanes:lanes,
        fetch:jest.fn(async()=>response([route('21')])),
        L:{map:()=>map,tileLayer:()=>({addTo(){}}),point:(x,y)=>({x,y}),polyline:()=>layer(),circleMarker:()=>layer(),
            layerGroup:()=>({items:new Set(),addTo(){return this;},clearLayers(){this.items.forEach(item=>active.delete(item));this.items.clear();}})}
    });
    vm.runInContext(source,context);
});

const openFilters = () => document.getElementById('btn-lines-filters').click();
const submit = () => document.getElementById('lines-filter-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
const urls = () => context.fetch.mock.calls.map(([url])=>new URL(url,'https://actv.test').searchParams);

test('i due pulsanti mostrano entrambi i servizi e aggiornano in pagina senza accumulare layer o listener',async()=>{
    initialize();await settle();
    expect(context.fetch).toHaveBeenCalledTimes(2);
    expect(active.size).toBe(4);expect(listeners.size).toBe(1);
    const bus=document.querySelector('[data-service="automobilistico"]');
    const boat=document.querySelector('[data-service="navigation"]');
    expect(bus.getAttribute('aria-pressed')).toBe('true');expect(boat.getAttribute('aria-pressed')).toBe('true');
    context.fetch.mockClear();bus.click();await settle();
    expect(urls()[0].get('service')).toBe('navigation');expect(active.size).toBe(2);expect(listeners.size).toBe(1);
    expect(bus.getAttribute('aria-pressed')).toBe('false');
    expect(localStorage.getItem('actv-lines-map-service')).toBe('navigation');
    boat.click();await settle();expect(boat.getAttribute('aria-pressed')).toBe('true');
});

test('invia più linee come richieste separate per ciascun servizio e sostituisce i percorsi precedenti',async()=>{
    initialize();await settle();context.fetch.mockClear();
    context.fetch.mockImplementation(async url=>response([route(new URL(url,'https://actv.test').searchParams.get('line'))]));
    openFilters();document.getElementById('lines-filter-query').value='5E, 21';submit();await settle();
    expect(urls().map(params=>params.get('line'))).toEqual(['5E','21','5E','21']);
    expect(urls().every(params=>!params.has('cache'))).toBe(true);
    expect(active.size).toBe(8);expect(listeners.size).toBe(1);
    expect(new URLSearchParams(window.location.search).get('line')).toBe('5E,21');
    expect(document.getElementById('lines-filter-summary').textContent).toBe('Linee 5E, 21');
    expect(document.getElementById('lines-filters-panel').hidden).toBe(true);
});

test('invia corse multiple e fermate, poi rimuove tutti i filtri conservando il servizio',async()=>{
    window.history.replaceState({},'','/lines-map?service=navigation');
    initialize();await settle();context.fetch.mockClear();openFilters();
    document.getElementById('lines-filter-mode').value='trips';
    document.getElementById('lines-filter-mode').dispatchEvent(new Event('change'));
    document.getElementById('lines-filter-query').value='t1, t2';
    document.getElementById('lines-show-stops').checked=true;
    submit();await settle();
    expect(urls()[0].get('tripIds')).toBe('t1,t2');expect(urls()[0].get('showStops')).toBe('1');
    expect(document.getElementById('lines-filter-count').textContent).toBe('2');
    context.fetch.mockClear();document.getElementById('btn-reset-lines-filters').click();await settle();
    expect(urls()[0].get('service')).toBe('navigation');expect(urls()[0].get('cache')).toBe('1');
    expect(urls()[0].has('tripIds')).toBe(false);expect(urls()[0].has('showStops')).toBe(false);
});

test('preserva i gruppi aperti dagli orari e invia anche gli ID necessari alla cache navigazione',async()=>{
    window.history.replaceState({},'','/lines-map?tripGroups=1:t1,t2|2:t3');
    initialize();await settle();
    expect(urls().every(params=>params.get('tripIds')==='t1,t2,t3')).toBe(true);
    openFilters();expect(document.getElementById('lines-filter-query').value).toBe('t1, t2, t3');submit();await settle();
    expect(new URLSearchParams(window.location.search).get('tripGroups')).toBe('1:t1,t2|2:t3');
    openFilters();document.getElementById('lines-filter-query').value='t4';submit();await settle();
    expect(new URLSearchParams(window.location.search).has('tripGroups')).toBe(false);
});

test('ignora risposte tardive di richieste annullate',async()=>{
    let resolveOld;
    context.fetch.mockImplementationOnce(()=>new Promise(resolve=>{resolveOld=resolve;}));
    window.history.replaceState({},'','/lines-map?service=automobilistico');
    initialize();
    document.querySelector('[data-service="navigation"]').click();await settle();
    expect(active.size).toBe(4);
    resolveOld(response([route('OLD')]));await settle();
    expect(active.size).toBe(4);expect(listeners.size).toBe(1);
});

test('gestisce risultati vuoti ed errori con un pulsante per riprovare',async()=>{
    context.fetch.mockResolvedValue(response([]));initialize();await settle();
    expect(document.getElementById('lines-map-status').textContent).toContain('Nessun percorso');
    context.fetch.mockRejectedValue(new Error('offline'));
    const log=jest.spyOn(console,'error').mockImplementation(()=>{});
    document.querySelector('[data-service="navigation"]').click();await settle();
    expect(document.querySelector('#loading button').textContent).toBe('Riprova');
    context.fetch.mockResolvedValue(response([route('21')]));document.querySelector('#loading button').click();await settle();
    expect(active.size).toBe(2);expect(document.getElementById('loading').style.display).toBe('none');log.mockRestore();
});

test('i pulsanti della legenda nascondono anche le fermate del percorso selezionato',async()=>{
    window.history.replaceState({},'','/lines-map?service=automobilistico&line=21&showStops=1');
    const stops=[{lat:45.4,lng:12.3,name:'A'},{lat:45.5,lng:12.4,name:'B'}];
    context.fetch.mockResolvedValue(response([{...route('21'),path:stops},{...route('5E'),path:stops}]));
    initialize();await settle();
    expect(active.size).toBe(8);
    const toggle=document.querySelector('.route-map-toggle');
    toggle.click();expect(active.size).toBe(4);
    toggle.click();expect(active.size).toBe(8);
});
