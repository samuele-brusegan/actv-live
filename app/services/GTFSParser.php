<?php

/**
 * GTFS Parser for ACTV Venezia
 * Simplified version for hybrid approach - parses GTFS data and creates JSON cache
 */
class GTFSParser {
    private const FEEDS = [
        'automobilistico' => 'https://actv.avmspa.it/sites/default/files/attachments/opendata/automobilistico/actv_aut.zip',
        'navigation' => 'https://actv.avmspa.it/sites/default/files/attachments/opendata/navigazione/actv_nav.zip',
    ];
    private string $profile;
    private string $gtfsUrl;
    private $dataDir;
    private $cacheDir;
    
    public function __construct(?string $dataDir = null, ?string $cacheDir = null, string $profile = 'automobilistico') {
        ini_set('memory_limit', '1024M');
        $this->profile = array_key_exists($profile, self::FEEDS) ? $profile : 'automobilistico';
        $this->gtfsUrl = self::FEEDS[$this->profile];
        $baseDataDir = $dataDir ?: BASE_PATH . '/data/gtfs';
        // Mantiene compatibilità con il feed automobilistico storico già
        // estratto direttamente in data/gtfs; la navigazione resta isolata.
        $legacyBusData = !$dataDir && $this->profile === 'automobilistico'
            && is_file($baseDataDir . '/routes.txt');
        $this->dataDir = $dataDir ? $baseDataDir : ($legacyBusData ? $baseDataDir : $baseDataDir . '/' . $this->profile);
        $legacyBusCache = !$cacheDir && $this->profile === 'automobilistico'
            && is_file($baseDataDir . '/cache/routes.json');
        $this->cacheDir = $cacheDir ?: ($legacyBusCache
            ? $baseDataDir . '/cache'
            : BASE_PATH . '/data/gtfs/cache/' . $this->profile);

        if (!file_exists($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
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
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($zipFile) !== TRUE) {
                throw new Exception("Failed to extract GTFS files");
            }
            $zip->extractTo($this->dataDir);
            $zip->close();
            echo "GTFS files extracted successfully\n";
        } elseif (is_executable('/usr/bin/unzip')) {
            exec(
                '/usr/bin/unzip -oq ' . escapeshellarg($zipFile) .
                ' -d ' . escapeshellarg($this->dataDir),
                $output,
                $code
            );
            if ($code !== 0) {
                throw new Exception("Failed to extract GTFS files with unzip");
            }
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
        $handle = $this->openCsv($file);
        $headers = $this->readCsvHeaders($handle, $file);

        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            if (count($data) !== count($headers)) {
                throw new RuntimeException($this->csvRowError($file, ftell($handle), $headers, $data));
            }
            $stop = array_combine($headers, $data);
            $stops[$stop['stop_id']] = [
                'id' => $stop['stop_id'],
                'name' => $stop['stop_name'],
                'lat' => floatval($stop['stop_lat']),
                'lon' => floatval($stop['stop_lon']),
                'service' => $this->profile,
                'mode' => $this->profile === 'navigation' ? 'water' : 'bus'
            ];
        }
        
        fclose($handle);
        
        $this->writeJsonCache($this->cacheDir . '/stops.json', $stops, 'stops.json', JSON_PRETTY_PRINT);
        
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
        $handle = $this->openCsv($file);
        $headers = $this->readCsvHeaders($handle, $file);

        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            if (count($data) !== count($headers)) {
                throw new RuntimeException($this->csvRowError($file, ftell($handle), $headers, $data));
            }
            $route = array_combine($headers, $data);
            $routes[$route['route_id']] = [
                'id' => $route['route_id'],
                'short_name' => $route['route_short_name'] ?? '',
                'long_name' => $route['route_long_name'] ?? '',
                'type' => intval($route['route_type']),
                'color' => $route['route_color'] ?? '',
                'text_color' => $route['route_text_color'] ?? '',
                'service' => $this->profile,
                'mode' => $this->profile === 'navigation' ? 'water' : 'bus',
                'route_color' => $this->normaliseColor($route['route_color'] ?? '', $this->profile === 'navigation' ? '#5B5B5B' : null),
                'route_text_color' => $this->normaliseColor($route['route_text_color'] ?? '', '#FFFFFF')
            ];
        }
        
