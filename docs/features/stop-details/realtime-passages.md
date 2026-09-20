# Stop details — real-time passages & scheduled fallback

The stop board shows upcoming passages from ACTV's real-time source, and falls back
to the GTFS schedule when real-time data is unavailable.

## Real-time source

`public/js/stop.js` fetches passages for the stop (`id` from the URL) from the ACTV
real-time backend and renders each upcoming bus/boat with its line, destination and
delay. Time/delay parsing helpers live in
[`public/js/utils.js`](../../../public/js/utils.js) (e.g. `HH:MM`, `MM min`, `MM'`).

## Scheduled fallback (GTFS)

When the real-time feed has no data, the app uses the GTFS schedule:

- `GET /api/stop-lines?stop=<id>&time=HH:MM` → `ApiController::stopLines()`
  delegates to `RoutePlanner::getLinesForStop()`, returning the next scheduled
  departures per line (from the JSON cache):

  ```json
  {
    "success": true,
    "stop_id": "...",
    "lines": [
      { "route_id": "...", "route_short_name": "2",
        "route_long_name": "...",
        "departures": [ { "time": "07:12", "destination": "..." } ] }
    ]
  }
  ```

- `GET /api/gtfs-passages` → `ApiController::gtfsPassages()` includes
  [`app/models/gtfsPassages.php`](../../../app/models/gtfsPassages.php), which queries
  scheduled passages from the MySQL GTFS tables.

> Limitation: `/api/gtfs-passages` è un fallback legacy e il suo formato non è
> completamente identico al payload realtime della sorgente ACTV.

## Delay recording

As passages are rendered with their delays, observations are appended to the local
`delay_history` store — see [delay-notifications.md](delay-notifications.md) and the
[delay statistics](../delay-stats.md) feature.
