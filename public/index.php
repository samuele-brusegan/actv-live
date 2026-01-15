<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di GradeCraft ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */

// use cvv\Collegamenti;
// use cvv\CvvIntegration;
require_once dirname(__DIR__) . '/app/bootstrap.php';

session_start();
checkSessionExpiration();


// Inizializza il router
$router = new Router();

// Definisci le rotte
require BASE_PATH . '/public/routes.php';

//Send globals to JS
//Send globals to JS
if (strpos($_SERVER['REQUEST_URI'], '/api/') === false && strpos($_SERVER['REQUEST_URI'], '/gtfs-test') === false) {
    echo "
    <script>
        sessionStorage.setItem('url', '" . URL_PATH . "');
        sessionStorage.setItem('theme', '" . THEME . "');
    </script>";
}

// Ottieni l'URL richiesto e fai partire il router
$url = $_SERVER['REQUEST_URI'];
$router->dispatch($url);
?>
