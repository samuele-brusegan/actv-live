# GTFS data pipeline

The offline pipeline that turns the official ACTV GTFS feed into the data structures
the app reads at runtime. This underpins the [route finder](route-finder/README.md)
(JSON cache) and most GTFS APIs (MySQL tables).

- **Service:** `app/services/GTFSParser.php`
- **CLI entry:** `scripts/parse_gtfs.php`
- **DB setup / helpers:** `scripts/setup_db.php`, `scripts/refine_shapes.php`,
  `scripts/update_stops_dataurl.php`, plus diagnostics
  (`check_db.php`, `check_refined.php`, `inspect_data.php`, `verify_shapes.php`,
  `test_planner.php`)

## Hybrid storage model

The app uses GTFS data in **two** forms:

1. **JSON cache** (`data/gtfs/cache/`) — produced by `GTFSParser`, optimized for the
   `RoutePlanner` so route planning never hits the database.
2. **MySQL tables** (`stops`, `routes`, `trips`, `stop_times`, `calendar`,
   `shapes_refined`) — used by the DB-backed APIs (live map, trip details, gtfs-*).

## `GTFSParser` flow (`parseAll()`)

| Step | Method | Output |
|------|--------|--------|
| Download + unzip the ACTV feed | `downloadGTFS()` | `data/gtfs/*.txt` |
| Parse stops | `parseStops()` | `cache/stops.json` (`id, name, lat, lon`) |
| Parse routes | `parseRoutes()` | `cache/routes.json` (`id, short_name, long_name, type`) |
| Parse trips | `parseTrips()` | `cache/trips.json` (`id, route_id, service_id, headsign`) |
| Parse stop_times | `parseStopTimes()` | per-route files + reverse index |

Feed URL: `http://actv.avmspa.it/sites/default/files/attachments/opendata/automobilistico/actv_aut.zip`

### Why stop_times is split per route

A single monolithic `stop_times.json` would be huge. Instead `parseStopTimes()`:

- writes one file per route: `cache/routes/route_<safeRouteId>.json`
  (`{ trip_id: [ {stop_id, arrival_time, departure_time, stop_sequence}, ... ] }`,
  each trip sorted by `stop_sequence`);
- builds a **reverse index** `cache/stop_routes_index.json` mapping
  `stop_id → [route_id, ...]`.

This lets the planner load only the routes relevant to a query (see
[route-finder/planning-algorithm.md](route-finder/planning-algorithm.md)).

## Refreshing the data

```bash
php scripts/parse_gtfs.php
```

`GTFSParser::isCacheValid($maxAge = 86400)` checks the age of `stops.json` so callers
can decide whether a refresh is needed (default freshness window: 24h). `parseStops`
raises the PHP memory limit to 1024M.

## Aggiornamento completo e pianificato

La pagina autenticata `/admin/gtfs-update` gestisce la pipeline completa:

1. selezione del feed più recente fra gli URL in `GTFS_URLS`;
2. download ed estrazione in una directory temporanea;
3. rigenerazione della cache JSON;
4. import nelle tabelle DB di staging;
5. compilazione di `stops.data_url`;
6. creazione di `shapes_refined` con ID auto-incrementali;
7. validazione e pubblicazione con `RENAME TABLE` atomico.

Il runner usa `flock`, quindi due aggiornamenti non possono essere eseguiti insieme.
La pianificazione è disabilitata di default. Salvando giorno e ora dalla pagina
admin, il sistema installa o aggiorna automaticamente una voce marcata nel crontab
dell'utente che esegue PHP-FPM. Disattivando lo switch, la voce viene rimossa.
L'esecuzione avviene esattamente nel giorno e all'ora selezionati, secondo il fuso
orario del server.

La sincronizzazione può essere eseguita anche da CLI:

```bash
php scripts/sync_gtfs_cron.php
```

Il comando `crontab` deve essere installato e l'utente PHP deve poter gestire il
proprio crontab. Il salvataggio dal pannello restituisce un errore e ripristina la
configurazione precedente se la sincronizzazione fallisce.

Avvio CLI manuale:

```bash
php scripts/update_gtfs.php --trigger=manual
```

### Dipendenze runtime

L'aggiornamento richiede:

- PHP CLI, configurabile tramite `GTFS_PHP_CLI` quando il percorso non è standard;
- comando `crontab` e demone cron attivo per la pianificazione settimanale;
- estensione PHP `curl` per scaricare il feed;
- estensione `pdo_mysql` per importare i dati;
- estensione PHP `zip`, che fornisce `ZipArchive`, oppure il comando `unzip`.

Il runner prova prima `ZipArchive` e usa `unzip` come fallback. Se entrambi sono
assenti, l'aggiornamento termina con l'errore `Né ZipArchive né il comando unzip
sono disponibili`.

Verifica rapida:

```bash
php --ri curl
php --ri pdo_mysql
php -r 'var_dump(class_exists("ZipArchive"));'
command -v unzip
```
