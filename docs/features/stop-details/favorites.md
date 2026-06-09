# Stop details — favourites

Users can bookmark stops; favourites are stored locally and surfaced on the
[home page](../home.md).

- **Storage:** `localStorage` key `favorite_stops` (JSON array).
- **Logic:** `public/js/stop.js` (`getFavorites()` and the add/remove toggles).

## Behaviour

- `getFavorites()` reads and parses `favorite_stops`, returning `[]` on any parse
  error (defensive `try/catch`).
- The stop page exposes a toggle to add/remove the current stop.
- The home page reads the same key to render favourite `StopCard`s.

## Notes

Favourites are per-device/per-browser (no server-side account). The favourites list
is also what the [delay notifications](delay-notifications.md) feature can monitor.
</content>
