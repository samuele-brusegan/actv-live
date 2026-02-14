# Frontend & UI System

Il frontend di ACTV Live è progettato per essere leggero, veloce e ottimizzato per mobile (PWA).

## Design System (`style.css`)

L'intero aspetto visiva è basato su variabili CSS (`:root`) per permettere una facile manutenzione e il supporto al **Tema Scuro**.

### Variabili Principali
- `--color-primary-green`: #009E61 (Colore brand ACTV).
- `--color-primary-blue`: #0152BB (Colore secondario).
- `--radius-lg`: 15px (Smussamento angoli per le card).

Il tema scuro viene attivato aggiungendo `data-theme="dark"` all'elemento `html`.

---

## Moduli JavaScript

L'applicazione non usa framework pesanti (come React o Vue), ma moduli Vanilla JS organizzati per responsabilità:

- **`js/theme.js`**: Gestisce il toggle tra tema light e dark e salva la preferenza nel `localStorage`.
- **`js/time-machine.js`**: Gestisce lo stato globale della riproduzione simulata. Quando attivo, intercetta tutte le chiamate API dirette al server reale.
- **`js/cookie-notice.js`**: Gestisce l'informativa sui cookie.
- **`js/liveBusMap.js`**: Logica di visualizzazione geografica (vedi [Live Tracker](live-tracker.md)).

---

## Integrazione con Leaflet

Le mappe utilizzano Leaflet.js caricato via CDN per ridurre il peso del pacchetto.
- **Tiles**: Utilizza OpenStreetMap.
- **Inversione Colori**: In modalità dark, viene applicato un filtro CSS `filter: invert(1) hue-rotate(180deg)` ai tile della mappa per renderla scura senza cambiare provider.

---

## Progressive Web App (PWA)

I file necessari sono in `/public/pwa/`:
- **`site.webmanifest`**: Definisce icone, colori e nome dell'app per l'installazione su home screen.
- **`service-worker.js`**: (Se implementato) gestisce il caching offline delle risorse statiche.

---

## Componenti UI Ricorrenti

### Header Verde
Implementato con un `clip-path` poligonale per creare l'effetto "onda" caratteristico in alto.
```css
clip-path: polygon(0 0, 100% 0, 100% 75%, 0 100%);
```

### Card delle Fermate
Utilizzano Flexbox per allineare l'orario, l'ID della fermata e il badge della linea.
