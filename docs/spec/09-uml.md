# 09 — Diagrammi UML

Tutti i diagrammi sono in sintassi Mermaid (renderizzabili su GitHub, VS Code
con estensione Mermaid, mermaid.live).

## 9.1 Diagramma delle classi (backend)

```mermaid
classDiagram
    class Router {
        -array routes
        +add(url, controller, action, params)
        +dispatch(url)
        +list() array
    }

    class Controller {
        +index()
        +stops()
        +stop()
        +routeFinder()
        +stationSelector()
        +routeResults()
        +routeDetails()
        +linesMap()
        +liveMap()
        +tripDetails()
        +widget()
        +delayStats()
        +routes()
        +lineSchedule()
        +tripFinder()
        +deleteCookie()
        +feedback()
        +credits()
        +homeAlt()
        +adminLogin()
        +adminLogout()
        +logs()
        +adminDashboard()
        +adminDatabaseHealth()
        +adminGtfsUpdate()
        +adminGtfsRealtimeInspector()
        +adminFeedback()
        -fetchData(url, timeout) array
    }

    class ApiController {
        -getDb(joins) databaseConnector
        -requireAdminJson() bool
        -adminJsonInput() ?array
        -parseLatLon(value) ?array
        -mergeNavigationStops(stops) array
        -filterStopsByParam(stops) array
        -navigationPlanner() RoutePlanner
        -enrichRealtimeVehicleTrips(vehicles, service) array
        +planRoute()
        +stops()
        +stopLines()
        +stopUpcoming()
        +navigationStops()
        +navigationLines()
        +navigationPassages()
        +navigationVehicles()
        +realtimeVehicles()
        +lineColors()
        +linesShapes()
        +tripStops()
        +busPosition()
        +lineCatalog()
        +lineVariants()
        +lineSchedule()
        +lineTrips()
        +gtfsBusesRunningNow()
        +gtfsStops()
        +gtfsPassages()
        +gtfsResolve()
        +gtfsTripBuilder()
        +gtfsStopTranslater()
        +api_gtfsIdentify()
        +logJsError()
        +feedback()
        +deleteCookie()
        +adminGtfsUpdateStatus()
        +adminGtfsUpdateConfig()
        +adminGtfsUpdateStart()
        +adminDatabaseHealth()
        +adminGtfsRealtimeInspect()
    }

    class databaseConnector {
        -PDO db
        -string tableJoins
        -static databaseConnector instance$
        +getInstance()$ databaseConnector
        +connect(user, pass, host, name, joins) bool
        +query(query, params) array
        +close()
        +getJoins() string
    }

    class AdminAuth {
        -ensureSession()$
        +check()$ bool
        +attempt(password)$ bool
        +requireAuth()$
        +logout()$
        +csrfToken()$ string
        +verifyCsrf(token)$ bool
    }

    class Logger {
        +log(type, message, file, line, stackTrace, context)$
        +phpErrorHandler(errno, errstr, errfile, errline)$
        +exceptionHandler(exception)$
    }

    class RoutePlanner {
        -string cacheDir
        -array stops
        -array routes
        -array stopRoutesIndex
        +__construct(profile)
        +findRoutes(origin, dest, time) array
        +findNearestStop(lat, lon) ?array
        +getLinesForStop(stopId, time) array
        +debugStop(id) array
        +getCacheStats() array
        -calculateGeoDistance(lat1, lon1, lat2, lon2) float
    }

    class ConnectionScanPlanner {
        +plan(origin, dest, date, time) ?array
        +planAlternatives(origin, dest, date, time) array
    }

    class ConnectionCacheBuilder {
        -string plannerDir
        -array profiles
        +pathForDate(date) string
        +ensure(date) string
        -build(date, path)
        -activeServices(dataDir, date) array
        -isFresh(path) bool
    }

    class GTFSParser {
        -string gtfsUrl
        -string dataDir
        -string cacheDir
        +downloadGTFS()
        +parseAll()
        +parseStops() array
        +parseRoutes() array
        +parseTrips() array
        +parseStopTimes()
        +isCacheValid(maxAge) bool
    }

    class GtfsUpdateManager {
        +getOverview() array
        +saveConfig(data) array
        +start(trigger) array
        -runJob(workspace)
        -syncCron(config)
    }

    class GtfsRealtime {
        +read(kind, service)$ array
        +decode(kind, raw, service)$ array
        +inspect(kind, service)$ array
        +freshVehicles(vehicles, now)$ array
        -download(url)$ string
        -vehicles(raw, service)$ array
        -updates(raw, service)$ array
    }

    class LineScheduleProvider {
        +variants(line, day, date) array
        +schedule(line, tripIds, day, date) array
    }

    class ResponseCache {
        +remember(key, ttl, producer)$ mixed
    }

    class DatabaseHealthService {
        +inspect() array
    }

    class RoadSnapper
    class gtfsReader
    class gtfsStops
    class gtfsPassages
    class gtfsBusesNowRunning
    class gtfsTripResolver

    Router ..> Controller : instanzia
    Router ..> ApiController : instanzia
    Controller ..> AdminAuth : gate /admin
    Controller ..> databaseConnector : logs, feedback
    Controller ..> Logger : errori cURL
    ApiController ..> databaseConnector : query GTFS
    ApiController ..> AdminAuth : /api/admin
    ApiController ..> RoutePlanner : nearest stop, compat
    ApiController ..> ConnectionScanPlanner : plan-route
    ApiController ..> GtfsRealtime : realtime/inspector
    ApiController ..> GtfsUpdateManager : status/config/start
    ApiController ..> DatabaseHealthService : health
    ApiController ..> LineScheduleProvider : line-schedule
    ApiController ..> ResponseCache : cache risposte
    ApiController ..> Logger : errori
    ConnectionScanPlanner ..> ConnectionCacheBuilder : ensure(date)
    GtfsUpdateManager ..> GTFSParser : rigenera cache
    RoutePlanner ..> GTFSParser : legge cache prodotta da
    ApiController ..> gtfsPassages : include model
    ApiController ..> gtfsBusesNowRunning : include model
    ApiController ..> gtfsTripResolver : include model
    Logger ..> databaseConnector : scrittura logs
```

