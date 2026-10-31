# 05 — Pipeline dati GTFS e realtime

## I tre livelli di dati

```text
Feed GTFS ACTV (zip: stops/routes/trips/stop_times/calendar/calendar_dates/shapes .txt)
        │
        ├──▶ Cache JSON   data/gtfs/cache/        (metadata + compatibilità)
        ├──▶ Cache TSV    data/gtfs/cache/planner/ (connessioni per data, planner)
        └──▶ MySQL        stops, routes, trips, stop_times, calendar,
                          calendar_dates, shapes, shapes_refined

Feed GTFS-RT (protobuf) ──▶ GtfsRealtime.php ──▶ API realtime/vehicles
                            (+ cache file 120s in data/gtfs/realtime/)

Backend realtime ACTV (oraritemporeale.actv.it) ──▶ chiamato dal browser
```

Lo stesso modello è replicato per il servizio `navigation` in
`data/gtfs/navigation/` e `data/gtfs/cache/navigation/`.

## `GTFSParser` — feed → cache JSON

`app/services/GTFSParser.php`, eseguito da `scripts/update_gtfs.php` e
`scripts/parse_gtfs.php`:

| Passo | Metodo | Output |
|---|---|---|
| Download + unzip | `downloadGTFS()` | `data/gtfs/*.txt` |
| Fermate | `parseStops()` | `cache/stops.json` `{id,name,lat,lon}` (memory_limit 1024M) |
| Linee | `parseRoutes()` | `cache/routes.json` `{id,short_name,long_name,type}` |
| Corse | `parseTrips()` | `cache/trips.json` `{id,route_id,service_id,headsign}` |
| Orari | `parseStopTimes()` | `cache/routes/route_<safeId>.json` per route + `cache/stop_routes_index.json` |

`stop_times.txt` è troppo grande per un unico JSON: viene streammato in bucket
JSONL temporanei per route, poi convertito. Ogni `route_X.json` contiene
`{trip_id: [{stop_id, arrival_time, departure_time, stop_sequence}]}` ordinato
per sequenza. `stop_routes_index.json` mappa `stop_id → [route_id,…]`.

Selezione feed: `GTFS_URLS` può elencare più URL; viene scelto quello con
header `Last-Modified` più recente (`GTFS_URL` singolare = fallback).
`isCacheValid($maxAge=86400)` verifica l'età di `stops.json`.

## `GtfsUpdateManager` — aggiornamento completo e atomico

Orchestra il job completo (pannello `/admin/gtfs-update`, CLI
`scripts/update_gtfs.php --trigger=manual`, o cron):

1. scelta del feed più recente;
2. download ed estrazione in `data/gtfs-update/work-<ts>-<pid>/`;
3. rigenerazione cache JSON;
4. import nelle tabelle di staging;
5. compilazione di `stops.data_url` dal feed realtime;
6. creazione di `shapes_refined` (id auto-incrementali);
7. validazione e pubblicazione con `RENAME TABLE` atomico;
8. update feed navigazione + rigenerazione planner cache (un errore qui non
   invalida i dati automobilistici pubblicati; viene riportato nel task
   `navigation` dello stato).

### Stato, lock, log

- `flock` su `data/gtfs-update/update.lock`: mai due update concorrenti;
- stato JSON in `data/gtfs-update/state.json` (`status`, `tasks[]`, `stats`,
  `failure` con file/riga/trace e `workspace` conservato per ispezione);
- log progressi in `update.log` (metodo parser, ogni 50.000 righe, memoria).

### Pianificazione settimanale

Config salvata da `/api/admin/gtfs-update/config` in
`data/gtfs-update/config.json` (`enabled`, `weekday` 1-7 ISO, `time HH:MM:SS`).
Il salvataggio installa/rimuove una voce marcata nel **crontab** dell'utente
PHP-FPM (`CRON_TZ=UTC`, secondi simulati via `sleep`). Disabilitata di default.
Sync CLI: `php scripts/sync_gtfs_cron.php`. Se la sync crontab fallisce, la
config precedente è ripristinata.

