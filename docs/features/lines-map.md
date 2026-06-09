# Lines map

Geographic visualization of bus/water-bus route paths on a Leaflet map. Can show a
single line, or all lines at once.

- **Route:** `/lines-map` → `Controller::linesMap()`
- **View:** `app/views/linesMap.php`
- **Client JS:** `public/js/linesMap.js`
- **API:** `/api/lines-shapes` → `ApiController::linesShapes()`

## API: `/api/lines-shapes`

Builds polylines from the database. Three query modes:

| Query param | Behaviour |
|-------------|-----------|
| `tripId` (or `trip_id`) | Path of one specific trip |
| `line` | Path of one route, using its `MIN(trip_id)` as representative trip |
| _(none)_ | All routes, one representative trip each |

The SQL joins `trips → routes → stop_times → stops` ordered by `stop_sequence`, then
PHP groups the flat rows by `route_id` into:

```json
[
  {
    "route_id": "...",
    "route_short_name": "2",
    "route_long_name": "...",
    "path": [ { "lat": 45.4, "lng": 12.3, "name": "Stop A" }, ... ]
  }
]
```

The endpoint raises its memory limit to 256M and time limit to 120s because the
"all lines" mode can be heavy.

> The `path` is built from **stop coordinates**, not from the detailed GTFS
> `shapes` geometry. For the smoothed shape geometry used by the live map, see
> [live-bus-map/position-and-shape.md](live-bus-map/position-and-shape.md).
</content>
