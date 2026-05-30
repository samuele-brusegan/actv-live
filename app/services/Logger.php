<?php
class Logger
{
    private static $db = null;

    private static function getDb()
    {
        if (self::$db === null) {
            if (!class_exists('databaseConnector')) {
                require_once BASE_PATH . '/app/models/databaseConnector.php';
            }
            self::$db = databaseConnector::getInstance();
            self::$db->connect(ENV['DB_USER'], ENV['DB_PASS'], ENV['DB_HOST'], ENV['DB_NAME']);
        }
        return self::$db;
    }

    public static function log($type, $message, $file = null, $line = null, $stackTrace = null, $context = null)
    {
        // Logging must never throw: it is also wired as the global error/exception
        // handler in bootstrap.php, so a failure here would cascade.
        try {
            $db = self::getDb();

            $sql = "INSERT INTO `logs` (`type`, `message`, `file`, `line`, `stack_trace`, `context`) VALUES (?, ?, ?, ?, ?, ?)";
            $contextJson = $context ? json_encode($context) : null;

            $db->query($sql, [
                $type,
                $message,
                $file,
                $line !== null ? (int) $line : null,
                $stackTrace,
                $contextJson,
            ]);
        } catch (\Throwable $e) {
            error_log('Logger::log failed: ' . $e->getMessage());
        }
    }

    public static function phpErrorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno))
            return false;

        $type = 'PHP_ERROR';
        self::log($type, $errstr, $errfile, $errline, null, ['errno' => $errno]);

        return false; // Let standard PHP error handler continue
    }

    public static function exceptionHandler($exception)
    {
        self::log(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }
}
