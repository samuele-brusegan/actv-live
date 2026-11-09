/**
 * Inject a small IT/EN language switcher (fixed bottom-left pill).
 * The ?lang= parameter is handled server-side and persisted via cookie.
 */
(function () {
    const path = window.location.pathname;
    const params = new URLSearchParams(window.location.search);
    if (path.startsWith('/admin') || path === '/widget' || params.get('embedded') === '1') return;

    const current = window.ACTV_LANG || 'it';

    function urlFor(lang) {
        const p = new URLSearchParams(window.location.search);
        p.set('lang', lang);
        const qs = p.toString();
        return window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
    }

    const style = document.createElement('style');
    style.textContent = `
        .actv-lang-switch{position:fixed;left:12px;bottom:12px;z-index:9999;display:flex;
            background:rgba(0,0,0,.55);backdrop-filter:blur(6px);border-radius:999px;
            overflow:hidden;font:600 14px/1 system-ui,sans-serif}
        .actv-lang-switch a{color:#fff;text-decoration:none;padding:7px 10px;opacity:.6}
        .actv-lang-switch a.active{opacity:1;background:rgba(255,255,255,.18)}
    `;
    document.head.appendChild(style);

    const box = document.createElement('nav');
    box.className = 'actv-lang-switch';
    box.setAttribute('aria-label', 'Language / Lingua');
    box.innerHTML =
        `<a href="${urlFor('it')}" hreflang="it" lang="it" aria-label="Italiano" title="Italiano"${current === 'it' ? ' class="active" aria-current="true"' : ''}>🇮🇹</a>` +
        `<a href="${urlFor('en')}" hreflang="en" lang="en" aria-label="English" title="English"${current === 'en' ? ' class="active" aria-current="true"' : ''}>🇬🇧</a>`;

    document.addEventListener('DOMContentLoaded', () => document.body.appendChild(box));
})();
