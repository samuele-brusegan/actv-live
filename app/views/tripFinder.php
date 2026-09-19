<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cerca corsa - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/tripFinder.css">
        <script defer src="/js/tripFinder.js"></script>
    </head>
    <body>
        <header class="header-green">
            <div>
                <a class="back-button" href="/" aria-label="Torna alla home">&larr;</a>
            </div>
            <h1 class="header-title">Cerca una corsa</h1>
            <p class="header-subtitle">Seleziona linea, fermate e la corsa da aprire.</p>
        </header>

        <main class="trip-finder-content" id="trip-finder">
            <section class="finder-card" aria-labelledby="finder-title">
                <div class="finder-card-heading">
                    <div>
                        <p class="eyebrow">Servizio automobilistico</p>
                        <h2 id="finder-title">Trova il viaggio</h2>
                    </div>
                </div>

                <div class="finder-grid">
                    <div class="finder-field">
                        <label for="line-input">Linea</label>
                        <select id="line-input" class="form-select" autocomplete="off">
                            <option value="">Caricamento linee...</option>
                        </select>
                        <small class="field-help">Scegli una linea per caricare i capolinea disponibili.</small>
                    </div>
                    <div class="finder-field">
                        <label for="date-input">Data servizio</label>
                        <input id="date-input" class="form-control" type="date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="finder-field">
                    <label for="origin-input">Partenza</label>
                    <select id="origin-input" class="form-select" disabled>
                        <option value="">Prima scegli una linea</option>
                    </select>
                </div>

                <div class="direction-arrow" aria-hidden="true">↓</div>

                <div class="finder-field">
                    <label for="destination-input">Destinazione</label>
                    <select id="destination-input" class="form-select" disabled>
                        <option value="">Prima scegli la partenza</option>
                    </select>
                </div>

                <div id="status" class="finder-status" role="status" aria-live="polite"></div>
            </section>

            <section class="finder-card trips-card" aria-labelledby="trips-title">
                <div class="finder-card-heading">
                    <div>
                        <p class="eyebrow">Risultato della tratta</p>
                        <h2 id="trips-title">Seleziona la tratta</h2>
                    </div>
                    <span id="trip-count" class="trip-count" hidden></span>
                </div>
                <div id="trips-list" class="trips-list">
                    <p class="empty-state">Dopo aver scelto partenza e destinazione compariranno qui le corse.</p>
                </div>
                <div id="trips-navigation" class="trips-navigation" hidden>
                    <div class="pagination-row">
                        <button id="previous-page" class="pagination-button" type="button" aria-label="Pagina precedente">‹</button>
                        <span id="page-label" class="page-label"></span>
                        <button id="next-page" class="pagination-button" type="button" aria-label="Pagina successiva">›</button>
                    </div>
                    <div class="jump-row">
                        <div class="jump-control">
                            <label for="page-input">Vai alla pagina</label>
                            <div class="jump-input-group">
                                <input id="page-input" class="jump-input" type="number" min="1" step="1" inputmode="numeric" aria-label="Numero pagina">
                                <button id="page-jump-button" class="jump-button" type="button">Vai</button>
                            </div>
                        </div>
                        <span class="jump-or">oppure</span>
                        <div class="jump-control">
                            <label for="time-input">Vai all’orario</label>
                            <div class="jump-input-group">
                                <input id="time-input" class="jump-input time-input" type="time" aria-label="Orario di partenza">
                                <button id="time-jump-button" class="jump-button" type="button">Vai</button>
                            </div>
                        </div>
                    </div>
                </div>
                <button id="open-trip-button" class="btn-primary open-trip-button" type="button" disabled>
                    Apri dettagli della corsa
                </button>
            </section>
        </main>
    </body>
</html>
