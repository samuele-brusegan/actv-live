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
</content>
