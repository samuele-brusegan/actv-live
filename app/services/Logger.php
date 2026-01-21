<?php
class Logger {
    private static $db = null;

    private static function getDb() {
        if (self::$db === null) {
            if (!class_exists('databaseConnector')) {
                require_once BASE_PATH . '/app/models/databaseConnector.php';
            }
            self::$db = new databaseConnector();
            self::$db->connect(ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_HOST'], ENV['DB_NAME']);
        }
        return self::$db;
    }

    private static function getMysqli() {
        $db = self::getDb();
        return Closure::bind(function($db) { return $db->db; }, null, 'databaseConnector')($db);
    }

    public static function log($type, $message, $file = null, $line = null, $stackTrace = null, $context = null) {
        $mysqli = self::getMysqli();
        
        $sql = "INSERT INTO `logs` (`type`, `message`, `file`, `line`, `stack_trace`, `context`) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        
        $contextJson = $context ? json_encode($context) : null;
        
        $stmt->bind_param("sssiss", $type, $message, $file, $line, $stackTrace, $contextJson);
        $stmt->execute();
        $stmt->close();
    }

    public static function phpErrorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) return false;

        $type = 'PHP_ERROR';
        self::log($type, $errstr, $errfile, $errline, null, ['errno' => $errno]);
        
        return false; // Let standard PHP error handler continue
    }

    public static function exceptionHandler($exception) {
        self::log(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }
}
?>
