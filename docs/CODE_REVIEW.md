# Code Review — actv-live

Report dei problemi individuati durante la review della codebase.
Stato: `[ ]` da fare · `[~]` in corso · `[x]` risolto

> Nota: la suite di test (217 Jest + 15 Pest) passa, ma **non** copre i bug critici #1, #2, #3.

---

## Bug critici (funzionalità rotte)

### [x] #1 — `Logger` mischia API PDO e mysqli (logging totalmente rotto)
> RISOLTO: `log()` riscritto su PDO via `databaseConnector::query()`, racchiuso in
> try/catch (`\Throwable` → `error_log`), uso del singleton `getInstance()`.
- File: `app/services/Logger.php` (righe ~19-39)
- `databaseConnector::$db` è un oggetto **PDO**, ma `Logger` lo usa con metodi **mysqli**
  (`bind_param`, `$stmt->close()`). Ogni `Logger::log()` lancia un `Error`.
- Aggravante: `Logger` è registrato come `set_error_handler`/`set_exception_handler`
  in `app/bootstrap.php` (righe 26-29) → ogni errore PHP genera un secondo errore.
- Impatto: logging DB e endpoint `/api/log-js-error` inutilizzabili.
- Fix: riscrivere `log()` usando PDO (prepared statement + bind via `execute([...])`),
  rimuovere `getMysqli()`.

### [x] #2 — `/api/addFavorite` → fatal error (file mancante)
> RISOLTO: route e metodo `favorite()` rimossi (codice morto; i preferiti sono
> gestiti client-side via `localStorage` in `stop.js`, nessuna chiamata al backend).
- File: `app/controllers/ApiController.php` (righe 94-96)
- `favorite()` fa `require_once .../app/models/addToFavorites.php` ma il file NON esiste.
- Fix: implementare il model mancante oppure rimuovere route+metodo se i preferiti sono
  gestiti solo lato client (vedi `public/js/stop.js`, usa `localStorage`).

### [x] #3 — Chiavi `.env` errate (pagina admin log e script rotti)
> RISOLTO: uniformato a `DB_USER`/`DB_PASS` in `Controller::logs()`,
> `scripts/setup_db.php`, `scripts/update_stops_dataurl.php`.
- File: `app/controllers/Controller.php` (riga 72), `scripts/setup_db.php` (riga 9),
  `scripts/update_stops_dataurl.php` (riga 8)
- Usano `ENV['DB_USERNAME']`/`ENV['DB_PASSWORD']` ma `.env` definisce `DB_USER`/`DB_PASS`.
- Fix: uniformare a `DB_USER`/`DB_PASS`.

---

## Sicurezza

### [x] #4 — Pagine admin senza autenticazione
> RISOLTO: nuovo servizio `app/services/AdminAuth.php` (sessione PHP, CSRF token,
> `hash_equals`, `session_regenerate_id`). Aggiunte route `/admin/login` e
> `/admin/logout`, view `app/views/admin/login.php`, guard `AdminAuth::requireAuth()`
> su `logs()` e `adminDashboard()`. Password in `.env` come `ADMIN_PASSWORD`
> (default `changeme` — DA CAMBIARE). Link logout aggiunto alla pagina log.
- File: `public/routes.php` (righe 22-23), `app/views/admin/*`
- `/admin/logs` e `/admin/dashboard` pubbliche, nessun controllo sessione/ruolo.
- Fix: gate di autenticazione (sessione admin) prima del rendering.

### [x] #5 — XSS riflessa nel 404 del Router
> RISOLTO: rimosso l'echo di `$path`/`$url`/`PHP_URL_PATH` (input utente non
> sanitizzato); resta solo il messaggio statico "Pagina non trovata!".
- File: `app/Router.php` (righe 38-47)
- `echo $path; echo $url;` (input utente) senza escaping.
- Fix: `htmlspecialchars()` o pagina 404 statica.

### [x] #6 — Information disclosure negli errori
> RISOLTO: `planRoute`/`stopLines` e `gtfsIdentify.php` ora restituiscono messaggi
> generici al client e loggano il dettaglio server-side via `Logger::log()`. Rimosse
> dalle risposte la query SQL (`queryBuilder`) e l'URL completo della richiesta.
- File: `app/views/gtfsIdentify.php`, `ApiController::planRoute`/`stopLines`
- Espongono `$e->getMessage()`, query SQL complete e URL della richiesta al client.
- Fix: messaggio generico al client, dettaglio solo nei log lato server.

