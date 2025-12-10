<?php

class RoutePlanner {
    private $cacheDir;
    private $routesDir;
    private $stops;
    private $routes;
    private $stopRoutesIndex;
    private $loadedRoutesData = []; // Cache for loaded route files
    
    public function __construct() {
        $this->cacheDir = BASE_PATH . '/data/gtfs/cache';
        $this->routesDir = BASE_PATH . '/data/gtfs/cache/routes';
        
        $this->loadBaseCache();
    }
    
    private function loadBaseCache() {
        // Load stops (small)
        if (file_exists($this->cacheDir . '/stops.json')) {
            $this->stops = json_decode(file_get_contents($this->cacheDir . '/stops.json'), true);
        }
        
        // Load routes (small)
        if (file_exists($this->cacheDir . '/routes.json')) {
            $this->routes = json_decode(file_get_contents($this->cacheDir . '/routes.json'), true);
        }
        
        // Load stop-routes index (medium)
        if (file_exists($this->cacheDir . '/stop_routes_index.json')) {
            $this->stopRoutesIndex = json_decode(file_get_contents($this->cacheDir . '/stop_routes_index.json'), true);
        }
    }
    
    /**
     * Find routes between two stops at a given time
     * Supports direct connections and 1 transfer
     * @param string $originId
     * @param string $destId
     * @param string $departureTime HH:MM:SS
     * @return array
     */
    public function findRoutes($originId, $destId, $departureTime) {
        if (!$this->stopRoutesIndex || !isset($this->stopRoutesIndex[$originId]) || !isset($this->stopRoutesIndex[$destId])) {
            return [];
        }
        
        $originRoutes = $this->stopRoutesIndex[$originId];
        $destRoutes = $this->stopRoutesIndex[$destId];
        $destRoutesSet = array_flip($destRoutes); // For fast lookup
        
        $results = [];
        
        // 1. Direct connections
        $commonRoutes = array_intersect($originRoutes, $destRoutes);
        foreach ($commonRoutes as $routeId) {
            $tripResults = $this->findTripsForRoute($routeId, $originId, $destId, $departureTime);
            $results = array_merge($results, $tripResults);
        }
        
        // 2. Connections with 1 transfer
        // Optimization: Only search for transfers if we have few direct results or if direct results are much later
        // For now, we'll keep searching but limit the number of routes we check to avoid timeouts
        
        $routesChecked = 0;
        $maxRoutesToCheck = 50; // Safety limit
        
        foreach ($originRoutes as $routeId) {
            // Skip if it's a direct route (already handled)
            if (in_array($routeId, $commonRoutes)) continue;
            
            if ($routesChecked++ > $maxRoutesToCheck) break;
            
            // Get potential transfer stops on this route
            $routeStops = $this->getRouteStopsAfter($routeId, $originId);
            
            foreach ($routeStops as $stopId => $timeFromOrigin) {
                // Check if this stop connects to any destination route
                if (!isset($this->stopRoutesIndex[$stopId])) continue;
                
                // Optimization: Check intersection of transfer stop routes and destination routes
                $transferRoutes = $this->stopRoutesIndex[$stopId];
                $connectingRoutes = array_intersect($transferRoutes, $destRoutes);
                
                if (!empty($connectingRoutes)) {
                    // Found a transfer point!
                    // 1. Find leg 1: Origin -> TransferStop
                    $leg1Trips = $this->findTripsForRoute($routeId, $originId, $stopId, $departureTime);
                    
                    if (empty($leg1Trips)) continue;

                    // We only need the best leg1 to proceed for now, or top 3?
                    // Let's take the first valid leg1
                    $leg1 = $leg1Trips[0];
                    
                    // 2. Find leg 2: TransferStop -> Destination (departing after leg1 arrival)
                    // Add buffer time for transfer (e.g. 2 minutes)
                    $minTransferTime = $this->addMinutes($leg1['arrival_time'], 2);
                    
                    $bestLeg2 = null;
                    
                    foreach ($connectingRoutes as $connRouteId) {
                        $leg2Trips = $this->findTripsForRoute($connRouteId, $stopId, $destId, $minTransferTime);
                        
                        if (!empty($leg2Trips)) {
                            // Since trips are sorted, the first one is the earliest departure
                            $candidateLeg2 = $leg2Trips[0];
                            
                            if ($bestLeg2 === null || $candidateLeg2['arrival_time'] < $bestLeg2['arrival_time']) {
                                $bestLeg2 = $candidateLeg2;
                            }
                        }
                    }
                    
                    if ($bestLeg2) {
                        // Combine into a journey
                        $results[] = [
                            'type' => 'transfer',
                            'departure_time' => $leg1['departure_time'],
                            'arrival_time' => $bestLeg2['arrival_time'],
                            'duration' => $this->calculateDuration($leg1['departure_time'], $bestLeg2['arrival_time']),
                            'stops_count' => $leg1['stops_count'] + $bestLeg2['stops_count'],
                            'legs' => [$leg1, $bestLeg2],
                            'transfer_stop' => $this->stops[$stopId]['name'] ?? $stopId,
                            'route_short_name' => $leg1['route_short_name'] . ' → ' . $bestLeg2['route_short_name'],
                            'route_long_name' => 'Cambio a ' . ($this->stops[$stopId]['name'] ?? $stopId)
                        ];
                    }
                }
            }
        }

        // --- NEXT DAY SEARCH LOGIC ---
        // If we found no results (or very few), try searching for the next day
        if (empty($results)) {
            $nextDayDepartureTime = '00:00:00';
            $nextDayResults = [];

            // 1. Direct connections (Next Day)
            foreach ($commonRoutes as $routeId) {
                $tripResults = $this->findTripsForRoute($routeId, $originId, $destId, $nextDayDepartureTime);
                foreach ($tripResults as &$trip) {
                    $trip['day_offset'] = 1; // Mark as next day
                    $trip['duration'] += 24 * 60; // Add 24 hours to duration for sorting/display logic if needed
                }
                $nextDayResults = array_merge($nextDayResults, $tripResults);
            }

            // 2. Connections with 1 transfer (Next Day)
            // Reuse the same logic but with nextDayDepartureTime
            // NOTE: For brevity, repeating the transfer logic here. Ideally, refactor into a private method.
            $routesChecked = 0;
            foreach ($originRoutes as $routeId) {
                if (in_array($routeId, $commonRoutes)) continue;
                if ($routesChecked++ > $maxRoutesToCheck) break;
                
                $routeStops = $this->getRouteStopsAfter($routeId, $originId);
                foreach ($routeStops as $stopId => $timeFromOrigin) {
                    if (!isset($this->stopRoutesIndex[$stopId])) continue;
                    $transferRoutes = $this->stopRoutesIndex[$stopId];
                    $connectingRoutes = array_intersect($transferRoutes, $destRoutes);
                    
                    if (!empty($connectingRoutes)) {
                        $leg1Trips = $this->findTripsForRoute($routeId, $originId, $stopId, $nextDayDepartureTime);
                        if (empty($leg1Trips)) continue;
                        $leg1 = $leg1Trips[0];
                        $minTransferTime = $this->addMinutes($leg1['arrival_time'], 2);
                        $bestLeg2 = null;
                        foreach ($connectingRoutes as $connRouteId) {
                            $leg2Trips = $this->findTripsForRoute($connRouteId, $stopId, $destId, $minTransferTime);
                            if (!empty($leg2Trips)) {
                                $candidateLeg2 = $leg2Trips[0];
                                if ($bestLeg2 === null || $candidateLeg2['arrival_time'] < $bestLeg2['arrival_time']) {
                                    $bestLeg2 = $candidateLeg2;
                                }
                            }
                        }
                        if ($bestLeg2) {
                            $nextDayResults[] = [
                                'type' => 'transfer',
                                'departure_time' => $leg1['departure_time'],
                                'arrival_time' => $bestLeg2['arrival_time'],
                                'duration' => $this->calculateDuration($leg1['departure_time'], $bestLeg2['arrival_time']) + (24 * 60),
                                'stops_count' => $leg1['stops_count'] + $bestLeg2['stops_count'],
                                'legs' => [$leg1, $bestLeg2],
                                'transfer_stop' => $this->stops[$stopId]['name'] ?? $stopId,
                                'route_short_name' => $leg1['route_short_name'] . ' → ' . $bestLeg2['route_short_name'],
                                'route_long_name' => 'Cambio a ' . ($this->stops[$stopId]['name'] ?? $stopId),
                                'day_offset' => 1
                            ];
                        }
                    }
                }
            }
            $results = array_merge($results, $nextDayResults);
        }
        
        // Sort by weighted score (Arrival Time + Penalty for Transfers + Day Offset)
        usort($results, function($a, $b) {
            $penaltyPerTransfer = 15 * 60; // 15 minutes penalty for a transfer
            $secondsPerDay = 24 * 3600;

            $scoreA = $this->gtfsToSeconds($a['arrival_time']);
            if ($a['type'] === 'transfer') {
                $scoreA += $penaltyPerTransfer;
            }
            if (isset($a['day_offset'])) {
                $scoreA += $a['day_offset'] * $secondsPerDay;
            }
            
            $scoreB = $this->gtfsToSeconds($b['arrival_time']);
            if ($b['type'] === 'transfer') {
                $scoreB += $penaltyPerTransfer;
            }
            if (isset($b['day_offset'])) {
                $scoreB += $b['day_offset'] * $secondsPerDay;
            }
            
            if ($scoreA == $scoreB) {
                return $a['duration'] - $b['duration'];
            }
            return $scoreA - $scoreB;
        });
        
        // Limit results
        return array_slice($results, 0, 10);
    }
    
