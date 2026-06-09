# Home

The landing page of the app. It surfaces the stops the user is most likely to
need right now and provides navigation to every other feature.

- **Route:** `/` → `Controller::index()`
- **View:** `app/views/home.php`
- **Client JS:** `public/js/script-home.js`
- **Component:** `public/components/StopCard.js`

## What it does

- **Favourite stops** — read from `localStorage` (`favorite_stops`) and rendered as
  `StopCard` items. Favourites are managed from the [stop details](stop-details/favorites.md) page.
- **Nearby stops** — uses the browser Geolocation API; the closest stops are computed
  client-side with a Haversine distance (see [`public/js/utils.js`](../../public/js/utils.js)).
- **Quick navigation** — links to route finder, live map, lines map and stop list.
- **Theme toggle** — light/dark mode handled by [`public/js/theme.js`](../../public/js/theme.js)
  and persisted in `localStorage` (`theme`).

## Notes

`Controller::index()` enables verbose PHP error logging to `php_error.log` before
including the view. No server-side data is fetched for the home page itself — all
dynamic content (favourites, nearby stops) is built on the client and hydrated from
`/api/stops` when needed.
</content>
