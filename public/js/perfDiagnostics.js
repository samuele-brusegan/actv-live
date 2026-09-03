/* Temporary, opt-in ACTV Live performance diagnostics. */
(function () {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    if (params.get('perf') !== '1' || window.ACTV_PERF_DIAGNOSTICS !== true) return;

    const startedAt = performance.now();
    const state = {
        enabled: true,
        startedAt,
        fetches: [],
        longTasks: [],
        slowFrames: [],
        marks: [],
        errors: []
    };

    function now() { return Math.round((performance.now() - startedAt) * 100) / 100; }
    function byteLength(value) {
        if (typeof value !== 'string') return null;
        return new Blob([value]).size;
    }
    function recordMark(name, detail) {
        const item = { name, at_ms: now() };
        if (detail !== undefined) item.detail = detail;
        state.marks.push(item);
        performance.mark(`actv-perf:${name}`);
    }

    const originalFetch = window.fetch;
    window.fetch = async function (input, init) {
        const url = typeof input === 'string' ? input : input?.url || String(input);
        const started = performance.now();
        let response;
        let error = null;
        try {
            response = await originalFetch.apply(this, arguments);
            return response;
        } catch (cause) {
            error = cause?.name || 'fetch-error';
            throw cause;
        } finally {
            const item = {
                url,
                method: init?.method || (typeof input === 'object' && input?.method) || 'GET',
                duration_ms: Math.round((performance.now() - started) * 100) / 100,
                status: response?.status ?? null,
                ok: response?.ok ?? false,
                error
            };
            if (response) {
                const length = response.headers.get('content-length');
                item.content_length = length ? Number(length) : null;
            }
            state.fetches.push(item);
            if (item.duration_ms >= 1000) console.warn('[ACTV perf] slow fetch', item);
        }
    };

    if (window.PerformanceObserver) {
        try {
            new PerformanceObserver(list => list.getEntries().forEach(entry => {
                state.longTasks.push({ at_ms: now(), duration_ms: Math.round(entry.duration * 100) / 100, name: entry.name });
            })).observe({ type: 'longtask', buffered: true });
        } catch (_) { /* Long Task API unavailable. */ }
    }

    let previousFrame = performance.now();
    function frameProbe(timestamp) {
        const elapsed = timestamp - previousFrame;
        if (elapsed > 50) state.slowFrames.push({ at_ms: now(), frame_ms: Math.round(elapsed * 100) / 100 });
        previousFrame = timestamp;
        window.requestAnimationFrame(frameProbe);
    }
    window.requestAnimationFrame(frameProbe);

    window.ACTVPerf = {
        state,
        mark: recordMark,
        snapshot() {
            const resources = performance.getEntriesByType('resource').filter(entry => entry.name.includes('/api/'));
            const result = {
                ...state,
                elapsed_ms: Math.round((performance.now() - startedAt) * 100) / 100,
                page: location.pathname,
                dom_nodes: document.getElementsByTagName('*').length,
                marker_nodes: document.querySelectorAll('.leaflet-marker-icon').length,
                memory: performance.memory ? {
                    used_js_bytes: performance.memory.usedJSHeapSize,
                    total_js_bytes: performance.memory.totalJSHeapSize,
                    limit_js_bytes: performance.memory.jsHeapSizeLimit
                } : null,
                api_resources: resources.map(entry => ({
                    name: entry.name,
                    duration_ms: Math.round(entry.duration * 100) / 100,
                    transfer_size: entry.transferSize,
                    decoded_body_size: entry.decodedBodySize
                }))
            };
            console.table(result.fetches);
            console.info('[ACTV perf] snapshot', result);
            return result;
        },
        download() {
            const blob = new Blob([JSON.stringify(this.snapshot(), null, 2)], { type: 'application/json' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `actv-perf-${location.pathname.replace(/[^a-z0-9]+/gi, '-')}.json`;
            link.click();
            URL.revokeObjectURL(link.href);
        }
    };
    recordMark('diagnostics-enabled');
    window.addEventListener('error', event => state.errors.push({ at_ms: now(), message: event.message, source: event.filename, line: event.lineno }));
    window.addEventListener('unhandledrejection', event => state.errors.push({ at_ms: now(), message: String(event.reason) }));
    window.addEventListener('beforeunload', () => {
        state.end = { at_ms: now(), dom_nodes: document.getElementsByTagName('*').length, marker_nodes: document.querySelectorAll('.leaflet-marker-icon').length };
    });
    console.info('[ACTV perf] enabled. Use ACTVPerf.snapshot() or ACTVPerf.download().');
})();
