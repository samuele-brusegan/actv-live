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
│   └── gtfs/cache/       # JSON ottimizzati generati dal parser
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
4.  **Esecuzione**: Il Controller interagisce con i Modelli o i Services (es. `RoutePlanner`) e carica una View in `app/views/`.

## Componenti Chiave

### Il Router (`app/Router.php`)
Una classe semplice che mappa URL a coppie `Controller->Action`. Supporta parametri opzionali passati tramite variabili di istanza o query string.

### Gestione Errori Centralizzata
Qualsiasi errore PHP (Notice, Warning, Fatal) o Eccezione viene intercettato da `Logger::phpErrorHandler` e salvato nel database. Questo permette di monitorare lo stato di salute dell'app dalla sezione Admin.

### Modalità Time Machine
Se abilitata tramite cookie/sessione, l'applicazione devia le richieste di dati in tempo reale dal backend ufficiale ACTV al database locale del `tm_data`, simulando il passato.
