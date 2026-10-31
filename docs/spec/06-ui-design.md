# 06 — Design system e interfaccia grafica

L'UI è mobile-first, interamente in italiano, costruita con CSS custom
(`public/css/style.css`, `app-shell.css`, `structure/*.css` + fogli per pagina)
e Bootstrap 5 solo per utility. Il tema cambia aggiungendo
`data-theme="dark"` a `<html>` (più `data-bs-theme` per Bootstrap).

## Palette colori

### Tema chiaro (`:root`, `public/css/style.css`)

| Token | Valore | Uso |
|---|---|---|
| `--color-primary-green` | `#009E61` | header, brand ACTV, accenti positivi, spinner, FAB QR |
| `--color-primary-blue` | `#0152BB` | bottoni primari, badge linea, badge ID fermata |
| `--color-primary-blue-dark` | `#013d99` | stato `:active`/hover bottoni |
| `--color-primary-red` | `#FA2020` | badge linee regionali/rosse, pulsante avvisi |
| `--color-actv-red` | `#E30613` | offline indicator, errori, barre ritardo >10 min |
| `--color-night-blue` | `#002B57` | badge linee notturne (`N…`) |
| `--color-gold` | `#FFD700` | icona preferiti (stella) |
| `--color-orange` | `#FF9800` | ritardi medi (5–10 min), banner, header pannello N/R |
| `--color-accent-blue` | `#007bff` | badge secondari |
| `--color-accent-blue-dark` | `#0056b3` | — |
| `--color-white` | `#FFFFFF` | superfici/card |
| `--color-black` | `#000000` | testo primario |
| `--color-bg-gray` | `#F5F5F5` | sfondo body |
| `--color-text-gray` | `#666` | testo secondario |
| `--color-text-light` | `#888` | label piccole |
| `--color-text-muted` | `#999` | placeholder/disabled |
| `--color-text-dark` | `#333` | testo su card |
| `--color-border-gray` | `#e0e0e0` | bordi |
| `--color-light-gray` | `#ccc` | icone secondarie, chevron |
| `--color-lighter-gray` | `#eee` | divisori leggeri |
| `--color-bg-light` | `#f0f0f0` | sfondi input/secondari |
| `--color-handle-gray` | `#D1D1D6` | handle delle bottom-sheet modal |
| `--color-spinner-base` | `#f3f3f3` | base spinner |

### Tema scuro (`[data-theme="dark"]`)

| Token | Valore |
|---|---|
| `--color-primary-green` | `#00995e` |
| `--color-primary-blue` | `#0085ff` |
| `--color-night-blue` | `#0095ff` |
| `--color-white` (superfici) | `#2a2a2a` |
| `--color-black` (testo) | `#FFF` |
| `--color-bg-gray` | `#111` |
| `--color-text-dark` | `#F5F5F5` |
| `--color-border-gray` | `#333` |
| `--color-light-gray` | `#555` |
| `--color-bg-light` | `#333` |
| `--color-text-gray` | `#666` (invariato) |
| `--bs-body-color` | `#F5F5F5` |

### Shell prodotto (`app-shell.css` — sovrascrive il look "distribuito")

| Token | Light | Dark |
|---|---|---|
| `--actv-ink` | `#10231f` | `#edf7f2` |
| `--actv-muted` | `#64736f` | `#a6b8b1` |
| `--actv-surface` | `#fff` | `#17221f` |
| `--actv-surface-2` | `#f2f6f4` | `#0d1513` |
| `--actv-border` | `#dbe5e1` | `#30423c` |
| `--actv-green` | `#008f5b` | `#20b878` |
| `--actv-green-dark` | `#006e46` | `#15935e` |
| `--actv-blue` | `#0759b8` | `#4c9af0` |
| `--actv-blue-dark` | `#064694` | `#77b5ff` |
| `--actv-radius` | `18px` | — |
| `--actv-max` | `1120px` (larghezza max contenuto) | — |
| `--actv-shadow` | `0 10px 28px rgba(16,35,31,.08)` | `rgba(0,0,0,.25)` |
| `--actv-shadow-sm` | `0 2px 8px rgba(16,35,31,.08)` | `rgba(0,0,0,.2)` |

L'header in app-shell usa `linear-gradient(135deg, #006e46, #008f5b)`.

