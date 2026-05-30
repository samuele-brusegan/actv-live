<?php
class databaseConnector {

    private $db = null;
    private $tableJoins = "";
    private static $instance = null;

    public function __construct() {
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connect(string $dbUsername, string $dbPassword, string $url, string $dbName, string $tableJoins = ""): bool {
        $this->tableJoins = $tableJoins;
        
        if ($this->db !== null) {
            return true;
        }

        try {
            $dsn = "mysql:host=$url;dbname=$dbName;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->db = new PDO($dsn, $dbUsername, $dbPassword, $options);
            return true;
        } catch (PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            return false;
        }
    }

    public function query(string $query, array $params = []): array {
        if ($this->db === null) {
            throw new RuntimeException("Database connection not established (connect() failed or was not called).");
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function close() {
        $this->db = null;
    }

    public function getJoins(): string {
        return $this->tableJoins;
    }
}
