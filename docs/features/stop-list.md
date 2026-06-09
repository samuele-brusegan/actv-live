# Stop list

A browsable catalog of every stop, with a flat list view and a zone-grouped view.

- **Route:** `/stopList` → `Controller::stops()`
- **View:** `app/views/stopList.php`
- **Components:** `public/components/StopCard.js`, `public/components/StopListItem.js`

## Data source

`Controller::stops()` fetches the stop catalog directly from the ACTV backend:

```
https://oraritemporeale.actv.it/aut/backend/page/stops
```

via the private `Controller::fetchData()` helper (a short-timeout cURL wrapper that
logs failures through `Logger` and returns `[]` on error). The decoded payload is
passed to the view as `$stations`.

> The internal JSON endpoint `/api/stops` (`ApiController::stops()`) returns the
> stops stored in the local MySQL `stops` table and is used by other features
> (route finder, station selector) for client-side search.

## Notes

The view exposes tabs to switch between the plain list and a zone-based grouping.
Each entry is rendered with the shared `StopCard` / `StopListItem` components so the
look matches the home page.
</content>
