# Sezione Amministrazione
Questo documento descrive l'architettura e l'implementazione pratica della sezione di amministrazione dell'applicazione ACTV Live.

## 1. Accesso Nascosto
L'accesso alla sezione admin è protetto da un meccanismo nascosto per evitare l'esposizione diretta dell'interfaccia di gestione a utenti occasionali.

- **Meccanismo**: L'utente deve cliccare 5 volte rapidamente (entro un intervallo di 500ms tra i click) sulla sezione del footer (#footer-licence).
- **Implementazione**: Il codice JavaScript in `home.php`gestisce il conteggio dei click e rivela un'icona nascosta nell'header (#admin-secret-link) che punta alla Time Machine.

```js
// Estratto da home.php
let footerClicks = 0;
let lastClickTime = 0;
document.getElementById('footer-licence').addEventListener('click', (e) => {
    const currentTime = new Date().getTime();
    if (currentTime - lastClickTime < 500) {
        footerClicks++;
    } else {
        footerClicks = 1;
    }
    lastClickTime = currentTime;
    if (footerClicks >= 5) {
        const adminLink = document.getElementById('admin-secret-link');
        adminLink.classList.remove('d-none');
        alert("Admin access unlocked!");
    }
});
```

## 2. Gestione Eccezioni e Bug (Logs)
Il sistema implementa un logger centralizzato che registra sia gli errori lato server (PHP) che quelli lato client (JavaScript).

### Architettura Server-side
- **Service**: `Logger.php` gestisce l'inserimento nel database (tabella `logs`).
- **Integrazione**: In `bootstrap.php`, vengono impostati gli handler globali:
    - `set_error_handler`: Cattura i warning e gli errori PHP standard.
    - `set_exception_handler`: Cattura le eccezioni PHP non gestite.
### Log Client-side (JavaScript)
Le eccezioni JavaScript vengono inviate al server tramite una chiamata fetch all'endpoint:

- Rotta: `/api/log-js-error`
- Controller: `ApiController::logJsError()`

### Visualizzazione Admin
L'interfaccia in `logs.php` permette di visualizzare gli ultimi 100 log, con filtri per tipo (EXCEPTION, PHP_ERROR, JS_ERROR).

## 3. Time Machine
La Time Machine permette di registrare i passaggi in tempo reale per determinate fermate e "riprodurli" in un momento successivo per simulazione o debug.

### Database Schema
- `tm_sessions`: Memorizza i metadati della sessione (nome, orario di inizio/fine, lista fermate, stato).
- `tm_data`: Memorizza il payload JSON grezzo ricevuto dall'API ACTV per ogni fermata durante la registrazione.

### Processo di Registrazione
Esistono due modi per gestire la registrazione dei dati:

1.  **Metodo API Heartbeat (Consigliato)**: Uno speciale endpoint `/api/tm/heartbeat` che, se richiamato, esegue un ciclo di registrazione. Questo metodo è "disaccoppiato" dall'host e permette di innescare la registrazione dall'esterno.
2.  **Script Locale**: Lo script PHP `record_tm.php` può essere eseguito via cron job direttamente sul server.

### Setup della Registrazione (Heartbeat)
Per configurare la registrazione senza accesso SSH o cron di sistema:

1.  **Configurazione .env**: Aggiungi una chiave di sicurezza al tuo file `.env`:
    ```ini
    TM_HEARTBEAT_TOKEN=una_stringa_segreta_e_casuale
    ```
2.  **Servizio Esterno**: Utilizza un servizio di "Cron-job" esterno (come [Cron-job.org](https://cron-job.org/)) o un sistema di monitoraggio (UptimeRobot).
3.  **Configurazione URL**: Imposta il servizio per richiamare ogni minuto il seguente URL:
    ```
    https://tuo-dominio.test/api/tm/heartbeat?token=IL_TUO_TOKEN_SEGRETO
    ```
4.  **Verifica**: L'endpoint restituirà un JSON con il riepilogo delle operazioni effettuate.

### Setup Alternativo (Cron di Sistema)
Se hai accesso SSH, puoi aggiungere questa riga al crontab (`crontab -e`):
```cron
* * * * * /usr/bin/php /percorso/assoluto/scripts/record_tm.php >> /percorso/assoluto/tm_cron.log 2>&1
```

### Riproduzione (Playback)
Quando la Time Machine è attiva nel client, le richieste di dati per le fermate vengono deviate a:
- **Endpoint**: `/api/tm/simulated-data?stopId=ID&time=YYYY-MM-DD HH:MM:SS`
- **Logica**: Il database cerca il dato registrato più vicino temporalmente (tramite `ORDER BY ABS(TIMESTAMPDIFF(...))`) per simulare fedelmente il passaggio dei bus in quel momento.