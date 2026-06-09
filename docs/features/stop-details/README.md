# Stop details

The real-time arrivals board for a single stop: upcoming passages with live delays,
falling back to the GTFS schedule, plus favourites, delay monitoring and a shareable
widget.

- **Route:** `/aut/stops/stop?id=...&name=...` → `Controller::stop()`
- **View:** `app/views/stop.php`
- **Client JS:** `public/js/stop.js` (+ `notifications.js`, `delayHistory.js`, `widget.js`)
- **Component:** `public/components/StopCard.js`

This is a **large feature**; its sub-problems are documented separately:

- [realtime-passages.md](realtime-passages.md) — fetching live passages and the GTFS
  scheduled fallback (`/api/stop-lines`, `/api/gtfs-passages`).
- [delay-notifications.md](delay-notifications.md) — local delay monitoring and
  notifications for favourite stops.
- [favorites.md](favorites.md) — `localStorage`-based favourite stops.

## Page parameters

`stop.js` reads `id` and `name` from the query string. The controller action itself
is a thin include of `stop.php`; all data is fetched client-side.

## Related features

- Observed delays feed the [delay statistics](../delay-stats.md) page.
- The [shareable widget](../shareable-widget.md) reuses the same passage rendering.
</content>
