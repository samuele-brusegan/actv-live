/**
 * Client-side i18n helper. The dictionary is injected by /js/i18n.js
 * (window.ACTV_T + window.ACTV_LANG). Usage:
 *   actvT('js.key')                 -> translated string or the key itself
 *   actvT('js.key', {name: 'Rialto'}) -> replaces {name} placeholders
 *   actvT('js.key', 'Fallback')     -> fallback text if the key is missing
 */
(function () {
    if (typeof window.actvT === 'function') return;
    const dict = window.ACTV_T || {};
    window.actvT = function (key, fallback, vars) {
        let s = dict[key];
        if (s === undefined) {
            s = (typeof fallback === 'string' ? fallback : key);
        }
        if (vars && typeof vars === 'object') {
            for (const k in vars) s = String(s).split('{' + k + '}').join(String(vars[k]));
        }
        return s;
    };
    // Alias breve (nessun utilizzo esistente di `t` nei file del progetto)
    if (typeof window.t !== 'function') window.t = window.actvT;
})();
