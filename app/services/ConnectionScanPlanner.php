<?php

require_once __DIR__ . '/ConnectionCacheBuilder.php';

/** Earliest-arrival public-transport planner based on Connection Scan. */
class ConnectionScanPlanner {
    private const TRANSFER_SECONDS = 120;
    private const MAX_WALK_METERS = 300;
    private ConnectionCacheBuilder $cacheBuilder;
    private array $stops = [];
    private array $routes = [];
    private array $keysByRawId = [];
    private array $stationGroups = [];
    private array $footpaths = [];

    public function __construct(?string $dataRoot = null) {
        $dataRoot = rtrim($dataRoot ?: BASE_PATH . '/data', '/');
        $this->cacheBuilder = new ConnectionCacheBuilder($dataRoot);
        $this->loadMetadata('automobilistico', 'bus', $dataRoot . '/gtfs/cache');
        $this->loadMetadata('navigation', 'water', $dataRoot . '/gtfs/cache/navigation');
        $this->buildFootpaths();
    }

    public function plan(
        string $originId,
        string $destinationId,
        string $date,
        string $time,
        ?string $originService = null,
        ?string $destinationService = null
    ): array {
        $requested = ConnectionCacheBuilder::timeToSeconds(strlen($time) === 5 ? $time . ':00' : $time);
        if ($requested === null) throw new InvalidArgumentException('Ora non valida');

        $origins = $this->resolveKeys($originId, $originService);
        $destinations = $this->resolveKeys($destinationId, $destinationService);
        if (!$origins || !$destinations) return [];

        $infinity = PHP_INT_MAX;
        $arrival = [];
        $ready = [];
        $nodes = [];
        $stopNode = [];
        foreach ($this->stops as $key => $_) {
            $arrival[$key] = $infinity;
            $ready[$key] = $infinity;
        }
        foreach ($origins as $key) {
            $arrival[$key] = $requested;
            $ready[$key] = $requested;
            $stopNode[$key] = $this->addNode($nodes, ['type' => 'origin', 'stop' => $key, 'arrival' => $requested, 'parent' => null]);
            $this->relaxFootpaths($key, $requested, $arrival, $ready, $nodes, $stopNode);
        }

        $destinationSet = array_fill_keys($destinations, true);
        $bestDestination = null;
        $bestArrival = $infinity;
        $destinationCandidates = [];
        $tripNode = [];

        for ($dayOffset = 0; $dayOffset <= 1; $dayOffset++) {
            $serviceDate = (new DateTimeImmutable($date))->modify('+' . $dayOffset . ' day')->format('Y-m-d');
            $path = $this->cacheBuilder->ensure($serviceDate);
            $handle = fopen($path, 'r');
            if (!$handle) continue;
            while (($line = fgets($handle)) !== false) {
                if ($line === '' || $line[0] === '#') continue;
                $fields = explode("\t", rtrim($line, "\r\n"));
                if (count($fields) < 9) continue;
                [$departureRaw, $arrivalRaw, $from, $to, $tripId, $routeKey, $service, $mode, $sequence] = $fields;
                $departure = (int)$departureRaw + ($dayOffset * 86400);
                $connectionArrival = (int)$arrivalRaw + ($dayOffset * 86400);
                if ($departure < $requested || !isset($arrival[$from], $arrival[$to])) continue;
                if ($bestArrival !== $infinity && $departure > $bestArrival) break;

                $tripKey = $service . ':' . $tripId;
                $parentNode = $tripNode[$tripKey] ?? null;
                if ($parentNode === null && $ready[$from] <= $departure) $parentNode = $stopNode[$from] ?? null;
                if ($parentNode === null) continue;

                $connectionNode = $this->addNode($nodes, [
                    'type' => 'transit', 'parent' => $parentNode,
                    'from' => $from, 'to' => $to,
                    'departure' => $departure, 'arrival' => $connectionArrival,
                    'trip_id' => $tripId, 'route_key' => $routeKey,
                    'service' => $service, 'mode' => $mode,
                    'sequence' => (int)$sequence,
                ]);
                $tripNode[$tripKey] = $connectionNode;

                if ($connectionArrival < $arrival[$to]) {
                    $arrival[$to] = $connectionArrival;
                    $ready[$to] = $connectionArrival + self::TRANSFER_SECONDS;
                    $stopNode[$to] = $connectionNode;
                    $this->relaxFootpaths($to, $connectionArrival, $arrival, $ready, $nodes, $stopNode);
                }

                foreach ($destinationSet as $destinationKey => $_) {
                    if (($arrival[$destinationKey] ?? $infinity) < $bestArrival) {
                        $bestArrival = $arrival[$destinationKey];
                        $bestDestination = $destinationKey;
                        if (isset($stopNode[$destinationKey])) {
                            $destinationCandidates[] = [
                                'arrival' => $bestArrival,
                                'node' => $stopNode[$destinationKey],
                            ];
                        }
                    }
                }
            }
            fclose($handle);
            if ($bestDestination !== null) break;
        }

        if ($bestDestination === null) return [];
        $journeys = [];
        foreach ($destinationCandidates as $candidate) {
            $edges = $this->reconstruct($candidate['node'], $nodes);
            if (!$edges) continue;
            $journey = $this->formatJourney($edges, $requested, $candidate['arrival']);
            $signature = implode('|', array_map(
                fn($leg) => ($leg['trip_id'] ?? 'walk') . ':' . ($leg['origin_id'] ?? '') . ':' . ($leg['destination_id'] ?? ''),
                $journey['legs']
            ));
            $journeys[$signature] = $journey;
        }
        $journeys = array_values($journeys);
        usort($journeys, fn($a, $b) => ($a['arrival_time'] <=> $b['arrival_time']));
        return array_slice($journeys, 0, 5);
    }

