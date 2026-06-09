# Route finder — results, optimization & return trip

Covers what happens after itineraries are computed: how they are ranked by the
user's chosen criterion, compared, and how round trips are handled. Client logic
lives in `public/js/routeFinder.js`, `routeResults.js` and `routeDetails.js`.

## Optimization criterion

The user picks one of three criteria on the `/route-finder` page; it is stored in
`searchState.optimize` and sent as `?optimize=` to `/api/plan-route`. The API
validates it (falling back to `time`) and, when it is **not** `time`, re-sorts the
already-ranked routes:

| `optimize` | Sort behaviour (`planRoute()`) |
|------------|--------------------------------|
| `time` (default) | Keep the planner's weighted score (earliest arrival, transfer penalty, next-day last) |
| `transfers` | Direct rides first, then by shorter duration |
| `walking` | Least total walking distance (sum of walking-leg `distance`), then duration |

The response echoes the applied criterion: `{ "optimize": "...", "routes": [...] }`.

## Results & comparison

`/route-results` (`routeResults.js`) renders the ranked list. Each card summarises
departure/arrival, duration, transfer count and the line badges from `legs`.
Itineraries can be viewed side by side for comparison, and the user can open one in
`/route-details` (`routeDetails.js`) for the full leg-by-leg timeline, including
walking legs and a map.

## Return trip

`routeFinder.js` keeps return-trip state in `searchState`:

```js
returnTrip:   false,        // toggle
returnHour:   (now + 1) % 24,
returnMinute: nearest 5-min
```

When enabled, the outbound and return legs are planned independently (a second
`/api/plan-route` call with origin/destination swapped and the return time), so each
direction is optimized on its own. The time-picker modal tracks which time it is
editing via `timeModalTarget` (`'departure'` | `'return'`).
</content>
