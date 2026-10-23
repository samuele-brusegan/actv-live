<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Orari delle linee - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/lineSchedule.css">
        <script src="/js/tour.js"></script>
        <script defer src="/js/lineSchedule.js"></script>
    </head>
    <body class="schedule-page">
        <header class="header-green schedule-brand">
            <div class="schedule-header">
                <a href="/" class="schedule-back" aria-label="Torna alla home">&larr;</a>
                <div><h1>Orari delle linee</h1><p>Tutti i percorsi e le corse, in un unico orario.</p></div>
            </div>
        </header>
        <main class="schedule-main">
            <section class="schedule-controls" aria-label="Scegli il servizio e la linea">
                <div class="service-switch" role="group" aria-label="Servizio">
                    <button type="button" class="service-switch-btn" data-service="automobilistico" aria-label="Autobus" title="Autobus" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="16" rx="3"/><path d="M5 11h14M9 3v8M7 19v2m10-2v2M8 15h1m6 0h1"/></svg></button>
                    <button type="button" class="service-switch-btn" data-service="navigation" aria-label="Vaporetti" title="Vaporetti" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13V7h14v6M9 7V4h6v3M3 14l9-3 9 3-3 5H6zM2 21q2-2 4 0t4 0 4 0 4 0 4 0 4 0"/></svg></button>
                </div>
                <div class="line-picker">
                    <div><label for="line-search">Cerca linea</label><input type="search" id="line-search" class="form-control" placeholder="Numero o destinazione" autocomplete="off" aria-controls="line-input"></div>
                    <div><label for="line-input">Linea</label><select id="line-input" class="form-select" disabled><option value="">Caricamento linee…</option></select></div>
                </div>
            </section>
            <div id="status" class="alert alert-info d-none" role="status" aria-live="polite"></div>
            <div class="schedule-workspace">
                <aside id="variants" aria-label="Direzione e percorso"></aside>
                <section id="variant-detail" aria-label="Orario completo" aria-live="polite">
                    <div class="schedule-empty"><h2>Da quale linea partiamo?</h2><p>Scegli una linea per consultare percorsi, fermate e tutti gli orari disponibili.</p></div>
                </section>
            </div>
            <div id="feed-meta" class="schedule-feed"></div>
        </main>
    </body>
</html>
