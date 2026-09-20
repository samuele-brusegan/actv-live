<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di actv-live ed è rilasciato
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
$router->add('/live-map'                , 'Controller', 'liveMap');
$router->add('/trip-details'            , 'Controller', 'tripDetails');
$router->add('/widget'                  , 'Controller', 'widget');
$router->add('/delay-stats'             , 'Controller', 'delayStats');
$router->add('/routes'                  , 'Controller', 'routes');
$router->add('/route'                   , 'Controller', 'routes');
$router->add('/line-schedule'           , 'Controller', 'lineSchedule');
$router->add('/trip-finder'             , 'Controller', 'tripFinder');
$router->add('/delete-cookie'           , 'Controller', 'deleteCookie');
$router->add('/feedback'                , 'Controller', 'feedback');

$router->add('/admin/login'             , 'Controller', 'adminLogin');
$router->add('/admin/logout'            , 'Controller', 'adminLogout');
$router->add('/admin/logs'              , 'Controller', 'logs');
$router->add('/admin/dashboard'         , 'Controller', 'adminDashboard');
$router->add('/admin/gtfs-update'       , 'Controller', 'adminGtfsUpdate');
$router->add('/admin/gtfs-rt-inspector' , 'Controller', 'adminGtfsRealtimeInspector');
$router->add('/admin/feedback'          , 'Controller', 'adminFeedback');

// === API ===
$router->add('/api/plan-route'          , 'ApiController', 'planRoute');
$router->add('/api/stops'               , 'ApiController', 'stops');
$router->add('/api/navigation/stops'    , 'ApiController', 'navigationStops');
$router->add('/api/navigation/lines'    , 'ApiController', 'navigationLines');
$router->add('/api/line-colors'         , 'ApiController', 'lineColors');
$router->add('/api/navigation/passages' , 'ApiController', 'navigationPassages');
$router->add('/api/navigation/vehicles' , 'ApiController', 'navigationVehicles');
$router->add('/api/realtime/vehicles'   , 'ApiController', 'realtimeVehicles');
$router->add('/api/stop-lines'          , 'ApiController', 'stopLines');
$router->add('/api/lines-shapes'        , 'ApiController', 'linesShapes');
$router->add('/api/trip-stops'          , 'ApiController', 'tripStops');
$router->add('/api/bus-position'        , 'ApiController', 'busPosition');
$router->add('/api/log-js-error'        , 'ApiController', 'logJsError');
$router->add('/api/line-variants'       , 'ApiController', 'lineVariants');
$router->add('/api/line-catalog'        , 'ApiController', 'lineCatalog');
$router->add('/api/line-schedule'       , 'ApiController', 'lineSchedule');
$router->add('/api/line-trips'          , 'ApiController', 'lineTrips');
$router->add('/api/stop-upcoming'       , 'ApiController', 'stopUpcoming');
$router->add('/api/delete-cookie'       , 'ApiController', 'deleteCookie');
$router->add('/api/feedback'            , 'ApiController', 'feedback');

// === API Admin ===
$router->add('/api/admin/gtfs-update/status', 'ApiController', 'adminGtfsUpdateStatus');
$router->add('/api/admin/gtfs-update/config', 'ApiController', 'adminGtfsUpdateConfig');
$router->add('/api/admin/gtfs-update/start', 'ApiController', 'adminGtfsUpdateStart');
$router->add('/api/admin/gtfs-rt-inspector', 'ApiController', 'adminGtfsRealtimeInspect');

// === API GTFS ===
$router->add('/api/gtfs-identify'       , 'ApiController', 'api_gtfsIdentify');
$router->add('/api/gtfs-builder'        , 'ApiController', 'gtfsTripBuilder');
$router->add('/api/gtfs-stop-translater', 'ApiController', 'gtfsStopTranslater');
$router->add('/api/gtfs-resolve'        , 'ApiController', 'gtfsResolve');
$router->add('/api/gtfs-bnr'            , 'ApiController', 'gtfsBusesRunningNow');
$router->add('/api/gtfs-stops'          , 'ApiController', 'gtfsStops');
$router->add('/api/gtfs-passages'       , 'ApiController', 'gtfsPassages');