### Altri colori hardcoded rilevanti

| Valore | Uso |
|---|---|
| `#9E9E9E` | marker bus "programmato" sulla live map |
| `#616161` + bordo tratteggiato `#ccc` | marker "non realtime" da API |
| `#E8F5E9` | sfondo card fermata selezionata |
| `#ef4444` / `#ef4444A0` | popup errori, bottoni pericolo |
| `#2ebd85`, `#10b981` | accenti verdi secondari (admin, badge ok) |
| `#f59e0b`, `#eab308`, `#e8b44a`, `#F4B942` | warning/gialli (ritardi, badge) |
| `#c62828`, `#FCA5A5` | rossi pieno/tenue |
| `#004d30`, `#1a1a1a` | widget tema scuro |
| `rgba(7,89,184,.4)` | outline `:focus-visible` |
| `rgba(7,91,187,.28)` | glow focus input |

Badge linea nel widget e nelle card: rosso `#E30613` (default/regionali), blu
`#0152BB` (tag `US`,`UN`,`EN`), night `#002B57` (nome che inizia con `N`).
Barre delay-stats: `#009E61` ≤5 min, `#FF9800` 5–10, `#E30613` >10.

## Tipografia

| Token | Valore |
|---|---|
| `--font-primary` | `'Inter', sans-serif` (body, titoli header) |
| `--font-secondary` | `'SF Pro', sans-serif` (sottotitoli, card, badge, bottoni) |
| monospace | per log/code (`ui-monospace, SFMono-Regular, Menlo`) |

Fallback Inter: `Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI"`.
Le icone testo usano anche Material Symbols (font `material-symbols-rounded.woff2`
in `public/css`).

Dimensioni chiave: `header-title` `clamp(24px,5vw,36px)` peso 800;
`section-title` 20px peso 750; `stop-name` 20px/700; body 16px/1.45;
`section-label`/`eyebrow` 11–12px uppercase letter-spacing .07–.08em.

## Raggi, ombre, spaziature

```css
--radius-sm: 7px; --radius-md: 12px; --radius-lg: 15px;
--radius-xl: 25px; --radius-round: 50%;
--shadow-card: 0 2px 8px rgba(0,0,0,.1);
--shadow-card-alt: 2px 0 9.7px -4px rgba(0,0,0,.24);
--spacing-xs:.5rem; --spacing-sm:1rem; --spacing-md:1.5rem; --spacing-lg:2rem;
```

In dark le stesse ombre con `rgba(255,255,255,*)`.

## Componenti strutturali

### Header verde "a onda" (`.header-green`)

Tutte le pagine condividono un header verde con **clip-path poligonale** che
taglia il bordo inferiore in diagonale:

```css
background: linear-gradient(135deg,#006e46,#008f5b); /* app-shell */
clip-path: polygon(0 0,100% 0,100% calc(100% - 38px),0 100%);
min-height: 174px; padding: 18px max(20px,calc((100vw-1120px)/2)) 58px;
```

Contiene: back button circolare semitrasparente (`rgba(255,255,255,.14)`),
titolo `.header-title`, sottotitolo `.header-subtitle` (bianco 82% opacity), e
opzionalmente il theme-toggle in alto a destra. Variante `.compact` più bassa.

### Contenuto (`.main-content` / `.main-card`)

`width: min(100% - 32px, 1120px); margin: -18px auto 0;` — il contenuto si
sovrappone all'onda verde. `.main-card` è una card bianca con ombra grande.

### Card (`.card-base`, `.stop-card`, `.selection-card`, `.route-card`, …)

Sfondo `--actv-surface`, bordo `1px solid --actv-border`, radius 15–18px,
ombra sm. `:active { transform: scale(.98) }` per feedback touch.

### Badge

| Classe | Stile |
|---|---|
| `.line-badge` | bg `--color-primary-blue`, radius 7px, testo bianco 14px/700, min-width 36px; `.large` 18px |
| `.line-badge.badge-red` | `--color-primary-red` |
| `.line-badge.badge-night` | `--color-night-blue` |
| `.line-badge.badge-walking` | grigio |
| `.id-badge-small` / `.stop-id-badge` | bg blu, radius 5px, 11px bold, min-width 45px |
| `.stop-line-badge` | badge con `route_color`/`route_text_color` inline da `/api/line-colors` |

