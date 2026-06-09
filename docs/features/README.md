# Features Documentation

This directory documents **ACTV Live** feature by feature using a tree structure:

- **Small features** → a single Markdown file.
- **Large features** (multiple sub-problems) → a folder with a generic `README.md`
  overview plus one file per sub-problem.

For higher-level / topic-oriented documentation (architecture, database schema,
API conventions, design system) see the files in the parent [`docs/`](../) folder.

## Tree

```
docs/features/
├── README.md                  ← you are here (feature index)
├── home.md                    Home page (nearby + favourite stops, announcements)
├── stop-list.md               Browsable catalog of all stops
├── lines-map.md               Geographic view of route paths
├── trip-details.md            Stop-by-stop view of a single trip
├── delay-stats.md             Historical delay analytics (local data)
├── shareable-widget.md        Embeddable stop widget (iframe)
├── gtfs-pipeline.md           GTFS download / parse / JSON cache pipeline
│
├── route-finder/              ▸ LARGE — journey planner
│   ├── README.md              Overview & multi-page flow
│   ├── planning-algorithm.md  Direct + 1-transfer search, next-day fallback
│   ├── address-geocoding.md   Coordinates → nearest stop + walking legs
│   └── results-and-options.md Optimization, comparison, return trip
│
├── stop-details/              ▸ LARGE — real-time stop board
│   ├── README.md              Overview
│   ├── realtime-passages.md   Live passages + GTFS scheduled fallback
│   ├── delay-notifications.md Local delay monitoring & notifications
│   └── favorites.md           localStorage favourites
│
├── live-bus-map/              ▸ LARGE — real-time fleet map
│   ├── README.md              Overview
│   ├── buses-running-now.md   /api/gtfs-bnr ±30 min query
│   ├── position-and-shape.md  /api/bus-position stops + shape interpolation
│   └── async-loading.md       Concurrent loading & client-side filtering
│
└── admin/                     ▸ LARGE — admin area
    ├── README.md              Overview & auth gate
    ├── authentication.md      Session login + CSRF
    ├── error-logging.md       Centralized PHP/JS log system
    └── dashboard.md           Live metrics dashboard & log viewer
```

## Feature index

| Feature | Type | Route(s) | Entry point |
|---------|------|----------|-------------|
| [Home](home.md) | small | `/` | `app/views/home.php`, `public/js/script-home.js` |
| [Stop list](stop-list.md) | small | `/stopList` | `app/views/stopList.php` |
| [Lines map](lines-map.md) | small | `/lines-map` | `app/views/linesMap.php`, `public/js/linesMap.js` |
| [Trip details](trip-details.md) | small | `/trip-details` | `app/views/tripDetails.php`, `public/js/tripDetails.js` |
| [Delay statistics](delay-stats.md) | small | `/delay-stats` | `app/views/delayStats.php`, `public/js/delayHistory.js` |
| [Shareable widget](shareable-widget.md) | small | `/widget` | `app/views/widget.php`, `public/js/widget.js` |
| [GTFS pipeline](gtfs-pipeline.md) | small | (CLI) | `app/services/GTFSParser.php`, `scripts/parse_gtfs.php` |
| [Route finder](route-finder/README.md) | large | `/route-finder`, `/station-selector`, `/route-results`, `/route-details` | `app/services/RoutePlanner.php` |
| [Stop details](stop-details/README.md) | large | `/aut/stops/stop` | `app/views/stop.php`, `public/js/stop.js` |
| [Live bus map](live-bus-map/README.md) | large | `/live-map` | `app/views/liveBusMap.php`, `public/js/liveBusMap.js` |
| [Admin](admin/README.md) | large | `/admin/*` | `app/services/AdminAuth.php`, `app/services/Logger.php` |

All routes are declared in [`public/routes.php`](../../public/routes.php) and dispatched
by [`app/Router.php`](../../app/Router.php). Page actions live in
[`app/controllers/Controller.php`](../../app/controllers/Controller.php); JSON endpoints
in [`app/controllers/ApiController.php`](../../app/controllers/ApiController.php).
</content>
