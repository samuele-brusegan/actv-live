# Admin — authentication

Session-based login gate for `/admin/*`, implemented in
[`app/services/AdminAuth.php`](../../../app/services/AdminAuth.php).

## Configuration

The expected password is read from `ENV['ADMIN_PASSWORD']` (`.env`). If it is empty,
login always fails.

## API surface

| Method | Purpose |
|--------|---------|
| `check()` | `true` if `$_SESSION['is_admin']` is set |
| `attempt($password)` | Validate password and open a session |
| `requireAuth()` | Redirect to `/admin/login` if not authenticated |
| `logout()` | Clear the admin session flag |
| `csrfToken()` | Lazily generate/return the login CSRF token |
| `verifyCsrf($token)` | Constant-time check of a submitted token |

## Login flow (`Controller::adminLogin`)

1. On `POST`, first verify the CSRF token (`verifyCsrf`) — failure ⇒ "Sessione
   scaduta, riprova."
2. Then `attempt($_POST['password'])`:
   - compares with `hash_equals()` (constant-time, no early-exit timing leak);
   - on success calls `session_regenerate_id(true)` (prevents fixation) and sets
     `$_SESSION['is_admin'] = true`, then redirects to `/admin/dashboard`.
   - on failure ⇒ "Password non valida."
3. The login view receives a fresh `$csrf` token.

`logout()` simply unsets the session flag; `adminLogout` then redirects to the login
page.

## Security notes

- Constant-time comparisons (`hash_equals`) for both password and CSRF.
- Session id regenerated on successful login.
- CLI context skips `session_start()` (`ensureSession` guard).
</content>
