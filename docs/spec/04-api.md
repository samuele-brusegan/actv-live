# 04 — API Reference completa

Tutti gli endpoint sotto `/api/`. Salvo indicazione sono `GET` e rispondono JSON.
Il router non distingue il metodo HTTP: i metodi indicati sono quelli del contratto.

Convenzioni:

- nessuna API key per gli endpoint pubblici;
- i nomi dei giorni sono in inglese minuscolo (`monday`…`sunday`);
- gli orari GTFS possono superare `24:00:00` (corse notturne);
- alcuni errori legacy tornano `200` con `success:false`: controllare sempre il body;
- diversi endpoint usano `ResponseCache` (cache file con TTL) e header
  `Cache-Control` espliciti.

## Indice

| Endpoint | Scopo | Fonte dati |
|---|---|---|
| `/api/plan-route` | Itinerari multimodali | cache planner |
| `/api/stops` | Tutte le fermate (bus+navigazione) | DB + cache |
| `/api/stop-lines` | Prossime linee a una fermata | cache JSON |
| `/api/stop-upcoming` | Passaggi nella prossima ora | DB |
| `/api/navigation/stops` | Fermate navigazione | cache JSON |
| `/api/navigation/lines` | Linee navigazione | cache JSON |
| `/api/navigation/passages` | Passaggi fermata acquea | planner navigation |
| `/api/navigation/vehicles` | Veicoli navigazione realtime | GTFS-RT |
| `/api/realtime/vehicles` | Veicoli realtime per servizio | GTFS-RT |
| `/api/line-colors` | Colori GTFS delle linee | DB + cache |
| `/api/lines-shapes` | Path/shape di linee e corse | DB + cache |
| `/api/trip-stops` | Fermate di una linea | DB |
| `/api/bus-position` | Fermate + shape di una corsa | DB |
| `/api/line-catalog` | Catalogo linee per servizio/data | DB |
| `/api/line-variants` | Varianti di una linea | DB |
| `/api/line-schedule` | Matrice orari variante | DB |
| `/api/line-trips` | Corse origine→destinazione | DB |
| `/api/gtfs-identify` | Risolve un passaggio in trip_id | DB |
| `/api/gtfs-resolve` | Metadati di un trip_id | DB |
| `/api/gtfs-builder` | Fermate di un trip_id | DB |
| `/api/gtfs-stop-translater` | Join stops+stop_times per trip | DB |
| `/api/gtfs-stops` | Tabella stops grezza | DB |
| `/api/gtfs-passages` | Passaggi programmati fermata | DB |
| `/api/gtfs-bnr` | Corse attive ±30 min | DB |
| `/api/log-js-error` | Log errori JS | DB |
| `/api/feedback` | Invio feedback | DB |
| `/api/delete-cookie` | Cancellazione cookie | — |

---

## Pianificazione

### `GET /api/plan-route`

Pianifica itinerari multimodali (bus + navigazione + tratti a piedi, cambi
multipli). Implementato da `ConnectionScanPlanner` su cache connessioni per data.

| Parametro | Obbl. | Descrizione |
|---|---|---|
| `from` | sì | `stop_id` oppure coordinate `lat,lon` |
| `to` | sì | `stop_id` oppure `lat,lon` |
| `date` | no | `YYYY-MM-DD` (default: oggi). Data non valida → errore |
| `time` | no | `HH:MM`/`HH:MM:SS` (default: ora server) |
| `from_service`, `to_service` | no | `automobilistico`\|`navigation` per ID ambigui |
| `optimize` | no | `time` (default), `transfers`, `walking` |
| `debug` | no | restituisce diagnostica cache/stop |

Comportamento:

- estremi `lat,lon` → risolti alla fermata più vicina via
  `RoutePlanner::findNearestStop()` (Haversine; walking_time = `ceil(distanza/83)`
  minuti a ~5 km/h); la tratta a piedi è inserita come leg `type:"walking"`,
  `route_short_name:"Cammina"`;
