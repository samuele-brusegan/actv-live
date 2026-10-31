# 07 — Frontend: JavaScript, storage e PWA

Vanilla JS senza build. Ogni pagina include il `<head>` condiviso
(`commons/head.php`: Bootstrap, Popper, Axios, icone, CSS globali, PWA, script
comuni) più i moduli della feature.

## Script globali (inclusi in `commons/head.php`)

| File | Ruolo |
|---|---|
| `cookie-notice.js` | classe `CookieNotice`: banner consenso; accettare → `localStorage.cookie_consent_accepted='true'` + `window.actvLoadAnalytics()` (carica gtag G-56Z18G0ZJT solo dopo consenso) |
| `theme.js` | `toggleTheme()`/`updateTheme()`; `localStorage.theme` chiaro/scuro, fallback `prefers-color-scheme`, aggiorna `data-theme`, `data-bs-theme` e l'icona `#theme-icon` (`light_mode.svg`/`dark_mode.svg`) |
| `offline.js` | registra `/sw.js`, mostra `.offline-indicator` su `offline`/`online`, comandi SW (precache GTFS, clear cache) |
| `ui-feedback.js` | `actvConfirm(message,title)` — modale di conferma usata per azioni distruttive; popup errori `.error-container` |
| `app-shell.js` | inietta bottom nav + menu "Altro" (saltato su `/admin*`, `/widget`, `?embedded=1`) |
| `perfDiagnostics.js` | attivo solo con `ACTV_PERF_DIAGNOSTICS`: fetch timing, long task, frame lenti, `ACTVPerf.snapshot()/download()` |

## Moduli per pagina

| File | Pagina | Cosa fa |
|---|---|---|
| `script-home.js` | `/`, `/home-alt` | render preferiti (`favorite_stops`→`StopCard`), fetch fermate realtime ACTV, mappa Leaflet con marker delle fermate (raggio per zoom), geolocalizzazione (`map.locate`), 5 fermate vicine via Haversine, banner stato, **strike check** (`checkImportantNotices`: campiona 15 fermate, confronta passaggi realtime vs schedulati → FAB "!" + modal `strike-card`), resume ultimo viaggio (`route_origin`/`route_destination`), easter egg: 7 click su `#footer-licence` → `/route`, `home_tutorial_seen` |
| `stop.js` | `/aut/stops/stop` | tabellone fermata: fetch passages realtime ACTV (fallback `/api/gtfs-passages`, `/api/navigation/passages` per navigazione), merge dedup realtime+schedule (tolleranza ±10 min), rilevamento sciopero (`isLikelyStrike`+cross-check), bacheca avvisi (`/aut/backend/page/{id}`) con icone per tipo, filtri per linea/destinazione (sessionStorage `stop_filter`), preferiti toggle, pannello notifiche, associazione veicoli realtime ai passaggi + progresso su shape, click passaggio → salva contesto in sessionStorage e apre `/trip-details` |
| `routeFinder.js` | `/route-finder` | `searchState` (origin, destination, date, time, optimize, returnTrip, returnHour/Minute), date-modal calendario, time-modal ruote, toggle ritorno, swap ⇅, apertura `/station-selector` in iframe panel (`station-picker-panel`), salvataggio chiavi `route_*` in localStorage |
| `stationSelector.js` | `/station-selector` | carica `/api/stops` + `/api/navigation/stops` (+ fallback backend ACTV), sezioni Recenti/Preferite/Tutte, ricerca fuzzy (normalizza accenti, token `santa/san→s`, Levenshtein con soglia), geocoding Nominatim (`viewbox` Venezia, `bounded=1`, limit 5), selezione → `localStorage.route_origin|route_destination`, supporto `?embedded=1` (iframe) |
| `routeResults.js` | `/route-results` | legge `route_*`, chiama `/api/plan-route` (cache risultati in `ROUTE_RESULTS_CACHE_KEY`), render card percorso con timeline + badge linea, tab Andata/Ritorno (due chiamate plan-route), modal dettagli, **compare mode** (selezione multipla + mappa confronto), enrich realtime (vehicle position per leg trip_id), `selected_route` → `/route-details` |
| `routeDetails.js` | `/route-details` | timeline completa delle legs, orari, durata, tragitto a piedi, "parti da qui" se vicino, mappa modale con polyline/shape dei legs, `favoriteRoutes` (salva tragitto) |
| `liveBusMap.js` | `/live-map` | mappa flotta realtime: `MAX_CONCURRENT=6` fetch paralleli, `REFRESH_INTERVAL=60000`, palette `BUS_COLORS` (14 colori deterministici per linea), service switch bus/vaporetto, filtro testuale su `route_short_name`/`tripId`/`routeId` con pannello impostazioni (campi attivi, mostra N/D), ricerche recenti, marker div-icon circolari per stato (`realtime`/`scheduled`/`scheduled-api`), posizione interpolata su shape raffinata, pannello dettaglio mezzo, pannello N/D collassabile, contatore + last-update |
| `linesMap.js` | `/lines-map` | disegna `/api/lines-shapes` (tutte le linee o filtro per linea/tripIds/tripGroups), service switch, pannello filtri, toggle fermate, colore deterministico per linea |
| `mapRouteLanes.js` | condiviso mappe | offset laterale delle polilinee sovrapposte (lane offsetting) |
| `tripDetails.js` | `/trip-details` | contesto da URL (`tripId`, `stopId`, `line`, `dest`, `time`) + sessionStorage, fetch `/api/gtfs-builder` + `/api/trip-stops`, timeline fermate con stato (passata/prossima/da raggiungere), countdown + ritardo, mappa dialog fullscreen con posizione veicolo realtime (`/api/realtime/vehicles?tripId=`), mezzi vicini (`nearby`), lista fermate espandibile con legenda |
| `tripFinder.js` | `/trip-finder` | wizard linea→data→partenza→destinazione→corsa via `/api/line-catalog`, `/api/line-variants`, `/api/line-trips`; paginazione 10/pagina + jump a pagina/orario; badge "In movimento secondo orario" (±30 min); stato in `sessionStorage['actvLive.tripFinder']` |
| `lineSchedule.js` | `/line-schedule` | service switch, ricerca linea, varianti → matrice fermate×orari via `line-schedule` API con `all=1`, legenda giorni/validità, link mappa su corsa rappresentativa |
| `adminDashboard.js` | `/admin/dashboard` | metriche flotta: bus attivi, ritardo medio/max, copertura GPS, distribuzione per linea (da API realtime) |
| `gtfsUpdate.js` | `/admin/gtfs-update` | polling `status`, POST config/start con CSRF, task list e log tail |
| `gtfsRtInspector.js` | `/admin/gtfs-rt-inspector` | selettore servizio/feed, hex dump + record decodificati |
| `databaseHealth.js` | `/admin/database-health` | check connessione, tabelle, conteggi |
| `feedback.js` | `/feedback` | submit `/api/feedback`, validazioni client, honeypot |
| `notifications.js` | cross | monitor ritardi locali: `CHECK_INTERVAL=60000`, Notification API + SW, soglie 3/5/10/15 min |
| `delayHistory.js` | cross | `delay_history` (max `MAX_HISTORY_ENTRIES=500`): `getOverallStats`, `getStatsByLine`, `getStatsByHour` |
| `favoriteRoutes.js` | route-* | tragitti salvati: `favorite_routes` in localStorage |
| `tour.js` | cross | `ACTVTour.start()` tour guidato al primo avvio |
| `widget.js` | cross | `getWidgetUrl(stopId,name,{max,theme})`, modal con iframe embed + copia URL/codice |
| `offline.js` | cross | registrazione SW e stato rete |
| `utils.js` | condiviso | `timeToSec`, `secToTime`, `getDist` (Haversine), `parseRealTime` (`HH:MM`, `MM'`, `MM min`), `getDeterministicColor`, `filterBuses`, `findNextStopWithDelay` |
| `StopCard.js`, `StopListItem.js` | `components/` | builder HTML delle card fermata (home, selector, liste) |

