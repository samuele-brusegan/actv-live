<?php
/**
 * Application Bootstrap
 * This file initializes constants and environment variables without triggering the router.
 */

date_default_timezone_set('Europe/Rome');

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('ENV')) {
    $envFile = BASE_PATH . '/.env';
    $envValues = is_file($envFile) ? parse_ini_file($envFile) : [];
    define('ENV', is_array($envValues) ? $envValues : []);
}

//const URL_PATH = "https://actv-live.test";
define('URL_PATH', rtrim(trim((string) (ENV['URL_PATH'] ?? '')), '/'));
const COMMON_HTML_HEAD = BASE_PATH . '/public/commons/head.php';
const COMMON_HTML_FOOT = BASE_PATH . '/public/commons/bottom_navigation.php';
const THEME = 'dark'; // Simplified for now

require_once BASE_PATH . '/public/imports.php';
require_once BASE_PATH . '/app/services/Logger.php';

// Only set handlers if not already set or in CLI
if (php_sapi_name() === 'cli' || !headers_sent()) {
    set_error_handler(['Logger', 'phpErrorHandler']);
    set_exception_handler(['Logger', 'exceptionHandler']);
}