## 9.2 Diagramma dei componenti / deployment

```mermaid
flowchart TB
    subgraph Browser["Browser (client)"]
        UI["Pagine HTML + CSS"]
        JS["Moduli JS vanilla"]
        SW["Service Worker /sw.js<br/>(static/api cache)"]
        LS["localStorage / sessionStorage"]
    end

    subgraph Edge["TLS edge"]
        NPM["nginx-proxy-manager :443<br/>actv-live.test"]
        NGINX["nginx :80<br/>try_files → index.php"]
    end

    subgraph PHP["PHP-FPM (actv-live)"]
        IDX["public/index.php"]
        RTR["Router + routes.php"]
        CTL["Controller → app/views/*"]
        API["ApiController"]
        SVC["services/<br/>AdminAuth, Logger, GtfsRealtime,<br/>RoutePlanner, ConnectionScanPlanner,<br/>GtfsUpdateManager, LineScheduleProvider"]
    end

    subgraph Data["Dati"]
        DB[("MySQL actv<br/>GTFS tables + logs + feedback")]
        CACHE[("data/gtfs/cache/*<br/>JSON + planner TSV")]
        RTCACHE[("data/gtfs/realtime/*")]
        STATE[("data/gtfs-update/*")]
    end

    subgraph Ext["Sorgenti esterne"]
        GTFSFEED["actv.avmspa.it<br/>(GTFS zip)"]
        GTFSRT["tpl.actv.it<br/>(GTFS-RT protobuf)"]
        ACTVRT["oraritemporeale.actv.it<br/>(passaggi/bacheca)"]
        OSM["tile OSM + Nominatim"]
        CDN["CDN: Bootstrap, Leaflet, Axios…"]
    end

    Browser --> NPM --> NGINX --> IDX --> RTR
    RTR --> CTL --> UI
    RTR --> API --> SVC
    API --> DB
    API --> CACHE
    SVC --> RTCACHE
    SVC --> STATE
    JS -->|fetch /api/*| NGINX
    JS -->|passaggi realtime| ACTVRT
    JS -->|tiles/geocoding| OSM
    UI --> CDN
    CTL -->|fetchData stops| ACTVRT
    GtfsRealtime --> GTFSRT
    GtfsUpdateManager --> GTFSFEED
    GtfsUpdateManager --> DB
    GtfsUpdateManager --> CACHE
    SW -. cache .-> JS
    JS -.-> LS
    CRON["crontab (UTC)"] -.->|update settimanale| PHP
```

## 9.3 Diagrammi di sequenza

### a) Caricamento di una pagina pubblica

```mermaid
sequenceDiagram
    participant B as Browser
    participant N as nginx/proxy
    participant I as index.php
    participant R as Router
    participant C as Controller
    participant V as View (PHP)
    participant A as ApiController

    B->>N: GET /stopList
    N->>I: try_files → index.php
    I->>I: bootstrap (env, Logger, session)
    I->>I: redirect HTTPS se necessario
    I->>R: dispatch('/stopList')
    R->>C: stops()
    C->>C: fetchData(oraritemporeale.../stops)
    C->>V: require stopList.php
    V-->>B: HTML (head comune + lista fermate)
    B->>A: GET /api/navigation/stops (JS)
    A-->>B: JSON fermate navigazione
    B->>A: GET /api/line-colors (JS)
    A-->>B: JSON colori linee
```

### b) Pianificazione percorso (`/api/plan-route`)

