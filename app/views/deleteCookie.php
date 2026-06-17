<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Risultati Percorso - ACTV</title>
        <?php require COMMON_HTML_HEAD; ?>
        <link rel="stylesheet" href="/css/structure/structure-routeResults.css">
        <link rel="stylesheet" href="/css/routeResults.css">
        <script src="/js/routeResults.js"></script>
    </head>
    <body>
        <!-- Header -->
        <div class="header-green">
            <!-- Logo o Icona Menu (Placeholder basato su spazio vuoto nel design) -->
            <div style="height: 20px;"></div>
            <h1 class="header-title">ACTV Live <br> Venezia</h1>
            <h3 class="header-subtitle">Orari in Tempo Reale</h3>
            <div class="theme-toggle" style="position: absolute; top: 20px; right: 20px;">
                <button class="btn btn-primary rounded-pill px-4 py-2" onclick="toggleTheme()">
                    <img src="/svg/light_mode.svg" alt="Toggle Theme" id="theme-icon"> (Demo)
                </button>
            </div>
        </div>
        <div class="container mt-5 d-flex justify-content-center align-items-center" style="flex-grow: 1;">
            <button class="btn btn-danger" onclick="deleteCookie()" style="font-size: 3rem; border-radius: 1rem; padding: 1rem 2rem;">Elimina tutti Cookie</button>
        </div>
    </body>
    <script>
        function deleteCookie() {

            // Confirm deletion
            if (!confirm('Sei sicuro di voler eliminare tutti i cookie?')) {
                return;
            }

            fetch('/api/deleteCookie')
            .then(response => response.json())
            .then(data => {
                console.log(data);
            })
            .catch(error => {
                console.error('Error:', error);
            });

            // Esegui il codice JS per eliminare i cookie lato client
            eval(data.js_code);
        }
    </script>
</html>