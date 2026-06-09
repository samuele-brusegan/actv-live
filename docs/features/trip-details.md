# Trip details

Stop-by-stop view of a single trip (a specific run of a line): all stops in
sequence with their scheduled times.

- **Route:** `/trip-details` → `Controller::tripDetails()`
- **View:** `app/views/tripDetails.php`
- **Client JS:** `public/js/tripDetails.js`
- **APIs:** `/api/gtfs-builder` → `ApiController::gtfsTripBuilder()`, and
  `/api/trip-stops` → `ApiController::tripStops()`

## APIs

### `/api/gtfs-builder?trip_id=...`
Returns the ordered stops for a trip by joining `stops` and `stop_times` on
`stop_id`, ordered by `stop_sequence`. Consecutive duplicate stops are collapsed.
Responds `400` if `trip_id` is missing.

### `/api/trip-stops?line=...&dest=...`
Resolves a trip from a **line short name** (+ optional destination), then returns its
stops. Trip resolution order:

1. `trips.trip_headsign LIKE %dest%`
2. otherwise, the trip whose **last** stop name `LIKE %dest%`
3. otherwise, the first trip of the route

Stop times are formatted to `HH:MM`. Responds `404` if the route or a trip cannot
be found.

## Related

- `gtfs-stop-translater` (`/api/gtfs-stop-translater`) returns the same trip stops
  ordered by `arrival_time` — used when translating raw GTFS times.
</content>
