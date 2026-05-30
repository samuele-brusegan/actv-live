<?php
/*
 * Copyright (c) 2025. Brusegan Samuele, Davanzo Andrea
 * Questo file fa parte di actv-live ed è rilasciato
 * sotto la licenza MIT. Vedere il file LICENSE per i dettagli.
 */

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
