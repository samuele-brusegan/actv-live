<?php

/**
 * Builds a date-specific, globally ordered list of GTFS connections.
 * The expensive work happens once per feed/date, never during graph search.
 */
class ConnectionCacheBuilder {
    private string $plannerDir;
    private array $profiles;

    public function __construct(?string $dataRoot = null, ?string $plannerDir = null) {
        $dataRoot = rtrim($dataRoot ?: BASE_PATH . '/data', '/');
        $this->plannerDir = $plannerDir ?: $dataRoot . '/gtfs/cache/planner';
        $this->profiles = [
            'automobilistico' => [
                'data' => $dataRoot . '/gtfs',
                'cache' => $dataRoot . '/gtfs/cache',
                'mode' => 'bus',
            ],
            'navigation' => [
                'data' => $dataRoot . '/gtfs/navigation',
                'cache' => $dataRoot . '/gtfs/cache/navigation',
                'mode' => 'water',
            ],
        ];
    }

    public function pathForDate(string $date): string {
        return $this->plannerDir . '/' . str_replace('-', '', $date) . '.connections.tsv';
    }

    public function ensure(string $date): string {
        $this->validateDate($date);
        $path = $this->pathForDate($date);
        if ($this->isFresh($path)) return $path;

        // Nei bind mount Docker PHP-FPM può leggere la cache pubblicata dal
        // job di aggiornamento senza poter creare nuove date. In quel caso
        // usa una cache runtime isolata e scrivibile, mantenendo intatta quella
        // persistente.
        if (is_dir($this->plannerDir) && !is_writable($this->plannerDir)) {
            $this->plannerDir = rtrim(sys_get_temp_dir(), '/')
                . '/actv-live-planner-' . substr(sha1(BASE_PATH), 0, 12);
            $path = $this->pathForDate($date);
            if ($this->isFresh($path)) return $path;
        }

        if (!is_dir($this->plannerDir) && !mkdir($this->plannerDir, 0775, true) && !is_dir($this->plannerDir)) {
            throw new RuntimeException('Impossibile creare la cache del planner');
        }

        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Impossibile bloccare la cache del planner');
        try {
            if (!$this->isFresh($path)) $this->build($date, $path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }
        return $path;
    }

    private function build(string $date, string $path): void {
        ini_set('memory_limit', '1024M');
        $connections = [];

        foreach ($this->profiles as $service => $profile) {
            if (!is_file($profile['cache'] . '/trips.json')) continue;
            $activeServices = $this->activeServices($profile['data'], $date);
            if (!$activeServices) continue;

            $allTrips = json_decode((string)file_get_contents($profile['cache'] . '/trips.json'), true);
            $trips = [];
            foreach (is_array($allTrips) ? $allTrips : [] as $tripId => $trip) {
                if (isset($activeServices[(string)($trip['service_id'] ?? '')])) $trips[(string)$tripId] = $trip;
            }
            unset($allTrips);

            foreach (glob($profile['cache'] . '/routes/route_*.json') ?: [] as $routeFile) {
                $schedule = json_decode((string)file_get_contents($routeFile), true);
                foreach (is_array($schedule) ? $schedule : [] as $tripId => $stops) {
                    if (!isset($trips[$tripId]) || count($stops) < 2) continue;
                    $routeId = (string)($trips[$tripId]['route_id'] ?? '');
                    for ($i = 0, $last = count($stops) - 1; $i < $last; $i++) {
                        $from = $stops[$i];
                        $to = $stops[$i + 1];
                        $departure = self::timeToSeconds((string)($from['departure_time'] ?? ''));
                        $arrival = self::timeToSeconds((string)($to['arrival_time'] ?? ''));
                        if ($departure === null || $arrival === null || $arrival < $departure) continue;
                        $fields = [
                            sprintf('%06d', $departure), (string)$arrival,
                            $service . ':' . (string)$from['stop_id'],
                            $service . ':' . (string)$to['stop_id'],
                            (string)$tripId, $service . ':' . $routeId,
                            $service, $profile['mode'], (string)($from['stop_sequence'] ?? ($i + 1)),
                        ];
                        $connections[] = implode("\t", $fields);
                    }
                }
                unset($schedule);
            }
        }

        sort($connections, SORT_STRING);
        $temporary = $path . '.tmp-' . getmypid();
        $header = '# actv-connections-v1 date=' . $date . ' generated=' . gmdate(DATE_ATOM) . "\n";
        if (file_put_contents($temporary, $header . implode("\n", $connections) . "\n") === false) {
            throw new RuntimeException('Scrittura cache connessioni fallita');
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Pubblicazione cache connessioni fallita');
        }
    }

    private function activeServices(string $dataDir, string $date): array {
        $compact = str_replace('-', '', $date);
        $weekday = strtolower((new DateTimeImmutable($date))->format('l'));
        $active = [];
        foreach ($this->readCsv($dataDir . '/calendar.txt') as $row) {
            if (($row[$weekday] ?? '0') === '1'
                && $compact >= (string)($row['start_date'] ?? '')
                && $compact <= (string)($row['end_date'] ?? '')) {
                $active[(string)$row['service_id']] = true;
            }
        }
        foreach ($this->readCsv($dataDir . '/calendar_dates.txt') as $row) {
            if ((string)($row['date'] ?? '') !== $compact) continue;
            $id = (string)($row['service_id'] ?? '');
            if (($row['exception_type'] ?? '') === '1') $active[$id] = true;
            if (($row['exception_type'] ?? '') === '2') unset($active[$id]);
        }
        return $active;
    }

    private function readCsv(string $path): iterable {
        if (!is_file($path)) return;
        $handle = fopen($path, 'r');
        if (!$handle) return;
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($headers) === count($values)) yield array_combine($headers, $values);
        }
        fclose($handle);
    }

    private function isFresh(string $path): bool {
        if (!is_file($path)) return false;
        $mtime = filemtime($path) ?: 0;
        foreach ($this->profiles as $profile) {
            foreach (['trips.json', 'routes.json', 'stops.json'] as $file) {
                $source = $profile['cache'] . '/' . $file;
                if (is_file($source) && (filemtime($source) ?: 0) > $mtime) return false;
            }
            foreach (['calendar.txt', 'calendar_dates.txt'] as $file) {
                $source = $profile['data'] . '/' . $file;
                if (is_file($source) && (filemtime($source) ?: 0) > $mtime) return false;
            }
        }
        return true;
    }

    private function validateDate(string $date): void {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Data non valida');
    }

    public static function timeToSeconds(string $time): ?int {
        if (!preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $time, $parts)) return null;
        return ((int)$parts[1] * 3600) + ((int)$parts[2] * 60) + (int)$parts[3];
    }
}