- scansiona anche il giorno di servizio successivo preservando orari >24:00;
- `optimize=transfers`: meno tratte transit poi durata; `optimize=walking`: meno
  metri a piedi poi durata;
- `memory_limit` 512M, `time_limit` 120s.

Risposta:

```json
{
  "success": true, "date": "2026-09-02", "optimize": "time",
  "mode": "all", "planner": "connection-scan-v1",
  "routes": [
    { "departure_time": "10:32:00", "arrival_time": "11:15:00",
      "duration": 43, "transfer_stop": "Piazzale Roma",
      "legs": [
        { "type": "transit", "mode": "bus", "service": "automobilistico",
          "route_short_name": "4L", "trip_id": "AUT_ACTV_4L_21001",
          "origin": "Stazione MESTRE FS C1", "origin_id": "612",
          "destination": "Piazzale Roma", "destination_id": "5031",
          "departure_time": "10:32:00", "arrival_time": "11:15:00",
          "duration": 43, "stops_count": 18 },
        { "type": "walking", "route_short_name": "Cammina",
          "distance": 550, "duration": 7, "stops_count": 0,
          "origin": "...", "destination": "...",
          "departure_time": "...", "arrival_time": "..." }
      ] }
  ]
}
```

Errori: `400 {success:false, code:"INVALID_REQUEST"}` per input invalido;
`500 code:"PLANNER_ERROR", retryable:true` per errori interni.

## Fermate

### `GET /api/stops`

Tutte le righe di `stops` + merge delle fermate `navigation` dal cache
(`data/gtfs/cache/navigation/stops.json`). Campi: `stop_id, stop_code,
stop_name, stop_lat, stop_lon, data_url, service ('automobilistico'|'navigation'),
mode ('bus'|'water')`.

- `?stop=<data_url-token>` — filtra le fermate i cui token `data_url` (o stop_id)
  compaiono nel parametro (es. `?stop=4-1004`); usato dalla pagina fermata;
- fallback: se il DB è down risponde con `cache/stops.json` (500 solo se anche
  la cache manca).

### `GET /api/stop-lines`

| Parametro | Obbl. | Descrizione |
|---|---|---|
| `stop` | sì | `stop_id` GTFS |
| `time` | no | `HH:MM[:SS]` (default ora server) |

Delega a `RoutePlanner::getLinesForStop()`. Risposta
`{success, stop_id, lines:[{route_id, route_short_name, route_long_name,
departures:[{time, destination}]}]}`.

### `GET /api/stop-upcoming`

Passaggi previsti nei 60 minuti successivi. Parametri: `stop` (obbl.),
`time` (`HH:MM`), `day`. Risposta
`{success, stop, day, time, count, buses:[{route_short_name, route_id,
trip_headsign, trip_id, time, in_min}]}`.

### `GET /api/navigation/stops`

Fermate di navigazione dal cache: array di
`{stop_id, stop_name, stop_lat, stop_lon, service:"navigation", mode:"water"}`.

### `GET /api/navigation/lines`

Linee da `cache/navigation/routes.json` (nessun parametro).

### `GET /api/navigation/passages`

`stop_id`/`stop` (obbl.), `time` opzionale → passaggi previsti alla fermata acquea.

## Realtime

### `GET /api/realtime/vehicles`

Vehicle Positions GTFS-RT arricchite con metadati statici. Cache 3 s.

| Parametro | Obbl. | Descrizione |
|---|---|---|
| `service` | sì | `automobilistico` \| `navigation` |
| `tripId`/`tripIds` | no | filtro per uno o più trip_id (CSV) |
| `routeId`/`routeIds` | no | filtro per route_id (CSV) |
| `nearby` | no | raggio in metri: aggiunge veicoli vicini ai selezionati |

```json
{ "success": true, "service": "automobilistico",
  "vehicles": [ { "trip_id": "AUT_ACTV_5E_123", "route_id": "10",
      "route_short_name": "5E", "trip_headsign": "Venezia",
      "vehicle_position": {"lat": 45.49, "lon": 12.24} } ] }
```