### [x] #7 — TLS verification disabilitata
> RISOLTO: `CURLOPT_SSL_VERIFYPEER => true` + `SSL_VERIFYHOST => 2`. Inoltre (#14)
> `fetchData` ora usa il parametro `$timeout` (via `*_TIMEOUT_MS`), chiude l'handle
> con `curl_close` e logga gli errori cURL. NB: se l'endpoint ACTV avesse problemi
> di certificato, valutare il bundle CA del server.
- File: `app/controllers/Controller.php` (righe 40-42)
- `CURLOPT_SSL_VERIFYPEER => false` (rischio MITM).
- Fix: rimuovere/portare a `true` in produzione.

---

## Codice morto / residui di altri progetti

### [x] #8 — Codice "ClasseViva/GradeCraft" eseguito ad ogni richiesta
> RISOLTO: rimosse `checkSessionExpiration()` e `loginRequest()` da `functions.php`,
> rimossa la chiamata in `index.php` e la funzione morta `checkSessionExpirationCLI()`
> in `bootstrap.php`. `associativeArrayToTable()` mantenuta (usata da più view/model).
> Corretti gli header copyright "GradeCraft" → "actv-live".
- File: `public/functions.php` (righe 8-53), header copyright in più file
- `checkSessionExpiration()` + `loginRequest()` appartengono ad altro progetto, girano
  su ogni request via `index.php`; `loginRequest()` chiama `URL_PATH/login` inesistente.
- Fix: rimuovere/neutralizzare e correggere gli header copyright "GradeCraft".

### [x] #9 — Grossi blocchi di codice commentato
> RISOLTO: rimossi i blocchi commentati morti in `linesShapes()` (~100 righe) e la
> riga stantia `//$results = $stmt->fetchAll(...)`.
- File: `app/controllers/ApiController.php` (`linesShapes()`, ~righe 343-440)

### [x] #10 — `public/test-merge.html` lasciato in produzione
> RISOLTO: file rimosso (`git rm`), nessun riferimento nel codice.

### [x] #11 — `console.log`/debug residui
> RISOLTO: rimossi 21 statement di debug attivi (`console.log`) da `stop.js`,
> `tripDetails.js`, `liveBusMap.js` (incluso uno multi-linea). Preservati
> `console.error`/`warn` e le righe già commentate. Jest: 217/217 OK.
- File: `public/js/stop.js` (+ TODO riga 111), `public/js/liveBusMap.js`, `public/js/tripDetails.js`

---

## Best practice / bug minori

### [x] #12 — Condizione sempre falsa
> RISOLTO: rimosso il guard morto `!isset($_GET) && !isset($_GET['return'])`.
> In `gtfsTripBuilder()`/`gtfsStopTranslater()` sostituito con validazione reale di
> `trip_id` (400 se mancante); in `stops()` semplicemente rimosso (nessun param richiesto).
- `if (!isset($_GET) && !isset($_GET['return']))` (`$_GET` sempre definito) in
  `ApiController::stops()`, `gtfsTripBuilder()`, `gtfsStopTranslater()`. Il `die` non scatta mai.

### [x] #13 — Singleton incoerente + ritorno `connect()` ignorato
> RISOLTO: `query()` ora lancia `RuntimeException` chiara se `$this->db` è null
> (invece di un fatal "call on null"); `Controller::logs()` usa `getInstance()`.
- File: `app/models/databaseConnector.php` (righe 18-44)
- Esiste `getInstance()` ma `Logger`/`Controller::logs()` usano `new databaseConnector()`.
- `connect()` ritorna `false` silenziosamente; nessun chiamante controlla → fatal "call on null".

### [x] #14 — `fetchData` ignora `$timeout` e non chiude curl
> RISOLTO insieme a #7.
- File: `app/controllers/Controller.php` (righe 37-49)
- `CURLOPT_TIMEOUT` hardcoded a 1s; `curl_close` commentato (leak handle).

### [x] #15 — `seamsValidSQL` refuso + metodo mai usato
> RISOLTO: metodo rimosso (usato solo dai test); rimossi anche i 2 test relativi.
- File: `app/models/databaseConnector.php` (righe 54-67)

### [x] #16 — Manca `.env.example` e validazione input
> RISOLTO: creato `.env.example` (DB_* + ADMIN_PASSWORD); aggiunto helper
> `ApiController::parseLatLon()` che valida formato/numero/range delle coordinate
> in `planRoute` (input invalido ignorato in modo sicuro).
- Es. `explode(',', $origin)` in `planRoute` non valida formato/numero coordinate.
