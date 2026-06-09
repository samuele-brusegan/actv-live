# Route finder — planning algorithm

Implemented in [`app/services/RoutePlanner.php`](../../../app/services/RoutePlanner.php).
The planner works purely on the JSON cache (see [../gtfs-pipeline.md](../gtfs-pipeline.md)).

## Entry points

- `findRoutesMulti($originId, $destId, $departureTime)` — the public entry point used
  by the API.
- `findRoutes($originId, $destId, $departureTime)` — the single-stop-pair core.

### Why `findRoutesMulti` exists

In the ACTV GTFS a physical stop has **several `stop_id`s** (one per direction /
platform). Searching only the selected id often misses valid connections. So
`findRoutesMulti`:

1. expands origin and destination into all **sibling stop ids** that share the same
   normalized name (`getSiblingStopIds()` builds a name → ids index lazily);
2. runs `findRoutes()` for every origin×destination pair;
3. de-duplicates by `(departure, arrival, route, type)`;
4. re-sorts and returns the top 10.

## `findRoutes` — the core search

Both endpoints are looked up in `stop_routes_index.json`; if either is absent the
result is empty.

### 1. Direct connections
Intersect the routes serving the origin with those serving the destination
(`array_intersect`). For each common route, `findTripsForRoute()` returns every trip
where the origin's `stop_sequence` precedes the destination's and the origin
`departure_time >= requested time`.

### 2. One-transfer connections
For each non-direct origin route (capped at `maxRoutesToCheck = 50` for safety):

- enumerate stops reachable after the origin (`getRouteStopsAfter()`);
- if a reachable stop is served by any **destination** route, it's a transfer point;
- build **leg 1** (origin → transfer) and take the earliest valid trip;
- build **leg 2** (transfer → destination) departing after leg-1 arrival **+ 2 min**
  buffer (`addMinutes`), choosing the connecting route with the earliest arrival.

A transfer itinerary records both legs, a combined `route_short_name`
(`"A → B"`), the transfer stop name and total `stops_count`.

### 3. Next-day fallback
If **no** results were found for today, the same direct + transfer search is repeated
from `00:00:00` with `day_offset = 1` (and +24h added to duration), so the user always
gets the earliest option the following day.

## Ranking

Results are sorted by a weighted score:

```
score = arrivalSeconds
      + (type == 'transfer' ? 15*60 : 0)   // 15-min transfer penalty
      + day_offset * 86400                  // next-day pushed to the bottom
```

Ties break on shorter `duration`. The list is capped at **10** itineraries.
The user-selected optimization (`time` / `transfers` / `walking`) is applied
afterwards in the API — see [results-and-options.md](results-and-options.md).

## Itinerary shape

Each itinerary has a `type` (`direct` | `transfer`), `departure_time`,
`arrival_time`, `duration` (minutes), `stops_count`, and a `legs` array. A direct
trip has a single `bus` leg; walking legs are added later by the API for
address-based searches (see [address-geocoding.md](address-geocoding.md)).

## Helper methods of note

- `getLinesForStop($stopId, $afterTime, $limit)` — next departures per line at a
  stop; backs the `/api/stop-lines` endpoint (see
  [../stop-details/realtime-passages.md](../stop-details/realtime-passages.md)).
- `findNearestStop($lat, $lon)` — Haversine nearest-stop lookup, used for geocoding.
- `getCacheStats()` / `debugStop()` — diagnostics surfaced by `?debug=1`.
</content>
