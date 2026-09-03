<?php

/** Shared, file-backed provider for the isolated navigation GTFS feed. */
class LineScheduleProvider
{
    private string $root;
    private array $routes;
    private array $trips;
    private array $stops;
    private array $calendar;
    private array $exceptions;
    private array $stopTimesByTrip = [];

    public function __construct(?string $root = null)
    {
        $this->root = $root ?: BASE_PATH . '/data/gtfs/navigation';
        foreach (['routes', 'trips', 'stops', 'calendar', 'calendar_dates'] as $file) {
            $this->{$file === 'calendar_dates' ? 'exceptions' : $file} = $this->readCsv($this->root . '/' . $file . '.txt');
        }
        $this->routes = array_column($this->routes, null, 'route_id');
        $this->trips = array_column($this->trips, null, 'trip_id');
        $this->stops = array_column($this->stops, null, 'stop_id');
        $this->calendar = array_column($this->calendar, null, 'service_id');
    }

    private function readCsv(string $file): array
    {
        if (!is_file($file) || !($h = fopen($file, 'r'))) return [];
        $headers = fgetcsv($h, 0, ',', '"', '\\');
        $rows = [];
        while (($values = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            if (count($values) === count($headers)) $rows[] = array_combine($headers, $values);
        }
        fclose($h);
        return $rows;
    }

    public function updatedAt(): ?string
    {
        $files = glob($this->root . '/*.txt') ?: [];
        $mtime = 0;
        foreach ($files as $file) $mtime = max($mtime, (int) filemtime($file));
        return $mtime ? date(DATE_ATOM, $mtime) : null;
    }

    public function active(string $serviceId, string $date): bool
    {
        $date = str_replace('-', '', $date);
        $active = isset($this->calendar[$serviceId])
            && $date >= ($this->calendar[$serviceId]['start_date'] ?? '')
            && $date <= ($this->calendar[$serviceId]['end_date'] ?? '')
            && (($this->calendar[$serviceId][strtolower(date('l', strtotime($date)))] ?? '0') === '1');
        foreach ($this->exceptions as $row) {
            if (($row['service_id'] ?? '') !== $serviceId || ($row['date'] ?? '') !== $date) continue;
            if (($row['exception_type'] ?? '') === '1') $active = true;
            if (($row['exception_type'] ?? '') === '2') $active = false;
        }
        return $active;
    }

    private function activeTrips(string $line, string $date): array
    {
        $routeIds = [];
        foreach ($this->routes as $id => $route) if (($route['route_short_name'] ?? '') === $line) $routeIds[$id] = true;
        $out = [];
        foreach ($this->trips as $trip) if (isset($routeIds[$trip['route_id'] ?? '']) && $this->active($trip['service_id'] ?? '', $date)) $out[] = $trip;
        return $out;
    }

    private function loadStopTimes(array $tripIds): void
    {
        $wanted = array_fill_keys($tripIds, true);
        foreach ($tripIds as $tripId) {
            if (array_key_exists($tripId, $this->stopTimesByTrip)) unset($wanted[$tripId]);
        }
        $file = $this->root . '/stop_times.txt';
        if (!$wanted || !is_file($file) || !($h = fopen($file, 'r'))) return;
        $headers = fgetcsv($h, 0, ',', '"', '\\');
        while (($values = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            if (count($values) !== count($headers)) continue;
            $tripId = $values[array_search('trip_id', $headers, true)] ?? '';
            if (isset($wanted[$tripId])) $this->stopTimesByTrip[$tripId][] = array_combine($headers, $values);
        }
        fclose($h);
        foreach ($tripIds as $tripId) {
            if (isset($this->stopTimesByTrip[$tripId])) {
                usort($this->stopTimesByTrip[$tripId], fn($a, $b) => (int)$a['stop_sequence'] <=> (int)$b['stop_sequence']);
            }
        }
    }

    private function stopTimes(string $tripId): array
    {
        return $this->stopTimesByTrip[$tripId] ?? [];
    }

    public function catalog(string $date): array
    {
        $counts = [];
        foreach ($this->trips as $trip) {
            if (!$this->active($trip['service_id'] ?? '', $date) || !isset($this->routes[$trip['route_id'] ?? ''])) continue;
            $route = $this->routes[$trip['route_id']]; $line = $route['route_short_name'] ?? '';
            if ($line === '') continue;
            $counts[$line]['name'] = $route['route_long_name'] ?? '';
            $counts[$line]['trips_count'] = ($counts[$line]['trips_count'] ?? 0) + 1;
            $key = ($trip['shape_id'] ?? '') . '|' . ($trip['trip_headsign'] ?? '');
            $counts[$line]['variants'][$key] = true;
        }
        $out = [];
        foreach ($counts as $line => $item) $out[] = ['line' => (string) $line, 'name' => $item['name'], 'variants_count' => count($item['variants']), 'trips_count' => $item['trips_count']];
        usort($out, fn($a, $b) => strnatcasecmp($a['line'], $b['line']));
        return $out;
    }

    public function variants(string $line, string $date): array
    {
        $groups = [];
        $activeTrips = $this->activeTrips($line, $date);
        $this->loadStopTimes(array_column($activeTrips, 'trip_id'));
        foreach ($activeTrips as $trip) {
            $key = ($trip['shape_id'] ?? '') . '|' . ($trip['trip_headsign'] ?? '');
            $st = $this->stopTimes($trip['trip_id']);
            if (!isset($groups[$key]) || count($st) > count($groups[$key]['stops'])) $groups[$key] = ['trip' => $trip, 'stops' => $st];
            $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
        }
        $out = [];
        foreach ($groups as $g) {
            $stops = array_map(fn($s) => ['id'=>$s['stop_id'], 'name'=>$this->stops[$s['stop_id']]['stop_name'] ?? $s['stop_id'], 'lat'=>$this->stops[$s['stop_id']]['stop_lat'] ?? null, 'lng'=>$this->stops[$s['stop_id']]['stop_lon'] ?? null, 'seq'=>$s['stop_sequence'], 'time'=>$s['departure_time'] ?? $s['arrival_time'] ?? null], $g['stops']);
            $out[] = ['shape_id'=>$g['trip']['shape_id'] ?? null, 'headsign'=>$g['trip']['trip_headsign'] ?? '', 'direction_id'=>$g['trip']['direction_id'] ?? null, 'trip_id'=>$g['trip']['trip_id'], 'stops_count'=>count($stops), 'trips_count'=>$g['count'], 'stops'=>$stops, 'origin'=>$stops[0]['name'] ?? null, 'terminus'=>end($stops)['name'] ?? null];
        }
        return $out;
    }

    public function schedule(string $line, array $representatives, string $date): array
    {
        $variants = $this->variants($line, $date); $wanted = [];
        foreach ($variants as $v) if (in_array($v['trip_id'], $representatives, true)) $wanted[$v['shape_id'].'|'.$v['headsign']] = true;
        $runs = [];
        foreach ($this->activeTrips($line, $date) as $trip) {
            if (!isset($wanted[($trip['shape_id'] ?? '').'|'.($trip['trip_headsign'] ?? '')])) continue;
            $times = $this->stopTimes($trip['trip_id']); $mapped = [];
            foreach ($times as $row) $mapped[$row['stop_id']] = substr($row['departure_time'] ?? $row['arrival_time'] ?? '', 0, 5);
            $runs[] = ['trip_id'=>$trip['trip_id'], 'headsign'=>$trip['trip_headsign'] ?? '', 'start'=>reset($mapped) ?: '99:99', 'times'=>$mapped];
        }
        usort($runs, fn($a,$b) => strcmp($a['start'], $b['start']));
        $base = $variants[0] ?? null; foreach ($variants as $v) if (in_array($v['trip_id'], $representatives, true)) { $base=$v; break; }
        $stops = $base['stops'] ?? [];
        foreach ($runs as &$run) { $run['times'] = array_map(fn($s) => $run['times'][$s['id']] ?? null, $stops); } unset($run);
        return ['headsign'=>$base['headsign'] ?? '', 'stops'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name']], $stops), 'runs'=>$runs];
    }
}
