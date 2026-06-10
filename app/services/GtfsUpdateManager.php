<?php

class GtfsUpdateManager
{
    private const DEFAULT_URL = 'https://actv.avmspa.it/sites/default/files/attachments/opendata/automobilistico/actv_aut.zip';
    private const CRON_MARKER_START = '# BEGIN ACTV-LIVE GTFS UPDATE';
    private const CRON_MARKER_END = '# END ACTV-LIVE GTFS UPDATE';
    private const TABLE_FILES = [
        'routes' => 'routes.txt',
        'trips' => 'trips.txt',
        'stops' => 'stops.txt',
        'stop_times' => 'stop_times.txt',
        'calendar' => 'calendar.txt',
        'calendar_dates' => 'calendar_dates.txt',
        'shapes' => 'shapes.txt',
    ];
    private const GTFS_ID_COLUMNS = [
        'routes' => ['route_id', 'agency_id'],
        'trips' => ['route_id', 'service_id', 'trip_id', 'block_id', 'shape_id'],
        'stops' => ['stop_id', 'stop_code', 'zone_id', 'parent_station'],
        'stop_times' => ['trip_id', 'stop_id'],
        'calendar' => ['service_id'],
        'calendar_dates' => ['service_id'],
        'shapes' => ['shape_id'],
        'shapes_refined' => ['shape_id'],
    ];

    private string $runtimeDir;
    private string $configFile;
    private string $stateFile;
    private string $lockFile;
    private string $logFile;

    public function __construct()
    {
        $this->runtimeDir = BASE_PATH . '/data/gtfs-update';
        $this->configFile = $this->runtimeDir . '/config.json';
        $this->stateFile = $this->runtimeDir . '/state.json';
        $this->lockFile = $this->runtimeDir . '/update.lock';
        $this->logFile = $this->runtimeDir . '/update.log';
        if (!is_dir($this->runtimeDir)) {
            mkdir($this->runtimeDir, 0777, true);
        }
        @chmod($this->runtimeDir, 0777);
    }

    public function getConfig(): array
    {
        return array_merge([
            'enabled' => false,
            'weekday' => 1,
            'time' => '03:00:00',
            'last_scheduled_week' => null,
        ], $this->normalizeConfig($this->readJson($this->configFile)));
    }

