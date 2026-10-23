<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crediti e licenze - ACTV Live</title>
    <?php require COMMON_HTML_HEAD; ?>
    <link rel="stylesheet" href="/css/credits.css">
</head>
<body>
    <header class="header-green compact">
        <a href="/" class="back-button" aria-label="Torna alla home">←</a>
        <h1 class="header-title">Crediti e licenze</h1>
        <p class="header-subtitle">Attribuzioni, dati e software di terze parti.</p>
    </header>
    <main class="credits-page">

        <section class="credits-card">
            <h2>Mappe e dati geografici</h2>
            <p>
                Le mappe utilizzano le tile di
                <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>,
                &copy; OpenStreetMap contributors, dati distribuiti con licenza
                <a href="https://opendatacommons.org/licenses/odbl/" target="_blank" rel="noopener">ODbL</a>.
            </p>
            <p>
                La ricerca dei luoghi usa
                <a href="https://nominatim.openstreetmap.org/" target="_blank" rel="noopener">Nominatim</a>,
                servizio di geocoding di OpenStreetMap.
            </p>
        </section>

        <section class="credits-card">
            <h2>Dati del trasporto pubblico</h2>
            <p>
                Orari, linee, fermate e posizioni dei mezzi derivano dai dati open data (GTFS/GTFS-RT)
                pubblicati da <strong>ACTV S.p.A.</strong> sul portale
                <a href="https://actv.avmspa.it" target="_blank" rel="noopener">actv.avmspa.it</a>.
            </p>
            <p class="credits-disclaimer">
                Questo è un progetto <strong>non ufficiale</strong> e <strong>non affiliato ad ACTV S.p.A.</strong>
                "ACTV Live" è un nome provvisorio; il marchio ACTV appartiene ai rispettivi titolari.
                Gli orari mostrati possono differire dalle fonti ufficiali: per informazioni vincolanti
                fare sempre riferimento ai canali ACTV.
            </p>
        </section>

        <section class="credits-card">
            <h2>Librerie open source</h2>
            <ul class="credits-list">
                <li><a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a> — BSD 2-Clause</li>
                <li><a href="https://github.com/Leaflet/Leaflet.fullscreen" target="_blank" rel="noopener">Leaflet.fullscreen</a> — BSD 2-Clause</li>
                <li><a href="https://getbootstrap.com" target="_blank" rel="noopener">Bootstrap</a> — MIT</li>
                <li><a href="https://popper.js.org" target="_blank" rel="noopener">Popper.js</a> — MIT</li>
                <li><a href="https://axios-http.com" target="_blank" rel="noopener">Axios</a> — MIT</li>
                <li><a href="https://github.com/Choices-js/Choices" target="_blank" rel="noopener">Choices.js</a> — MIT</li>
                <li><a href="https://github.com/griffinmichl/ldrs" target="_blank" rel="noopener">ldrs</a> — MIT</li>
            </ul>
        </section>

        <section class="credits-card">
            <h2>Questo progetto</h2>
            <p>
                ACTV Live è distribuito con
                <a href="https://github.com/samuele-brusegan/actv-live/blob/main/LICENSE.md" target="_blank" rel="noopener">licenza MIT</a>.
                Sviluppato da <a href="https://github.com/samuele-brusegan" target="_blank" rel="noopener">Samuele Brusegan</a>,
                grafica e design di <a href="https://github.com/andreadavanzo09-bit" target="_blank" rel="noopener">Andrea Davanzo</a>.
            </p>
        </section>

    </main>
</body>
</html>
