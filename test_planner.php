<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/app/services/RoutePlanner.php';

try {
    $planner = new RoutePlanner();
    echo "Planner created.\n";
    
    // Test with Venezia (509) -> Mestre Centro (384)
    // Time: 10:00:00
    $routes = $planner->findRoutes('509', '384', '10:00:00');
    
    echo "Found " . count($routes) . " routes.\n";
    print_r($routes);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
