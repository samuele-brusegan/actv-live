# Live bus map — "buses running now" query

`GET /api/gtfs-bnr` → `ApiController::gtfsBusesRunningNow()` returns the trips active
around a given time, which seed the markers on the live map.

## Parameters

| Param | Default | Notes |
|-------|---------|-------|
| `time` | server `H:i` | Reference time |
| `day` | server weekday (`monday`..`sunday`) | Validated against an allow-list; invalid → `{"error":"Invalid day"}` |

## Query logic

The endpoint must include late-night services that belong to the **previous**
calendar day (GTFS encodes these as times `≥ 24:00:00`). It therefore `UNION ALL`s:

1. **today**: every trip whose `calendar.<day> = 1`;
2. **yesterday**: trips of the previous day whose `arrival_time >= '24:00:00'`.

(Joins: `stops → stop_times → trips → routes → calendar`.)

## ±30 min window & dedup

Results are filtered in PHP to trips within **±1800 s (30 min)** of the reference
time, with wrap-around handling for the 24h clock. For each kept trip:

- `diff_min` = signed minutes from now (negative = already passed), with a
  correction for `yesterday` trips when the reference time is in the first hour;
- trips are de-duplicated by `trip_id` (`$seen`);
- the list is sorted by `diff_min` ascending.

## Response

```json
{
  "time": "07:30",
  "day": "monday",
  "count": 42,
  "buses": [
    { "route_short_name": "2", "route_id": "...", "trip_headsign": "...",
      "arrival_time": "07:35:00", "trip_id": "...",
      "source": "today", "diff_min": 5 }
  ]
}
```

Each returned `trip_id` is then expanded into a positioned marker via
[position-and-shape.md](position-and-shape.md).
</content>
