# 02 — Architettura backend

## Pattern generale

MVC semplificato senza framework: un front controller (`public/index.php`), un
router a tabella (`app/Router.php`), due controller (`Controller` per le pagine
HTML, `ApiController` per il JSON), view PHP "template-inline" e servizi di
dominio. Nessun autoloader: `imports.php` carica i file comuni, ogni servizio è
richiesto con `require_once` dove serve.

## Ciclo di vita della richiesta

```text
HTTP Request
   │  (Apache .htaccess / nginx try_files → /index.php)
   ▼
public/index.php
   │  1. require app/bootstrap.php
   │     - BASE_PATH, ENV (da .env), URL_PATH, COMMON_HTML_HEAD, THEME='dark'
   │     - require public/imports.php (Router, functions, controller, databaseConnector, AdminAuth)
   │     - require app/services/Logger.php
   │     - set_error_handler(Logger::phpErrorHandler)
   │     - set_exception_handler(Logger::exceptionHandler)
   │     - timezone Europe/Rome
   │  2. session_start()
   │  3. se path ∈ /api/* (non /api/admin/*) → session_write_close()
   │     (evita il lock di sessione: le chiamate API pubbliche possono andare in parallelo)
   │  4. $router = new Router(); require public/routes.php
   │  5. Redirect HTTPS: se X-Forwarded-Scheme ≠ https o HTTPS ≠ on → 301 a https://
   │  6. Dispatch: $router->dispatch($_SERVER['REQUEST_URI'])
   │     - se ACTV_PERF_DIAGNOSTICS=1 e ?perf=1 → misura e invia header
   │       Server-Timing: app;dur=<ms> e X-ACTV-Perf (JSON)
   ▼
Router::dispatch($url)
   │  - normalizza path (trim '/'), match esatto su tabella rotte
   │  - match → new $controller() → $controller->$action($params)
   │  - miss → HTTP 404 + "<pre>Pagina non trovata!</pre>"
   ▼
Controller (HTML) o ApiController (JSON)
```

### Note importanti

- **Il router fa match esatto del path**, senza placeholder: tutti i parametri
  viaggiano in query string (`$_GET`). Le "rotte" `/route` e `/routes` condividono
  la stessa azione.
- **HTTPS forzato**: `index.php` verifica `HTTP_X_FORWARDED_SCHEME`/`HTTPS` e
  reindirizza con `Location: https://<host><uri>` — pensata per stare dietro un
  reverse proxy TLS.
- **Lock sessione**: le API pubbliche chiudono subito la sessione per consentire
  fetch concorrenti dal browser; le API admin e le pagine la mantengono.
- **Perf diagnostics**: con `ACTV_PERF_DIAGNOSTICS=1` e `?perf=1` la risposta
  include `Server-Timing` e `X-ACTV-Perf`; il JS `perfDiagnostics.js` raccoglie
  metriche browser (`ACTVPerf.snapshot()`/`download()`).

## Tabella completa delle rotte

### Pagine pubbliche

| Path | Controller::azione | View | JS principale |
|---|---|---|---|
| `/` | `index` | `home.php` | `script-home.js`, `StopCard.js`, `tour.js`, `widget.js` |
| `/home-alt` | `homeAlt` | `homeAlt.php` | `script-home.js` |
| `/route-finder` | `routeFinder` | `routeFinder.php` | `routeFinder.js`, `favoriteRoutes.js` |
| `/station-selector` | `stationSelector` | `stationSelector.php` | `stationSelector.js`, `StopCard.js` |
| `/route-results` | `routeResults` | `routeResults.php` | `routeResults.js` |
| `/route-details` | `routeDetails` | `routeDetails.php` | `routeDetails.js`, `favoriteRoutes.js` |
| `/stopList` | `stops` | `stopList.php` | script inline nella view |
| `/aut/stops/stop` | `stop` | `stop.php` | `stop.js`, `notifications.js`, `widget.js`, `delayHistory.js` |
| `/lines-map` | `linesMap` | `linesMap.php` | `linesMap.js`, `mapRouteLanes.js` |
| `/live-map` | `liveMap` | `liveBusMap.php` | `liveBusMap.js` |
| `/trip-details` | `tripDetails` | `tripDetails.php` | `tripDetails.js` |
| `/widget` | `widget` | `widget.php` | script inline |
| `/delay-stats` | `delayStats` | `delayStats.php` | `delayHistory.js` + inline |
| `/routes`, `/route` | `routes` | `routes.php` | — (admin) |
| `/line-schedule` | `lineSchedule` | `lineSchedule.php` | `lineSchedule.js` |
| `/trip-finder` | `tripFinder` | `tripFinder.php` | `tripFinder.js` |
| `/delete-cookie`, `/privacy` | `deleteCookie` | `deleteCookie.php` | script inline |
| `/feedback` | `feedback` | `feedback.php` | `feedback.js` |
| `/credits` | `credits` | `credits.php` | — |

### Pagine admin

| Path | Azione | Note |
|---|---|---|
| `/admin/login` | `adminLogin` | form password + CSRF; GET/POST |
| `/admin/logout` | `adminLogout` | unset sessione → redirect login |
| `/admin/logs` | `logs` | ultimi 100 log, filtro `?type=` |
| `/admin/dashboard` | `adminDashboard` | metriche flotta via `adminDashboard.js` |
| `/admin/database-health` | `adminDatabaseHealth` | view diagnostica DB |
| `/admin/gtfs-update` | `adminGtfsUpdate` | pannello update GTFS (`gtfsUpdate.js`) |
| `/admin/gtfs-rt-inspector` | `adminGtfsRealtimeInspector` | ispezione feed RT (`gtfsRtInspector.js`) |
| `/admin/feedback` | `adminFeedback` | lista feedback filtrabile |

