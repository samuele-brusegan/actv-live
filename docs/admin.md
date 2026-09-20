# Area amministrativa

L’area amministrativa di ACTV Live è una superficie protetta da sessione per
monitorare l’applicazione, leggere i log, aggiornare i dati GTFS e ispezionare i
feed GTFS-RT.

## Pagine disponibili

| Pagina | Scopo |
|---|---|
| `/admin/login` | Login con password e token CSRF |
| `/admin/dashboard` | Metriche operative della flotta e tabella dettagli |
| `/admin/logs` | Ultimi 100 log PHP, eccezioni e JavaScript |
| `/admin/feedback` | Feedback ricevuti, filtrabili per categoria e stato |
| `/admin/gtfs-update` | Stato, avvio e pianificazione dell’importazione GTFS |
| `/admin/gtfs-rt-inspector` | Ispezione raw e decodifica locale dei feed GTFS-RT |
| `/admin/logout` | Chiusura della sessione admin |

Le pagine sono registrate in [`public/routes.php`](../public/routes.php) e
richiedono `AdminAuth::requireAuth()`, tranne login e logout.

## Autenticazione e CSRF

`app/services/AdminAuth.php` legge `ADMIN_PASSWORD` dal file `.env`.
All’accesso riuscito:

1. verifica il token CSRF del form;
2. confronta la password con `hash_equals()`;
3. rigenera l’identificatore di sessione;
4. imposta `$_SESSION['is_admin']` e reindirizza alla dashboard.

Le API amministrative richiedono il cookie di sessione. Le richieste `POST`
(`/api/admin/gtfs-update/config` e `/api/admin/gtfs-update/start`) devono
includere anche il token CSRF nel body JSON o nel form.

## Logging

`app/services/Logger.php` registra nella tabella `logs`:

- `PHP_ERROR` per warning ed errori PHP;
- `EXCEPTION` per eccezioni non gestite;
- `JS_ERROR` per errori inviati dal frontend tramite `/api/log-js-error`.

La pagina `/admin/logs` supporta `?type=PHP_ERROR`, `?type=EXCEPTION` e
`?type=JS_ERROR`, e mostra messaggio, file, riga, contesto e stack trace quando
disponibili.

## Aggiornamento GTFS

La pagina `/admin/gtfs-update` usa le API:

- `GET /api/admin/gtfs-update/status`;
- `POST /api/admin/gtfs-update/config`;
- `POST /api/admin/gtfs-update/start`.

Il processo è gestito da `app/services/GtfsUpdateManager.php`, usa un lock per
impedire esecuzioni concorrenti e pubblica cache e tabelle solo dopo la
validazione. La pianificazione settimanale aggiorna il crontab dell’utente che
esegue PHP-FPM e usa UTC+00.

## GTFS-RT Inspector

`/admin/gtfs-rt-inspector` richiama
`GET /api/admin/gtfs-rt-inspector?service=automobilistico|navigation&kind=vehicles|updates`.
Mostra payload protobuf in Base64, anteprima esadecimale e record decodificati
dal decoder locale `app/services/GtfsRealtime.php`.

## Nota sulla vecchia Time Machine

Le tabelle `tm_sessions` e `tm_data` create dallo script storico
`scripts/setup_db.php` sono mantenute per compatibilità dello schema, ma le
vecchie rotte `/api/tm/*`, gli heartbeat e il playback non sono registrati nel
router corrente. Non devono essere considerati funzionalità attive.
