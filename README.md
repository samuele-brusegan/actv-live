## Licenza
Questo progetto è rilasciato sotto la licenza MIT. Vedere il file [LICENSE](LICENSE.md) per maggiori dettagli.

## Requisiti
- Node.js >= 20.x
- Composer >= 2.9.5
- PHP >= 8.4 con estensioni `curl` e `pdo_mysql`
- Estensione PHP `zip` (`ZipArchive`) oppure comando di sistema `unzip`
- Comando `crontab` e servizio cron attivo per la pianificazione automatica
- MySQL o MariaDB

Su Debian/Ubuntu:

```bash
sudo apt install cron php8.4-cli php8.4-curl php8.4-mysql php8.4-zip unzip
sudo systemctl enable --now cron
```

Nei container basati sull'immagine PHP ufficiale:

```dockerfile
RUN apt-get update \
    && apt-get install -y --no-install-recommends cron libcurl4-openssl-dev libzip-dev unzip \
    && docker-php-ext-install curl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*
```

Nei container il demone cron deve essere avviato dal processo di init o dal
supervisor usato dallo stack.

Verificare che almeno uno dei due metodi di estrazione sia disponibile:

```bash
php -r 'var_dump(class_exists("ZipArchive"));'
command -v unzip
```

## Configurazione Locale
Per configurare il progetto, è necessario creare un file .env con le seguenti variabili:

```
DB_HOST=
DB_USER=
DB_PASS=
DB_NAME=
```

Eseguire i seguenti comandi per installare le dipendenze:
```bash
npm install
composer install
```

## Configurazione Docker

[non ancora implementato]

## TODO / Da fare

### 🔴 Priorità alta
- [ ] **Notifiche push reali**: l'attuale implementazione (`public/js/notifications.js`) fa polling locale dell'API ACTV e mostra notifiche solo a tab aperto. Per vere push in background servono:
  - Generazione e configurazione chiavi VAPID
  - Endpoint `/api/push/subscribe` per salvare le subscription dei client
  - Cron/job lato server che pusha quando rileva ritardi
  - Chiamata `pushManager.subscribe()` in `notifications.js`
  - In alternativa: rinominare la feature in "Avvisi ritardi (mentre l'app è aperta)"

### 🟡 Priorità media
- [ ] **Widget embed CORS**: `app/views/widget.php` chiama direttamente `https://oraritemporeale.actv.it/...`. In contesti embed su domini terzi potrebbe rompersi. Valutare proxy `/api/widget-passages` con CORS aperto.
- [ ] **Format adapter `/api/gtfs-passages`**: in `public/js/stop.js:111` c'è un `//todo:correct format` — il fallback locale ritorna le righe raw di `stop_times` invece del formato `{line, destination, time, real}` di ACTV. Serve un mapper.
- [ ] **Rimuovere `console.log` di debug** in produzione (sono volutamente lasciati per ora):
  - `public/js/stop.js:237`
  - `public/js/liveBusMap.js:317, 324, 391, 446`
  - `app/views/stopList.php:163`

### 🟢 Pulizia / quality
- [ ] **CSS empty rulesets** pre-esistenti in `public/css/stop.css` (linee 2, 58, 79)
- [ ] **Documentazione Docker** (sezione sopra è vuota)
- [ ] **Pre-cache GTFS in SW**: la funzione `cacheGtfsData()` in `sw.js` è già pronta ma nessuno invia il messaggio `CACHE_GTFS` al SW
- [ ] **Bottone widget in home**: la feature widget è scoperta solo dal pulsante share nella pagina fermata; valutare punto di accesso più visibile
