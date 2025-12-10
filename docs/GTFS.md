# GTFS Implementation Documentation

## Overview
This project uses a simplified and cached version of the GTFS (General Transit Feed Specification) data to provide offline-capable and fast route planning. The raw GTFS data is processed into JSON files stored in `data/gtfs/cache/`.

## Cache Structure

The cache is located in `data/gtfs/cache/` and consists of the following components:

### 1. `stops.json`
Contains a list of all stops indexed by `stop_id`.
```json
{
    "stop_id": {
        "name": "Stop Name",
        "lat": 45.123,
        "lon": 12.123
    }
}
```

### 2. `routes.json`
Contains a list of all routes indexed by `route_id`.
```json
{
    "route_id": {
        "route_short_name": "10",
        "route_long_name": "Route Description"
    }
}
```

### 3. `stop_routes_index.json`
A reverse index mapping each `stop_id` to a list of `route_id`s that pass through it. This is critical for finding common routes between two stops (direct connections) or finding transfer points.
```json
{
    "stop_id": ["route_id_1", "route_id_2"]
}
```

### 4. `routes/` Directory
Contains individual JSON files for each route (e.g., `route_10_UM.json`). Each file contains the full schedule for that route, organized by trips.
```json
{
    "trip_id": [
        {
            "stop_id": "stop_1",
            "arrival_time": "10:00:00",
            "departure_time": "10:00:00",
            "stop_sequence": 1
        },
        ...
    ]
}
```

## Route Planning Logic (`RoutePlanner.php`)

The `RoutePlanner` class uses these cache files to find connections:

1.  **Direct Connections**: It checks `stop_routes_index.json` for the origin and destination stops. If they share a `route_id`, it loads the specific route file and looks for trips where the origin comes before the destination and the departure time is after the requested time.
2.  **Transfers (1 hop)**: If no direct connection is found, it looks for an intermediate stop where a route from the origin intersects with a route to the destination.
3.  **Next Day Search**: If no results are found for the current day, the system automatically searches for trips starting from `00:00:00` of the next day. These results are marked with a `day_offset` flag.

## Limitations
-   **Calendar/Dates**: The current implementation assumes a static schedule (e.g., "Monday-Friday" or generic). It does not currently handle specific calendar dates, holidays, or day-of-week exceptions (e.g., `calendar.txt` and `calendar_dates.txt` from GTFS are not fully utilized in the runtime logic).
-   **Transfers**: Limited to 1 transfer to ensure performance.
