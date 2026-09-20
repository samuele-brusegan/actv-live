# GTFS data pipeline

The offline pipeline that turns the official ACTV GTFS feed into the data structures
the app reads at runtime. This underpins the [route finder](route-finder/README.md)
(metadata and date-specific connection caches) and the GTFS APIs (MySQL tables and
legacy JSON caches).

- **Service:** `app/services/GTFSParser.php`
- **CLI entry principale:** `scripts/update_gtfs.php`
- **Parser/cache locale:** `scripts/parse_gtfs.php`
- **DB setup / helpers:** `scripts/setup_db.php`, `scripts/refine_shapes.php`,
  `scripts/update_stops_dataurl.php`, plus diagnostics
  (`check_db.php`, `check_refined.php`, `inspect_data.php`, `verify_shapes.php`,
  `test_planner.php`)

## Runtime storage model

The app uses GTFS data in **three** related forms:

1. **Metadata and compatibility JSON** (`data/gtfs/cache/`) — produced by
   `GTFSParser` and used by the production planner to resolve stops/routes, by
   compatibility helpers, and by some APIs.
2. **Date-specific connection cache** (`data/gtfs/cache/planner/`) — ordered TSV
   connections built by `ConnectionCacheBuilder` for the production
   `ConnectionScanPlanner`; it combines bus and navigation services.
3. **MySQL tables** (`stops`, `routes`, `trips`, `stop_times`, `calendar`,
   `shapes_refined`) — used by DB-backed APIs such as live map, trip details and
   the `gtfs-*` endpoints.

## `GTFSParser` flow (`parseAll()`)

| Step | Method | Output |
|------|--------|--------|
| Download + unzip the ACTV feed | `downloadGTFS()` | `data/gtfs/*.txt` |
| Parse stops | `parseStops()` | `cache/stops.json` (`id, name, lat, lon`) |
| Parse routes | `parseRoutes()` | `cache/routes.json` (`id, short_name, long_name, type`) |
| Parse trips | `parseTrips()` | `cache/trips.json` (`id, route_id, service_id, headsign`) |
| Parse stop_times | `parseStopTimes()` | per-route files + reverse index |

Feed predefinito: `https://actv.avmspa.it/sites/default/files/attachments/opendata/automobilistico/actv_aut.zip`.
Il runner può ricevere più URL tramite `GTFS_URLS` e sceglie quello con header
`Last-Modified` più recente; `GTFS_URL` singolare è mantenuto come fallback di
compatibilità.

### Why stop_times is split per route

A single monolithic `stop_times.json` would be huge. Instead `parseStopTimes()`:

- writes one file per route: `cache/routes/route_<safeRouteId>.json`
  (`{ trip_id: [ {stop_id, arrival_time, departure_time, stop_sequence}, ... ] }`,
  each trip sorted by `stop_sequence`);
- builds a **reverse index** `cache/stop_routes_index.json` mapping
  `stop_id → [route_id, ...]`.

During generation, `stop_times.txt` is streamed into temporary per-route JSONL
buckets and each route is then converted independently. This avoids keeping the
entire feed in PHP memory. The updater also logs the current parser method,
progress every 50,000 rows, memory usage, the effective `memory_limit`, and
precise CSV/JSON/write errors.

These files remain available to compatibility helpers and DB-backed APIs. The
production route finder uses the date-specific connection cache described in
[route-finder/planning-algorithm.md](route-finder/planning-algorithm.md).

## Refreshing the data

```bash
php scripts/update_gtfs.php --trigger=manual
```

`GTFSParser::isCacheValid($maxAge = 86400)` checks the age of `stops.json` so callers
can decide whether a refresh is needed (default freshness window: 24h). `parseStops`
raises the PHP memory limit to 1024M. L’aggiornamento completo usa invece
`GtfsUpdateManager`, che scarica il feed, costruisce una cache temporanea, importa
le tabelle di staging e pubblica tutto solo dopo la validazione.

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

La pianificazione usa `CRON_TZ=UTC` e quindi il fuso UTC+00. I secondi vengono applicati tramite un
ritardo `sleep` dopo l'avvio del job da parte di cron, che ha precisione al minuto.

Avvio CLI manuale:

```bash
php scripts/update_gtfs.php --trigger=manual
```

In caso di errore, lo stato in `data/gtfs-update/state.json` contiene `failure`
con task corrente, file/riga, memoria e trace quando disponibili; `update.log`
contiene gli stessi dettagli e i progressi del parser. Se PHP termina con un
errore fatale, il workspace temporaneo viene conservato nel percorso indicato
da `failure.workspace` per consentire l'ispezione.

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