    /**
     * Get cached route data
     */
    private function getRouteData($routeId) {
        if (isset($this->loadedRoutesData[$routeId])) {
            return $this->loadedRoutesData[$routeId];
        }
        
        $safeRouteId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeId);
        $file = $this->routesDir . '/route_' . $safeRouteId . '.json';
        
        if (!file_exists($file)) {
            return [];
        }
        
        $data = json_decode(file_get_contents($file), true);
        $this->loadedRoutesData[$routeId] = $data;
        
        return $data;
    }

    /**
     * Get all stops on a route that come after the origin
     * Returns [stop_id => min_time_offset]
     */
    private function getRouteStopsAfter($routeId, $originId) {
        $trips = $this->getRouteData($routeId);
        $stops = [];
        
        // Just check the first trip to get the sequence of stops
        foreach ($trips as $trip) {
            $originFound = false;
            foreach ($trip as $stop) {
                if ($stop['stop_id'] == $originId) {
                    $originFound = true;
                    continue;
                }
                
                if ($originFound) {
                    $stops[$stop['stop_id']] = 0; 
                }
            }
            if (!empty($stops)) break; 
        }
        
        return $stops;
    }
    
    private function findTripsForRoute($routeId, $originId, $destId, $departureTime) {
        $trips = $this->getRouteData($routeId);
        $validTrips = [];
        
        foreach ($trips as $tripId => $stops) {
            $originStop = null;
            $destStop = null;
            
            foreach ($stops as $stop) {
                if ($stop['stop_id'] == $originId) {
                    $originStop = $stop;
                }
                if ($stop['stop_id'] == $destId) {
                    $destStop = $stop;
                }
            }
            
            if ($originStop && $destStop && $originStop['stop_sequence'] < $destStop['stop_sequence']) {
                // Check time
                if ($originStop['departure_time'] >= $departureTime) {
                    $validTrips[] = [
                        'type' => 'direct',
                        'route_id' => $routeId,
                        'trip_id' => $tripId,
                        'departure_time' => $originStop['departure_time'],
                        'arrival_time' => $destStop['arrival_time'],
                        'duration' => $this->calculateDuration($originStop['departure_time'], $destStop['arrival_time']),
                        'stops_count' => $destStop['stop_sequence'] - $originStop['stop_sequence'],
                        'route_short_name' => $this->routes[$routeId]['route_short_name'] ?? $routeId,
                        'route_long_name' => $this->routes[$routeId]['route_long_name'] ?? ''
                    ];
                }
            }
        }
        
        // Sort valid trips by departure time
        usort($validTrips, function($a, $b) {
            return strcmp($a['departure_time'], $b['departure_time']);
        });
        
        return $validTrips;
    }

    private function calculateDuration($start, $end) {
        $startSeconds = $this->gtfsToSeconds($start);
        $endSeconds = $this->gtfsToSeconds($end);
        return floor(($endSeconds - $startSeconds) / 60);
    }

    private function addMinutes($time, $minutes) {
        $seconds = $this->gtfsToSeconds($time);
        $seconds += $minutes * 60;
        
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private function gtfsToSeconds($time) {
        $parts = explode(':', $time);
        return intval($parts[0]) * 3600 + intval($parts[1]) * 60 + intval($parts[2]);
    }

    /**
     * Find the nearest stop to a set of coordinates
     * @param float $lat
     * @param float $lon
     * @return array|null Returns stop array with 'distance' (meters) and 'walking_time' (minutes)
     */
    public function findNearestStop($lat, $lon) {
        if (!$this->stops) return null;
        
        $nearestStop = null;
        $minDist = PHP_FLOAT_MAX;
        
        foreach ($this->stops as $stop) {
            $dist = $this->calculateGeoDistance($lat, $lon, $stop['lat'], $stop['lon']);
            
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearestStop = $stop;
            }
        }
        
        if ($nearestStop) {
            $nearestStop['distance'] = round($minDist); // meters
            // Estimate walking time: 5km/h = 83 m/min 
            $nearestStop['walking_time'] = ceil($minDist / 83); 
        }
        
        return $nearestStop;
    }

    private function calculateGeoDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
