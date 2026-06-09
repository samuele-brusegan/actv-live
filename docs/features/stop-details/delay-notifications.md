# Stop details — delay monitoring & notifications

Local (client-side) monitoring that warns the user when a bus on a watched stop is
running late. Implemented in `public/js/notifications.js`.

## Storage keys (`localStorage`)

| Key | Purpose |
|-----|---------|
| `notifications_enabled` | Master on/off toggle |
| `notification_delay_threshold` | Minimum delay (minutes) that triggers a notice |
| `monitored_stops` | Stops the user wants monitored |

## How it works

1. On enable, a Service Worker (`/sw.js`) is registered via `registerServiceWorker()`.
2. A timer polls every `CHECK_INTERVAL = 60000` ms (1 minute).
3. For each monitored stop it checks current delays; when a delay exceeds the
   configured threshold, a **local notification** is shown.

The polling handle is kept in `checkIntervalId` so it can be cleared when monitoring
is disabled.

## Scope & limitations

- These are **local** notifications driven by client-side polling — not true server
  push. Real push is listed as a TODO in the project README.
- Monitoring only runs while the app (or its Service Worker) is active in the browser.

## Related

- The same `/sw.js` registration is used by [offline support](../../frontend.md)
  (`public/js/offline.js`).
- Recorded delays also feed the [delay statistics](../delay-stats.md) page.
</content>
