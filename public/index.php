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


// Ottieni l'URL richiesto e fai partire il router
$url = $_SERVER['REQUEST_URI'];
$router->dispatch($url);
?>