    public function planAlternatives(
        string $originId,
        string $destinationId,
        string $date,
        string $time,
        ?string $originService = null,
        ?string $destinationService = null,
        int $limit = 3
    ): array {
        $base = ConnectionCacheBuilder::timeToSeconds(strlen($time) === 5 ? $time . ':00' : $time);
        if ($base === null) throw new InvalidArgumentException('Ora non valida');
        $unique = [];
        foreach ([0, 15, 30] as $offsetMinutes) {
            $seconds = $base + ($offsetMinutes * 60);
            $searchDate = (new DateTimeImmutable($date))->modify('+' . intdiv($seconds, 86400) . ' day')->format('Y-m-d');
            $searchTime = $this->formatSeconds($seconds);
            foreach ($this->plan($originId, $destinationId, $searchDate, $searchTime, $originService, $destinationService) as $journey) {
                $signature = implode('|', array_column(
                    array_values(array_filter($journey['legs'], fn($leg) => isset($leg['trip_id']))),
                    'trip_id'
                ));
                if ($signature !== '') $unique[$signature] = $journey;
            }
            if (count($unique) >= $limit) break;
        }
        $routes = array_values($unique);
        usort($routes, fn($a, $b) => ($a['arrival_time'] <=> $b['arrival_time']));
        return array_slice($routes, 0, $limit);
    }

    private function loadMetadata(string $service, string $mode, string $cacheDir): void {
        $stops = is_file($cacheDir . '/stops.json')
            ? json_decode((string)file_get_contents($cacheDir . '/stops.json'), true) : [];
        foreach (is_array($stops) ? $stops : [] as $rawId => $stop) {
            $key = $service . ':' . $rawId;
            $this->stops[$key] = [
                'id' => (string)$rawId,
                'key' => $key,
                'name' => (string)($stop['name'] ?? $rawId),
                'lat' => (float)($stop['lat'] ?? 0),
                'lon' => (float)($stop['lon'] ?? 0),
                'service' => $service,
                'mode' => $mode,
            ];
            $this->keysByRawId[(string)$rawId][] = $key;
            $this->stationGroups[$this->stationName($this->stops[$key]['name'])][] = $key;
        }

        $routes = is_file($cacheDir . '/routes.json')
            ? json_decode((string)file_get_contents($cacheDir . '/routes.json'), true) : [];
        foreach (is_array($routes) ? $routes : [] as $routeId => $route) {
            $this->routes[$service . ':' . $routeId] = $route + ['id' => $routeId, 'service' => $service, 'mode' => $mode];
        }
    }

