# Admin — centralized error logging

All PHP and JavaScript errors are funneled into a single MySQL `logs` table via
[`app/services/Logger.php`](../../../app/services/Logger.php), and browsed from the
admin [log viewer](dashboard.md).

## `logs` table columns

`type`, `message`, `file`, `line`, `stack_trace`, `context` (JSON), `created_at`.

## Capturing errors

`Logger` is wired as the global handler in `app/bootstrap.php`:

- `phpErrorHandler($errno, $errstr, $errfile, $errline)` — logs as `PHP_ERROR`
  (respecting `error_reporting()`), then returns `false` so the default PHP handler
  still runs.
- `exceptionHandler($exception)` — logs uncaught exceptions as `EXCEPTION` with the
  full trace.
- `Logger::log($type, $message, $file, $line, $stackTrace, $context)` — the generic
  writer used directly across the codebase (e.g. cURL failures in
  `Controller::fetchData`, errors in `ApiController::planRoute`).

### JavaScript errors

The client posts errors to `POST /api/log-js-error` (`ApiController::logJsError`),
which stores them as `JS_ERROR` with the page URL, line, stack and the request's
`User-Agent` in `context`. A missing/invalid body returns `400`.

## Robustness

`Logger::log()` wraps its DB write in `try/catch` and, on failure, falls back to
`error_log()`. This is deliberate: since `Logger` is *also* the global error handler,
a throwing logger would cascade into an infinite/again-failing error loop.

## Connection

`Logger` lazily creates a `databaseConnector` singleton using the `ENV` DB
credentials, shared across all log writes for the request.
</content>
