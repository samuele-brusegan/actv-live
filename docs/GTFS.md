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

## Logica di Ricerca Percorsi (`RoutePlanner.php`)

Il `RoutePlanner` implementa un algoritmo di ricerca percorsi personalizzato.

### 1. Connessioni Dirette
Cerca nel `stop_routes_index.json` se l'origine e la destinazione condividono una linea. In caso affermativo, cerca i trip che passano per entrambe le fermate nell'ordine corretto e dopo l'orario richiesto.

### 2. Cambi (1 Transfer)
Se non c'è una connessione diretta:
1.  Prende tutte le linee che passano per l'origine.
2.  Prende tutte le linee che passano per la destinazione.
3.  Cerca una fermata intermedia dove queste linee si incrociano.
4.  Calcola il tempo totale includendo un margine di 2 minuti per il cambio.

### 3. Ricerca nel Giorno Successivo
Se dopo le 23:00 non ci sono più corse, il sistema riprova automaticamente la ricerca partendo dalle 00:00 del giorno dopo, marcando i risultati con un `day_offset`.

---

## Struttura della Cache JSON

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

---

## Funzioni Particolari

### `calculateGeoDistance` (Haversine)
Utilizzata per trovare la fermata più vicina partendo da coordinate GPS. Implementa la formula matematica per calcolare la distanza su una sfera (Terra).

### `Weighted Scoring`
I risultati non sono ordinati solo per orario di arrivo, ma penalizzati se prevedono un cambio (+15 minuti virtuali) o se sono nel giorno successivo (+24 ore virtuali). Questo assicura che un viaggio diretto alle 14:10 sia preferito a un viaggio con cambio che arriva alle 14:05.
