<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di GradeCraft ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */
global $router;

// === Pagine ===
$router->add('/'                        , 'Controller', 'index');
$router->add('/route-finder'            , 'Controller', 'routeFinder');
$router->add('/station-selector'        , 'Controller', 'stationSelector');
$router->add('/route-results'           , 'Controller', 'routeResults');
$router->add('/route-details'           , 'Controller', 'routeDetails');
$router->add('/stopList'                , 'Controller', 'stops');
$router->add('/aut/stops/stop'          , 'Controller', 'stop');
$router->add('/lines-map'               , 'Controller', 'linesMap');
$router->add('/trip-details'            , 'Controller', 'tripDetails');
$router->add('/admin/logs'              , 'Controller', 'logs');
$router->add('/admin/time-machine'      , 'Controller', 'timeMachine');

$router->add('/api/addFavorite'         , 'ApiController', 'favorite');
$router->add('/api/plan-route'          , 'ApiController', 'planRoute');
$router->add('/api/stops'               , 'ApiController', 'stops');
$router->add('/api/lines-shapes'        , 'ApiController', 'linesShapes');
$router->add('/api/trip-stops'          , 'ApiController', 'tripStops');
$router->add('/api/gtfs-identify'       , 'ApiController', 'api_gtfsIdentify');
$router->add('/api/gtfs-builder'        , 'ApiController', 'gtfsTripBuilder');
$router->add('/api/gtfs-stop-translater', 'ApiController', 'gtfsStopTranslater');
$router->add('/api/log-js-error'        , 'ApiController', 'logJsError');
$router->add('/api/tm/sessions'         , 'ApiController', 'getTmSessions');
$router->add('/api/tm/create-session'   , 'ApiController', 'createTmSession');
$router->add('/api/tm/simulated-data'   , 'ApiController', 'getSimulatedData');
$router->add('/api/tm/heartbeat'        , 'ApiController', 'runTmHeartbeat');
$router->add('/api/gtfs-resolve'        , 'ApiController', 'gtfsResolve');
$router->add('/api/gtfs-bnr'            , 'ApiController', 'gtfsBusesRunningNow');
$router->add('/api/bus-position'        , 'ApiController', 'busPosition');
$router->add('/live-map'                , 'Controller', 'liveMap');
