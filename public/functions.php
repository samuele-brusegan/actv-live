<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di GradeCraft ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */

function checkSessionExpiration(): void {
    if (isset($_SESSION['classeviva_session_expiration_date'])) {
        $requestDate = $_SESSION['classeviva_session_request_date'];
        $expirationDate = $_SESSION['classeviva_session_expiration_date'];

        date_default_timezone_set('Europe/Rome');
        $currentDate = date(DATE_ATOM, time());
        $reLoginOffest = 10;

        if ($expirationDate < $currentDate && $requestDate < date(DATE_ATOM, time() - $reLoginOffest)) {
            echo "<pre>"; print_r(loginRequest()); echo "</pre>"; 
        }
    } elseif (isset($_COOKIE['users']) && $_COOKIE['users'] != "") {

        $users = json_decode($_COOKIE['users'], true);
        $user = $users[0];

        $_SESSION['classeviva_username'] = $user['username'];
        $_SESSION['classeviva_password'] = $user['password'];

        loginRequest();
    }
}

/**
 * Sends an HTTP POST request to the specified URL and processes the response.
 *
 * @return mixed Returns the decoded JSON response as an array if the HTTP status code is 200.
 * Otherwise, returns an associative array containing error details, status code, response message, headers, and body.
 */
function loginRequest(): mixed {
    $headers = [
        'Content-Type: application/json',
    ];
    $ch = curl_init(URL_PATH."/login");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([$_SESSION['classeviva_username'], $_SESSION['classeviva_password']]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode == 200) {
        return json_decode($response, true);
    }
    return ["error" => "HTTP_CODE_DIFFERS_FROM_200", "status" => $httpCode, "message" => $response, "headers" => $headers, "body" => $response, "stackTrace" => debug_backtrace()];
}

/**
 * Converts an associative array to an HTML table.
 * 
 * @param array $array The data to convert.
 * @return string The HTML table.
 */
function associativeArrayToTable(array $array) {
    if (count($array) == 0) {
        return "<p>Nessun risultato</p>";
    }

    $tableStr = "<p>" . count($array) . " risultati</p>";

    $tableStr .= "<table class='table'>";

    $tripKeys = array_keys($array[0]);

    $tableStr .= "<tr>";
    foreach ($tripKeys as $key) {
        $tableStr .= "<th>{$key}</th>";
    }
    $tableStr .= "</tr>";

    foreach ($array as $trip) {
        $tableStr .= "<tr>";
        for ($i = 0; $i < count($trip); $i++) {
            $tableStr .= "<td>{$trip[$tripKeys[$i]]}</td>";
        }
        $tableStr .= "</tr>";
    }

    $tableStr .= "</table>";
    return $tableStr;
}
