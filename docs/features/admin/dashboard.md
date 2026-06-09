# Admin — dashboard & log viewer

The two authenticated admin pages.

## Log viewer — `/admin/logs`

`Controller::logs()` (after `requireAuth()`):

- connects to the DB via `databaseConnector`;
- optional `?type=` filter (e.g. `PHP_ERROR`, `EXCEPTION`, `JS_ERROR`);
- query: `SELECT * FROM logs [WHERE type = ?] ORDER BY created_at DESC LIMIT 100`;
- renders `app/views/admin/logs.php`.

This is the read side of the [error logging](error-logging.md) system: it lets an
admin browse the latest 100 entries and drill into stack traces / context.

## Metrics dashboard — `/admin/dashboard`

`Controller::adminDashboard()` simply renders `app/views/admin/dashboard.php` (after
`requireAuth()`); the numbers are computed client-side in
`public/js/adminDashboard.js`.

The dashboard reuses the live-map data feed: it queries
[`/api/gtfs-bnr`](../live-bus-map/buses-running-now.md) (and related endpoints) to
derive operational metrics such as the count of buses running now, average/max delay,
and GPS/positioning coverage, then renders them as summary cards/charts.

## Notes

The admin area is intentionally minimal (no user accounts, single shared password —
see [authentication.md](authentication.md)).
</content>
