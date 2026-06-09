# Live bus map

A real-time Leaflet map of every ACTV vehicle currently in service, with positions
interpolated along route geometry, live delay estimates, client-side filtering and
auto-refresh.

- **Route:** `/live-map` → `Controller::liveMap()`
- **View:** `app/views/liveBusMap.php`
- **Client JS:** `public/js/liveBusMap.js` (+ `public/js/utils.js`)
- **APIs:** `/api/gtfs-bnr` and `/api/bus-position`

This is a **large feature**; its sub-problems are documented separately:

- [buses-running-now.md](buses-running-now.md) — the `/api/gtfs-bnr` query that
  decides which trips are "running now" (±30 min, incl. past-midnight services).
- [position-and-shape.md](position-and-shape.md) — `/api/bus-position` stops + refined
  shape geometry, and how a marker's position is derived.
- [async-loading.md](async-loading.md) — concurrent per-bus loading, auto-refresh and
  client-side filtering.

## Client constants (`liveBusMap.js`)

| Constant | Value | Meaning |
|----------|-------|---------|
| `MAX_CONCURRENT` | 6 | Max parallel `/api/bus-position` fetches |
| `REFRESH_INTERVAL` | 60 000 ms | Auto-refresh period |
| `BUS_COLORS` | 14-colour palette | Per-line marker colours |
</content>
