# Route finder

The journey planner. The user picks an origin and a destination (a stop, or an
address/coordinate), a date/time, and an optimization criterion; the app returns
ranked itineraries supporting **direct** rides and **single-transfer** connections,
with a **next-day fallback** when nothing is found today.

This is a **large feature** spanning several pages and one core service. The
sub-problems are documented separately:

- [planning-algorithm.md](planning-algorithm.md) — how `RoutePlanner` finds and
  ranks itineraries (direct + 1 transfer, next-day fallback, scoring).
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

- **Service:** [`app/services/RoutePlanner.php`](../../../app/services/RoutePlanner.php)
- **API:** `GET /api/plan-route` → `ApiController::planRoute()`

`planRoute()` is the single backend entry point. It resolves origin/destination
(stop id or address), calls `RoutePlanner::findRoutesMulti()`, post-processes walking
legs, applies the optimization sort, and returns:

```json
{ "success": true, "optimize": "time", "routes": [ /* itineraries */ ] }
```

A diagnostic mode is available at `GET /api/plan-route?debug=1` which dumps cache
stats and per-stop debug info instead of routes.

## Data source

The planner reads **only the JSON cache** in `data/gtfs/cache/` (never the DB),
produced by the [GTFS pipeline](../gtfs-pipeline.md):
`stops.json`, `routes.json`, `stop_routes_index.json`, and per-route
`routes/route_<id>.json` schedules.
</content>
