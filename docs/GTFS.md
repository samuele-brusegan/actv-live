# GTFS & Route Planning

Il cuore dell'applicazione è la gestione dei dati GTFS per fornire orari e calcolo percorsi in modo estremamente rapido.

## Ingestione dei Dati (`GTFSParser.php`)

Il parser scarica i feed ufficiali ACTV e li trasforma in una cache JSON ottimizzata. Questo passaggio è critico perché permette al server di non dover processare file CSV giganti ad ogni richiesta.

### Processo di Ingestione
1.  **Download**: Scarica `actv_aut.zip`.
2.  **Stops/Routes/Trips**: Converte i file `.txt` in dizionari JSON semplici.
3.  **Stop Times (La parte difficile)**: Il file `stop_times.txt` è troppo grande per essere caricato in memoria. Il parser lo divide:
    -   Crea un file JSON per ogni singola linea in `/data/gtfs/cache/routes/route_X.json`.
    -   All'interno, i dati sono raggruppati per `trip_id`.
4.  **Indexing**: Crea `stop_routes_index.json`, che mappa ogni fermata alle linee che la servono.

---

## Logica di Ricerca Percorsi

Il percorso normale `/api/plan-route` usa `ConnectionScanPlanner` e la cache
date-specifica generata da `ConnectionCacheBuilder`. Il planner combina le reti
automobilistica e di navigazione in una sequenza ordinata di connessioni tra
fermate consecutive.

Per ogni fermata raggiungibile conserva il primo arrivo noto e il momento dal
quale è possibile prendere un altro mezzo. Un'etichetta associata al `trip_id`
permette di rimanere sullo stesso mezzo senza trattare ogni fermata come un nuovo
cambio. Sono supportati cambi multipli tra bus, navigazione e percorsi a piedi;
non esiste il vecchio limite concettuale di “un solo cambio” nella ricerca
principale.

Le fermate con lo stesso nome e compatibili per distanza possono essere raggruppate.
Bus e navigazione entro 300 metri vengono collegati da un tratto a piedi, con una
durata minima di trasferimento. La scansione considera la data richiesta e, se
necessario, il giorno di servizio successivo, preservando gli orari GTFS oltre
`24:00:00`.

`RoutePlanner` resta disponibile per compatibilità, per alcune API basate sulla
cache JSON e per la risoluzione della fermata più vicina a coordinate geografiche;
non è il planner usato per le richieste normali di `/api/plan-route`.

---

## Struttura della Cache JSON di compatibilità

-   **`stops.json`**: `{ "stop_id": { "name", "lat", "lon" } }`
-   **`stop_routes_index.json`**: `{ "stop_id": ["route_id1", "route_id2"] }`
-   **`routes/route_X.json`**:
    ```json
    {
      "trip_id_1": [
        { "stop_id": "1", "arrival_time": "08:00:00", "stop_sequence": 1 },
        ...
      ]
    }
    ```

Questa cache è utilizzata da `RoutePlanner` per le API compatibili e per alcune
ricerche di supporto; il planner di produzione usa invece
`data/gtfs/cache/planner/*.connections.tsv`.

---

## Funzioni Particolari

### `calculateGeoDistance` (Haversine)
Utilizzata per trovare la fermata più vicina partendo da coordinate GPS. Implementa la formula matematica per calcolare la distanza su una sfera (Terra).

### Ordinamento delle alternative

`ConnectionScanPlanner::planAlternatives()` esegue ricerche a partire dall'orario
richiesto e da due orari successivi ravvicinati, elimina i duplicati e restituisce
le alternative ordinate per arrivo. L'API può poi riordinare le alternative con
`optimize=transfers` oppure `optimize=walking`; non applica un weighted scoring
con una penalità fissa per i cambi.
