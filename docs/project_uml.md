# Progetto ACTV Live - UML Diagram

Questo documento contiene la rappresentazione UML del progetto utilizzando la sintassi Mermaid.

## Diagramma delle Classi

```mermaid
classDiagram
    class Router {
        -array routes
        +add(string url, string controller, string action, string params) void
        +dispatch(string url) void
    }

    class Controller {
        +index() void
        +stops() void
        +routeFinder() void
        +logs() void
        +adminDashboard() void
        +liveMap() void
        -fetchData(string url, int timeout) array
    }

    class ApiController {
        -getDb(string joins) databaseConnector
        +api_gtfsIdentify() void
        +gtfsTripBuilder() void
        +gtfsStopTranslater() void
        +stops() void
        +planRoute() void
        +linesShapes() void
        +tripStops() void
        +logJsError() void
        +gtfsResolve() void
        +gtfsBusesRunningNow() void
        +busPosition() void
        +gtfsStops() void
        +gtfsPassages() void
    }

    class databaseConnector {
        -PDO db
        -string tableJoins
        -static databaseConnector instance
        +static getInstance() databaseConnector
        +connect(string user, string pass, string host, string name, string joins) bool
        +query(string query, array params) array
        +close() void
        +getJoins() string
        +seamsValidSQL(string query) bool
    }

    class RoutePlanner {
        -string cacheDir
        -string routesDir
        -array stops
        -array routes
        -array stopRoutesIndex
        -array loadedRoutesData
        +__construct()
        +findRoutes(string originId, string destId, string departureTime) array
        +findNearestStop(float lat, float lon) array|null
        -loadBaseCache() void
        -getRouteData(string routeId) array
        -calculateGeoDistance(float lat1, float lon1, float lat2, float lon2) float
    }

    class ConnectionCacheBuilder {
        -string plannerDir
        +ensure(string date) string
        -build(string date, string path) void
    }

    class ConnectionScanPlanner {
        -ConnectionCacheBuilder cacheBuilder
        +plan(string originId, string destinationId, string date, string time) array
        +planAlternatives(string originId, string destinationId, string date, string time) array
    }

    class GTFSParser {
        -string gtfsUrl
        -string dataDir
        -string cacheDir
        +__construct()
        +downloadGTFS() void
        +parseAll() void
        +parseStops() array
        +parseRoutes() array
        +parseTrips() array
        +parseStopTimes() void
        +isCacheValid(int maxAge) bool
    }

    class Logger {
        +static log(string type, string message, ...) void
    }

    Router ..> Controller : instanzia
    Router ..> ApiController : instanzia
    Controller ..> databaseConnector : usa
    ApiController ..> databaseConnector : usa
    ApiController ..> RoutePlanner : compatibilita e geocoding
    ApiController ..> ConnectionScanPlanner : planner di produzione
    ConnectionScanPlanner ..> ConnectionCacheBuilder : cache per data
    ApiController ..> Logger : usa
    RoutePlanner ..> GTFSParser : dipende dalla cache generata da
```

## Descrizione dei Componenti

- **Router**: Gestisce il routing delle richieste HTTP verso i controller appropriati.
- **Controller**: Gestisce le viste principali dell'applicazione e le operazioni amministrative.
- **ApiController**: Fornisce endpoint per le funzionalità dinamiche (GTFS, pianificazione percorsi, posizioni bus).
- **databaseConnector**: Implementa il pattern Singleton per gestire la connessione al database tramite PDO.
- **ConnectionScanPlanner**: Planner di produzione date-aware per connessioni bus,
  navigazione e cammini.
- **ConnectionCacheBuilder**: Costruisce e pubblica la cache ordinata delle
  connessioni per ogni data di servizio.
- **RoutePlanner**: Planner compatibile e servizio di supporto per cache legacy,
  API esistenti e ricerca della fermata più vicina.
- **GTFSParser**: Utility per scaricare e convertire i file GTFS in cache JSON ottimizzate.
- **Logger**: Utility statica per la registrazione di log ed errori.
