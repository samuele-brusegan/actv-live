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

    /** Human-readable validity, including additions and cancellations. */
    public static function describeCalendar(array $calendar, array $exceptions = []): string
    {
        $days = [];
        foreach (['monday'=>'Lun', 'tuesday'=>'Mar', 'wednesday'=>'Mer', 'thursday'=>'Gio', 'friday'=>'Ven', 'saturday'=>'Sab', 'sunday'=>'Dom'] as $key=>$label) {
            if (($calendar[$key] ?? '0') === '1' || ($calendar[$key] ?? 0) === 1) $days[] = $label;
        }
        $format = fn($date) => substr($date, 6, 2) . '/' . substr($date, 4, 2) . '/' . substr($date, 0, 4);
        $text = count($days) === 7 ? 'Tutti i giorni' : implode(' · ', $days);
        if ($text && !empty($calendar['start_date']) && !empty($calendar['end_date'])) {
            $text .= ' (' . $format($calendar['start_date']) . ' – ' . $format($calendar['end_date']) . ')';
        }
        foreach ($exceptions as $exception) {
            $text .= ($text ? '; ' : '') . (($exception['exception_type'] ?? '') == 1 ? 'Anche il ' : 'Escluso il ') . $format($exception['date']);
        }
        return $text ?: 'Validità non disponibile';
    }

    private function calendarLabel(string $serviceId): string
    {
        return self::describeCalendar($this->calendar[$serviceId] ?? [], array_values(array_filter(
            $this->exceptions, fn($row) => ($row['service_id'] ?? '') === $serviceId
        )));
    }

    private function activeTrips(string $line, ?string $date): array
    {
        $routeIds = [];
        foreach ($this->routes as $id => $route) if (($route['route_short_name'] ?? '') === $line) $routeIds[$id] = true;
        $out = [];
        foreach ($this->trips as $trip) if (isset($routeIds[$trip['route_id'] ?? '']) && ($date === null || $this->active($trip['service_id'] ?? '', $date))) $out[] = $trip;
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
            if (isset($wanted[$tripId])) {
                $row = array_combine($headers, $values);
                $this->stopTimesByTrip[$tripId][] = [
                    'stop_id'=>$row['stop_id'], 'stop_sequence'=>$row['stop_sequence'],
                    'departure_time'=>$row['departure_time'] ?? $row['arrival_time'] ?? '',
                ];
            }
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

    public function catalog(?string $date): array
    {
        $counts = [];
        foreach ($this->trips as $trip) {
            if (($date !== null && !$this->active($trip['service_id'] ?? '', $date)) || !isset($this->routes[$trip['route_id'] ?? ''])) continue;
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

    public function variants(string $line, ?string $date): array
    {
        $groups = [];
        $activeTrips = $this->activeTrips($line, $date);
        // Count in a stream, then retain only one representative per shape.
        // Loading every trip's stop rows here exceeds PHP's 128 MB budget.
        $counts = array_fill_keys(array_column($activeTrips, 'trip_id'), 0);
        $file = $this->root . '/stop_times.txt';
        if (is_file($file) && ($h = fopen($file, 'r'))) {
            $headers = fgetcsv($h, 0, ',', '"', '\\');
            $tripColumn = array_search('trip_id', $headers, true);
            while (($values = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
                $id = $values[$tripColumn] ?? '';
                if (isset($counts[$id])) $counts[$id]++;
            }
            fclose($h);
        }
        foreach ($activeTrips as $trip) {
            $key = ($trip['shape_id'] ?? '') . '|' . ($trip['trip_headsign'] ?? '');
            $count = ($groups[$key]['count'] ?? 0) + 1;
            if (!isset($groups[$key]) || $counts[$trip['trip_id']] > $counts[$groups[$key]['trip']['trip_id']]) {
                $groups[$key] = ['trip' => $trip];
            }
            $groups[$key]['count'] = $count;
        }
        $this->loadStopTimes(array_map(fn($g) => $g['trip']['trip_id'], $groups));
        foreach ($groups as &$group) $group['stops'] = $this->stopTimes($group['trip']['trip_id']);
        unset($group);
        $out = [];
        foreach ($groups as $g) {
            $stops = array_map(fn($s) => ['id'=>$s['stop_id'], 'name'=>$this->stops[$s['stop_id']]['stop_name'] ?? $s['stop_id'], 'lat'=>$this->stops[$s['stop_id']]['stop_lat'] ?? null, 'lng'=>$this->stops[$s['stop_id']]['stop_lon'] ?? null, 'seq'=>$s['stop_sequence'], 'time'=>$s['departure_time'] ?? $s['arrival_time'] ?? null], $g['stops']);
            $out[] = ['shape_id'=>$g['trip']['shape_id'] ?? null, 'headsign'=>$g['trip']['trip_headsign'] ?? '', 'direction_id'=>$g['trip']['direction_id'] ?? null, 'trip_id'=>$g['trip']['trip_id'], 'stops_count'=>count($stops), 'trips_count'=>$g['count'], 'stops'=>$stops, 'origin'=>$stops[0]['name'] ?? null, 'terminus'=>end($stops)['name'] ?? null];
        }
        return $out;
    }

    public function schedule(string $line, array $representatives, ?string $date): array
    {
        $variants = $this->variants($line, $date); $wanted = [];
        foreach ($variants as $v) if (in_array($v['trip_id'], $representatives, true)) $wanted[$v['shape_id'].'|'.$v['headsign']] = true;
        $runs = [];
        $selectedTrips = array_values(array_filter($this->activeTrips($line, $date), fn($t) => isset($wanted[($t['shape_id'] ?? '').'|'.($t['trip_headsign'] ?? '')])));
        $this->loadStopTimes(array_column($selectedTrips, 'trip_id'));
        foreach ($selectedTrips as $trip) {
            if (!isset($wanted[($trip['shape_id'] ?? '').'|'.($trip['trip_headsign'] ?? '')])) continue;
            $times = $this->stopTimes($trip['trip_id']); $mapped = [];
            foreach ($times as $row) $mapped[$row['stop_id']] = substr($row['departure_time'] ?? $row['arrival_time'] ?? '', 0, 5);
            $runs[] = ['trip_id'=>$trip['trip_id'], 'headsign'=>$trip['trip_headsign'] ?? '', 'start'=>reset($mapped) ?: '99:99', 'times'=>$mapped, 'calendar'=>$this->calendarLabel($trip['service_id'] ?? '')];
        }
        usort($runs, fn($a,$b) => strcmp($a['start'], $b['start']));
        $selectedVariants = array_values(array_filter($variants, fn($v) => in_array($v['trip_id'], $representatives, true)));
        usort($selectedVariants, fn($a,$b) => count($b['stops']) <=> count($a['stops']));
        $base = $selectedVariants[0] ?? null;
        $stops = $base['stops'] ?? [];
        foreach (array_slice($selectedVariants, 1) as $variant) {
            $pending = [];
            foreach ($variant['stops'] as $stop) {
                $index = array_search($stop['id'], array_column($stops, 'id'), true);
                if ($index === false) { $pending[] = $stop; continue; }
                if ($pending) { array_splice($stops, $index, 0, $pending); $pending = []; }
            }
            if ($pending) $stops = array_merge($stops, $pending);
        }
        foreach ($runs as &$run) { $run['times'] = array_map(fn($s) => $run['times'][$s['id']] ?? null, $stops); } unset($run);
        return ['headsign'=>$base['headsign'] ?? '', 'stops'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name']], $stops), 'runs'=>$runs];
    }
}
