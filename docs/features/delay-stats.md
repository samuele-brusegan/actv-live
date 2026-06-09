# Delay statistics

Historical delay analytics built entirely from data observed **locally** in the
user's browser while they use the app.

- **Route:** `/delay-stats` → `Controller::delayStats()`
- **View:** `app/views/delayStats.php`
- **Client JS:** `public/js/delayHistory.js`

## How data is collected

There is no server-side delay database. While browsing real-time passages (e.g. on
the [stop details](stop-details/realtime-passages.md) page), observed delays are
recorded into `localStorage`:

- Storage key: `delay_history`
- Capped at `MAX_HISTORY_ENTRIES = 500` records (oldest pruned)
- Each record: `{ line, destination, stop, stopName, delay, timestamp }`

## What it shows

`delayHistory.js` aggregates the recorded records and the view renders statistics
such as average/worst delay broken down **by line, by stop, and by time-of-day band**.

## Limitations

- Data is per-device and per-browser (not shared, not synced).
- Statistics are only as complete as the pages the user has visited.
</content>
