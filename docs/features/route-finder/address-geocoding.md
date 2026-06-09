# Route finder — address geocoding & walking legs

Origin and destination can be an **address / coordinate** instead of a stop. This is
resolved in `ApiController::planRoute()` together with
`RoutePlanner::findNearestStop()`.

## Input detection

`planRoute()` treats a `from`/`to` value as coordinates when it contains a comma and
parses as a valid `lat,lon` via the private `parseLatLon()` helper, which validates:

- exactly two comma-separated parts;
- both numeric;
- `-90 ≤ lat ≤ 90` and `-180 ≤ lon ≤ 180`.

The `stationSelector` page produces these `lat,lon` strings from its address search
(`public/js/stationSelector.js`).

## Nearest-stop lookup

`RoutePlanner::findNearestStop($lat, $lon)` scans all cached stops and returns the
closest one using the Haversine formula (`calculateGeoDistance`, Earth radius
6 371 000 m). It augments the stop with:

- `distance` — metres (rounded);
- `walking_time` — minutes, estimated at **5 km/h ≈ 83 m/min** (`ceil(dist / 83)`).

## Injecting walking legs

Planning then runs **stop-to-stop** between the resolved nearest stops, and the API
splices walking legs around the transit itinerary:

- **Start walk** (origin address → first stop): prepended to `legs`, the itinerary's
  `departure_time` is set to the original time and `duration` increased; the planning
  departure time is shifted by the walking time so the first ride is catchable.
- **End walk** (last stop → destination address): appended to `legs`, with the final
  `arrival_time` and `duration` extended by the walking time.

Walking legs use `type: "walking"` with `route_short_name: "Cammina"`, `stops_count: 0`,
plus `distance`, `duration` and from/to names.
</content>