`route_guess` marca le linee stimate per vicinanza quando il feed non associa
un trip certo.

### `GET /api/navigation/vehicles`

Alias storico per `service=navigation`: stessa logica, ma restituisce
direttamente l'array veicoli (non l'involucro `{success,...}`).

## Linee, varianti, orari

### `GET /api/line-colors`

Mappa `bus|LINEA` / `navigation|LINEA` → `{route_color, route_text_color}`
(esadecimale normalizzato con `#`; fallback `#5B5B5B`). Usata per i badge colorati.

### `GET /api/lines-shapes`

| Parametro | Descrizione |
|---|---|
| `line` | path di una `route_short_name` (trip rappresentativa `MIN(trip_id)`) |
| `tripId`/`trip_id` | path di una corsa |
| `tripIds` | CSV di trip_id |
| `tripGroups` | gruppi `N:id1,id2` separati da `\|` (shape raggruppate) |
| _(nessuno)_ | una corsa rappresentativa per ogni route |

SQL: `trips → routes → stop_times → stops` ordinato per `stop_sequence`,
raggruppato per `route_id`. Con filtri include anche `shape` (punti di
`shapes_refined`); se il DB non risponde, cade sulla cache JSON. `memory_limit`
256M, `time_limit` 120s.

```json
[ { "route_id": "10", "trip_id": "123", "group_number": null,
    "route_short_name": "5E", "route_long_name": "Noale - Venezia",
    "shape_id": "shape-1",
    "path": [{"lat":45.49,"lng":12.24,"name":"Mestre Centro"}],
    "shape": [{"lat":45.49,"lng":12.24}] } ]
```

Nota: `path` è costruito dalle **coordinate delle fermate**; `shape` dalla
geometria raffinata.

### `GET /api/trip-stops`

`line` (obbl., `route_short_name`), `dest` opzionale. Risolve una corsa
rappresentativa (headsign `LIKE %dest%` → altrimenti ultima fermata → altrimenti
prima trip) e restituisce le fermate `[{id,name,lat,lng,time HH:MM}]`.
`400` senza `line`, `404` se non trovata.

### `GET /api/bus-position`

`tripId` (obbl.). Fermate ordinate + shape raffinata:

```json
{ "stops": [ { "stop_id":"1234","stop_name":"Mestre Centro",
      "stop_lat":"45.493","stop_lon":"12.242",
      "arrival_time":"14:35:00","departure_time":"14:35:00",
      "stop_sequence":"4","data_url":"1234-web-aut" } ],
  "shape": [ {"lat":"45.493","lng":"12.242","sequence":"1","dist_traveled":"0"} ] }
```

`400` senza `tripId`; `{error:"No stops found for this trip"}` se vuota.

### `GET /api/line-catalog`

`service` (`automobilistico` default), `date`, `day`, `all=1`. Elenco linee:
`{success, service, updated_at, lines:[{line, name, variants_count,
trips_count}]}`. Con `all=1` include tutte le corse del feed indipendentemente
dal giorno.

### `GET /api/line-variants`

`line` (obbl.), `day`, `date`, `all=1`. Varianti raggruppate per
shape/destinazione:

```json
{ "success": true, "line": "5E",
  "variants": [ { "shape_id":"shape-1","headsign":"Venezia",
      "direction_id":"0","trip_id":"123","stops_count":25,"trips_count":12,
      "origin":"Noale","terminus":"Venezia",
      "stops":[{"id":"1234","name":"Mestre Centro","lat":"45.493",
                "lng":"12.242","seq":"10","time":"14:35:00"}] } ] }
```

### `GET /api/line-schedule`

`line` (obbl.), `trips` (CSV dei trip_id rappresentativi) o `trip` singolo,
`day`, `date`, `all=1`. Matrice fermate×corse:
`{success, line, day, headsign, stops:[{id,name}], runs:[{trip_id, start,
times:[HH:MM|null]}]}` — `times[i]` corrisponde a `stops[i]`, `null` se la corsa
non serve la fermata. Con `all=1` ogni run include `calendar` (giorni
settimanali, periodo di validità, eccezioni `calendar_dates`).

