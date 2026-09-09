<?php

class RoutePlanner {
    private const MAX_MULTIMODAL_TRANSFERS = 4;
    private string $service;
    private ?RoutePlanner $navigationPlanner = null;
    private $cacheDir;
    private $routesDir;
    private $stops;
    private $routes;
    private $stopRoutesIndex;
    private $loadedRoutesData = []; // Cache dell'ultima rotta caricata
    private $nameIndex = null; // Index: normalized stop name -> [stop_id, ...]
    
    public function __construct(string $service = 'automobilistico') {
        $this->service = $service === 'navigation' ? 'navigation' : 'automobilistico';
        $this->cacheDir = BASE_PATH . '/data/gtfs/cache/' . $this->service;
        // Compatibilita con la cache storica automobilistica.
        if ($this->service === 'automobilistico' && !is_file($this->cacheDir . '/routes.json')) {
            $this->cacheDir = BASE_PATH . '/data/gtfs/cache';
        }
        $this->routesDir = $this->cacheDir . '/routes';
        
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
        $maxRoutesToCheck = 25; // Safety limit

        // Se esistono già almeno tre alternative dirette, il confronto è più
        // utile e molto più rapido senza esplorare anche tutti i cambi.
        if (count($results) < 3) foreach ($originRoutes as $routeId) {
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

        // Alcuni collegamenti periferici richiedono due cambi bus (per
        // esempio Casona Marziale -> Fornase: 24H -> 7 -> GSB).
        if (empty($results)) {
            $results = $this->findRoutesWithTwoTransfers($originId, $destId, $departureTime);
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

    private function findRoutesWithTwoTransfers($originId, $destId, $departureTime): array {
        if (!isset($this->stopRoutesIndex[$originId], $this->stopRoutesIndex[$destId])) return [];
        $destinationRoutes = $this->stopRoutesIndex[$destId];
        $results = [];
        $tripCache = [];
        $getTrips = function ($route, $from, $to, $time) use (&$tripCache) {
            $key = implode('|', [$route, $from, $to, $time]);
            return $tripCache[$key] ??= $this->findTripsForRoute($route, $from, $to, $time);
        };

        foreach (array_slice($this->stopRoutesIndex[$originId], 0, 10) as $route1) {
            $firstStops = $this->getRouteStopsAfter($route1, $originId);
            foreach (array_slice(array_keys($firstStops), 0, 40) as $transfer1) {
                // Il cambio può avvenire anche tra due corse della stessa
                // linea: Padova Fiera -> Stazione Padova, poi la 53E
                // prosegue verso Mestre/Venezia. Il trip_id e gli orari
                // impediscono comunque di riutilizzare la stessa corsa.
                $firstRoutes = $this->stopRoutesIndex[$transfer1] ?? [];
                foreach (array_slice($firstRoutes, 0, 8) as $route2) {
                    $secondStops = $this->getRouteStopsAfter($route2, $transfer1);
                    foreach (array_slice(array_keys($secondStops), 0, 40) as $transfer2) {
                        $connecting = array_intersect($this->stopRoutesIndex[$transfer2] ?? [], $destinationRoutes);
                        if (!$connecting) continue;

                        $first = $getTrips($route1, $originId, $transfer1, $departureTime);
                        if (!$first || !isset($first[0])) continue;
                        $second = $getTrips($route2, $transfer1, $transfer2, $this->addMinutes($first[0]['arrival_time'], 2));
                        if (!$second || !isset($second[0])) continue;
                        $connectingRoute = array_values($connecting)[0] ?? null;
                        if ($connectingRoute === null) continue;
                        $third = $getTrips($connectingRoute, $transfer2, $destId, $this->addMinutes($second[0]['arrival_time'], 2));
                        if (!$third) continue;

                        $first = $first[0];
                        $second = $second[0];
                        $third = $third[0];
                        $results[] = [
                            'type' => 'transfer',
                            'service' => $this->service,
                            'mode' => $this->service === 'navigation' ? 'water' : 'bus',
                            'departure_time' => $first['departure_time'],
                            'arrival_time' => $third['arrival_time'],
                            'duration' => $this->calculateDuration($first['departure_time'], $third['arrival_time']),
                            'stops_count' => ($first['stops_count'] ?? 0) + ($second['stops_count'] ?? 0) + ($third['stops_count'] ?? 0),
                            'legs' => [$first['legs'][0], $second['legs'][0], $third['legs'][0]],
                            'transfer_stop' => ($this->stops[$transfer1]['name'] ?? $transfer1) . ' / ' . ($this->stops[$transfer2]['name'] ?? $transfer2),
                            'route_short_name' => implode(' → ', [$first['route_short_name'], $second['route_short_name'], $third['route_short_name']]),
                            'route_long_name' => 'Cambi a ' . ($this->stops[$transfer1]['name'] ?? $transfer1) . ' e ' . ($this->stops[$transfer2]['name'] ?? $transfer2),
                        ];
                        // La ricerca a due cambi è il fallback costoso: appena
                        // trova un itinerario valido non continua a caricare
                        // altre route complete inutilmente.
                        return $results;
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Normalize a stop name for grouping (same logic as the station selector).
     */
    private function normalizeStopName($name) {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $name, 'UTF-8')));
    }

    /**
     * Returns every stop_id that shares the same name as the given stop
     * (i.e. all the physical platforms/directions of that stop).
     */
    private function getSiblingStopIds($stopId) {
        if (!$this->stops || !isset($this->stops[$stopId])) {
            return [$stopId];
        }

        if ($this->nameIndex === null) {
            $this->nameIndex = [];
            foreach ($this->stops as $sid => $info) {
                $key = $this->normalizeStopName($info['name'] ?? '');
                $this->nameIndex[$key][] = $sid;
            }
        }

        $key = $this->normalizeStopName($this->stops[$stopId]['name'] ?? '');
        return $this->nameIndex[$key] ?? [$stopId];
    }

    /**
     * Find routes considering ALL platforms of the origin and destination stops.
     * In the ACTV GTFS a physical stop has several stop_ids (one per direction),
     * so searching only the selected stop_id often misses valid connections.
     */
    public function findRoutesMulti($originId, $destId, $departureTime, string $mode = 'all') {
        $results = $this->findRoutesMultiAt($originId, $destId, $departureTime, $mode);
        if (!empty($results)) return $results;

        // Dopo l'ultima corsa di oggi cerca la prima soluzione del giorno
        // successivo, anche per i cambi bus <-> Navigazione.
        $nextDay = $this->findRoutesMultiAt($originId, $destId, '00:00:00', $mode);
        foreach ($nextDay as &$route) {
            $route['day_offset'] = 1;
            $route['duration'] = ($route['duration'] ?? 0) + 1440;
        }
        unset($route);
        return $nextDay;
    }

    private function findRoutesMultiAt($originId, $destId, $departureTime, string $mode = 'all') {
        if ($this->service === 'automobilistico' && $mode === 'all') {
            $busOrigin = isset($this->stops[$originId]);
            $busDest = isset($this->stops[$destId]);
            $nav = $this->getNavigationPlanner();
            $navOrigin = isset($nav->getStops()[$originId]);
            $navDest = isset($nav->getStops()[$destId]);
            if ($navOrigin && $navDest && !$busOrigin && !$busDest) return $nav->findRoutesMultiForService($originId, $destId, $departureTime);
            $results = $this->findRoutesMultiForService($originId, $destId, $departureTime);
            $navigation = $nav->findRoutesMultiForService($originId, $destId, $departureTime);
            $results = array_merge($results, $navigation, $this->findCrossServiceRoutes($originId, $destId, $departureTime));
            $unique = [];
            foreach ($results as $result) {
                $key = implode('|', array_map(fn($leg) => ($leg['service'] ?? '') . ':' . ($leg['route_id'] ?? '') . ':' . ($leg['trip_id'] ?? ''), $result['legs'] ?? []));
                $key .= '|' . ($result['departure_time'] ?? '') . '|' . ($result['arrival_time'] ?? '');
                $unique[$key] = $result;
            }
            $results = array_values($unique);
            usort($results, fn($a, $b) => (($a['arrival_time'] ?? '') <=> ($b['arrival_time'] ?? '')));
            return array_slice($results, 0, 10);
        }
        if ($mode === 'water' && $this->service === 'automobilistico') {
            return $this->getNavigationPlanner()->findRoutesMultiForService($originId, $destId, $departureTime);
        }
        return $this->findRoutesMultiForService($originId, $destId, $departureTime);
    }

    private function findRoutesMultiForService($originId, $destId, $departureTime) {
        $originIds = $this->getSiblingStopIds($originId);
        $destIds = $this->getSiblingStopIds($destId);

        $all = [];
        $seen = [];

        foreach (array_unique($originIds) as $o) {
            foreach (array_unique($destIds) as $d) {
                if ($o === $d) continue;
                foreach ($this->findRoutes($o, $d, $departureTime) as $r) {
                    $key = ($r['departure_time'] ?? '') . '|' . ($r['arrival_time'] ?? '')
                        . '|' . ($r['route_short_name'] ?? '') . '|' . ($r['type'] ?? '');
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $all[] = $r;
                }
            }
        }

        usort($all, function ($a, $b) {
            $penalty = 15 * 60;
            $day = 24 * 3600;
            $sa = $this->gtfsToSeconds($a['arrival_time'])
                + (($a['type'] ?? '') === 'transfer' ? $penalty : 0)
                + (($a['day_offset'] ?? 0) * $day);
            $sb = $this->gtfsToSeconds($b['arrival_time'])
                + (($b['type'] ?? '') === 'transfer' ? $penalty : 0)
                + (($b['day_offset'] ?? 0) * $day);
            if ($sa === $sb) {
                return ($a['duration'] ?? 0) <=> ($b['duration'] ?? 0);
            }
            return $sa <=> $sb;
        });

        return array_slice($all, 0, 10);
    }

    private function getNavigationPlanner(): RoutePlanner {
        return $this->navigationPlanner ??= new RoutePlanner('navigation');
    }

    public function getStops(): array { return is_array($this->stops) ? $this->stops : []; }
    public function getService(): string { return $this->service; }

    private function compatibleStops(array $stops, array $reference): array {
        $name = $this->normalizeStopName($reference['name'] ?? '');
        $matches = [];
        foreach ($stops as $id => $stop) {
            $sameName = $name !== '' && $this->normalizeStopName($stop['name'] ?? '') === $name;
            $distance = $sameName ? 0 : $this->calculateGeoDistance((float)($reference['lat'] ?? 0), (float)($reference['lon'] ?? 0), (float)($stop['lat'] ?? 0), (float)($stop['lon'] ?? 0));
            if ($sameName || $distance <= 250) $matches[$id] = $distance;
        }
        uasort($matches, fn($a, $b) => $a <=> $b);
        return array_keys($matches);
    }

    /**
     * Cerca un itinerario attraversando un numero arbitrario di servizi.
     *
     * La ricerca non contiene più casi speciali bus->navigazione o
     * navigazione->bus: ad ogni passo prova tutti i planner disponibili,
     * collegandoli tramite fermate compatibili. Il limite sui cambi è una
     * protezione contro cicli e combinazioni inutilmente costose.
     */
    private function findCrossServiceRoutes($originId, $destId, $departureTime): array {
        $planners = [$this->getService() => $this, 'navigation' => $this->getNavigationPlanner()];
        $results = [];

        foreach ($planners as $service => $planner) {
            if (!isset($planner->getStops()[$originId])) continue;
            $results = array_merge($results, $this->searchMultimodal(
                $planner,
                $originId,
                $destId,
                $departureTime,
                $planners,
                [],
                [],
                0,
                self::MAX_MULTIMODAL_TRANSFERS
            ));
        }

        $unique = [];
        foreach ($results as $result) {
            $key = implode('|', array_map(
                fn($leg) => ($leg['service'] ?? '') . ':' . ($leg['route_id'] ?? '') . ':' . ($leg['trip_id'] ?? ''),
                $result['legs'] ?? []
            ));
            $unique[$key . '|' . ($result['departure_time'] ?? '') . '|' . ($result['arrival_time'] ?? '')] = $result;
        }

        $results = array_values($unique);
        usort($results, fn($a, $b) => ($a['arrival_time'] ?? '') <=> ($b['arrival_time'] ?? ''));
        return array_slice($results, 0, 10);
    }

    private function searchMultimodal(
        RoutePlanner $current,
        string $fromId,
        string $destId,
        string $departureTime,
        array $planners,
        array $segments,
        array $visited,
        int $transfers,
        int $maxTransfers
    ): array {
        $stateKey = $current->getService() . ':' . $fromId;
        if (isset($visited[$stateKey]) || $transfers > $maxTransfers) return [];
        $visited[$stateKey] = true;

        $results = [];
        if (isset($current->getStops()[$destId])) {
            foreach ($current->findRoutesMultiForService($fromId, $destId, $departureTime) as $last) {
                $journeySegments = array_merge($segments, [$last]);
                $results[] = $this->combineJourneySegments($journeySegments);
            }
        }

        if ($transfers >= $maxTransfers) return $results;

        foreach ($planners as $service => $next) {
            if ($next === $current) continue;

            // Calcola solo interscambi raggiungibili dalla fermata corrente;
            // non scansiona tutti i nodi della rete a ogni ricorsione.
            // Il grafo può contenere migliaia di fermate compatibili. Le
            // fermate sono già ordinate secondo la topologia delle corse
            // raggiungibili, quindi esploriamo solo i primi candidati.
            foreach (array_slice($current->getInterchangeStopIds($next, $fromId), 0, 20) as $transferId) {
                if ($transferId === $fromId) continue;
                $nextStops = $next->compatibleStops($next->getStops(), $current->getStops()[$transferId] ?? []);
                if (!$nextStops) continue;

                // Il trasferimento viene valutato come singola tratta: la
                // ricorsione gestisce i cambi di mezzo; i cambi interni allo
                // stesso servizio sono già coperti dal planner principale.
                $legs = $current->findDirectRoutes($fromId, $transferId, $departureTime);
                foreach (array_slice($legs, 0, 1) as $leg) {
                    $arrival = $leg['arrival_time'] ?? null;
                    if (!$arrival) continue;
                    foreach (array_slice($nextStops, 0, 1) as $nextId) {
                        $results = array_merge($results, $this->searchMultimodal(
                            $next,
                            $nextId,
                            $destId,
                            $this->addMinutes($arrival, 5),
                            $planners,
                            array_merge($segments, [$leg]),
                            $visited,
                            $transfers + 1,
                            $maxTransfers
                        ));
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Restituisce gli interscambi dell'altro servizio raggiungibili da una
     * fermata. Include anche un secondo tratto nello stesso servizio, utile
     * quando il primo mezzo non arriva direttamente al nodo multimodale.
     */
    private function getInterchangeStopIds(RoutePlanner $other, ?string $originId = null): array {
        $possibleIds = array_keys($this->stops ?? []);
        if ($originId !== null && isset($this->stops[$originId], $this->stopRoutesIndex[$originId])) {
            $reachable = [];
            foreach ($this->stopRoutesIndex[$originId] as $routeId) {
                foreach ($this->getRouteStopsAfter($routeId, $originId) as $stopId => $_) {
                    $reachable[$stopId] = true;
                    foreach ($this->stopRoutesIndex[$stopId] ?? [] as $nextRouteId) {
                        foreach ($this->getRouteStopsAfter($nextRouteId, $stopId) as $nextStopId => $_) {
                            $reachable[$nextStopId] = true;
                        }
                    }
                }
            }
            $possibleIds = array_keys($reachable);
        }

        $ids = [];
        foreach ($possibleIds as $id) {
            $stop = $this->stops[$id] ?? null;
            if (!$stop || !isset($this->stopRoutesIndex[$id])) continue;
            if ($other->compatibleStops($other->getStops(), $stop)) $ids[] = $id;
        }
        return $ids;
    }

    private function combineJourneySegments(array $segments): array {
        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $legs = [];
        $transferNames = [];
        $stopsCount = 0;

        foreach ($segments as $index => $segment) {
            if ($index > 0) {
                $previous = $segments[$index - 1];
                $transferTime = $this->addMinutes($previous['arrival_time'], 5);
                $transferNames[] = $previous['destination'] ?? $previous['route_long_name'] ?? 'interscambio';
                $legs[] = [
                    'type' => 'walking', 'service' => 'walking', 'mode' => 'walking',
                    'duration' => 5,
                    'departure_time' => $previous['arrival_time'],
                    'arrival_time' => $transferTime,
                    'origin' => $previous['destination'] ?? '',
                    'destination' => $segment['origin'] ?? '',
                    'route_short_name' => 'Cammina'
                ];
            }
            $legs = array_merge($legs, $segment['legs'] ?? []);
            $stopsCount += (int)($segment['stops_count'] ?? 0);
        }

        return [
            'type' => 'transfer',
            'service' => 'mixed',
            'mode' => 'mixed',
            'departure_time' => $first['departure_time'],
            'arrival_time' => $last['arrival_time'],
            'duration' => $this->calculateDuration($first['departure_time'], $last['arrival_time']),
            'stops_count' => $stopsCount,
            'route_short_name' => implode(' → ', array_filter(array_map(fn($s) => $s['route_short_name'] ?? '', $segments))),
            'route_long_name' => $transferNames ? 'Cambi a ' . implode(' e ', $transferNames) : 'Percorso multimodale',
            'transfer_stop' => implode(' / ', $transferNames),
            'legs' => $legs
        ];
    }

    private function findBusToNavigation(string $originId, string $navDest, string $departureTime): array {
        $nav = $this->getNavigationPlanner();
        $out = [];
        $candidateIds = $this->getInterchangeBusStopIds($nav, $originId);
        foreach ($candidateIds as $busTransferId) {
            $busTransfer = $this->stops[$busTransferId] ?? null;
            if (!$busTransfer) continue;
            $navTransfers = $nav->compatibleStops($nav->getStops(), $busTransfer);
            if (!$navTransfers) continue;
            $first = $this->findRoutes($originId, $busTransferId, $departureTime);
            if (!$first) continue;
            foreach (array_slice($navTransfers, 0, 2) as $navTransferId) {
                $second = $nav->findRoutesMultiForService($navTransferId, $navDest, $this->addMinutes($first[0]['arrival_time'], 5));
                if ($first && $second && ($journey = $this->combineLegs($first[0], $second[0], $busTransferId, true))) $out[] = $journey;
                if ($out) break;
            }
            if ($out) break;
        }
        usort($out, fn($a, $b) => ($a['arrival_time'] ?? '') <=> ($b['arrival_time'] ?? ''));
        return array_slice($out, 0, 10);
    }

    private function findNavigationToBus(string $navOrigin, string $busDest, string $departureTime): array {
        $nav = $this->getNavigationPlanner();
        $out = [];
        $candidateIds = $this->getInterchangeBusStopIds($nav);
        foreach ($candidateIds as $busTransferId) {
            $busTransfer = $this->stops[$busTransferId] ?? null;
            if (!$busTransfer) continue;
            foreach ($nav->compatibleStops($nav->getStops(), $busTransfer) as $navTransferId) {
                $first = $nav->findRoutesMultiForService($navOrigin, $navTransferId, $departureTime);
                $second = $first ? $this->findRoutes($busTransferId, $busDest, $this->addMinutes($first[0]['arrival_time'], 5)) : [];
                if ($first && $second && ($journey = $this->combineLegs($first[0], $second[0], $busTransferId, true))) $out[] = $journey;
            }
        }
        usort($out, fn($a, $b) => ($a['arrival_time'] ?? '') <=> ($b['arrival_time'] ?? ''));
        return array_slice($out, 0, 10);
    }

    private function getInterchangeBusStopIds(RoutePlanner $nav, ?string $originId = null): array {
        $possibleIds = array_keys($this->stops);

        // Limita il lavoro alla porzione di rete raggiungibile dalla partenza.
        // In questo modo una ricerca Marcon -> Murano non deve provare tutti
        // gli approdi compatibili della rete prima di arrivare a Venezia.
        if ($originId !== null && isset($this->stops[$originId], $this->stopRoutesIndex[$originId])) {
            $reachable = [];
            foreach ($this->stopRoutesIndex[$originId] as $routeId) {
                foreach ($this->getRouteStopsAfter($routeId, $originId) as $stopId => $_) {
                    $reachable[$stopId] = true;

                    // Include anche il secondo tratto bus: alcuni approdi,
                    // come Liberta' Santa Chiara, richiedono un cambio bus.
                    foreach ($this->stopRoutesIndex[$stopId] ?? [] as $nextRouteId) {
                        foreach ($this->getRouteStopsAfter($nextRouteId, $stopId) as $nextStopId => $_) {
                            $reachable[$nextStopId] = true;
                        }
                    }
                }
            }
            $possibleIds = array_keys($reachable);
        }

        $ids = [];
        foreach ($possibleIds as $busId) {
            $busStop = $this->stops[$busId] ?? null;
            if (!$busStop || !isset($this->stopRoutesIndex[$busId])) continue;
            if ($nav->compatibleStops($nav->getStops(), $busStop)) $ids[] = $busId;
        }
        return $ids;
    }

    private function combineLegs(array $first, array $second, string $transfer, bool $walking): array {
        $transferTime = $this->addMinutes($first['arrival_time'], 5);
        if ($this->gtfsToSeconds($second['departure_time']) < $this->gtfsToSeconds($transferTime)) return [];
        $dayOffset = max((int)($first['day_offset'] ?? 0), (int)($second['day_offset'] ?? 0));
        return [
            'type' => 'transfer', 'service' => 'mixed', 'day_offset' => $dayOffset,
            'departure_time' => $first['departure_time'], 'arrival_time' => $second['arrival_time'],
            'duration' => $this->calculateDuration($first['departure_time'], $second['arrival_time']),
            'stops_count' => ($first['stops_count'] ?? 0) + ($second['stops_count'] ?? 0),
            'route_short_name' => ($first['route_short_name'] ?? '') . ' → ' . ($second['route_short_name'] ?? ''),
            'route_long_name' => 'Cambio a ' . ($this->getStops()[$transfer]['name'] ?? $transfer),
            'transfer_stop' => $this->getStops()[$transfer]['name'] ?? $transfer,
            'legs' => array_merge($first['legs'] ?? [], $walking ? [[
                'type' => 'walking', 'service' => 'walking', 'mode' => 'walking', 'duration' => 5,
                'departure_time' => $first['arrival_time'], 'arrival_time' => $transferTime,
                'origin' => $first['destination'] ?? '', 'destination' => $this->getStops()[$transfer]['name'] ?? $transfer,
                'route_short_name' => 'Cammina'
            ]] : [], $second['legs'] ?? [])
        ];
    }

    /**
     * Diagnostica: stato della cache caricata.
     */
    public function getCacheStats() {
        return [
            'cache_dir' => $this->cacheDir,
            'stops_json_exists' => file_exists($this->cacheDir . '/stops.json'),
            'routes_json_exists' => file_exists($this->cacheDir . '/routes.json'),
            'index_json_exists' => file_exists($this->cacheDir . '/stop_routes_index.json'),
            'routes_dir_exists' => is_dir($this->routesDir),
            'stops_count' => is_array($this->stops) ? count($this->stops) : 0,
            'routes_count' => is_array($this->routes) ? count($this->routes) : 0,
            'index_count' => is_array($this->stopRoutesIndex) ? count($this->stopRoutesIndex) : 0,
            'sample_stop_ids' => is_array($this->stops) ? array_slice(array_keys($this->stops), 0, 8) : [],
            'sample_index_ids' => is_array($this->stopRoutesIndex) ? array_slice(array_keys($this->stopRoutesIndex), 0, 8) : [],
        ];
    }

    /**
     * Diagnostica: informazioni su una singola fermata richiesta.
     */
    public function debugStop($stopId) {
        $siblings = $this->getSiblingStopIds($stopId);
        $inIndex = [];
        foreach ($siblings as $s) {
            if (is_array($this->stopRoutesIndex) && isset($this->stopRoutesIndex[$s])) {
                $inIndex[$s] = count($this->stopRoutesIndex[$s]);
            }
        }
        return [
            'input' => $stopId,
            'in_stops_cache' => is_array($this->stops) && isset($this->stops[$stopId]),
            'name' => $this->stops[$stopId]['name'] ?? null,
            'directly_in_route_index' => is_array($this->stopRoutesIndex) && isset($this->stopRoutesIndex[$stopId]),
            'siblings' => $siblings,
            'siblings_in_route_index' => $inIndex,
        ];
    }

    /**
     * Restituisce il nome (breve/lungo) di una rotta, gestendo sia il formato
     * della cache (`short_name`/`long_name`) sia quello del DB (`route_short_name`).
     */
    private function getRouteName($routeId, $which = 'short') {
        $info = $this->routes[$routeId] ?? null;
        if (!$info) {
            return $which === 'long' ? '' : $routeId;
        }
        if ($which === 'long') {
            return $info['route_long_name'] ?? $info['long_name'] ?? '';
        }
        return $info['route_short_name'] ?? $info['short_name'] ?? $routeId;
    }

    private function getRouteColor($routeId) {
        $color = $this->routes[$routeId]['route_color']
            ?? $this->routes[$routeId]['color']
            ?? '';
        $color = ltrim(trim((string) $color), '#');
        return preg_match('/^[0-9a-fA-F]{6}$/', $color) ? '#' . strtoupper($color) : null;
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
        // Conserva le route già lette durante la ricerca: il percorso a due
        // cambi visita più volte le stesse linee e non deve rileggere i JSON.
        $this->loadedRoutesData[$routeId] = $data;
        return $data;
    }

    /**
     * Get all stops on a route that come after the origin
     * Returns [stop_id => min_time_offset]
     */
    private function getRouteStopsAfter($routeId, $originId) {
        $trips = $this->getRouteData($routeId);
        
        // Una linea può avere corse che terminano nella fermata richiesta e
        // altre che proseguono, anche in direzioni diverse. Unisci quindi la
        // topologia di tutte le corse: gli orari di findTripsForRoute
        // selezioneranno poi solo la direzione realmente percorribile.
        $stops = [];
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
        }
        
        return $stops;
    }

    private function getRouteStopsBefore($routeId, $destId) {
        $trips = $this->getRouteData($routeId);
        $stops = [];
        foreach ($trips as $trip) {
            $destinationFound = false;
            foreach ($trip as $stop) {
                if ($stop['stop_id'] === $destId) {
                    $destinationFound = true;
                    break;
                }
                $stops[$stop['stop_id']] = 0;
            }
            if ($destinationFound) break;
        }
        return $stops;
    }

    /**
     * Singola tratta diretta usata come arco della ricerca multimodale.
     * Non richiama il fallback a uno/due cambi: quello appartiene alla
     * ricerca principale e, dentro un grafo, moltiplicherebbe inutilmente i
     * rami esplorati.
     */
    private function findDirectRoutes($originId, $destId, $departureTime): array {
        if (!isset($this->stopRoutesIndex[$originId], $this->stopRoutesIndex[$destId])) return [];
        $results = [];
        foreach (array_intersect($this->stopRoutesIndex[$originId], $this->stopRoutesIndex[$destId]) as $routeId) {
            $results = array_merge($results, $this->findTripsForRoute($routeId, $originId, $destId, $departureTime));
        }
        usort($results, fn($a, $b) => strcmp($a['departure_time'] ?? '', $b['departure_time'] ?? ''));
        return array_slice($results, 0, 3);
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
                    $shortName = $this->getRouteName($routeId, 'short');
                    $longName = $this->getRouteName($routeId, 'long');
                    $originName = $this->stops[$originId]['name'] ?? $originId;
                    $destName = $this->stops[$destId]['name'] ?? $destId;
                    $dep = $originStop['departure_time'];
                    $arr = $destStop['arrival_time'];
                    $stopsCount = $destStop['stop_sequence'] - $originStop['stop_sequence'];

                    $validTrips[] = [
                        'type' => 'direct',
                        'route_id' => $routeId,
                        'trip_id' => $tripId,
                        'departure_time' => $dep,
                        'arrival_time' => $arr,
                        'duration' => $this->calculateDuration($dep, $arr),
                        'stops_count' => $stopsCount,
                        'route_short_name' => $shortName,
                        'route_long_name' => $longName,
                        'route_color' => $this->getRouteColor($routeId),
                        'route_text_color' => $this->routes[$routeId]['route_text_color'] ?? $this->routes[$routeId]['text_color'] ?? '#FFFFFF',
                        'service' => $this->service,
                        'mode' => $this->service === 'navigation' ? 'water' : 'bus',
                        'origin' => $originName,
                        'destination' => $destName,
                        'origin_id' => (string) $originId,
                        'destination_id' => (string) $destId,
                        // Una tratta diretta è composta da una singola "leg" (corsa bus)
                        'legs' => [[
                            'type' => $this->service === 'navigation' ? 'water' : 'bus',
                            'service' => $this->service,
                            'mode' => $this->service === 'navigation' ? 'water' : 'bus',
                            'route_id' => $routeId,
                            'trip_id' => $tripId,
                            'route_color' => $this->getRouteColor($routeId),
                            'route_text_color' => $this->routes[$routeId]['route_text_color'] ?? $this->routes[$routeId]['text_color'] ?? '#FFFFFF',
                    'route_short_name' => $shortName,
                    'route_long_name' => $longName,
                    'route_color' => $this->getRouteColor($routeId),
                            'departure_time' => $dep,
                            'arrival_time' => $arr,
                            'stops_count' => $stopsCount,
                            'origin' => $originName,
                            'destination' => $destName,
                            'origin_id' => (string) $originId,
                            'destination_id' => (string) $destId,
                        ]],
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

        if ($this->service === 'automobilistico') {
            foreach ($this->getNavigationPlanner()->getStops() as $stop) {
                $dist = $this->calculateGeoDistance($lat, $lon, $stop['lat'], $stop['lon']);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $nearestStop = $stop;
                }
            }
        }
        
        if ($nearestStop) {
            $nearestStop['distance'] = round($minDist); // meters
            $nearestStop['service'] = $nearestStop['service'] ?? $this->service;
            $nearestStop['mode'] = $nearestStop['mode'] ?? ($nearestStop['service'] === 'navigation' ? 'water' : 'bus');
            // Estimate walking time: 5km/h = 83 m/min 
            $nearestStop['walking_time'] = ceil($minDist / 83); 
        }
        
        return $nearestStop;
    }

    /**
     * Get all lines serving a stop with their next scheduled departures
     * @param string $stopId
     * @param string $afterTime HH:MM:SS - only show departures after this time
     * @param int $limit Max departures per line
     * @return array
     */
    public function getLinesForStop($stopId, $afterTime = '00:00:00', $limit = 5) {
        if (!$this->stopRoutesIndex || !isset($this->stopRoutesIndex[$stopId])) {
            return [];
        }

        $routeIds = $this->stopRoutesIndex[$stopId];
        $lines = [];

        foreach ($routeIds as $routeId) {
            $routeInfo = $this->routes[$routeId] ?? null;
            if (!$routeInfo) continue;

            $trips = $this->getRouteData($routeId);
            $departures = [];

            foreach ($trips as $tripId => $stops) {
                foreach ($stops as $stop) {
                    if ($stop['stop_id'] == $stopId && $stop['departure_time'] >= $afterTime) {
                        $lastStop = end($stops);
                        $departures[] = [
                            'trip_id' => (string) $tripId,
                            'time' => substr($stop['departure_time'], 0, 5),
                            'destination' => $this->stops[$lastStop['stop_id']]['name'] ?? $lastStop['stop_id']
                        ];
                        break;
                    }
                }
            }

            usort($departures, function($a, $b) {
                return strcmp($a['time'], $b['time']);
            });

            if (!empty($departures)) {
                $lines[] = [
                    'route_id' => $routeId,
                    'service' => $this->service,
                    'mode' => $this->service === 'navigation' ? 'water' : 'bus',
                    'route_short_name' => $this->getRouteName($routeId, 'short'),
                    'route_long_name' => $this->getRouteName($routeId, 'long'),
                    'route_color' => $this->getRouteColor($routeId),
                    'route_text_color' => $routeInfo['route_text_color'] ?? $routeInfo['text_color'] ?? '#FFFFFF',
                    'stop_lat' => (float) ($this->stops[$stopId]['lat'] ?? $this->stops[$stopId]['stop_lat'] ?? 0),
                    'stop_lon' => (float) ($this->stops[$stopId]['lon'] ?? $this->stops[$stopId]['stop_lon'] ?? 0),
                    'departures' => array_slice($departures, 0, $limit)
                ];
            }
        }

        usort($lines, function($a, $b) {
            return strcmp($a['route_short_name'], $b['route_short_name']);
        });

        return $lines;
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
