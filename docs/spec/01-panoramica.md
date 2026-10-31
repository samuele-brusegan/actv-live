# 01 — Panoramica dell'applicazione

## Cos'è ACTV Live

**ACTV Live** è una web application (PWA) per consultare il trasporto pubblico
ACTV nell'area di Venezia (autobus di terraferma e mezzi di navigazione/vaporetti).
È un progetto **non ufficiale**, non affiliato ad ACTV S.p.A., distribuito con
licenza MIT.

Combina tre tipi di dati:

1. **GTFS statici** — orari, linee, fermate, corse e shape geografiche, sia del
   servizio *automobilistico* (bus) sia del servizio *navigation* (vaporetti);
2. **GTFS-RT** — posizioni dei veicoli e trip updates in tempo reale, decodificati
   lato server da un parser protobuf scritto in PHP;
3. **Backend realtime ACTV** (`oraritemporeale.actv.it`) — passaggi in tempo reale
   per fermata, interrogati direttamente dal browser.

## Funzionalità principali (elenco completo)

| # | Funzionalità | Pagina | Dettaglio |
|---|---|---|---|
| 1 | Home con fermate vicine, preferite e mappa | `/` | geolocalizzazione, preferiti localStorage, rilevamento scioperi |
| 2 | Home alternativa | `/home-alt` | seconda variante grafica della landing |
| 3 | Elenco fermate | `/stopList` | lista completa, ricerca fuzzy, vista per zone |
| 4 | Dettaglio fermata | `/aut/stops/stop` | passaggi realtime + fallback GTFS, preferiti, notifiche ritardi, widget |
| 5 | Route finder | `/route-finder` | pianificazione viaggio multi-step |
| 6 | Selettore fermata | `/station-selector` | scelta fermata o indirizzo (geocoding Nominatim) |
| 7 | Risultati percorso | `/route-results` | itinerari multimodali, confronto, andata/ritorno |
| 8 | Dettagli viaggio | `/route-details` | timeline fermata per fermata, mappa, salvataggio tragitto |
| 9 | Mappa delle linee | `/lines-map` | tutte le shape di bus e vaporetti su mappa |
| 10 | Mappa mezzi live | `/live-map` | posizioni GTFS-RT in tempo reale, filtri, auto-refresh |
| 11 | Dettaglio corsa | `/trip-details` | fermate di un trip con stato avanzamento e mappa live |
| 12 | Trip finder | `/trip-finder` | cerca una corsa per linea/capolinea/data |
| 13 | Orari delle linee | `/line-schedule` | matrice fermate×corse con validità calendario |
| 14 | Widget incorporabile | `/widget` | tabellino fermata embeddabile via iframe |
| 15 | Statistiche ritardi | `/delay-stats` | analytics sui ritardi osservati localmente |
| 16 | Feedback | `/feedback` | form pubblico di segnalazione |
| 17 | Crediti | `/credits` | attribuzioni e licenze |
| 18 | Privacy | `/privacy` (= `/delete-cookie`) | gestione dati locali e cookie |
| 19 | Area admin | `/admin/*` | login, dashboard, log, feedback, GTFS update, GTFS-RT inspector, database health |
| 20 | Elenco rotte (admin) | `/route` | route registrate (easter egg: 7 click sul footer) |
| 21 | Identificazione GTFS | `/api/gtfs-identify` | pagina diagnostica + API |
| 22 | PWA | — | installabile, service worker, offline indicator |
| 23 | Tema chiaro/scuro | — | toggle persistente, rispetta `prefers-color-scheme` |
| 24 | Tour guidato | — | primo avvio, `ACTVTour` |
| 25 | QR share | — | FAB per mostrare il QR della pagina corrente |
| 26 | Cookie consent | — | banner GDPR che attiva Google Analytics solo dopo consenso |

## Stack tecnologico

| Livello | Tecnologia | Versione/note |
|---|---|---|
| Backend | PHP puro, nessun framework | 8.4+ |
| Router | custom `app/Router.php` | URL esatti → `Controller::azione` |
| Frontend | Vanilla JavaScript | nessun build step, nessun framework |
| CSS | custom + Bootstrap 5.3 via CDN | variabili CSS per i temi |
| Mappe | Leaflet via CDN + OpenStreetMap tiles | plugin Leaflet.fullscreen |
| DB | MySQL 8 / MariaDB | PDO, `databaseConnector` singleton |
| Librerie CDN | Bootstrap 5.3.3/5.3.6, Popper 2.11.8, Axios, qrcode-generator 1.4.4, ldrs | caricate in `commons/head.php` e nelle view |
| Geocoding | Nominatim (OpenStreetMap) | chiamata dal browser |
| Test PHP | Pest 4 | `tests/Unit`, `tests/Feature` |
| Test JS | Jest 30 + jsdom | `tests/jest` |
| CI | GitHub Actions | `.github/workflows/test.yml` |

## Struttura del repository

