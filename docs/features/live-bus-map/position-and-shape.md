# Live bus map — position & shape

`GET /api/bus-position?tripId=...` → `ApiController::busPosition()` returns everything
the client needs to place and animate one vehicle.

## Response

```json
{
  "stops": [
    { "stop_id": "...", "stop_name": "...", "stop_lat": 45.4, "stop_lon": 12.3,
      "arrival_time": "07:35:00", "departure_time": "07:35:00",
      "stop_sequence": 3, "data_url": "..." }
  ],
  "shape": [
    { "lat": 45.40, "lng": 12.30, "sequence": 0, "dist_traveled": 0 }
  ]
}
```

- **stops** — ordered by `stop_sequence`, joined from `stop_times` + `stops`.
- **shape** — the refined polyline for the trip's `shape_id`, read from the
  `shapes_refined` table (ordered by `sequence`). Empty if the trip has no shape.

`400` if `tripId` is missing; `{"error": ...}` if no stops are found.

## Deriving the current position (client side)

`public/js/liveBusMap.js` uses the stop schedule to estimate where the vehicle is
**now**: it finds the segment between the last passed stop and the next upcoming stop
(by comparing the current time to the stops' times) and interpolates a point along the
`shape` geometry between them. Delay is estimated by comparing the schedule to the
reference time. Time helpers come from [`public/js/utils.js`](../../../public/js/utils.js).

## Shape data

The `shapes_refined` table is produced/cleaned by `scripts/refine_shapes.php` (see the
[GTFS pipeline](../gtfs-pipeline.md)); `dist_traveled` enables proportional placement
along the polyline.
</content>
