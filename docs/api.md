# API Reference

ACTV Live fornisce diverse API per l'integrazione frontend e l'uso di dati GTFS.

## Endpoint Pubblici

### `GET /api/stops`
Ritorna la lista completa di tutte le fermate presenti nel database.
- **Risposta**: Array di oggetti fermata (stop_id, name, lat, lon...).

### `GET /api/gtfs-bnr`
**GTFS Buses Running Now**. Ritorna i bus attualmente in servizio.
- **Parametri**:
    - `time` (opzionale): Orario nel formato `HH:MM`. Default: ora server.
    - `day` (opzionale): Giorno della settimana (`monday`..`sunday`). Default: giorno server.
- **Risposta**:
    ```json
    {
      "time": "12:30",
      "day": "monday",
      "count": 45,
      "buses": [
        { "trip_id": "123", "route_short_name": "5E", "diff_min": -5, "route_id": "10" }
      ]
    }
    ```

### `GET /api/bus-position`
Ritorna le fermate e gli orari di un trip specifico per permettere il calcolo della posizione.
- **Parametri**: `tripId` (obbligatorio).
- **Risposta**: Array di fermate ordinate con `arrival_time`, `lat`, `lng`.

### `GET /api/lines-shapes`
Ritorna i percorsi (punti geografici) delle linee per il disegno su mappa.
- **Parametri**: `line` (opzionale) o `tripId` (opzionale).
- **Risposta**: GeoJSON-like structure con array di coordinate.

### `GET /api/trip-stops`
Ritorna la lista delle fermate per una linea e una destinazione.
- **Parametri**: `line`, `dest`.

## Servizi & Utilità

### `GET /api/gtfs-resolve`
Risolve i dettagli di un tripId.

### `POST /api/log-js-error`
Invia un errore JavaScript al logger del server.
- **Body**: `{ message, url, line, stack }`.

## Time Machine (Richiede autenticazione/token)

### `GET /api/tm/simulated-data`
Ritorna i dati registrati per una fermata a un determinato orario.
- **Parametri**: `stopId`, `time`.

### `GET /api/tm/heartbeat`
Trigger di registrazione per le sessioni attive.
- **Parametri**: `token`.
