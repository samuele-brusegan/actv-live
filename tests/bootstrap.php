<?php
// Test Bootstrap

require_once __DIR__ . '/../vendor/autoload.php';

// Define BASE_PATH if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Mock ENV if needed or load from .env.example/testing
// Load ENV from .env if it exists
if (!defined('ENV')) {
    $envFile = BASE_PATH . '/.env';
    if (file_exists($envFile)) {
        define('ENV', parse_ini_file($envFile));
    } else {
        define('ENV', [
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'actv_live_test',
            'DB_USER' => 'root',
            'DB_PASS' => '',
        ]);
    }
}

// Load necessary files manually if not PSR-4 compliant enough yet
require_once BASE_PATH . '/public/functions.php';
require_once BASE_PATH . '/app/services/Logger.php';
require_once BASE_PATH . '/app/services/GTFSParser.php';