    private function buildFootpaths(): void {
        foreach ($this->stationGroups as $keys) {
            foreach ($keys as $from) foreach ($keys as $to) {
                if ($from === $to) continue;
                $distance = $this->distance($this->stops[$from], $this->stops[$to]);
                if ($distance <= 600) $this->addFootpath($from, $to, $distance);
            }
        }

        $bus = array_filter($this->stops, fn($stop) => $stop['service'] === 'automobilistico');
        $water = array_filter($this->stops, fn($stop) => $stop['service'] === 'navigation');
        foreach ($bus as $busKey => $busStop) foreach ($water as $waterKey => $waterStop) {
            $distance = $this->distance($busStop, $waterStop);
            if ($distance <= self::MAX_WALK_METERS) {
                $this->addFootpath($busKey, $waterKey, $distance);
                $this->addFootpath($waterKey, $busKey, $distance);
            }
        }
    }

    private function addFootpath(string $from, string $to, float $distance): void {
        $duration = max(self::TRANSFER_SECONDS, (int)ceil($distance / 1.2));
        $this->footpaths[$from][$to] = ['seconds' => $duration, 'distance' => (int)round($distance)];
    }

    private function relaxFootpaths(
        string $from,
        int $fromArrival,
        array &$arrival,
        array &$ready,
        array &$nodes,
        array &$stopNode
    ): void {
        foreach ($this->footpaths[$from] ?? [] as $to => $walk) {
            $candidate = $fromArrival + $walk['seconds'];
            if ($candidate >= ($arrival[$to] ?? PHP_INT_MAX)) continue;
            $arrival[$to] = $candidate;
            $ready[$to] = $candidate;
            $stopNode[$to] = $this->addNode($nodes, [
                'type' => 'walking', 'parent' => $stopNode[$from] ?? null, 'from' => $from, 'to' => $to,
                'departure' => $fromArrival, 'arrival' => $candidate,
                'distance' => $walk['distance'],
            ]);
        }
    }

    private function resolveKeys(string $rawId, ?string $service): array {
        $keys = $this->keysByRawId[$rawId] ?? [];
        if ($service) $keys = array_values(array_filter($keys, fn($key) => str_starts_with($key, $service . ':')));
        $expanded = array_fill_keys($keys, true);
        foreach ($keys as $key) {
            $group = $this->stationGroups[$this->stationName($this->stops[$key]['name'])] ?? [];
            foreach ($group as $sibling) {
                if ($this->distance($this->stops[$key], $this->stops[$sibling]) <= 600) $expanded[$sibling] = true;
            }
        }
        return array_keys($expanded);
    }

    private function addNode(array &$nodes, array $node): int {
        $nodes[] = $node;
        return count($nodes) - 1;
    }

    private function reconstruct(?int $nodeId, array $nodes): array {
        $edges = [];
        $guard = 0;
        while ($nodeId !== null && isset($nodes[$nodeId]) && $guard++ < 10000) {
            $node = $nodes[$nodeId];
            if (($node['type'] ?? '') !== 'origin') $edges[] = $node;
            $nodeId = $node['parent'] ?? null;
        }
        return array_reverse($edges);
    }

