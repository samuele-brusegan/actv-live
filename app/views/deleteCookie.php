<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy e dati - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/privacy.css">
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="Torna alla home">←</a>
        <h1 class="header-title">Privacy e dati</h1>
        <p class="header-subtitle">Cosa salviamo, dove, e come eliminarlo.</p>
    </header>
    <main class="privacy-page">

        <section class="privacy-card">
            <h2>Dati salvati sul tuo dispositivo</h2>
            <p>Tutto ciò che l'app memorizza resta nel tuo browser (localStorage), non viene inviato ai nostri server:</p>
            <ul class="privacy-list">
                <li><strong>favorite_stops</strong> — le tue fermate preferite</li>
                <li><strong>recent_stops</strong> — le fermate viste di recente</li>
                <li><strong>route_origin / route_destination / route_*</strong> — l'ultimo viaggio pianificato</li>
                <li><strong>theme</strong> — tema chiaro o scuro</li>
                <li><strong>home_tutorial_seen</strong> — flag del tour iniziale</li>
                <li><strong>actv-*-service</strong> — il servizio selezionato nelle mappe (auto/navigazione)</li>
                <li><strong>cookie_consent_accepted</strong> — il consenso ai cookie</li>
            </ul>
            <div class="privacy-actions">
                <button class="pv-btn pv-btn-danger" id="pv-clear-local">Elimina dati locali</button>
                <button class="pv-btn pv-btn-ghost" id="pv-revoke-consent">Revoca consenso cookie</button>
            </div>
        </section>

        <section class="privacy-card">
            <h2>Cookie</h2>
            <p>
                L'app usa il cookie di sessione PHP (tecnico, necessario) e, previo consenso,
                i cookie di <strong>Google Analytics</strong> per statistiche di utilizzo anonime.
            </p>
            <div class="privacy-actions">
                <button class="pv-btn pv-btn-danger" id="pv-clear-cookies">Elimina tutti i cookie</button>
            </div>
            <p class="privacy-status" id="pv-status" role="status"></p>
        </section>

        <section class="privacy-card">
            <h2>Servizi di terze parti</h2>
            <ul class="privacy-list">
                <li><strong>OpenStreetMap / Nominatim</strong> — tile della mappa e ricerca luoghi: il tuo IP e la posizione approssimativa visibile sulla mappa transitano verso i loro server. <a href="https://wiki.osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener">Privacy OSMF</a></li>
                <li><strong>ACTV S.p.A.</strong> — orari e posizioni dei mezzi arrivano dai loro endpoint pubblici.</li>
                <li><strong>Google Analytics</strong> — statistiche anonime di navigazione, solo dopo il consenso.</li>
            </ul>
        </section>

        <section class="privacy-card">
            <h2>Feedback</h2>
            <p>I messaggi inviati da <a href="/feedback">/feedback</a> sono salvati nel database dell'app. Non lasciare dati personali se non necessario.</p>
        </section>

    </main>

<script>
(function () {
    const status = document.getElementById('pv-status');
    const say = msg => { status.textContent = msg; setTimeout(() => status.textContent = '', 4000); };

    document.getElementById('pv-clear-local').addEventListener('click', async () => {
        const ok = await actvConfirm('Verranno rimossi preferiti, tema, ultimo viaggio e le altre preferenze salvate nel browser.', 'Elimina dati locali');
        if (!ok) return;
        localStorage.clear();
        sessionStorage.clear();
        say('Dati locali eliminati. La pagina verrà ricaricata.');
        setTimeout(() => location.reload(), 1200);
    });

    document.getElementById('pv-revoke-consent').addEventListener('click', async () => {
        const ok = await actvConfirm('Il banner dei cookie verrà mostrato di nuovo alla prossima visita.', 'Revoca consenso');
        if (!ok) return;
        localStorage.removeItem('cookie_consent_accepted');
        say('Consenso revocato.');
    });

    document.getElementById('pv-clear-cookies').addEventListener('click', async () => {
        const ok = await actvConfirm('Verranno eliminati tutti i cookie di questo sito, inclusa la sessione.', 'Elimina cookie');
        if (!ok) return;
        try {
            await fetch('/api/delete-cookie').then(r => r.json());
        } catch (e) { /* i cookie lato client vengono comunque rimossi sotto */ }
        document.cookie.split(';').forEach(c => {
            const name = c.split('=')[0].trim();
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        });
        say('Cookie eliminati.');
    });
})();
</script>
</body>
</html>