### API pubbliche (`/api/*`)

Tutte su `ApiController`. Dettaglio completo in [04-api.md](04-api.md):
`plan-route`, `stops`, `navigation/stops`, `navigation/lines`, `line-colors`,
`navigation/passages`, `navigation/vehicles`, `realtime/vehicles`, `stop-lines`,
`lines-shapes`, `trip-stops`, `bus-position`, `log-js-error`, `line-variants`,
`line-catalog`, `line-schedule`, `line-trips`, `stop-upcoming`, `delete-cookie`,
`feedback`, `gtfs-identify`, `gtfs-builder`, `gtfs-stop-translater`,
`gtfs-resolve`, `gtfs-bnr`, `gtfs-stops`, `gtfs-passages`.

### API admin (`/api/admin/*`)

`gtfs-update/status` (GET), `database-health` (GET), `gtfs-update/config` (POST+CSRF),
`gtfs-update/start` (POST+CSRF), `gtfs-rt-inspector` (GET). Richiedono
`AdminAuth::check()` → `401` JSON se non autenticati.

## I due controller

### `Controller` (pagine)

Azioni quasi sempre *thin*: includono la view corrispondente. Eccezioni:

- `stops()` — scarica il catalogo fermate realtime ACTV via cURL (`fetchData`,
  timeout 0.5 s) e lo passa alla view `stopList.php`;
- `index()` — abilita logging errori verboso su `php_error.log`;
- `adminLogin()` — gestisce POST password con `AdminAuth`;
- `logs()`, `adminFeedback()` — query su `logs`/`feedback` via `databaseConnector`;
- tutte le azioni admin chiamano `AdminAuth::requireAuth()` → redirect login.

### `ApiController` (JSON)

~25 endpoint pubblici + 5 admin. Helper interni:

- `getDb($joins)` — connessione lazy al DB GTFS;
- `requireAdminJson()` — `401 {success:false}` se non admin;
- `adminJsonInput()` — parse body JSON/form + verifica CSRF → `403` se invalido;
- `parseLatLon($v)` — valida `lat,lon` con range check;
- `mergeNavigationStops()`, `filterStopsByParam()` — merge/normalizzazione fermate;
- `dayColumn()`, `lineService()`, `lineDate()` — normalizzazione giorno/servizio/data.

## Gestione errori centralizzata

`app/services/Logger.php` è il gestore globale:

- `phpErrorHandler` → logga `PHP_ERROR` (rispettando `error_reporting`), poi
  restituisce `false` per lasciare al PHP il comportamento standard;
- `exceptionHandler` → logga `EXCEPTION` con stack trace;
- `Logger::log(type, message, file, line, stackTrace, context)` scrive sulla
  tabella `logs`; in caso di fallimento DB fa fallback a `error_log()` (evita
  loop ricorsivi dato che è anche l'error handler);
- gli errori JS arrivano via `POST /api/log-js-error` → `JS_ERROR`.

## Configurazione `.env`

| Variabile | Uso |
|---|---|
| `DB_HOST`, `DB_PORT` | host/porta MySQL (default porta 3306) |
| `DB_USER`, `DB_PASS`, `DB_NAME` | credenziali/database |
| `URL_PATH` | base URL pubblico usato nei link/asset (`https://actv-live.test`) |
| `ADMIN_PASSWORD` | password unica area admin (confronto `hash_equals`) |
| `GTFS_URLS` | URL candidati del feed automobilistico, separati da virgola; scelto quello con `Last-Modified` più recente |
| `GTFS_PHP_CLI` | binario PHP CLI per gli update in background |
| `ACTV_PERF_DIAGNOSTICS` | `1` abilita header diagnostici con `?perf=1` |

## Componenti di servizio (ruoli)

| Classe | Ruolo |
|---|---|
| `AdminAuth` | gate `/admin/*`: session flag `is_admin`, CSRF login, `hash_equals`, `session_regenerate_id` |
| `databaseConnector` | PDO singleton: `connect`, `query` (prepared, emulate off, fetch assoc), `close`, `getJoins` |
| `ResponseCache` | cache file delle risposte API con TTL (`remember(key, ttl, fn)`) |
| `RoutePlanner` | compatibilità + `findNearestStop(lat,lon)` (Haversine, walking a 5 km/h ≈ 83 m/min) |
| `ConnectionScanPlanner` | planner di produzione: scan connessioni per data, multi-cambio, multimodale |
| `ConnectionCacheBuilder` | genera `planner/YYYYMMDD.connections.tsv` (bus+navigazione, file lock) |
| `GTFSParser` | download zip GTFS → `cache/*.json` (stops, routes, trips, stop_times per route, indice) |
| `GtfsUpdateManager` | orchestrazione completa: download → extract → cache → staging DB → data_url → shapes_refined → validazione → `RENAME TABLE` atomico → navigazione → planner cache; lock file; stato in `data/gtfs-update/state.json`; gestione crontab |
| `GtfsRealtime` | decoder protobuf minimale per vehiclePositions/tripUpdates, cache file 120 s, `inspect()` per l'admin |
| `LineScheduleProvider` | dati per `/api/line-schedule`: matrice fermate×corse, raggruppamento orari identici, descrizione `calendar` |
| `DatabaseHealthService` | check connessione, tabelle richieste, conteggi/dimensioni da `information_schema` |
| `RoadSnapper` | snapping delle shape GTFS alla rete stradale (script `refine_shapes.php`) |
