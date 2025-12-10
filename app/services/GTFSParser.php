<?php

/**
 * GTFS Parser for ACTV Venezia
 * Simplified version for hybrid approach - parses GTFS data and creates JSON cache
 */
class GTFSParser {
    private $gtfsUrl = 'http://actv.avmspa.it/sites/default/files/attachments/opendata/automobilistico/actv_aut.zip';
    private $dataDir;
    private $cacheDir;
    
    public function __construct() {
        ini_set('memory_limit', '1024M');
        $this->dataDir = BASE_PATH . '/data/gtfs';
        $this->cacheDir = BASE_PATH . '/data/gtfs/cache';
        
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }
    
    /**
     * Download and extract GTFS feed
     */
    public function downloadGTFS() {
        $zipFile = $this->dataDir . '/google_transit.zip';
        
        echo "Downloading GTFS feed...\n";
        $ch = curl_init($this->gtfsUrl);
        $fp = fopen($zipFile, 'w');
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $success = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        
        if (!$success) {
            throw new Exception("Failed to download GTFS feed");
        }
        
        echo "Extracting GTFS files...\n";
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($this->dataDir);
            $zip->close();
            echo "GTFS files extracted successfully\n";
        } else {
            throw new Exception("Failed to extract GTFS files");
        }
    }
    
    /**
     * Parse stops.txt and create cache
     */
    public function parseStops() {
        echo "Parsing stops.txt...\n";
        $file = $this->dataDir . '/stops.txt';
        
        if (!file_exists($file)) {
            throw new Exception("stops.txt not found");
        }
        
        $stops = [];
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ",", "\"", "\\");
        
        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            $stop = array_combine($headers, $data);
            $stops[$stop['stop_id']] = [
                'id' => $stop['stop_id'],
                'name' => $stop['stop_name'],
                'lat' => floatval($stop['stop_lat']),
                'lon' => floatval($stop['stop_lon'])
            ];
        }
        
        fclose($handle);
        
        file_put_contents(
            $this->cacheDir . '/stops.json',
            json_encode($stops, JSON_PRETTY_PRINT)
        );
        
        echo "Parsed " . count($stops) . " stops\n";
        return $stops;
    }
    
    /**
     * Parse routes.txt and create cache
     */
    public function parseRoutes() {
        echo "Parsing routes.txt...\n";
        $file = $this->dataDir . '/routes.txt';
        
        if (!file_exists($file)) {
            throw new Exception("routes.txt not found");
        }
        
        $routes = [];
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ",", "\"", "\\");
        
        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            $route = array_combine($headers, $data);
            $routes[$route['route_id']] = [
                'id' => $route['route_id'],
                'short_name' => $route['route_short_name'] ?? '',
                'long_name' => $route['route_long_name'] ?? '',
                'type' => intval($route['route_type'])
            ];
        }
        
        fclose($handle);
        
        file_put_contents(
            $this->cacheDir . '/routes.json',
            json_encode($routes, JSON_PRETTY_PRINT)
        );
        
        echo "Parsed " . count($routes) . " routes\n";
        return $routes;
    }
    
    /**
     * Parse trips.txt and create cache
     */
    public function parseTrips() {
        echo "Parsing trips.txt...\n";
        $file = $this->dataDir . '/trips.txt';
        
        if (!file_exists($file)) {
            throw new Exception("trips.txt not found");
        }
        
        $trips = [];
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ",", "\"", "\\");
        
        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            $trip = array_combine($headers, $data);
            $trips[$trip['trip_id']] = [
                'id' => $trip['trip_id'],
                'route_id' => $trip['route_id'],
                'service_id' => $trip['service_id'],
                'headsign' => $trip['trip_headsign'] ?? ''
            ];
        }
        
        fclose($handle);
        
        file_put_contents(
            $this->cacheDir . '/trips.json',
            json_encode($trips, JSON_PRETTY_PRINT)
        );
        
        echo "Parsed " . count($trips) . " trips\n";
        return $trips;
    }
    
    /**
     * Parse stop_times.txt, split by route, and create index
     * This replaces the monolithic stop_times.json with per-route files
     */
    public function parseStopTimes() {
        echo "Parsing stop_times.txt and creating route schedules...\n";
        $file = $this->dataDir . '/stop_times.txt';
        
        if (!file_exists($file)) {
            throw new Exception("stop_times.txt not found");
        }
        
        // Load trips to map trip_id -> route_id
        $tripsFile = $this->cacheDir . '/trips.json';
        if (!file_exists($tripsFile)) {
            throw new Exception("trips.json not found. Parse trips first.");
        }
        $trips = json_decode(file_get_contents($tripsFile), true);
        
        // Prepare routes directory
        $routesDir = $this->cacheDir . '/routes';
        if (!file_exists($routesDir)) {
            mkdir($routesDir, 0777, true);
        }
        
        $routeStopTimes = [];
        $stopRoutes = [];
        
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ",", "\"", "\\");
        
        $count = 0;
        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            $stopTime = array_combine($headers, $data);
            $tripId = $stopTime['trip_id'];
            $stopId = $stopTime['stop_id'];
            
            if (!isset($trips[$tripId])) {
                continue; // Skip if trip not found (shouldn't happen)
            }
            
            $routeId = $trips[$tripId]['route_id'];
            
            // Add to route schedule
            if (!isset($routeStopTimes[$routeId])) {
                $routeStopTimes[$routeId] = [];
            }
            if (!isset($routeStopTimes[$routeId][$tripId])) {
                $routeStopTimes[$routeId][$tripId] = [];
            }
            
            $routeStopTimes[$routeId][$tripId][] = [
                'stop_id' => $stopId,
                'arrival_time' => $stopTime['arrival_time'],
                'departure_time' => $stopTime['departure_time'],
                'stop_sequence' => intval($stopTime['stop_sequence'])
            ];
            
            // Add to index
            if (!isset($stopRoutes[$stopId])) {
                $stopRoutes[$stopId] = [];
            }
            if (!in_array($routeId, $stopRoutes[$stopId])) {
                $stopRoutes[$stopId][] = $routeId;
            }
            
            $count++;
            if ($count % 50000 == 0) {
                echo "Processed $count stop times...\n";
            }
        }
        
        fclose($handle);
        
        echo "Saving per-route schedules...\n";
        foreach ($routeStopTimes as $routeId => $tripTimes) {
            // Sort each trip by sequence
            foreach ($tripTimes as &$times) {
                usort($times, function($a, $b) {
                    return $a['stop_sequence'] - $b['stop_sequence'];
                });
            }
            
            $safeRouteId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeId);
            file_put_contents(
                $routesDir . '/route_' . $safeRouteId . '.json',
                json_encode($tripTimes) // Minified for space
            );
        }
        
        echo "Saving stop-routes index...\n";
        file_put_contents(
            $this->cacheDir . '/stop_routes_index.json',
            json_encode($stopRoutes, JSON_PRETTY_PRINT)
        );
        
        echo "Parsed $count stop times. Created schedules for " . count($routeStopTimes) . " routes.\n";
    }
    
    /**
     * Parse all GTFS files and create cache
     */
    public function parseAll() {
        $this->downloadGTFS();
        $this->parseStops();
        $this->parseRoutes();
        $this->parseTrips();
        $this->parseStopTimes(); // This now handles splitting and indexing
        
        echo "\nGTFS parsing complete!\n";
        echo "Cache files created in: " . $this->cacheDir . "\n";
    }
    
    /**
     * Check if cache exists and is recent
     */
    public function isCacheValid($maxAge = 86400) { // 24 hours default
        $cacheFile = $this->cacheDir . '/stops.json';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $age = time() - filemtime($cacheFile);
        return $age < $maxAge;
    }
}