    public function saveConfig(array $input): array
    {
        $previous = $this->getConfig();
        $config = $previous;
        $config['enabled'] = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $weekday = (int) ($input['weekday'] ?? 1);
        $config['weekday'] = max(1, min(7, $weekday));
        $time = (string) ($input['time'] ?? '03:00:00');
        $config['time'] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time)
            ? $time
            : '03:00:00';
        $this->writeJson($this->configFile, $config);
        try {
            $this->syncCron($config);
        } catch (Throwable $e) {
            $this->writeJson($this->configFile, $previous);
            throw $e;
        }
        return $config;
    }

    public function syncCron(?array $config = null): array
    {
        $config ??= $this->getConfig();
        $crontab = $this->resolveCrontabBinary();
        if ($crontab === null) {
            throw new RuntimeException('Comando crontab non disponibile.');
        }

        [$listStatus, $current, $listError] = $this->runProcess([$crontab, '-l']);
        if ($listStatus !== 0) {
            $noCrontab = stripos($listError, 'no crontab') !== false;
            if (!$noCrontab) {
                throw new RuntimeException('Impossibile leggere il crontab: ' . trim($listError));
            }
            $current = '';
        }

        $updated = $this->removeManagedCronBlock($current);
        $expression = null;
        if (!empty($config['enabled'])) {
            [$hour, $minute, $second] = array_map('intval', explode(':', $config['time']));
            $cronWeekday = (int) $config['weekday'] === 7 ? 0 : (int) $config['weekday'];
            $php = $this->resolvePhpCliBinary();
            if ($php === null) {
                throw new RuntimeException('PHP CLI non trovato per la pianificazione cron.');
            }
            $script = BASE_PATH . '/scripts/update_gtfs.php';
            $log = $this->runtimeDir . '/cron.log';
            $expression = "$minute $hour * * $cronWeekday";
            $commandParts = [];
            if ($second > 0) {
                $commandParts[] = 'sleep ' . $second . ' &&';
            }
            $commandParts[] = implode(' ', [
                escapeshellarg($php),
                escapeshellarg($script),
                '--trigger=scheduled',
                '>>',
                escapeshellarg($log),
                '2>&1',
            ]);
            $command = implode(' ', $commandParts);
            $block = self::CRON_MARKER_START . "\n" .
                "CRON_TZ=UTC\n" .
                $expression . ' ' . $command . "\n" .
                self::CRON_MARKER_END . "\n";
            $updated = rtrim($updated) . ($updated === '' ? '' : "\n\n") . $block;
        }

        [$installStatus, , $installError] = $this->runProcess([$crontab, '-'], $updated);
        if ($installStatus !== 0) {
            throw new RuntimeException('Impossibile aggiornare il crontab: ' . trim($installError));
        }

        return [
            'enabled' => !empty($config['enabled']),
            'expression' => $expression,
        ];
    }

    private function removeManagedCronBlock(string $crontab): string
    {
        $pattern = '/^' . preg_quote(self::CRON_MARKER_START, '/') . '\R.*?^' .
            preg_quote(self::CRON_MARKER_END, '/') . '\R?/ms';
        return preg_replace($pattern, '', $crontab) ?? $crontab;
    }

    private function normalizeConfig(array $config): array
    {
        if (isset($config['time']) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $config['time'])) {
            $config['time'] .= ':00';
        }
        return $config;
    }

    private function resolveCrontabBinary(): ?string
    {
        foreach (['/usr/bin/crontab', '/usr/local/bin/crontab'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) return $candidate;
        }
        return null;
    }

    private function runProcess(array $command, string $input = ''): array
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Impossibile eseguire il comando di sistema.');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $error];
    }

    public function getState(): array
    {
        $state = array_merge([
            'status' => 'idle',
            'trigger' => null,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'feed_url' => null,
            'feed_last_modified' => null,
            'error' => null,
            'tasks' => [],
            'stats' => [],
        ], $this->readJson($this->stateFile));
        $state['running'] = $this->isRunning();
        if (!$state['running'] && $state['status'] === 'running') {
            $state['status'] = 'failed';
            $state['error'] = $state['error'] ?: 'Il processo non è più attivo.';
        }
        return $state;
    }

    public function getOverview(): array
    {
        $database = [];
        try {
            $pdo = $this->connectPdo();
            $tables = array_merge(array_keys(self::TABLE_FILES), ['shapes_refined']);
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $stmt = $pdo->prepare(
                "SELECT TABLE_NAME, TABLE_ROWS
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ($placeholders)"
            );
            $stmt->execute(array_merge([ENV['DB_NAME']], $tables));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $database[$row['TABLE_NAME']] = (int) $row['TABLE_ROWS'];
            }
        } catch (Throwable $e) {
            $database['error'] = $e->getMessage();
        }

        $cacheFile = BASE_PATH . '/data/gtfs/cache/stops.json';
        return [
            'config' => $this->getConfig(),
            'state' => $this->getState(),
            'database' => $database,
            'cache_updated_at' => is_file($cacheFile) ? date(DATE_ATOM, filemtime($cacheFile)) : null,
            'log_tail' => $this->tailLog(30),
        ];
    }

    public function isRunning(): bool
    {
        $handle = fopen($this->lockFile, 'c+');
        if (!$handle) return false;
        $available = flock($handle, LOCK_EX | LOCK_NB);
        if ($available) flock($handle, LOCK_UN);
        fclose($handle);
        return !$available;
    }

    public function start(string $trigger = 'manual'): array
    {
        if ($this->isRunning()) {
            return ['started' => false, 'message' => 'Aggiornamento già in esecuzione.'];
        }

        $php = $this->resolvePhpCliBinary();
        if ($php === null) {
            return [
                'started' => false,
                'message' => 'PHP CLI non trovato. Configurare GTFS_PHP_CLI con il percorso del binario php.',
            ];
        }
        $script = BASE_PATH . '/scripts/update_gtfs.php';
        $command = escapeshellarg($php) . ' ' . escapeshellarg($script) .
            ' --trigger=' . escapeshellarg($trigger) .
            ' >> ' . escapeshellarg($this->logFile) . ' 2>&1 &';
        exec($command);
        usleep(150000);

        return [
            'started' => $this->isRunning(),
            'message' => $this->isRunning()
                ? 'Aggiornamento avviato.'
                : 'Impossibile avviare il processo in background.',
        ];
    }

    private function resolvePhpCliBinary(): ?string
    {
        $configured = trim((string) (ENV['GTFS_PHP_CLI'] ?? ''));
        $candidates = array_filter([
            $configured,
            defined('PHP_BINDIR') ? PHP_BINDIR . '/php' : null,
            PHP_BINARY ? dirname(PHP_BINARY) . '/php' : null,
            PHP_BINARY ?: null,
            '/usr/local/bin/php',
            '/usr/bin/php',
        ]);

        foreach (array_unique($candidates) as $candidate) {
            if (!is_file($candidate) || !is_executable($candidate)) continue;

            $output = [];
            $status = 1;
            exec(
                escapeshellarg($candidate) . ' -r ' .
                escapeshellarg('exit(PHP_SAPI === "cli" ? 0 : 1);') .
                ' 2>/dev/null',
                $output,
                $status
            );
            if ($status === 0) return $candidate;
        }

        return null;
    }

    public function run(string $trigger = 'manual'): int
    {
        $lock = fopen($this->lockFile, 'c+');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return 2;
        }

        $workspace = $this->runtimeDir . '/work-' . date('Ymd-His') . '-' . getmypid();
        $extractDir = $workspace . '/feed';
        $cacheDir = $workspace . '/cache';
        mkdir($extractDir, 0775, true);
        mkdir($cacheDir, 0775, true);

        $tasks = [
            ['id' => 'download', 'name' => 'Download GTFS più recente', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'extract', 'name' => 'Estrazione archivio', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'cache', 'name' => 'Creazione cache JSON', 'current' => 0, 'total' => 4, 'status' => 'pending'],
            ['id' => 'import_routes', 'name' => 'Import linee', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'import_trips', 'name' => 'Import corse', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'import_stops', 'name' => 'Import fermate', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'import_stop_times', 'name' => 'Import orari fermate', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'import_calendar', 'name' => 'Import calendario', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'import_calendar_dates', 'name' => 'Import eccezioni calendario', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'import_shapes', 'name' => 'Import shape GTFS', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'data_url', 'name' => 'Compilazione data_url fermate', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'shapes', 'name' => 'Creazione shapes_refined e ID', 'current' => 0, 'total' => 1, 'status' => 'pending'],
            ['id' => 'validate', 'name' => 'Validazione dati', 'current' => 0, 'total' => count(self::TABLE_FILES), 'status' => 'pending'],
            ['id' => 'publish', 'name' => 'Pubblicazione atomica', 'current' => 0, 'total' => 1, 'status' => 'pending'],
        ];
        $state = [
            'status' => 'running',
            'trigger' => $trigger,
            'started_at' => date(DATE_ATOM),
            'finished_at' => null,
            'updated_at' => date(DATE_ATOM),
            'feed_url' => null,
            'feed_last_modified' => null,
            'error' => null,
            'tasks' => $tasks,
            'stats' => [],
        ];
        $this->writeJson($this->stateFile, $state);
        $pdo = null;
        $staging = [];

        try {
            $feed = $this->selectFeed();
            $state['feed_url'] = $feed['url'];
            $state['feed_last_modified'] = $feed['last_modified'];
            $zipFile = $workspace . '/gtfs.zip';

            $this->setTask($state, 'download', 'running');
            $this->download($feed['url'], $zipFile);
            $this->setTask($state, 'download', 'completed', 1);

            $this->setTask($state, 'extract', 'running');
            $this->extractArchive($zipFile, $extractDir);
            $this->setTask($state, 'extract', 'completed', 1);

            $this->setTask($state, 'cache', 'running');
            require_once BASE_PATH . '/app/services/GTFSParser.php';
            $parser = new GTFSParser($extractDir, $cacheDir);
            $parser->parseStops();
            $this->advanceTask($state, 'cache');
            $parser->parseRoutes();
            $this->advanceTask($state, 'cache');
            $parser->parseTrips();
            $this->advanceTask($state, 'cache');
            $parser->parseStopTimes();
            $this->setTask($state, 'cache', 'completed', 4);

            $pdo = $this->connectPdo();
            $staging = $this->createStagingTables($pdo);
            foreach (self::TABLE_FILES as $table => $file) {
                $taskId = 'import_' . $table;
                $csvFile = $extractDir . '/' . $file;
                if ($table === 'calendar_dates' && !is_file($csvFile)) {
                    $state['stats'][$table] = 0;
                    $this->setTaskProgress($state, $taskId, 'completed', 0, 1);
                    continue;
                }
                $totalRows = max(1, $this->countCsvRows($csvFile));
                $this->setTaskProgress($state, $taskId, 'running', 0, $totalRows);
                $count = $this->importCsv(
                    $pdo,
                    $staging[$table],
                    $csvFile,
                    function ($current) use (&$state, $taskId, $totalRows) {
                        $this->setTaskProgress($state, $taskId, 'running', $current, $totalRows);
                    }
                );
                $state['stats'][$table] = $count;
                $this->setTaskProgress($state, $taskId, 'completed', $count, $totalRows);
            }

            $this->setTask($state, 'data_url', 'running');
            $state['stats']['data_url_updated'] = $this->populateDataUrls($pdo, $staging['stops']);
            $this->setTask($state, 'data_url', 'completed', 1);

            $this->setTask($state, 'shapes', 'running');
            $this->populateRefinedShapes($pdo, $staging['shapes'], $staging['shapes_refined']);
            $this->setTask($state, 'shapes', 'completed', 1);

            $this->setTask($state, 'validate', 'running');
            foreach (self::TABLE_FILES as $table => $_file) {
                $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$staging[$table]}`")->fetchColumn();
                if ($count === 0 && $table !== 'calendar_dates') {
                    throw new RuntimeException("La tabella $table è vuota.");
                }
                $this->advanceTask($state, 'validate');
            }
            $this->setTask($state, 'validate', 'completed', count(self::TABLE_FILES));

            $this->setTask($state, 'publish', 'running');
            $backups = $this->publishTables($pdo, $staging);
            try {
                $this->publishCache($cacheDir);
            } catch (Throwable $publishError) {
                $this->rollbackTables($pdo, $backups);
                throw $publishError;
            }
            $this->dropTableBackups($pdo, $backups);
            $this->setTask($state, 'publish', 'completed', 1);

            $state['status'] = 'completed';
            $state['finished_at'] = date(DATE_ATOM);
            $this->writeState($state);
            if ($pdo instanceof PDO) $this->dropStagingTables($pdo, $staging);
            $this->removeTree($workspace);
            flock($lock, LOCK_UN);
            fclose($lock);
            return 0;
        } catch (Throwable $e) {
            $state['status'] = 'failed';
            $state['error'] = $e->getMessage();
            $state['finished_at'] = date(DATE_ATOM);
            foreach ($state['tasks'] as &$task) {
                if ($task['status'] === 'running') $task['status'] = 'failed';
            }
            unset($task);
            $this->writeState($state);
            if ($pdo instanceof PDO) $this->dropStagingTables($pdo, $staging);
            $this->removeTree($workspace);
            flock($lock, LOCK_UN);
            fclose($lock);
            return 1;
        }
    }

    public function runScheduledIfDue(): bool {
        // Leggo la gonfigurazione
        $config = $this->getConfig();
        if (!$config['enabled']) return false;

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // Se non è il giorno corretto o l'orario non è arrivato, non faccio nulla
        if ((int) $now->format('N') !== (int) $config['weekday']) return false;
        if ($now->format('H:i:s') < $config['time']) return false;
        // Prendo il numero della settimana corrente
        $week = $now->format('o-W');
        // Se è già stato eseguito questa settimana, non faccio nulla
        if ($config['last_scheduled_week'] === $week) return false;

        $result = $this->start('scheduled');
        if ($result['started']) {
            $config['last_scheduled_week'] = $week;
            $this->writeJson($this->configFile, $config);
            return true;
        }
        return false;
    }

    private function selectFeed(): array
    {
        $configured = ENV['GTFS_URLS'] ?? ENV['GTFS_URL'] ?? self::DEFAULT_URL;
        $urls = array_values(array_filter(array_map('trim', explode(',', $configured))));
        $candidates = [];
        foreach ($urls as $url) {
            $headers = @get_headers($url, true);
            if (!$headers) continue;
            $lastModified = $headers['Last-Modified'] ?? null;
            if (is_array($lastModified)) $lastModified = end($lastModified);
            $candidates[] = [
                'url' => $url,
                'last_modified' => $lastModified,
                'timestamp' => $lastModified ? (strtotime($lastModified) ?: 0) : 0,
            ];
        }
        if (!$candidates) throw new RuntimeException('Nessun feed GTFS raggiungibile.');
        usort($candidates, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        return $candidates[0];
    }

    private function download(string $url, string $target): void
    {
        $out = fopen($target, 'wb');
        if (!$out) throw new RuntimeException('Impossibile creare il file GTFS temporaneo.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $out,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 300,
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($out);
        if (!$ok) throw new RuntimeException('Download GTFS fallito: ' . $error);
    }

    private function extractArchive(string $zipFile, string $targetDir): void
    {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) throw new RuntimeException('Archivio GTFS non valido.');
            $zip->extractTo($targetDir);
            $zip->close();
            return;
        }
        if (is_executable('/usr/bin/unzip')) {
            exec(
                '/usr/bin/unzip -oq ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($targetDir),
                $output,
                $code
            );
            if ($code === 0) return;
        }
        throw new RuntimeException('Né ZipArchive né il comando unzip sono disponibili.');
    }

    private function connectPdo(): PDO
    {
        return new PDO(
            'mysql:host=' . ENV['DB_HOST'] . ';dbname=' . ENV['DB_NAME'] . ';charset=utf8mb4',
            ENV['DB_USER'],
            ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
    }

    private function createStagingTables(PDO $pdo): array
    {
        $suffix = 'gtfs_' . getmypid() . '_' . time();
        $tables = [];
        foreach (array_keys(self::TABLE_FILES) as $table) {
            $name = $table . '_' . $suffix;
            $pdo->exec("CREATE TABLE `$name` LIKE `$table`");
            $tables[$table] = $name;
        }
        $tables['shapes_refined'] = 'shapes_refined_' . $suffix;
        $pdo->exec("CREATE TABLE `{$tables['shapes_refined']}` LIKE `shapes_refined`");
        $this->normalizeGtfsIdColumns($pdo, $tables);
        return $tables;
    }

    private function normalizeGtfsIdColumns(PDO $pdo, array $tables): void
    {
        foreach (self::GTFS_ID_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $pdo->exec(
                    "ALTER TABLE `{$tables[$table]}` " .
                    "MODIFY COLUMN `$column` VARCHAR(255) NULL"
                );
            }
        }
    }

    private function dropStagingTables(PDO $pdo, array $tables): void
    {
        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            } catch (Throwable $ignored) {
            }
        }
    }

    private function importCsv(PDO $pdo, string $table, string $file, ?callable $progress = null): int
    {
        if (!is_file($file)) throw new RuntimeException(basename($file) . ' mancante.');
        $handle = fopen($file, 'rb');
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) throw new RuntimeException('Header CSV non valido: ' . basename($file));

        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $insertColumns = array_values(array_intersect($headers, $columns));
        if (!$insertColumns) throw new RuntimeException('Nessuna colonna importabile per ' . basename($file));
        $positions = array_map(fn($column) => array_search($column, $headers, true), $insertColumns);
        $quoted = implode(',', array_map(fn($column) => "`$column`", $insertColumns));
        $count = 0;
        $batch = [];
        $batchSize = 500;
        $pdo->beginTransaction();
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) < count($headers)) $row = array_pad($row, count($headers), null);
            $batch[] = array_map(function ($position) use ($row) {
                $value = $row[$position] ?? null;
                return $value === '' ? null : $value;
            }, $positions);
            $count++;
            if (count($batch) >= $batchSize) {
                $this->insertCsvBatch($pdo, $table, $quoted, $batch);
                $batch = [];
                if ($count % 5000 === 0) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                    if ($progress) $progress($count);
                }
            }
        }
        if ($batch) $this->insertCsvBatch($pdo, $table, $quoted, $batch);
        $pdo->commit();
        fclose($handle);
        return $count;
    }

    private function insertCsvBatch(PDO $pdo, string $table, string $quotedColumns, array $rows): void
    {
        $rowPlaceholders = '(' . implode(',', array_fill(0, count($rows[0]), '?')) . ')';
        $placeholders = implode(',', array_fill(0, count($rows), $rowPlaceholders));
        $values = [];
        foreach ($rows as $row) {
            array_push($values, ...$row);
        }
        $pdo->prepare(
            "INSERT INTO `$table` ($quotedColumns) VALUES $placeholders"
        )->execute($values);
    }

    private function countCsvRows(string $file): int
    {
        if (!is_file($file)) throw new RuntimeException(basename($file) . ' mancante.');
        $handle = fopen($file, 'rb');
        $lines = 0;
        while (fgets($handle) !== false) $lines++;
        fclose($handle);
        return max(0, $lines - 1);
    }

    private function populateDataUrls(PDO $pdo, string $stopsTable): int
    {
        $json = @file_get_contents('https://oraritemporeale.actv.it/aut/backend/page/stops');
        $items = $json ? json_decode($json, true) : null;
        if (!is_array($items)) throw new RuntimeException('Feed fermate ACTV non disponibile.');
        $stmt = $pdo->prepare("UPDATE `$stopsTable` SET data_url = ? WHERE stop_id = ?");
        $updated = 0;
        foreach ($items as $item) {
            if (!preg_match_all('/\[(\d+)\]/', $item['description'] ?? '', $matches)) continue;
            foreach ($matches[1] as $stopId) {
                $stmt->execute([$item['name'] ?? null, $stopId]);
                $updated += $stmt->rowCount();
            }
        }
        return $updated;
    }

    private function populateRefinedShapes(PDO $pdo, string $shapesTable, string $refinedTable): void
    {
        $pdo->exec("INSERT INTO `$refinedTable` (shape_id, lat, lng, sequence, dist_traveled)
                    SELECT shape_id, shape_pt_lat, shape_pt_lon, shape_pt_sequence, shape_dist_traveled
                    FROM `$shapesTable`
                    ORDER BY shape_id, shape_pt_sequence");
    }

    private function publishTables(PDO $pdo, array $staging): array
    {
        $timestamp = date('YmdHis');
        $renames = [];
        $backups = [];
        foreach (array_merge(array_keys(self::TABLE_FILES), ['shapes_refined']) as $table) {
            $backup = $table . '_backup_' . $timestamp;
            $renames[] = "`$table` TO `$backup`";
            $renames[] = "`{$staging[$table]}` TO `$table`";
            $backups[] = $backup;
        }
        $pdo->exec('RENAME TABLE ' . implode(', ', $renames));
        return array_combine(
            array_merge(array_keys(self::TABLE_FILES), ['shapes_refined']),
            $backups
        );
    }

    private function dropTableBackups(PDO $pdo, array $backups): void
    {
        foreach ($backups as $backup) $pdo->exec("DROP TABLE `$backup`");
    }

    private function rollbackTables(PDO $pdo, array $backups): void
    {
        $renames = [];
        $failedTables = [];
        foreach ($backups as $table => $backup) {
            $failed = $table . '_failed_' . date('YmdHis');
            $renames[] = "`$table` TO `$failed`";
            $renames[] = "`$backup` TO `$table`";
            $failedTables[] = $failed;
        }
        $pdo->exec('RENAME TABLE ' . implode(', ', $renames));
        foreach ($failedTables as $failed) $pdo->exec("DROP TABLE `$failed`");
    }

    private function publishCache(string $newCache): void
    {
        $target = BASE_PATH . '/data/gtfs/cache';
        $parent = dirname($target);
        if (!is_dir($parent)) mkdir($parent, 0775, true);
        $backup = $target . '.backup-' . date('YmdHis');
        if (is_dir($target) && !rename($target, $backup)) {
            throw new RuntimeException('Impossibile archiviare la cache GTFS corrente.');
        }
        if (!rename($newCache, $target)) {
            if (is_dir($backup)) rename($backup, $target);
            throw new RuntimeException('Impossibile pubblicare la nuova cache GTFS.');
        }
        $this->removeTree($backup);
    }

    private function setTask(array &$state, string $id, string $status, ?int $current = null): void
    {
        foreach ($state['tasks'] as &$task) {
            if ($task['id'] !== $id) continue;
            $task['status'] = $status;
            if ($current !== null) $task['current'] = $current;
        }
        unset($task);
        $this->writeState($state);
    }

    private function advanceTask(array &$state, string $id): void
    {
        foreach ($state['tasks'] as &$task) {
            if ($task['id'] === $id) $task['current']++;
        }
        unset($task);
        $this->writeState($state);
    }

    private function setTaskProgress(
        array &$state,
        string $id,
        string $status,
        int $current,
        int $total
    ): void {
        foreach ($state['tasks'] as &$task) {
            if ($task['id'] !== $id) continue;
            $task['status'] = $status;
            $task['current'] = min($current, $total);
            $task['total'] = max(1, $total);
        }
        unset($task);
        $this->writeState($state);
    }

    private function writeState(array &$state): void
    {
        $state['updated_at'] = date(DATE_ATOM);
        $this->writeJson($this->stateFile, $state);
    }

    private function readJson(string $file): array
    {
        if (!is_file($file)) return [];
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function writeJson(string $file, array $data): void
    {
        $temp = $file . '.tmp-' . getmypid();
        file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($temp, $file);
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) $this->removeTree($path . '/' . $item);
        rmdir($path);
    }

    private function tailLog(int $lines): array
    {
        if (!is_file($this->logFile)) return [];
        $all = file($this->logFile, FILE_IGNORE_NEW_LINES);
        return array_slice($all ?: [], -$lines);
    }
}
