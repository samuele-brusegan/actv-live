# Route finder

The journey planner. The user picks an origin and a destination (a stop, or an
address/coordinate), a date/time, and an optimization criterion; the app returns
ranked multimodal itineraries made of transit and walking legs. The search can
combine bus and water services and also scans the next service day when nothing
is found in the requested day.

This is a **large feature** spanning several pages and one core service. The
sub-problems are documented separately:

- [planning-algorithm.md](planning-algorithm.md) — how `ConnectionScanPlanner`
  scans date-specific connections, handles transfers and generates alternatives.
- [address-geocoding.md](address-geocoding.md) — turning `lat,lon` input into the
  nearest stop and injecting walking legs.
- [results-and-options.md](results-and-options.md) — optimization modes, route
  comparison and return-trip handling.

## Multi-page flow

| Step | Route | View | JS |
|------|-------|------|----|
| 1. Pick date/time & criterion | `/route-finder` | `routeFinder.php` | `routeFinder.js` |
| 2. Pick origin / destination | `/station-selector` | `stationSelector.php` | `stationSelector.js` |
| 3. See ranked results | `/route-results` | `routeResults.php` | `routeResults.js` |
| 4. Inspect one itinerary | `/route-details` | `routeDetails.php` | `routeDetails.js` |

The page actions are thin wrappers in
[`Controller`](../../../app/controllers/Controller.php) that just include the views;
all logic is client-side JS calling the planning API.

## Core service & API

- **Production planner:** [`app/services/ConnectionScanPlanner.php`](../../../app/services/ConnectionScanPlanner.php)
- **Cache builder:** [`app/services/ConnectionCacheBuilder.php`](../../../app/services/ConnectionCacheBuilder.php)
- **Compatibility and geocoding helper:** [`app/services/RoutePlanner.php`](../../../app/services/RoutePlanner.php)
- **API:** `GET /api/plan-route` → `ApiController::planRoute()`

`planRoute()` is the single backend entry point. It resolves origin/destination
(stop id or address), uses `RoutePlanner` to resolve coordinates to the nearest
stop when needed, then calls `ConnectionScanPlanner::planAlternatives()`. It
post-processes walking legs, applies the selected optimization sort, and returns:

```json
{ "success": true, "optimize": "time", "routes": [ /* itineraries */ ] }
```

A diagnostic mode is available at `GET /api/plan-route?debug=1` which dumps cache
stats and per-stop debug info instead of routes.

## Data source

The production planner reads metadata from the JSON caches in
`data/gtfs/cache/` and `data/gtfs/cache/navigation/`, then scans date-specific
connection caches under `data/gtfs/cache/planner/`. The connection cache combines
bus and water services and is generated from the static GTFS feeds; the normal
planning request does not query MySQL for its transit connections.
