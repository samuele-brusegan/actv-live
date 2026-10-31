# 03 — Database

MySQL 8 / MariaDB, charset `utf8mb4`. Accesso esclusivamente tramite
`databaseConnector` (PDO, prepared statements nativi, `PDO::FETCH_ASSOC`).

## Diagramma ER

```mermaid
erDiagram
    routes ||--o{ trips : "route_id"
    trips ||--o{ stop_times : "trip_id"
    stops ||--o{ stop_times : "stop_id"
    calendar ||--o{ trips : "service_id"
    calendar_dates ||--o{ trips : "service_id (eccezioni)"
    trips ||--o| shapes : "shape_id"
    shapes ||--o{ shapes_refined : "shape_id (derivata)"

    routes {
        varchar route_id PK
        varchar agency_id
        varchar route_short_name
        varchar route_long_name
        varchar route_desc
        int route_type
        varchar route_url
        varchar route_color
        varchar route_text_color
    }
    trips {
        varchar trip_id
        varchar route_id "indice"
        varchar service_id "indice"
        varchar trip_headsign
        varchar trip_short_name
        int direction_id
        varchar block_id
        varchar shape_id
        varchar wheelchair_accessible
    }
    stops {
        varchar stop_id
        varchar stop_code
        varchar stop_name "indice"
        varchar stop_desc
        double stop_lat
        double stop_lon
        varchar zone_id
        varchar stop_url
        varchar location_type
        varchar parent_station
        varchar stop_timezone
        varchar wheelchair_boarding
        varchar data_url "estensione ACTV Live"
    }
    stop_times {
        varchar trip_id "indice"
        time arrival_time
        time departure_time
        varchar stop_id "indice"
        int stop_sequence
        varchar stop_headsign
        int pickup_type "indice"
        int drop_off_type
        varchar shape_dist_traveled
    }
    calendar {
        varchar service_id
        int monday
        int tuesday
        int wednesday
        int thursday
        int friday
        int saturday
        int sunday
        int start_date "YYYYMMDD"
        int end_date "YYYYMMDD"
    }
    calendar_dates {
        varchar service_id
        int date "YYYYMMDD"
        int exception_type "1=aggiunto 2=rimosso"
    }
    shapes {
        varchar shape_id
        double shape_pt_lat
        double shape_pt_lon
        int shape_pt_sequence
        double shape_dist_traveled
    }
    shapes_refined {
        int id PK
        varchar shape_id "indice"
        decimal lat "10,8"
        decimal lng "11,8"
        int sequence "indice"
        double dist_traveled
    }
```

## Tabelle applicative

```mermaid
erDiagram
    logs {
        int id PK
        enum type "PHP_ERROR, JS_ERROR, EXCEPTION"
        text message
        varchar file
        int line
        text stack_trace
        json context
        timestamp created_at
    }
    feedback {
        int id PK
        varchar category "feature,bug,improvement,question,other"
        varchar priority "low,normal,high (default normal)"
        varchar status "new,in_progress,resolved,archived (default new)"
        varchar name "nullable"
        varchar email "nullable"
        varchar subject "nullable"
        text message
        char ip_hash "sha256 REMOTE_ADDR, 64"
        timestamp created_at
    }
```

Indici su `feedback`: `idx_feedback_status(status)`,
`idx_feedback_category(category)`, `idx_feedback_created(created_at)`.
La tabella è creata lazy (`CREATE TABLE IF NOT EXISTS`) sia dall'API pubblica
sia dal pannello admin.

### Tabelle legacy

`tm_sessions` e `tm_data` (Time Machine) sono create da `scripts/setup_db.php`
per compatibilità ma **non usate** da nessuna rotta corrente.

```sql
CREATE TABLE tm_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  status ENUM('SCHEDULED','RECORDING','COMPLETED') DEFAULT 'SCHEDULED',
  stops JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Note sullo schema GTFS

- **`stops.data_url`** — campo custom (non GTFS). Contiene il segmento usato dal
  backend realtime ACTV, es. `4-1004-web-aut`. Più `stop_id` GTFS possono
  condividere lo stesso `data_url`. Compilato dalla pipeline associando gli ID
  trovati nella `description` del feed realtime.
- **`stop_times` e orari oltre le 24:00** — il feed usa orari `>= 24:00:00` per
  le corse notturne; la colonna è `time` ma i planner lavorano in secondi interi.
- **`pickup_type`/`drop_off_type`** — `0`/`NULL` regolare, `1` non disponibile,
  `2` su prenotazione telefonica, `3` coordinamento con personale.
- **`shapes_refined`** — copia applicativa di `shapes` con PK auto-incrementale,
  `lat`/`lng` decimal e `dist_traveled`; letta da `/api/bus-position` e
  `/api/lines-shapes` per le geometrie delle mappe.
- **Dimensioni tipiche** (feed di riferimento): `routes` ~97, `trips` ~30k,
  `stops` ~2.2k, `stop_times` ~690k, `shapes` ~700k righe.
- **Aggiornamento atomico** — l'import avviene su tabelle di staging e viene
  pubblicato con `RENAME TABLE`, così un update fallito non corrompe i dati
  pubblicati (vedi [05-pipeline-gtfs.md](05-pipeline-gtfs.md)).
- **Tabella `logs`** — `context` JSON contiene User-Agent, URL e payload client
  per i `JS_ERROR`.

## Query tipiche usate dall'app

```sql
-- Fermate ordinate di una corsa (trip-details, bus-position)
SELECT stops.*, stop_times.*
FROM stops INNER JOIN stop_times ON stops.stop_id = stop_times.stop_id
WHERE stop_times.trip_id = ?
ORDER BY stop_times.stop_sequence;

-- Corse attive ora (gtfs-bnr): oggi + ieri (orari >24:00)
SELECT ... FROM stops JOIN stop_times ... JOIN trips JOIN routes JOIN calendar
WHERE calendar.<giorno> = 1  UNION ALL  (... ieri, arrival_time >= '24:00:00');
```
