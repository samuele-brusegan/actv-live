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
    <style>
        :root {
            --font-size-rm-cookie: 1.5rem;
        }
        @media (max-width: 768px) {
            :root {
                --font-size-rm-cookie: 1rem;
            }
        }
        .my-container {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            flex-direction: column;
            gap: 2rem;
        }
        .btn:not(:has(img)) {
            font-size: var(--font-size-rm-cookie);
            border-radius: 1rem;
            padding: 1rem 2rem;
        }

    </style>
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
        <div class="container my-container">
            <button class="btn btn-danger"  onclick="deleteCookie()">Elimina tutti Cookie</button>
            <button class="btn btn-success" onclick="window.location.href = '/';">Torna alla home</button>
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
                // Esegui il codice JS per eliminare i cookie lato client
                eval(data.js_code);
            })
            .catch(error => {
                console.error('Error:', error);
            });

        }
    </script>
</html>