### Bottoni

- `.btn-primary` / `.search-button` / `.btn-select` / `.home-action-btn`:
  bg `--actv-blue`, radius 12px, peso 750, min-height ~50px, full-width su mobile;
- `.btn-secondary` / `.btn-outline`: superficie chiara, bordo `--actv-border`;
- `.btn-outline-primary`: bg `--color-bg-gray`, bordo 2px `--color-primary-blue`;
- `.rounded-pill` Bootstrap per i pill button;
- `:active → transform: scale(.98)` ovunque.

### Bottom navigation (`.actv-bottom-nav`)

Barra fixed in basso (esclusa su `/admin*`, `/widget`, `?embedded=1`), iniettata
da `app-shell.js`: 4 tab `Home ⌂`, `Fermate ●`, `Percorsi ⇄`, `Altro ⋯`.
Mobile: `calc(100% - 64px)`, radius 16px solo sopra, backdrop-blur, safe-area
insets. ≥760px: floating `min(100%-180px, 860px)` centrata a 16px dal fondo.
Voce attiva in `--actv-green-dark`. Il menu "Altro" apre `.actv-more-menu`
(card con Mappa linee, Orari delle linee, Bus in servizio, Statistiche ritardi,
Invia feedback evidenziato verde, Privacy e dati).

### Modali / bottom sheet

`.modal-overlay` full-screen con backdrop; `.modal-content` con `.modal-handle`
(barretta `--color-handle-gray`) in stile iOS bottom-sheet: date picker
(calendario custom), time picker (due ruote scrollabili ora/minuti con
highlight centrale), dettagli viaggio, confronto percorsi con mappa espandibile.
Mappa modale su route-details: `.map-modal-overlay`.

### FAB e indicatori

- `.qr-fab`: cerchio 52px verde fisso in basso a destra (sopra la nav), apre il
  QR della pagina (qrcode-generator CDN);
- `#important-info-btn`: cerchio rosso "!" per avvisi sciopero, animazione
  `@keyframes scale` pop-in;
- `.offline-indicator`: barra rossa `#E30613` con dot lampeggiante quando offline;
- `.bus-icon` marker Leaflet circolari 30px con bordo bianco (16px `.mini`);
- `#non-rt-panel`: pannello laterale destro collassabile per mezzi non realtime.

### Switch e toggle

`.toggle-switch` iOS-style (input checkbox + `.toggle-slider`); checkbox
`role="switch"` nelle impostazioni mappa. `.optimize-pill` (tempo/cambi/a piedi),
`.list-tab` (Elenco/Per zona), `.direction-tab` (Andata/Ritorno),
`.service-switch-btn` (bus/vaporetto, bordo verde quando attivo).

### Stati di caricamento

- `.spinner`: cerchio 40px con bordo grigio + arco `--color-primary-green`,
  `spin 1s linear infinite`;
- `l-hatch` loader (ldrs via CDN) su trip-details;
- `.loading`/`.loading-overlay` centrati con testo descrittivo.

### Tema scuro sulle mappe

```css
html[data-theme="dark"] .leaflet-layer,
... zoom controls, attribution, fullscreen {
    filter: invert(1) hue-rotate(180deg) brightness(95%) contrast(90%);
}
```

Tiles OSM invertiti via CSS senza cambiare provider.

### Layout responsive

- mobile-first; contenuto max `1120px`, padding laterali 16–20px;
- `.home-actions`: grid 2 colonne → 1 colonna ≤430px;
- bottom nav floating su ≥760px; header più alto (190px, onda 46px);
- `@media (prefers-reduced-motion: reduce)`: animazioni quasi azzerate;
- `env(safe-area-inset-*)` rispettati su nav e FAB.

### Iconografia

- `getIcon($name,$size)` in `app/views/common/icons.php`: Material-style SVG
  inline per `settings, history, arrow_back, bus, star, chevron_right,
  notifications, share, qr_code`;
- SVG inline custom (stroke 1.8, round) nelle view per bus/vaporetto, ricerca,
  filtri, posizione, refresh, impostazioni, storico;
- file in `public/svg/`: `arrow_back, dark_mode, light_mode, directions_bus,
  expand_more, logo(-icon), refresh, search, share_location, star(_fill),
  update`.
