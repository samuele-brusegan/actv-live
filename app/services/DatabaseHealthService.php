<?php

class DatabaseHealthService
{
    private const REQUIRED_TABLES = [
        'routes', 'trips', 'stops', 'stop_times', 'calendar',
        'calendar_dates', 'shapes', 'shapes_refined', 'logs',
    ];

    public function inspect(): array
    {
        $startedAt = microtime(true);
        try {
            $pdo = new PDO(
                'mysql:host=' . ENV['DB_HOST'] . ';dbname=' . ENV['DB_NAME'] . ';charset=utf8mb4',
                ENV['DB_USER'],
                ENV['DB_PASS'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]
            );

            $server = $pdo->query('SELECT VERSION() AS version, DATABASE() AS database_name, UTC_TIMESTAMP() AS checked_at_utc')->fetch(PDO::FETCH_ASSOC);
            $pdo->query('SELECT 1')->fetchColumn();

            $tableRows = $pdo->query(
                "SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, CREATE_TIME, UPDATE_TIME,
                        IF(CREATE_TIME IS NULL, NULL, TIMESTAMPDIFF(SECOND, CREATE_TIME, NOW())) AS AGE_SECONDS
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY TABLE_NAME"
            )->fetchAll(PDO::FETCH_ASSOC);

            $tables = [];
            $tableNames = [];
            $staging = [];
            foreach ($tableRows as $row) {
                $name = (string) $row['TABLE_NAME'];
                $tableNames[$name] = true;
                $tables[] = [
                    'name' => $name,
                    'engine' => $row['ENGINE'],
                    'rows_estimate' => $row['TABLE_ROWS'] === null ? null : (int) $row['TABLE_ROWS'],
                    'size_bytes' => (int) $row['DATA_LENGTH'] + (int) $row['INDEX_LENGTH'],
                    'created_at' => $row['CREATE_TIME'],
                    'updated_at' => $row['UPDATE_TIME'],
                ];

                if (preg_match('/_gtfs_[0-9]+_[0-9]+$/', $name)) {
                    $staging[] = [
                        'name' => $name,
                        'rows_estimate' => $row['TABLE_ROWS'] === null ? null : (int) $row['TABLE_ROWS'],
                        'created_at' => $row['CREATE_TIME'],
                        'updated_at' => $row['UPDATE_TIME'],
                        'age_seconds' => $row['AGE_SECONDS'] === null ? null : (int) $row['AGE_SECONDS'],
                    ];
                }
            }

            $missingTables = array_values(array_filter(self::REQUIRED_TABLES, fn(string $name): bool => !isset($tableNames[$name])));
            $latestLog = null;
            if (isset($tableNames['logs'])) {
                $latestLog = $pdo->query('SELECT MAX(created_at) FROM logs')->fetchColumn() ?: null;
            }

            $status = $missingTables ? 'warning' : 'ok';
            $messages = [];
            if ($missingTables) {
                $messages[] = 'Mancano tabelle richieste: ' . implode(', ', $missingTables) . '.';
            }
            if ($staging) {
                $ages = array_filter(array_column($staging, 'age_seconds'), fn($age): bool => $age !== null);
                $isStale = $ages && max($ages) > 3600;
                if ($isStale) {
                    $status = 'warning';
                    $messages[] = count($staging) . ' tabelle temporanee GTFS sono presenti da oltre un’ora; verifica se l’import è ancora attivo.';
                } else {
                    $messages[] = count($staging) . ' tabelle temporanee GTFS presenti; potrebbero appartenere a un import in corso.';
                }
            }

            return [
                'status' => $status,
                'checked_at_utc' => $server['checked_at_utc'],
                'database' => $server['database_name'],
                'server_version' => $server['version'],
                'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'select_1' => true,
                'required_tables' => self::REQUIRED_TABLES,
                'missing_tables' => $missingTables,
                'tables' => $tables,
                'staging_tables' => $staging,
                'latest_log_at' => $latestLog,
                'messages' => $messages,
            ];
        } catch (Throwable $error) {
            return [
                'status' => 'error',
                'checked_at_utc' => gmdate('Y-m-d H:i:s'),
                'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'select_1' => false,
                'error' => $error->getMessage(),
                'tables' => [],
                'staging_tables' => [],
                'messages' => ['Connessione o verifica SQL non riuscita.'],
            ];
        }
    }
}
