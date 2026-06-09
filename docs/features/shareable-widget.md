# Shareable widget

A self-contained, embeddable view of a single stop's upcoming passages that can be
dropped into an external website via an `<iframe>`.

- **Route:** `/widget` → `Controller::widget()`
- **View:** `app/views/widget.php`
- **Client JS:** `public/js/widget.js`

## URL parameters

| Param | Meaning |
|-------|---------|
| `stop` | Stop id (required) |
| `name` | Display name |
| `max` | Max number of passages to show |
| `theme` | `light` / `dark` |

## Embed-code generation

`widget.js` exposes `getWidgetUrl(stopId, stopName, options)` which builds the widget
URL with `URLSearchParams` (base defaults to `window.location.origin`). The UI on the
[stop details](stop-details/README.md) page uses it to produce a ready-to-copy
`<iframe>` snippet so users can share a stop board.

## Notes / known limitations

- The widget renders passages the same way as the stop page.
- Cross-origin embedding may require a CORS proxy for the upstream ACTV data — this
  is noted as a TODO in the project README.
</content>
