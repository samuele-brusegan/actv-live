<?php
// Script to update 'stops' table with 'data_url' from ACTV JSON

// 1. Bootstrap
require_once __DIR__ . '/app/bootstrap.php';

// 2. Connect to DB
$mysqli = new mysqli(ENV['DB_HOST'], ENV['DB_USERNAME'], ENV['DB_PASSWORD'], ENV['DB_NAME']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Connected to database.\n";

// 3. Add column 'data_url' if it doesn't exist
$result = $mysqli->query("SHOW COLUMNS FROM stops LIKE 'data_url'");
if ($result->num_rows == 0) {
    echo "Adding 'data_url' column to 'stops' table...\n";
    if ($mysqli->query("ALTER TABLE stops ADD COLUMN data_url VARCHAR(255) DEFAULT NULL")) {
        echo "Column added successfully.\n";
    } else {
        die("Error adding column: " . $mysqli->error);
    }
} else {
    echo "Column 'data_url' already exists.\n";
}

// 4. Fetch JSON
$jsonUrl = "https://oraritemporeale.actv.it/aut/backend/page/stops";
echo "Fetching JSON from $jsonUrl...\n";
$jsonData = file_get_contents($jsonUrl);
if (!$jsonData) {
    die("Error fetching JSON data.\n");
}

$stopsData = json_decode($jsonData, true);
if (!$stopsData) {
    die("Error decoding JSON.\n");
}

echo "Processing " . count($stopsData) . " items...\n";

// 5. Processing
$stmt = $mysqli->prepare("UPDATE stops SET data_url = ? WHERE stop_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $mysqli->error);
}

$updatedCount = 0;
$matchesCount = 0;

foreach ($stopsData as $item) {
    $dataUrl = $item['name']; // e.g., "4824-4825-web-aut"
    $description = $item['description']; // e.g., "Spinea Centro Sportivo [4824] [4825]"

    // Regex to find multiple IDs like [1234]
    if (preg_match_all('/\[(\d+)\]/', $description, $matches)) {
        if (!empty($matches[1])) {
            foreach ($matches[1] as $stopId) {
                $matchesCount++;
                $stmt->bind_param("ss", $dataUrl, $stopId);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $updatedCount++;
                }
            }
        }
    }
}

$stmt->close();
$mysqli->close();

echo "Done.\n";
echo "Found $matchesCount stop references in JSON.\n";
echo "Updated $updatedCount rows (may be 0 if values were already identical).\n";
?>
