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
        <div class="container mt-5">
            <?php
            if (isset($routes)) {
                // Find common prefix
                $prefixedList = [
                    // key => list<route>
                ];
                foreach ($routes as  $route => $things) {
                    $splitted = explode("/",$route);
                    $key = $splitted[1];
                    if (!key_exists($key, $prefixedList)){
                        $prefixedList[$key] = [];
                    }
                    $prefixedList[$key][][$route] = $things;
                }

                // Se ci sono meno di 2 con lo stesso prefisso mettilo in root
                foreach ($prefixedList as $prx => $routes) {
                    if (sizeof($routes) == 1) {
                        $prefixedList[""][] = $routes[0];
                        unset($prefixedList[$prx]);
                    }
                }

                //echo "<pre>"; print_r($prefixedList); echo "</pre>";

                foreach ($prefixedList as  $prx => $routes) {
                    render_block($routes, $prx);
                    echo "<hr>";
                }
            }
            function render_block($routes, $prx) {
                echo "\t<h4>".$prx."</h4>";
                echo "<div class='d-flex justify-content-between flex-wrap'>";
                foreach ($routes as  $key => $routeObj) {
                    //echo "<pre>"; print_r($routeObj); echo "</pre>";
                    $route = key($routeObj);
                    echo "\t<a class='btn btn-primary mb-3' style='width:18%; z-index:10; box-shadow: 3px 6px 10px 2px #0003;' href='".$route."'>".$route."</a>";
                }
                echo "</div>";
            }
        ?>
        </div>
    </body>
</html>