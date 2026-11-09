/**
 * Tour guidato cross-page: guida l'utente attraverso le funzioni reali dell'app.
 * Lo stato vive in localStorage, quindi il tour sopravvive ai cambi pagina:
 * gli step con `action: 'click'` si completano quando l'utente clicca davvero
 * l'elemento evidenziato (che resta interattivo).
 *
 * Ogni step: { page: RegExp su location.pathname, selector, title, text,
 *              action?: 'click'|'next', optional?: bool }
 */
(function () {
    const LS_ACTIVE = 'actv_tour_active';
    const LS_STEP = 'actv_tour_step';

    const STEPS = [
        {
            page: /^\/$/,
            selector: '#nearby-section',
            title: actvT('js.tour.t1','Fermate vicine'),
            text: actvT('js.tour.t1b','Con la geolocalizzazione attiva, qui compaiono le fermate più vicine a te.'),
            action: 'next'
        },
        {
            page: /^\/$/,
            selector: '#favorites-section',
            title: actvT('js.tour.t2','Le tue fermate'),
            text: actvT('js.tour.t2b','Le fermate che salvi con la stella le ritrovi sempre qui.'),
            action: 'next',
            optional: true
        },
        {
            page: /^\/$/,
            selector: '#map',
            title: actvT('js.tour.t3','La mappa'),
            text: actvT('js.tour.t3b','Ogni pallino è una fermata: toccalo per aprire i passaggi in tempo reale.'),
            action: 'next'
        },
        {
            page: /^\/$/,
            selector: '.home-actions',
            title: 'Tutto il resto',
            text: actvT('js.tour.t4','Da qui: percorsi con cambio, orari delle linee, statistiche sui ritardi e ricerca di una corsa. Le altre funzioni sono nel menu "Altro" in basso.'),
            action: 'next'
        },
        {
            page: /^\/$/,
            selector: '.home-primary-action',
            title: actvT('js.tour.t5','Cerca una fermata'),
            text: 'Tutto parte da qui: tocca il pulsante per aprire l\'elenco delle fermate.',
            action: 'click'
        },
        {
            page: /^\/stopList$/,
            selector: '#search-input',
            title: actvT('js.tour.t6','Trova la tua fermata'),
            text: actvT('js.tour.t6b','Digita il nome della fermata per filtrare l\'elenco in tempo reale.'),
            action: 'next',
            glow: true,
            demoText: ['Piazzale Roma', 'Mestre Centro', 'Bissuola Cadorna']
        },
        {
            page: /^\/stopList$/,
            selector: '#stations-list .station-item',
            title: actvT('js.tour.t7','Apri una fermata'),
            text: actvT('js.tour.t7b','Tocca una fermata: si apre la pagina con i prossimi passaggi.'),
            action: 'click'
        },
        {
            page: /^\/aut\/stops\/stop$/,
            selector: ['#passages-list > *', '#passages-list', '.section-title'],
            title: actvT('js.tour.t8','Passaggi in tempo reale'),
            text: actvT('js.tour.t8b','Qui vedi i prossimi mezzi: orario, linea, destinazione e posizione quando disponibile.'),
            action: 'next'
        },
        {
            page: /^\/aut\/stops\/stop$/,
            selector: '#favorite-btn',
            title: actvT('js.tour.t9','Salvala nei preferiti'),
            text: actvT('js.tour.t9b','Tocca la stella: la fermata comparirà in cima alla home.'),
            action: 'click'
        },
        {
            page: /^\/aut\/stops\/stop$/,
            selector: '.header-actions',
            title: actvT('js.tour.t10','Notifiche e condivisione'),
            text: actvT('js.tour.t10b','Da qui puoi attivare le notifiche sui ritardi e condividere la fermata.'),
            action: 'next'
        },
        {
            page: /^\/aut\/stops\/stop$/,
            selector: ['#passages-list .passage-card:not(.hidden)', '#passages-list .passage-card'],
            title: actvT('js.tour.t11','Dettaglio della corsa'),
            text: actvT('js.tour.t11b','Tocca un passaggio: si apre la pagina con tutte le fermate della corsa.'),
            action: 'click'
        },
        {
            page: /^\/trip-details$/,
            selector: '#stops-container',
            title: actvT('js.tour.t12','Le fermate della corsa'),
            text: actvT('js.tour.t12b','Tutte le fermate in ordine, con gli orari: quelle già passate e le prossime.'),
            action: 'next'
        },
        {
            page: /^\/trip-details$/,
            selector: '.map-fab',
            title: actvT('js.tour.t13','La corsa sulla mappa'),
            text: actvT('js.tour.t13b','Con questo pulsante vedi il percorso sulla mappa e la posizione del mezzo.'),
            action: 'next'
        },
        {
            page: /^\/trip-details$/,
            selector: '#actv-more-toggle',
            title: 'Menu "Altro"',
            text: actvT('js.tour.t14','Le funzioni secondarie vivono qui: tocca per aprire il menu.'),
            action: 'click'
        },
        {
            page: /^\/trip-details$/,
            selector: '.actv-more-menu a[href="/live-map"]',
            title: actvT('js.tour.t15','Bus in servizio'),
            text: actvT('js.tour.t15b','Apri la mappa live di tutti i mezzi ACTV in circolazione.'),
            action: 'click'
        },
        {
            page: /^\/live-map$/,
            selector: '#map',
            title: 'I mezzi in tempo reale',
            text: actvT('js.tour.t16','Ogni icona è un bus o un vaporetto in servizio: toccala per linea, destinazione e dettagli della corsa.'),
            action: 'next'
        },
        {
            page: /^\/live-map$/,
            selector: '#service-switch',
            title: actvT('js.tour.t17','Bus o vaporetti'),
            text: actvT('js.tour.t17b','Filtra i mezzi sulla mappa per tipo di servizio.'),
            action: 'next'
        },
        {
            page: /^\/live-map$/,
            selector: '.map-search',
            title: 'Cerca tra i mezzi',
            text: actvT('js.tour.t18','Filtra per linea, destinazione o ID corsa: puoi separare più ricerche con una virgola.'),
            action: 'next'
        },
        {
            page: /^\/live-map$/,
            selector: '#actv-more-toggle',
            title: 'Ancora "Altro"',
            text: actvT('js.tour.t19','Ultima tappa: gli orari delle linee. Riapri il menu.'),
            action: 'click'
        },
        {
            page: /^\/live-map$/,
            selector: '.actv-more-menu a[href="/line-schedule"]',
            title: actvT('js.tour.t20','Orari delle linee'),
            text: actvT('js.tour.t20b','Apri l\'orario completo di tutte le linee.'),
            action: 'click'
        },
        {
            page: /^\/line-schedule$/,
            selector: '.schedule-controls',
            title: actvT('js.tour.t21','Scegli la linea'),
            text: actvT('js.tour.t21b','Seleziona autobus o vaporetti, poi cerca la linea per numero o destinazione.'),
            action: 'next'
        },
        {
            page: /^\/line-schedule$/,
            selector: '.schedule-workspace',
            title: actvT('js.tour.t22','Percorsi e orari'),
            text: actvT('js.tour.t22b','A sinistra le varianti del percorso, a destra tutte le corse con fermate e orari. Tour finito!'),
            action: 'next'
        }
    ];

    let tour = null;

    function readIndex() {
        const i = parseInt(localStorage.getItem(LS_STEP) || '0', 10);
        return Number.isFinite(i) ? i : 0;
    }

    function isActive() {
        try { return localStorage.getItem(LS_ACTIVE) === '1'; } catch (e) { return false; }
    }

    function saveStep(index) {
        try {
            localStorage.setItem(LS_ACTIVE, '1');
            localStorage.setItem(LS_STEP, String(index));
        } catch (e) {}
    }

    /** Primo elemento visibile tra i selettori dello step (array o stringa). */
    function resolveTarget(step) {
        const selectors = Array.isArray(step.selector) ? step.selector : [step.selector];
        for (const sel of selectors) {
            const target = document.querySelector(sel);
            if (!target) continue;
            // Gli elementi position:fixed (es. .map-fab, bottom nav) hanno
            // offsetParent === null pur essendo visibili.
            const fixed = getComputedStyle(target).position === 'fixed';
            if ((target.offsetParent !== null || fixed) && target.getBoundingClientRect().height > 0) {
                return target;
            }
        }
        return null;
    }

    /** Primo step >= fromIndex che appartiene alla pagina corrente ed ha il
     *  target visibile. Gli step di altre pagine vengono saltati: dopo un
     *  cambio pagina il tour riprende dal primo step valido, non si interrompe. */
    function currentPageStep(fromIndex) {
        const path = window.location.pathname;
        for (let i = fromIndex; i < STEPS.length; i++) {
            const step = STEPS[i];
            if (!step.page.test(path)) continue;
            if (!resolveTarget(step)) continue;
            return i;
        }
        return fromIndex >= STEPS.length ? STEPS.length : -1;
    }

    function start() {
        try {
            localStorage.setItem(LS_ACTIVE, '1');
            localStorage.setItem(LS_STEP, '0');
        } catch (e) {}
        resume();
    }

    function resume() {
        if (!isActive()) return;
        const index = currentPageStep(readIndex());
        if (index < 0) return; // pagina fuori dal tour: resta attivo, riprende altrove
        if (index >= STEPS.length) { end(); return; }
        saveStep(index);
        ensureDom();
        tour.index = index;
        renderStep();
    }

    function ensureDom() {
        if (tour) return;
        const ring = document.createElement('div');
        ring.className = 'tour-ring';
        ring.innerHTML = '<div class="tour-wave"></div><div class="tour-wave tour-wave-2"></div>';
        const tip = document.createElement('div');
        tip.className = 'tour-tooltip';
        document.body.append(ring, tip);
        tour = { index: 0, ring, tip, target: null };
        window.addEventListener('resize', position);
    }

    function renderStep() {
        const step = STEPS[tour.index];
        const target = resolveTarget(step);
        if (!target) { end(false); return; }
        tour.target = target;
        // Se il target cresce dopo il rendering (es. lista passaggi caricata
        // in async), il ring lo segue invece di restare tagliato.
        if (tour.resizeObserver) tour.resizeObserver.disconnect();
        if (typeof ResizeObserver !== 'undefined') {
            tour.resizeObserver = new ResizeObserver(position);
            tour.resizeObserver.observe(target);
        }
        // I contenuti dinamici possono ricreare il target (refresh lista
        // passaggi): al cambio del DOM position() ri-risolve il selettore.
        if (typeof MutationObserver !== 'undefined') {
            if (tour.mutationObserver) tour.mutationObserver.disconnect();
            tour.mutationObserver = new MutationObserver(position);
            tour.mutationObserver.observe(document.body, { childList: true, subtree: true });
        }
        target.scrollIntoView({ block: 'center', behavior: 'smooth' });

        const isClick = step.action === 'click';
        tour.ring.classList.toggle('is-action', isClick || !!step.glow);

        tour.tip.innerHTML = `
            <div class="tour-progress">${tour.index + 1} di ${STEPS.length}</div>
            <div class="tour-title">${step.title}</div>
            <div class="tour-text">${step.text}</div>
            <div class="tour-actions">
                <button type="button" class="tour-skip" data-tour-end>Salta</button>
                ${isClick
                    ? `<span class="tour-hint">${actvT('js.tour.t23','Tocca il pulsante')}</span>`
                    : '<button type="button" class="tour-next" data-tour-next>' +
                      (tour.index === STEPS.length - 1 ? 'Fine' : 'Avanti') + '</button>'}
            </div>`;

        const nextBtn = tour.tip.querySelector('[data-tour-next]');
        if (nextBtn) nextBtn.addEventListener('click', advance);
        tour.tip.querySelector('[data-tour-end]').addEventListener('click', () => end());

        if (isClick) {
            // L'elemento resta cliccabile: al click il tour avanza e la
            // navigazione prosegue normalmente verso la pagina successiva.
            target.addEventListener('click', advance, { once: true });
        }

        // Demo di typing nel placeholder: si ferma al primo tocco dell'utente.
        if (step.demoText && target instanceof HTMLInputElement) {
            startInputDemo(target, step.demoText);
        } else {
            stopInputDemo();
        }

        position();
        // Retry: i contenuti async (passaggi) possono apparire dopo il render.
        [350, 1000, 2000].forEach(ms => setTimeout(position, ms));
        // Fade-in morbido: il ring appare già in posizione, senza scatti.
        requestAnimationFrame(() => tour && tour.ring.classList.add('is-visible'));
        if (tour.tip.animate) {
            tour.tip.animate([{ opacity: .35 }, { opacity: 1 }], { duration: 260, easing: 'ease-out' });
        }
    }

    /** Scrive `text` nel placeholder del target, poi ricomincia da capo.
     *  Qualunque interazione dell'utente con l'input ferma la demo e
     *  ripristina il placeholder originale. */
    function startInputDemo(target, texts) {
        stopInputDemo();
        const phrases = Array.isArray(texts) ? texts : [texts];
        const original = target.placeholder;
        let i = 0, pause = 0, phrase = 0;
        const timer = setInterval(() => {
            if (pause > 0) {
                if (--pause === 0) { i = 0; phrase = (phrase + 1) % phrases.length; target.placeholder = original; }
                return;
            }
            i++;
            target.placeholder = phrases[phrase].slice(0, i);
            if (i >= phrases[phrase].length) pause = 20; // ~1.6s di pausa a parola completa
        }, 80);
        tour.demo = { timer, target, original };
        ['focus', 'input', 'pointerdown', 'keydown'].forEach(ev =>
            target.addEventListener(ev, stopInputDemo, { once: true }));
    }

    function stopInputDemo() {
        const demo = tour && tour.demo;
        if (!demo) return;
        clearInterval(demo.timer);
        demo.target.placeholder = demo.original;
        tour.demo = null;
    }

    function advance() {
        if (!tour) return;
        saveStep(tour.index + 1);
        const nextIndex = currentPageStep(tour.index + 1);
        if (nextIndex === -1) return; // lo step successivo è su un'altra pagina
        if (nextIndex >= STEPS.length) { end(); return; }
        saveStep(nextIndex);
        tour.index = nextIndex;
        renderStep();
    }

    function position() {
        if (!tour || !tour.target) return;
        const step = STEPS[tour.index];
        // Se nel frattempo è apparso un target migliore (es. prima card dopo
        // il caricamento async) o il target è stato ricreato da un refresh
        // (le passage-card si aggiornano ogni 15s), il ring passa al nodo
        // nuovo invece di restare su quello staccato (rect 0,0).
        if (step && Array.isArray(step.selector)) {
            const better = resolveTarget(step);
            if (better && better !== tour.target) {
                if (tour.resizeObserver) {
                    tour.resizeObserver.disconnect();
                    tour.resizeObserver.observe(better);
                }
                if (step.action === 'click') {
                    better.addEventListener('click', advance, { once: true });
                }
                tour.target = better;
            }
        }
        // Target staccato e nessun sostituto (es. lista svuotata durante il
        // refresh): nascondi il ring invece di disegnarlo a 0,0.
        if (!tour.target.isConnected) {
            tour.ring.style.opacity = '0';
            return;
        }
        tour.ring.style.opacity = '';
        const rect = tour.target.getBoundingClientRect();
        const pad = 8;
        // Coordinate documento: ring e tooltip scorrono insieme all'elemento,
        // niente ricalcoli durante lo scroll (le onde non "saltellano").
        // Eccezione: i target position:fixed restano nel viewport, quindi il
        // ring li segue passando a fixed con coordinate viewport.
        const isFixed = getComputedStyle(tour.target).position === 'fixed';
        const top = rect.top + (isFixed ? 0 : window.scrollY);
        const left = rect.left + (isFixed ? 0 : window.scrollX);
        tour.ring.style.position = isFixed ? 'fixed' : 'absolute';
        const ring = tour.ring.style;
        ring.top = (top - pad) + 'px';
        ring.left = (left - pad) + 'px';
        ring.width = (rect.width + pad * 2) + 'px';
        ring.height = (rect.height + pad * 2) + 'px';

        const tip = tour.tip;
        tip.style.position = isFixed ? 'fixed' : 'absolute';
        tip.style.left = '50%';
        tip.style.transform = 'translateX(-50%)';
        const below = rect.bottom + 190 < window.innerHeight;
        tip.style.top = below ? (top + rect.height + pad + 14) + 'px' : 'auto';
        tip.style.bottom = 'auto';
        if (!below) tip.style.top = (top - pad - 14 - tip.offsetHeight) + 'px';
    }

    function end(markSeen = true) {
        if (tour) {
            stopInputDemo();
            if (tour.resizeObserver) tour.resizeObserver.disconnect();
            if (tour.mutationObserver) tour.mutationObserver.disconnect();
            window.removeEventListener('resize', position);
            tour.ring.remove();
            tour.tip.remove();
            tour = null;
        }
        try {
            localStorage.removeItem(LS_ACTIVE);
            localStorage.removeItem(LS_STEP);
            if (markSeen) localStorage.setItem('home_tutorial_seen', '1');
        } catch (e) {}
    }

    window.ACTVTour = { start, resume, end };

    // Riprende automaticamente un tour attivo dopo un cambio pagina.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resume);
    } else {
        resume();
    }
})();
