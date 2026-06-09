# Live bus map — async loading & filtering

How `public/js/liveBusMap.js` turns the list of active trips into live markers
without blocking the UI.

## Concurrent, progressive loading

1. Fetch the active trips from [`/api/gtfs-bnr`](buses-running-now.md).
2. For each trip, fetch [`/api/bus-position`](position-and-shape.md) — but cap
   concurrency at `MAX_CONCURRENT = 6` parallel requests.
3. Each marker appears on the map **as soon as its data arrives** (progressive
   rendering) rather than waiting for the whole batch.
4. A counter / "last update" indicator reflects loading progress.

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

Buses that cannot be positioned (e.g. missing shape) can be listed in a side panel
rather than dropped silently.
</content>