### `GET /api/line-trips`

`line`, `origin`, `destination` (obbl.), `date`, `day`. Corse che servono
entrambe le fermate in sequenza, rispettando `calendar` + `calendar_dates`:
`{success, service, line, origin, destination, date, trips:[{trip_id, headsign,
departure_time, arrival_time}]}` ordinati per partenza.

## Compatibilità GTFS (`/api/gtfs-*`)

Per quelli marcati, `return=true` → JSON, `rtable=true` → tabella HTML diagnostica.

| Endpoint | Parametri | Output |
|---|---|---|
| `gtfs-identify` | `return` (obbl. per JSON), `busTrack`, `busDirection`, `day`, `stop`/`stopId`, `time`, `lineId`, `excludeTripIds`, `limit` (default 1) | trip GTFS corrispondente al passaggio ACTV; senza `return` mostra la pagina diagnostica `gtfsIdentify.php`. Cache 300 s |
| `gtfs-resolve` | `return`, `tripId` | `{line_id, bus_track, bus_direction, line_tag}` |
| `gtfs-builder` | `trip_id` | fermate complete della corsa per `stop_sequence`, dedup consecutive; `400` se manca |
| `gtfs-stop-translater` | `trip_id` | join `stops.*`+`stop_times.*` per `arrival_time`; `400` se manca |
| `gtfs-stops` | `return=true` | tutte le righe di `stops` |
| `gtfs-passages` | `return`, `stop` (ID dentro `data_url`) | passaggi programmati: `[{line,destination,trip_id,gtfs_stop_id,time,real:false,stop,lineId,stop_lat,stop_lon,timingPoints:[{stop,time}]}]`; cache 12 s bucketizzata |
| `gtfs-bnr` | `time` (`HH:MM`), `day` | corse con passaggio entro ±30 min (include ieri per orari >24:00): `{time,day,count,buses:[{route_short_name,route_id,trip_headsign,arrival_time,trip_id,source:'today'|'yesterday',diff_min}]}` |

## Servizio

### `POST /api/log-js-error`

Body JSON `{message?, url?, line?, stack?}` → `JS_ERROR` su `logs` con User-Agent
in `context`. `400` per body invalido; `{"success":true}` se ok.

### `POST /api/feedback`

Solo POST (`405` altrimenti). Campi: `category` (obbl., una di
`feature|bug|improvement|question|other`), `priority` (`low|normal|high`),
`message` (obbl., 10–5000 char), `name`/`email`/`subject` opzionali, `website`
(honeypot: se compilato risponde `success` senza salvare). Salva `ip_hash` =
sha256 dell'IP. Errori `422` di validazione, `500` su fallimento DB.

### `GET|POST /api/delete-cookie`

Senza `name` elimina i cookie PHP ricevuti; con `name` solo quello. La risposta
elenca i cookie rimossi + script per quelli lato client.

## API amministrative

Richiedono sessione admin (`401 {success:false}`); le POST richiedono anche
`csrf` nel body (`403`). `409` se l'update GTFS è già attivo.

| Endpoint | Metodo | Descrizione |
|---|---|---|
| `/api/admin/gtfs-update/status` | GET | `{success,data:{config,state,database,cache_updated_at,log_tail}}` |
| `/api/admin/database-health` | GET | esito `DatabaseHealthService::inspect()` |
| `/api/admin/gtfs-update/config` | POST | `{csrf, enabled, weekday(1-7 ISO), time(HH:MM:SS)}` → salva config + crontab (UTC) |
| `/api/admin/gtfs-update/start` | POST | `{csrf}` → avvia job, `{success,started,message}` |
| `/api/admin/gtfs-rt-inspector` | GET | `service=automobilistico|navigation`, `kind=vehicles|updates` → payload base64, hex preview, record decodificati; `422` parametri, `502` feed down |