```text
actv-live/
├── app/
│   ├── bootstrap.php          # costanti, .env, error handler
│   ├── Router.php             # router URL→controller
│   ├── controllers/
│   │   ├── Controller.php     # azioni pagine (includono le view)
│   │   └── ApiController.php  # endpoint JSON /api/*
│   ├── models/
│   │   ├── databaseConnector.php   # PDO singleton
│   │   ├── api_template.php        # template legacy per endpoint a tabella
│   │   ├── gtfsReader.php          # lettura file GTFS txt
│   │   ├── gtfsStops.php           # model fermate
│   │   ├── gtfsPassages.php        # passaggi programmati per fermata
│   │   ├── gtfsBusesNowRunning.php # corse attive in una finestra oraria
│   │   └── gtfsTripResolver.php    # risoluzione trip_id da dati ACTV
│   ├── services/
│   │   ├── AdminAuth.php              # auth sessione /admin + CSRF
│   │   ├── Logger.php                 # log PHP/JS/EXCEPTION su tabella `logs`
│   │   ├── GTFSParser.php             # download feed → cache JSON
│   │   ├── GtfsUpdateManager.php      # aggiornamento atomico completo (feed+DB+cache+cron)
│   │   ├── GtfsRealtime.php           # decoder protobuf GTFS-RT
│   │   ├── RoutePlanner.php           # planner compatibilità + nearest stop
│   │   ├── ConnectionCacheBuilder.php # cache connessioni per data
│   │   ├── ConnectionScanPlanner.php  # planner di produzione (Connection Scan)
│   │   ├── LineScheduleProvider.php   # matrice orari per linea
│   │   ├── RoadSnapper.php            # affinamento shape su grafo stradale
│   │   ├── ResponseCache.php          # cache file delle risposte API
│   │   └── DatabaseHealthService.php  # diagnostica DB
│   └── views/                  # template PHP delle pagine
│       ├── common/icons.php    # helper SVG getIcon()
│       └── admin/              # viste area amministrativa
├── data/
│   ├── gtfs/                   # feed estratto + cache JSON + planner
│   ├── gtfs-update/            # stato e workspace del job di update
│   └── gtfs/navigation/        # feed e cache del servizio navigazione
├── public/                     # DOCUMENT ROOT
│   ├── index.php               # entry point
│   ├── routes.php              # tabella delle rotte
│   ├── functions.php           # helper globali
│   ├── imports.php             # require comuni
│   ├── commons/head.php        # <head> condiviso
│   ├── .htaccess               # rewrite Apache → index.php
│   ├── sw.js                   # service worker PWA
│   ├── css/                    # ~25 fogli di stile (+ structure/)
│   ├── js/                     # ~25 moduli JS
│   ├── components/             # StopCard.js, StopListItem.js
│   ├── svg/                    # icone SVG statiche
│   └── pwa/                    # manifest e icone
├── scripts/                    # utilità CLI GTFS/DB
├── tests/                      # Pest + Jest
├── docs/                       # documentazione (questa spec in docs/spec/)
├── composer.json               # Pest, var-dumper (solo dev)
├── package.json                # Jest (solo dev)
├── phpunit.xml / jest.config.js
├── .env / .env.example         # configurazione ambiente
└── .github/workflows/test.yml  # CI
```

## Installazione e avvio

```bash
cp .env.example .env      # compilare le variabili
composer install          # dipendenze PHP (dev)
npm ci                    # dipendenze JS (dev)
vendor/bin/pest           # test PHP
npm test                  # test JS
```

### Requisiti runtime

- PHP ≥ 8.4 con estensioni `pdo_mysql`, `curl`, `zip` (o comando `unzip`), `mbstring`;
- MySQL 8 / MariaDB con lo schema GTFS importato (`scripts/setup_db.php` +
  `scripts/update_gtfs.php`);
- web server Apache (`.htaccess` con `RewriteRule → index.php`) o nginx
  (`try_files → /index.php`), con TLS: l'app **reindirizza sempre a HTTPS**;
- comando `crontab` se si usa la pianificazione settimanale degli update GTFS;
- PHP CLI configurabile via `GTFS_PHP_CLI` per gli update in background.

### Sviluppo con server PHP integrato

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Il server integrato non fa TLS; l'app redirige a HTTPS, quindi in produzione/dev
serve un reverse proxy TLS (l'installazione di riferimento usa nginx +
nginx-proxy-manager, vedi [10-deploy-test.md](10-deploy-test.md)).

## Sorgenti dati esterne

| Sorgente | Uso | Chiamata da |
|---|---|---|
| `actv.avmspa.it/.../actv_aut.zip` | GTFS statico automobilistico | server (update pipeline) |
| `actv.avmspa.it` feed navigazione | GTFS statico navigation | server |
| `tpl.actv.it/aut/GTFSRT/vehiclePositions` | posizioni bus GTFS-RT | server |
| `tpl.actv.it/aut/GTFSRT/tripUpdates` | trip updates bus | server |
| `tpl.actv.it/nav/GTFSRT/*` | stessi feed per navigazione | server |
| `oraritemporeale.actv.it/aut/backend/page/stops` | catalogo fermate realtime | browser + server |
| `oraritemporeale.actv.it/aut/backend/passages/{data_url}` | passaggi realtime per fermata | browser |
| `oraritemporeale.actv.it/aut/backend/page/{data_url}` | bacheca avvisi fermata | browser |
| `nominatim.openstreetmap.org` | geocoding indirizzi | browser |
| `unpkg.com/leaflet`, tile OSM | mappe | browser |
| `googletagmanager.com` | GA (solo dopo consenso) | browser |