```mermaid
sequenceDiagram
    participant B as Browser
    participant API as ApiController::planRoute
    participant RP as RoutePlanner
    participant CB as ConnectionCacheBuilder
    participant CSP as ConnectionScanPlanner
    participant FS as data/gtfs/cache

    B->>API: GET /api/plan-route?from&to&date&time&optimize
    alt from/to è "lat,lon"
        API->>RP: findNearestStop(lat, lon) (Haversine)
        RP-->>API: stop + walking_time
        API->>API: tratta walking → planningOrigin/Dest
    end
    API->>CSP: planAlternatives(origin, dest, date, time)
    CSP->>CB: ensure(date)
    CB->>FS: legge trips/routes/calendar dei due feed
    CB-->>CSP: planner/YYYYMMDD.connections.tsv
    CSP->>CSP: scan connessioni (multi-cambio, multimodale)
    CSP-->>API: alternative ordinate per arrivo
    API->>API: splice walking legs + sort optimize
    API-->>B: {success, routes[], planner:'connection-scan-v1'}
```

### c) Mappa realtime (`/live-map`)

```mermaid
sequenceDiagram
    participant JS as liveBusMap.js
    participant API as ApiController
    participant RT as GtfsRealtime
    participant EXT as tpl.actv.it
    participant DB as MySQL

    loop ogni 60s
        JS->>API: GET /api/realtime/vehicles?service=automobilistico
        API->>RT: read('vehicles','automobilistico')
        alt feed raggiungibile
            RT->>EXT: cURL vehiclePositions (protobuf)
            EXT-->>RT: payload binario
            RT->>RT: decode + filtro ≤120s + cache file
        else fallback
            RT->>RT: cache file se <120s
        end
        RT-->>API: veicoli decodificati
        API->>DB: enrich (linea, colore, headsign)
        API-->>JS: {success, vehicles[]}
        par per ogni trip (max 6 paralleli)
            JS->>API: GET /api/bus-position?tripId=…
            API->>DB: stops + shapes_refined
            API-->>JS: {stops[], shape[]}
            JS->>JS: interpola posizione su shape
        end
        JS->>API: GET /api/navigation/vehicles (se layer attivo)
    end
```

### d) Login admin

```mermaid
sequenceDiagram
    participant B as Browser
    participant C as Controller::adminLogin
    participant A as AdminAuth

    B->>C: GET /admin/login
    C->>A: csrfToken() (session)
    C-->>B: form {password, csrf}
    B->>C: POST /admin/login {password, csrf}
    C->>A: verifyCsrf(csrf)
    alt CSRF ok
        C->>A: attempt(password)
        A->>A: hash_equals(ADMIN_PASSWORD, password)
        alt password ok
            A->>A: session_regenerate_id + is_admin=true
            C-->>B: 302 → /admin/dashboard
        else
            C-->>B: "Password non valida."
        end
    else
        C-->>B: "Sessione scaduta, riprova."
    end
```

### e) Aggiornamento GTFS schedulato

```mermaid
sequenceDiagram
    participant AD as Admin
    participant API as ApiController
    participant M as GtfsUpdateManager
    participant CRON as crontab
    participant FS as data/gtfs-update
    participant FEED as actv.avmspa.it
    participant DB as MySQL

    AD->>API: POST /api/admin/gtfs-update/config {csrf, weekday, time}
    API->>M: saveConfig(data)
    M->>CRON: installa voce marcata (CRON_TZ=UTC)
    Note over CRON: allo scheduled tick
    CRON->>M: start('scheduled')
    M->>FS: flock(update.lock)
    M->>FEED: HEAD → scelta feed per Last-Modified
    M->>FEED: download zip → work-*/
    M->>M: extract → cache JSON → staging import
    M->>DB: RENAME TABLE (publish atomico)
    M->>M: data_url, shapes_refined, navigation, planner cache
    M->>FS: state.json (success/failure + stats)
```

## 9.4 Diagramma di stato — aggiornamento GTFS

```mermaid
stateDiagram-v2
    [*] --> idle
    idle --> running: start() con lock acquisito
    running --> validating: staging import completato
    validating --> publishing: validazione ok
    publishing --> idle: RENAME TABLE + refresh navigation
    running --> failed: eccezione task (workspace conservato)
    validating --> failed: validazione fallita
    failed --> idle: reset manuale / nuovo start
```

## 9.5 Diagramma di stato — autenticazione admin

```mermaid
stateDiagram-v2
    [*] --> Guest
    Guest --> Authenticated: POST /admin/login<br/>password + CSRF validi
    Guest --> Guest: password errata / CSRF invalido
    Authenticated --> Guest: /admin/logout
    Guest --> LoginRedirect: qualsiasi /admin/* o /route<br/>requireAuth()
    LoginRedirect --> Guest: GET /admin/login
```

## 9.6 Flusso utente — route finder

```mermaid
flowchart LR
    A["/route-finder<br/>seleziona data/ora/optimize"] --> B["/station-selector?type=origin"]
    B --> C["/station-selector?type=destination"]
    C --> D["/route-results<br/>lista itinerari"]
    D --> E["modal dettagli / confronto"]
    D --> F["/route-details<br/>timeline + mappa"]
    F --> G["Salva tragitto<br/>(favorite_routes)"]
    D -. ritorno attivo .-> D2["tab Ritorno<br/>(2ª chiamata plan-route)"]
```
