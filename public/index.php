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

//Send globals to JS: moved inside head.php

// Redirect HTTP to HTTPS
if (
        (isset($_SERVER['HTTP_X_FORWARDED_SCHEME']) && $_SERVER['HTTP_X_FORWARDED_SCHEME'] !== 'https') || 
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on')
    ) {
    /* echo "<pre>";
    print_r($_SERVER);
    echo "</pre>";
    echo "Redirecting to HTTPS"; */
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// Ottieni l'URL richiesto e fai partire il router
$url = $_SERVER['REQUEST_URI'];
$router->dispatch($url);
?>