    private function formatJourney(array $edges, int $requested, int $arrival): array {
        $legs = [];
        foreach ($edges as $edge) {
            if ($edge['type'] === 'walking') {
                $legs[] = [
                    'type' => 'walking', 'service' => 'walking', 'mode' => 'walking',
                    'departure_time' => $this->formatSeconds($edge['departure']),
                    'arrival_time' => $this->formatSeconds($edge['arrival']),
                    'duration' => (int)ceil(($edge['arrival'] - $edge['departure']) / 60),
                    'distance' => $edge['distance'],
                    'origin' => $this->stops[$edge['from']]['name'],
                    'destination' => $this->stops[$edge['to']]['name'],
                    'origin_id' => $this->stops[$edge['from']]['id'],
                    'destination_id' => $this->stops[$edge['to']]['id'],
                    'route_short_name' => 'Cammina', 'stops_count' => 0,
                ];
                continue;
            }

            $last = count($legs) - 1;
            if ($last >= 0 && ($legs[$last]['trip_id'] ?? null) === $edge['trip_id']) {
                $legs[$last]['arrival_time'] = $this->formatSeconds($edge['arrival']);
                $legs[$last]['destination'] = $this->stops[$edge['to']]['name'];
                $legs[$last]['destination_id'] = $this->stops[$edge['to']]['id'];
                $legs[$last]['stops_count']++;
                continue;
            }

            $route = $this->routes[$edge['route_key']] ?? [];
            $legs[] = [
                'type' => $edge['mode'] === 'water' ? 'water' : 'bus',
                'service' => $edge['service'], 'mode' => $edge['mode'],
                'route_id' => (string)($route['id'] ?? substr($edge['route_key'], strpos($edge['route_key'], ':') + 1)),
                'trip_id' => $edge['trip_id'],
                'route_short_name' => (string)($route['short_name'] ?? $route['route_short_name'] ?? ''),
                'route_long_name' => (string)($route['long_name'] ?? $route['route_long_name'] ?? ''),
                'route_color' => $route['route_color'] ?? $route['color'] ?? null,
                'route_text_color' => $route['route_text_color'] ?? $route['text_color'] ?? '#FFFFFF',
                'departure_time' => $this->formatSeconds($edge['departure']),
                'arrival_time' => $this->formatSeconds($edge['arrival']),
                'origin' => $this->stops[$edge['from']]['name'],
                'destination' => $this->stops[$edge['to']]['name'],
                'origin_id' => $this->stops[$edge['from']]['id'],
                'destination_id' => $this->stops[$edge['to']]['id'],
                'stops_count' => 1,
            ];
        }

        $transit = array_values(array_filter($legs, fn($leg) => $leg['type'] !== 'walking'));
        $first = $legs[0];
        $last = $legs[count($legs) - 1];
        return [
            'type' => count($transit) > 1 ? 'transfer' : 'direct',
            'service' => count(array_unique(array_column($transit, 'service'))) > 1 ? 'mixed' : ($transit[0]['service'] ?? 'walking'),
            'mode' => count(array_unique(array_column($transit, 'mode'))) > 1 ? 'mixed' : ($transit[0]['mode'] ?? 'walking'),
            'departure_time' => $first['departure_time'], 'arrival_time' => $last['arrival_time'],
            'duration' => (int)ceil(($arrival - $requested) / 60),
            'day_offset' => intdiv($arrival, 86400),
            'stops_count' => array_sum(array_column($transit, 'stops_count')),
            'route_short_name' => implode(' → ', array_column($transit, 'route_short_name')),
            'route_long_name' => 'Percorso con ' . count($transit) . ' mezzi',
            'transfer_stop' => '', 'legs' => $legs,
        ];
    }

    private function stationName(string $name): string {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = preg_replace('/["“”]\s*[a-z]\s*["“”]$/u', '', $name);
        $name = preg_replace('/\s+(?:corsia\s+)?[a-z]\d+$/u', '', $name);
        return preg_replace('/\s+/', ' ', trim($name));
    }

    private function distance(array $a, array $b): float {
        $earth = 6371000;
        $lat1 = deg2rad($a['lat']); $lat2 = deg2rad($b['lat']);
        $dLat = $lat2 - $lat1; $dLon = deg2rad($b['lon'] - $a['lon']);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }

    private function formatSeconds(int $seconds): string {
        $seconds %= 86400;
        if ($seconds < 0) $seconds += 86400;
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
