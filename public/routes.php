<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di GradeCraft ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */
global $router;

// === Pagine ===
$router->add('/'               , 'Controller', 'index');
$router->add('/route-finder'   , 'Controller', 'routeFinder');
$router->add('/station-selector', 'Controller', 'stationSelector');
$router->add('/route-results'  , 'Controller', 'routeResults');
$router->add('/route-details'  , 'Controller', 'routeDetails');
$router->add('/stopList'       , 'Controller', 'stops');
$router->add('/aut/stops/stop' , 'Controller', 'stop');
$router->add('/api/addFavorite', 'Controller', 'favorite');
$router->add('/api/plan-route' , 'Controller', 'planRoute');
$router->add('/api/stops'      , 'Controller', 'stopsJson');
