# Live bus map — async loading & filtering

How `public/js/liveBusMap.js` turns the list of active trips into live markers
without blocking the UI.

## Concurrent, progressive loading

1. Fetch the vehicle positions from `/api/realtime/vehicles?service=automobilistico`.
2. Fetch waterbus positions from `/api/navigation/vehicles` when the navigation
   layer is enabled.
3. Use `/api/gtfs-bnr` and `/api/bus-position` for scheduled bus fallback and
   static trip geometry.
4. Load `/api/lines-shapes` on demand for the selected trip or line.
5. Each independent resource is rendered as soon as it arrives instead of
   waiting for all requests to complete.
6. A counter / "last update" indicator reflects loading progress.

Each line is drawn with a stable colour picked from the `BUS_COLORS` palette.

## Auto-refresh

A timer re-runs the whole load every `REFRESH_INTERVAL = 60 000 ms` (60 s); a manual
refresh button triggers it on demand.

## Client-side filtering

The filter input (`#filter-input`, with a clear button) narrows the visible markers
**on the client** by:

- `route_short_name`,
- `tripId`,
- `routeId`.

Buses that cannot be positioned or whose route is only heuristic remain marked as
such instead of being presented as an exact realtime association. When a shape is
missing, the map can fall back to the ordered stop path.
