# Admin

A minimal administrative area to log in, inspect the centralized error log, and view
live operational metrics.

- **Routes:** `/admin/login`, `/admin/logout`, `/admin/logs`, `/admin/dashboard`
- **Actions:** `Controller::adminLogin/adminLogout/logs/adminDashboard`
- **Auth gate:** `app/services/AdminAuth.php`
- **Logging:** `app/services/Logger.php`
- **Views:** `app/views/admin/{login,dashboard,logs}.php`
- **Client JS:** `public/js/adminDashboard.js`

This is a **large feature**; its sub-problems are documented separately:

- [authentication.md](authentication.md) — session login + CSRF protection.
- [error-logging.md](error-logging.md) — how PHP/JS errors are captured and stored.
- [dashboard.md](dashboard.md) — the metrics dashboard and the log viewer.

## Access control

Every protected action calls `AdminAuth::requireAuth()`, which redirects to
`/admin/login` when there is no authenticated session. Login validates the password
from `ENV['ADMIN_PASSWORD']`.
</content>