        fclose($handle);
        
        $this->writeJsonCache($this->cacheDir . '/routes.json', $routes, 'routes.json', JSON_PRETTY_PRINT);
        
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
        $handle = $this->openCsv($file);
        $headers = $this->readCsvHeaders($handle, $file);

        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            if (count($data) !== count($headers)) {
                throw new RuntimeException($this->csvRowError($file, ftell($handle), $headers, $data));
            }
            $trip = array_combine($headers, $data);
            $trips[$trip['trip_id']] = [
                'id' => $trip['trip_id'],
                'route_id' => $trip['route_id'],
                'service_id' => $trip['service_id'],
                'shape_id' => $trip['shape_id'] ?? '',
                'headsign' => $trip['trip_headsign'] ?? '',
                'service' => $this->profile,
                'mode' => $this->profile === 'navigation' ? 'water' : 'bus'
            ];
        }
        
        fclose($handle);
        
        $this->writeJsonCache($this->cacheDir . '/trips.json', $trips, 'trips.json', JSON_PRETTY_PRINT);
        
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
        try {
            $trips = json_decode(
                (string) file_get_contents($tripsFile),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Impossibile leggere trips.json: ' . $error->getMessage() . ' (' . $tripsFile . ')',
                0,
                $error
            );
        }
        if (!is_array($trips)) {
            throw new RuntimeException('trips.json non contiene un oggetto valido: ' . $tripsFile);
        }
        $tripRoutes = [];
        foreach ($trips as $tripId => $trip) {
            if (!is_array($trip) || !isset($trip['route_id'])) continue;
            $tripRoutes[(string) $tripId] = (string) $trip['route_id'];
        }
        unset($trips);
        
        // Prepare routes directory
        $routesDir = $this->cacheDir . '/routes';
        if (!is_dir($routesDir) && !mkdir($routesDir, 0777, true) && !is_dir($routesDir)) {
            throw new RuntimeException('Impossibile creare la directory cache route: ' . $routesDir);
        }

        // Non accumulare tutte le 900k+ righe in un unico array PHP: su feed
        // reali questo supera facilmente il limite di memoria del processo.
        $bucketsDir = $this->cacheDir . '/.stop-times-' . getmypid() . '-' . bin2hex(random_bytes(3));
        if (!mkdir($bucketsDir, 0777, true) && !is_dir($bucketsDir)) {
            throw new RuntimeException('Impossibile creare la cache temporanea stop_times: ' . $bucketsDir);
        }
        $bucketHandles = [];
        $bucketPaths = [];
        $routeNames = [];
        $stopRoutes = [];

        $handle = $this->openCsv($file);
        $headers = $this->readCsvHeaders($handle, $file);
        $count = 0;
        try {
            while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
                if (count($data) !== count($headers)) {
                    throw new RuntimeException($this->csvRowError($file, ftell($handle), $headers, $data));
                }
                $stopTime = array_combine($headers, $data);
                $tripId = (string) ($stopTime['trip_id'] ?? '');
                $stopId = (string) ($stopTime['stop_id'] ?? '');

                if (!isset($tripRoutes[$tripId])) {
                    continue;
                }

                $routeId = $tripRoutes[$tripId];
                $safeRouteId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeId);
                if (isset($routeNames[$safeRouteId]) && $routeNames[$safeRouteId] !== $routeId) {
                    throw new RuntimeException(
                        "Gli ID route '$routeId' e '{$routeNames[$safeRouteId]}' producono lo stesso file cache '$safeRouteId'."
                    );
                }
                $routeNames[$safeRouteId] = $routeId;
                if (!isset($bucketHandles[$routeId])) {
                    $bucketPaths[$routeId] = $bucketsDir . '/route_' . $safeRouteId . '.jsonl';
                    $bucketHandles[$routeId] = fopen($bucketPaths[$routeId], 'ab');
                    if (!$bucketHandles[$routeId]) {
                        throw new RuntimeException('Impossibile aprire il bucket temporaneo: ' . $bucketPaths[$routeId]);
                    }
                }

                $bucketRow = json_encode([
                    $tripId,
                    $stopId,
                    (string) ($stopTime['arrival_time'] ?? ''),
                    (string) ($stopTime['departure_time'] ?? ''),
                    (int) ($stopTime['stop_sequence'] ?? 0),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
                if (fwrite($bucketHandles[$routeId], $bucketRow) !== strlen($bucketRow)) {
                    throw new RuntimeException('Scrittura bucket stop_times fallita: ' . $bucketPaths[$routeId]);
                }

                if (!isset($stopRoutes[$stopId])) $stopRoutes[$stopId] = [];
                if (!in_array($routeId, $stopRoutes[$stopId], true)) $stopRoutes[$stopId][] = $routeId;

                $count++;
                if ($count % 50000 === 0) {
                    $this->debug("stop_times letti=$count route=" . count($bucketPaths));
                }
            }

            fclose($handle);
            $handle = null;
            foreach ($bucketHandles as $bucketHandle) fclose($bucketHandle);
            $bucketHandles = [];

            echo "Saving per-route schedules...\n";
            foreach ($bucketPaths as $routeId => $bucketPath) {
                $tripTimes = [];
                $bucket = fopen($bucketPath, 'rb');
                if (!$bucket) throw new RuntimeException('Impossibile leggere il bucket stop_times: ' . $bucketPath);
                while (($line = fgets($bucket)) !== false) {
                    try {
                        $row = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $error) {
                        throw new RuntimeException('JSON non valido nel bucket stop_times: ' . $bucketPath, 0, $error);
                    }
                    if (!is_array($row) || count($row) !== 5) {
                        throw new RuntimeException('Record non valido nel bucket stop_times: ' . $bucketPath);
                    }
                    [$tripId, $stopId, $arrivalTime, $departureTime, $stopSequence] = $row;
                    $tripTimes[$tripId][] = [
                        'stop_id' => $stopId,
                        'arrival_time' => $arrivalTime,
                        'departure_time' => $departureTime,
                        'stop_sequence' => (int) $stopSequence,
                    ];
                }
                fclose($bucket);

                foreach ($tripTimes as &$times) {
                    usort($times, fn($a, $b) => $a['stop_sequence'] <=> $b['stop_sequence']);
                }
                unset($times);

                $safeRouteId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeId);
                $this->writeJsonCache(
                    $routesDir . '/route_' . $safeRouteId . '.json',
                    $tripTimes,
                    'route_' . $safeRouteId . '.json'
                );
                unset($tripTimes);
            }

            echo "Saving stop-routes index...\n";
            $this->writeJsonCache(
                $this->cacheDir . '/stop_routes_index.json',
                $stopRoutes,
                'stop_routes_index.json',
                JSON_PRETTY_PRINT
            );
            echo "Parsed $count stop times. Created schedules for " . count($bucketPaths) . " routes.\n";
        } finally {
            if (is_resource($handle)) fclose($handle);
            foreach ($bucketHandles as $bucketHandle) {
                if (is_resource($bucketHandle)) fclose($bucketHandle);
            }
            $this->removeDirectory($bucketsDir);
        }
    }
    
    /**
     * Parse all GTFS files and create cache
     */
    public function parseAll() {
        $this->downloadGTFS();
        $publishedCache = $this->cacheDir;
        $stagingCache = $publishedCache . '.staging-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($stagingCache, 0777, true);
        $this->cacheDir = $stagingCache;
        try {
            $this->parseExtracted();
            // La cache automobilistica storica coincide con cache/, mentre
            // Navigazione vive in cache/navigation/. Preservala durante la
            // pubblicazione atomica del feed bus.
            if ($this->profile === 'automobilistico' && is_dir($publishedCache . '/navigation')) {
                $this->copyDirectory($publishedCache . '/navigation', $stagingCache . '/navigation');
            }
            $previous = $publishedCache . '.previous-' . getmypid();
            if (is_dir($publishedCache)) rename($publishedCache, $previous);
            rename($stagingCache, $publishedCache);
            $this->removeDirectory($previous);
        } catch (Throwable $e) {
            $this->removeDirectory($stagingCache);
            throw $e;
        } finally {
            $this->cacheDir = $publishedCache;
        }
    }

    /** Parse shapes.txt into an indexed cache usable by the map API. */
    public function parseShapes(): array {
        $file = $this->dataDir . '/shapes.txt';
        if (!is_file($file)) return [];
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        $shapes = [];
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $row = array_combine($headers, $data);
            $id = (string) ($row['shape_id'] ?? '');
            if ($id === '') continue;
            $shapes[$id][] = [
                'lat' => (float) ($row['shape_pt_lat'] ?? 0),
                'lng' => (float) ($row['shape_pt_lon'] ?? 0),
                'sequence' => (int) ($row['shape_pt_sequence'] ?? 0),
            ];
        }
        fclose($handle);
        foreach ($shapes as &$points) {
            usort($points, fn($a, $b) => $a['sequence'] <=> $b['sequence']);
            foreach ($points as &$point) unset($point['sequence']);
        }
        unset($points, $point);
        $this->writeJsonCache($this->cacheDir . '/shapes.json', $shapes, 'shapes.json');
        $shapesDir = $this->cacheDir . '/shapes';
        if (!is_dir($shapesDir)) mkdir($shapesDir, 0777, true);
        foreach ($shapes as $shapeId => $points) {
            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $shapeId);
            $this->writeJsonCache($shapesDir . '/shape_' . $safeId . '.json', $points, 'shape_' . $safeId . '.json');
        }
        return $shapes;
    }

    private function removeDirectory(string $directory): void {
        if (!is_dir($directory)) return;
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }

    private function copyDirectory(string $source, string $destination): void {
        if (!is_dir($destination)) mkdir($destination, 0777, true);
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $from = $source . '/' . $entry;
            $to = $destination . '/' . $entry;
            is_dir($from) ? $this->copyDirectory($from, $to) : copy($from, $to);
        }
    }

    private function normaliseColor(string $value, ?string $fallback = null): ?string {
        $value = ltrim(trim($value), '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $value)) return '#' . strtoupper($value);
        return $fallback;
    }

    /**
     * Generate JSON caches from GTFS text files already extracted in dataDir.
     */
    public function parseExtracted() {
        $this->parseStops();
        $this->parseRoutes();
        $this->parseTrips();
        $this->parseShapes();
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

    public function getProfile(): string { return $this->profile; }

    private function openCsv(string $file)
    {
        $handle = fopen($file, 'rb');
        if (!$handle) throw new RuntimeException('Impossibile aprire il CSV GTFS: ' . $file);
        return $handle;
    }

    private function readCsvHeaders($handle, string $file): array
    {
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($headers) || !$headers) {
            throw new RuntimeException('Header CSV GTFS non valido: ' . $file);
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        return $headers;
    }

    private function csvRowError(string $file, int $offset, array $headers, array $data): string
    {
        return sprintf(
            'Riga CSV GTFS non valida in %s (offset byte %d: attese %d colonne, trovate %d).',
            $file,
            $offset,
            count($headers),
            count($data)
        );
    }

    private function writeJsonCache(string $path, mixed $data, string $label, int $flags = 0): int
    {
        try {
            $json = json_encode($data, $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException(
                "Creazione $label fallita: {$error->getMessage()} (" . $path . ')',
                0,
                $error
            );
        }
        $bytes = file_put_contents($path, $json);
        if ($bytes === false) {
            $lastError = error_get_last();
            throw new RuntimeException(
                "Scrittura $label fallita (" . $path . ')' .
                ($lastError ? ': ' . $lastError['message'] : '.')
            );
        }
        return $bytes;
    }

    private function debug(string $message): void
    {
        echo sprintf(
            "[GTFSParser] %s | memory=%s peak=%s limit=%s\n",
            $message,
            $this->formatBytes(memory_get_usage(true)),
            $this->formatBytes(memory_get_peak_usage(true)),
            (string) ini_get('memory_limit')
        );
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
