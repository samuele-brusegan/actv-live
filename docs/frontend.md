# Frontend e UI

Il frontend di ACTV Live è JavaScript vanilla, ottimizzato per mobile e
distribuito senza una fase di build. Le pagine PHP includono gli asset comuni e
caricano il modulo della feature in uso.

## Design system (`style.css` e `app-shell.css`)

L’aspetto visivo usa variabili CSS (`:root`) per colori, spaziature, tipografia,
ombre e raggi dei bordi. `app-shell.css` aggiunge le variabili della shell
responsiva e della navigazione inferiore.

### Variabili Principali
- `--color-primary-green`: #009E61 (Colore brand ACTV).
- `--color-primary-blue`: #0152BB (Colore secondario).
- `--color-primary-green`: `#009E61`.
- `--color-primary-blue`: `#0152BB`.
- `--radius-lg`: `15px` (smussamento angoli per le card).

Il tema scuro viene attivato aggiungendo `data-theme="dark"` all'elemento `html`.

---

## Moduli JavaScript

L'applicazione non usa framework pesanti (come React o Vue), ma moduli Vanilla JS organizzati per responsabilità:

- **`js/theme.js`**: alterna tema chiaro/scuro e salva la preferenza in `localStorage`.
- **`js/offline.js`**: registra `/sw.js`, gestisce l’indicatore online/offline e
  invia i comandi di precaricamento o pulizia cache.
- **`js/cookie-notice.js`** e **`js/ui-feedback.js`**: componenti globali della
  shell.
- **`js/liveBusMap.js`**: mappa realtime e caricamento progressivo di mezzi e shape.
- **`js/tripFinder.js`**: selezione linea, capolinea, corsa e stato in
  `sessionStorage`.

---

## Integrazione con Leaflet

Le mappe utilizzano Leaflet.js caricato via CDN per ridurre il peso del pacchetto.
- **Tiles**: Utilizza OpenStreetMap.
- **Inversione Colori**: In modalità dark, viene applicato un filtro CSS `filter: invert(1) hue-rotate(180deg)` ai tile della mappa per renderla scura senza cambiare provider.

---

## Progressive Web App (PWA) e offline

I file necessari sono in `/public/pwa/` e `/public/sw.js`:
- **`pwa/site.webmanifest`**: definisce icone, colori e nome dell’app;
- **`sw.js`**: service worker che gestisce cache statiche e comandi offline;
- **`offline.js`**: registra il service worker e controlla lo stato di rete.

Il precaricamento GTFS è disponibile tramite `precacheGtfsData()` e deve essere
richiesto dal client; non implica che ogni endpoint dinamico sia utilizzabile
offline.

---

## Componenti UI Ricorrenti

### Header Verde
Implementato con un `clip-path` poligonale per creare l'effetto "onda" caratteristico in alto.
```css
clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
```

### Card delle Fermate
Utilizzano Flexbox per allineare l'orario, l'ID della fermata e il badge della linea.
