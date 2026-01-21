<?php
class databaseConnector {

    private $db = null;
    private $tableJoins = "";

    public function __construct() {
    }

    public function connect(string $dbUsername, string $dbPassword, string $url, string $dbName, string $tableJoins = ""): bool {

        $this->tableJoins = $tableJoins;
        $conn = new mysqli($url, $dbUsername, $dbPassword, $dbName);

        if (!$conn->connect_error) $this->db = $conn;

        return !($conn->connect_errno == 0);
    }

    public function query(string $query): array {
        //TODO: Assert $query is a valid SQL query
        $result = $this->db->query($query);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function close() {
        $this->db->close();
    }

    public function getJoins(): string {
        return $this->tableJoins;
    }

    /**
     * Checks if the query is a valid SQL query
     * @param string $query
     * @return bool True if the query is a valid SQL query, false otherwise
     */
    public function seamsValidSQL(string $query): bool {
        if (
            strpos($query, "SELECT") !== false ||
            strpos($query, "UPDATE") !== false ||
            strpos($query, "DELETE") !== false ||
            strpos($query, "INSERT") !== false
        ) {
            return false;
        }
        return true;
    }
}
