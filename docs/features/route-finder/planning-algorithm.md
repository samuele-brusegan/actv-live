# Route finder — planning algorithm

The production planner is implemented by
[`ConnectionScanPlanner.php`](../../../app/services/ConnectionScanPlanner.php).
`RoutePlanner.php` is retained for diagnostics and compatibility but is no
longer used for normal `/api/plan-route` requests.

## Date-specific connection cache

[`ConnectionCacheBuilder.php`](../../../app/services/ConnectionCacheBuilder.php)
combines the bus and navigation feeds into one ordered connection list for each
service date. A connection is one scheduled hop between consecutive stops and
contains its service, mode, route and trip identifiers.

Before publishing a date cache, the builder applies both `calendar.txt` and
`calendar_dates.txt`. GTFS times above `24:00:00` remain valid integer seconds.
The cache is rebuilt automatically when either source feed changes and is
published atomically under `data/gtfs/cache/planner/`.

## Connection Scan

The planner scans connections in departure order. For every reachable stop it
stores the earliest arrival and the time at which another vehicle may be
boarded. A trip-specific immutable label distinguishes staying aboard from
boarding the same trip at another stop, preventing impossible transfers.

This is service agnostic: bus and water are route metadata, so chains such as
`bus -> water -> bus -> ...` need no special cases or recursive route search.
The scan stops as soon as all later departures occur after the best destination
arrival.

## Stops and walking transfers

Raw stop IDs are namespaced by service. Platforms whose names differ only by a
platform suffix (for example Mestre FS C1/C3/C4) are grouped when geographically
compatible. Bus/navigation stops within 300 metres are connected by a walking
edge. Walking duration uses distance and a minimum transfer allowance.

## API contract

The planner receives origin, destination, service date, departure time and
optional endpoint services. It also scans the next service day, preserving GTFS
times across midnight. The response exposes every transit and walking leg in
chronological order.

## Cache generation

Normal feed updates prebuild today and tomorrow. Additional dates are generated
under a file lock on first request:

```bash
php scripts/build_planner_cache.php 2026-09-02 2026-09-03
```

## Verification fixture

`ConnectionScanPlannerTest.php` exercises the real multimodal Mestre FS to
Pellestrina itinerary, validates chronological legs, checks dates without active
services and covers GTFS times beyond midnight.
