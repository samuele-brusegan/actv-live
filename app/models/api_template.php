<?php
/**
 * API Template
 * Replace placeholders and logic as needed.
 * 
 * Accessed via ApiController which provides $db (databaseConnector instance)
 */

if (isset($_GET["return"]) || isset($_GET["rtable"])) {

    try {
        // Optional: Extract parameters
        // $param = $_GET['param'] ?? 'default';

        $data = dbquery($db);

        if (count($data) == 0) {
            echo json_encode(["error" => "No results found", "query" => queryBuilder(), "params" => []]);
            exit;
        }

        if (isset($_GET["return"])) {
            header("Content-Type: application/json");
            echo json_encode($data);
            exit;
        }

        if (isset($_GET["rtable"])) {
            header("Content-Type: text/html");
            // Basic styling for the table view
            echo "<style>table { border-collapse: collapse; width: 100%; } table, th, td { border: 1px solid #ccc; } th, td { padding: 8px; text-align: left; } th { background-color: #f2f2f2; } </style>";
            echo associativeArrayToTable($data);
            exit;
        }

    } catch (Exception $e) {
        header("Content-Type: application/json");
        echo json_encode(["error" => $e->getMessage(), "query" => queryBuilder(), "params" => []]);
        exit;
    }
}

/**
 * Builds the SQL query.
 */
function queryBuilder() {
    return "SELECT * FROM your_table LIMIT 10";
}

/**
 * Executes the query and returns the results.
 */
function dbquery(databaseConnector $db) {
    $query = queryBuilder();
    return $db->query($query);
}

?>
