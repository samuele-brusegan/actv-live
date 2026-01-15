<?php
/**
 * Application Bootstrap
 * This file initializes constants and environment variables without triggering the router.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('ENV')) {
    define('ENV', parse_ini_file(BASE_PATH . '/.env'));
}

const URL_PATH = "https://actv-live.test";
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

// Helper to check session (only for web)
function checkSessionExpirationCLI() {
    if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
        session_start();
        if (function_exists('checkSessionExpiration')) {
            checkSessionExpiration();
        }
    }
}
