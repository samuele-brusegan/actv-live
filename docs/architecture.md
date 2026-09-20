# Architettura del Sistema

ACTV Live segue un pattern MVC semplificato, ottimizzato per la velocità e la portabilità su diversi server (anche condivisi).

## Struttura delle Cartelle

```text
/
├── app/                  # Logica Core del Backend
│   ├── controllers/      # Gestori delle rotte (Business Logic)
│   ├── models/           # Interazione con DB e file di dati
│   ├── services/         # Classi di utilità (Routing, Parsing, Logging)
│   ├── views/            # Template HTML (componenti e pagine)
│   ├── bootstrap.php     # Inizializzazione applicazione
│   └── Router.php        # Gestione del routing custom
├── data/                 # Dati statici e GTFS
│   └── gtfs/cache/       # JSON, cache di navigazione e connessioni del planner
├── public/               # File accessibili via web
│   ├── css/              # Fogli di stile
│   ├── js/               # Script client-side
│   ├── index.php         # Entry point dell'applicazione
│   └── routes.php        # Definizione delle rotte
└── scripts/              # Script CLI (es. rigenerazione GTFS)
```

## Ciclo di Vita della Richiesta

1.  **Entry Point**: Tutte le richieste passano per `public/index.php` (grazie a `.htaccess`).
2.  **Bootstrap**: Viene caricato `app/bootstrap.php` che:
    -   Definisce costanti globali (`BASE_PATH`, `URL_PATH`).
    -   Carica variabili d'ambiente dal file `.env`.
    -   Configura i gestori degli errori centralizzati (`Logger.php`).
3.  **Routing**: Il file `public/routes.php` popola l'istanza di `Router`.
    -   Il `Router` analizza `REQUEST_URI`.
    -   Sceglie il Controller e il metodo corrispondente.
4.  **Esecuzione**: Il Controller interagisce con i Modelli o i Services (per
    esempio `ConnectionScanPlanner` per il route planning) e carica una View in
    `app/views/`.

## Componenti Chiave

### Il Router (`app/Router.php`)
Una classe semplice che mappa URL a coppie `Controller->Action`. Supporta parametri opzionali passati tramite variabili di istanza o query string.

### Pianificazione GTFS
`ApiController::planRoute()` usa `ConnectionScanPlanner` per la ricerca normale.
`ConnectionCacheBuilder` genera una cache ordinata per data in
`data/gtfs/cache/planner/`, mentre `RoutePlanner` resta utilizzato per la
compatibilità con alcune API e per trovare la fermata più vicina a coordinate.

### Gestione Errori Centralizzata
Qualsiasi errore PHP (Notice, Warning, Fatal) o Eccezione viene intercettato da `Logger::phpErrorHandler` e salvato nel database. Questo permette di monitorare lo stato di salute dell'app dalla sezione Admin.

### Time Machine storica
Le tabelle `tm_sessions` e `tm_data` possono ancora essere create dallo script
storico `scripts/setup_db.php`, ma le rotte di registrazione e playback non sono
registrate nel router corrente. Non esiste quindi una modalità Time Machine
attiva da configurare nell’installazione attuale.