## Storage keys

### `localStorage` (persistente per dispositivo)

| Chiave | Contenuto |
|---|---|
| `theme` | `light`/`dark` |
| `cookie_consent_accepted` | consenso banner |
| `favorite_stops` | array JSON fermate preferite |
| `recent_stops` | ultime fermate selezionate nel picker |
| `favorite_routes` | tragitti salvati |
| `route_origin`, `route_destination` | oggetto `{type:'stop'|'address',id|lat,lng,name,service}` |
| `route_departure_date`, `route_departure_time`, `route_return_time` | orari viaggio |
| `route_optimize`, `route_return_trip`, `route_last_used_at`, `route_selection_mode` | stato ricerca |
| `route_results_cache` | cache risposte plan-route |
| `selected_route` | itinerario scelto per `/route-details` |
| `notifications_enabled`, `notification_delay_threshold`, `monitored_stops` | monitor ritardi |
| `delay_history` | osservazioni ritardo (≤500) |
| `home_tutorial_seen` | tour già mostrato |
| `actv-*-service` | servizio selezionato nelle mappe (bus/navigation) |

### `sessionStorage` (per scheda)

`url` (URL_PATH), `theme`, `busTrack`, `timedStop`, `realTime`,
`contextIsRealtime`, `lastStop`, `lineId`, `tripDetails_selectedStop`,
`tripDetails_url`, `board_collapsed`, `stop_filter`, `actvLive.tripFinder`.

## PWA

- **manifest** `pwa/site.webmanifest`: `name`/`short_name` "Actv Live",
  `start_url`/`scope` `/`, `display: standalone`,
  `theme_color`/`background_color` `#009E61`, icone maskable 192/512;
- **Service Worker** `sw.js` (`CACHE_VERSION 'v2.9-vehicle-positions'`):
  - `actv-static-*`: precache ~45 asset (CSS, JS, icone, manifest);
  - `actv-api-*`: network-first per `/api/stops`, `/api/stop-lines`,
    `/api/plan-route`, `/api/gtfs-stops`, `/api/navigation/*`, `/api/line-colors`;
  - stessa strategia per `oraritemporeale.actv.it`;
  - cleanup cache vecchie in `activate`, `skipWaiting`, `clients.claim`;
  - messaggi client per precache GTFS e pulizia cache;
- **offline**: barra rossa `.offline-indicator` quando la rete cade; le pagine
  cachate restano consultabili.

## Sicurezza lato client

- escape HTML nelle stringhe utente (`escapeStopListHtml` e simili);
- honeypot `website` nel form feedback;
- `JSON.parse` difensivi con fallback a `[]`/`{}` in tutti i reader di storage;
- nessun dato sensibile in storage (solo preferenze e cache pubblica).