## Connection cache del planner

`ConnectionCacheBuilder` genera `data/gtfs/cache/planner/YYYYMMDD.connections.tsv`:
lista **globalmente ordinata** di connessioni (una connessione = salto tra due
fermate consecutive di una corsa). Unisce bus+navigazione applicando
`calendar.txt` + `calendar_dates.txt`; orari >24:00 restano secondi interi.

Formato TSV (una riga per connessione):

```text
<dep_sec>  <arr_sec>  <service>:<from_stop>  <service>:<to_stop>
<trip_id>  <service>:<route_id>  <service>  <mode>  <seq>
```

Esempio reale:

```text
012600  13500  navigation:5113  navigation:5093  NAV_ACTV_653_33291  navigation:NLN_UN  navigation  water  1
```

`ensure($date)` genera la cache al primo uso sotto file lock, in una directory
temporanea se quella persistente non è scrivibile. Prebuild di oggi+domani a
ogni update; CLI: `php scripts/build_planner_cache.php <date...>`.

## `ConnectionScanPlanner` — algoritmo

- scansione delle connessioni in ordine di partenza;
- per ogni fermata: primo arrivo noto + orario da cui si può salire su un altro
  mezzo; etichetta per `trip_id` per distinguere "resta a bordo" da cambio;
- cambi multipli bus↔navigazione senza casi speciali; stop prematuro quando le
  partenze successive superano il miglior arrivo a destinazione;
- fermate omonime per piattaforma (es. Mestre FS C1/C3/C4) raggruppate se
  geograficamente compatibili; stop bus↔navigazione entro **300 m** collegati da
  tratto a piedi con durata minima di trasferimento;
- `planAlternatives()`: ricerca dall'orario richiesto + due orari successivi,
  dedup, ordinamento per arrivo; scansiona anche il giorno successivo.

## `GtfsRealtime` — decoder GTFS-RT

Decoder protobuf minimale in PHP puro (nessuna dipendenza):

| Servizio | vehicles | updates |
|---|---|---|
| automobilistico | `tpl.actv.it/aut/GTFSRT/vehiclePositions` | `.../tripUpdates` |
| navigation | `tpl.actv.it/nav/GTFSRT/vehiclePositions` | `.../tripUpdates` |

- download cURL (connect 5 s, total 8 s, segue redirect);
- cache per tipo in `data/gtfs/realtime/<service>/<kind>.json`; se il download
  fallisce, usa la cache se più fresca di `MAX_POSITION_AGE = 120` s;
- `freshVehicles()` filtra veicoli con timestamp entro `[now-120s, now+60s]`;
- `inspect()` (admin): restituisce `url, fetched_at, bytes, sha256, raw_base64,
  raw_hex_preview, decoded`.

## Script CLI (`scripts/`)

| Script | Funzione |
|---|---|
| `update_gtfs.php` | job update completo (`--trigger=manual`) |
| `parse_gtfs.php` | solo parser → cache JSON |
| `sync_gtfs_cron.php` | sincronizza la voce crontab con la config |
| `gtfs_update_tick.php` | tick per schedulazione |
| `build_planner_cache.php` | prebuild connection cache per date |
| `update_navigation.php` | update solo feed navigazione |
| `setup_db.php` | crea tabelle app (logs, tm_*, feedback) |
| `refine_shapes.php` | genera `shapes_refined` (+ `RoadSnapper`) |
| `update_stops_dataurl.php` | compila `stops.data_url` |
| `check_db.php`, `check_refined.php`, `inspect_data.php`, `verify_shapes.php`, `test_planner.php` | diagnostica |

## Sorgente realtime ACTV (browser)

Usata dal frontend (non proxata): catalogo
`/aut/backend/page/stops`, passaggi `/aut/backend/passages/{data_url}`,
bacheca `/aut/backend/page/{data_url}`. Formato documentato in
`docs/actv-json-format.md` (campi `lineId, line, path, destination, time, real,
timingPoints[]`); `time` può essere `HH:MM`, `MM'`/`MM min` oppure `departure`.
