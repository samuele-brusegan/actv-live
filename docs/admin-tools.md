# Admin Tools & Logging

Questa sezione descrive gli strumenti a disposizione per il debug e la gestione avanzata dell'applicazione.

## 1. Sistema di Logging Centralizzato

Qualsiasi anomalia viene catturata dal service `app/services/Logger.php`.

### Tipi di Log
1.  **PHP_ERROR**: Errori standard di PHP (Warning, Notice).
2.  **EXCEPTION**: Eccezioni non catturate (`try-catch` globale).
3.  **JS_ERROR**: Errori JavaScript che avvengono nel browser dell'utente, inviati tramite l'endpoint `/api/log-js-error`.

### Visualizzazione
I log sono consultabili alla rotta `/admin/logs` (richiede accesso admin). Ogni log include:
- Messaggio di errore.
- File e riga.
- Stack trace (per eccezioni).
- **Context**: Un dump JSON che include lo User Agent dell'utente, l'URL visitato e eventuali parametri POST.

---

## 2. Time Machine (Registrazione & Playback)

La Time Machine risolve il problema del "tempo reale non testabile di notte". Permette di registrare il traffico di un pomeriggio e riprodurlo la sera.

### Registrazione (Heartbeat)
La registrazione avviene tramite "Heartbeat". Un servizio esterno (es. Cron-job.org) deve chiamare ogni minuto:
`https://tuo-dominio.it/api/tm/heartbeat?token=IL_TUO_TOKEN`

**Dettagli Tecnici**:
1. Il sistema controlla in `tm_sessions` se ci sono sessioni in stato `RECORDING`.
2. Per ogni fermata configurata nella sessione, chiama l'API ufficiale ACTV.
3. Salva l'intero JSON ricevuto nella tabella `tm_data`.

### Riproduzione (Playback)
Quando un utente attiva la Time Machine:
1. Imposta una data e ora "simulata".
2. Il frontend aggiunge un header o parametro alle richieste API.
3. Il backend, invece di chiamare ACTV, esegue questa query:
   ```sql
   SELECT data_json FROM tm_data 
   WHERE stop_id = ? 
   ORDER BY ABS(TIMESTAMPDIFF(SECOND, fetched_at, ?)) LIMIT 1
   ```
4. Viene restituito il dato registrato più vicino al momento richiesto.

---

## 3. Accesso Segreto
L'interfaccia admin non è linkata pubblicamente.
- **Attivazione**: 5 click rapidi sul footer della home page.
- **Logica**: Una volta sbloccato, appare l'icona dell'ingranaggio nell'header.
