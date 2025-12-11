<?php

/**
 * Legge un file GTFS (CSV), lo carica in una tabella temporanea SQLite
 * e quindi esegue una query SQL.
 *
 * Il GTFS è composto da file CSV. La funzione si aspetta che $file
 * sia il percorso di uno di questi file (es. 'stops.txt', 'routes.txt', ecc.).
 *
 * @param string $file Percorso completo del file GTFS (CSV) da leggere.
 * @param string $sqlQuery Query SQL da eseguire sulla tabella temporanea creata.
 * @return array|false Un array associativo dei risultati della query, o false in caso di errore.
 */
function readGTFS(string $file, string $sqlQuery): array|false {
    // --- 1. Controllo Preliminare e Apertura File ---
    if (!file_exists($file) || !is_readable($file)) {
        echo "Errore: il file '$file' non è accessibile o non esiste.\n";
        return false;
    }

    $handle = fopen($file, 'r');
    if ($handle === false) {
        echo "Errore: Impossibile aprire il file '$file'.\n";
        return false;
    }

    // --- 2. Inizializzazione del Database SQLite in Memoria ---
    try {
        // Connessione a un database SQLite in memoria
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "Errore di connessione a SQLite: " . $e->getMessage() . "\n";
        fclose($handle);
        return false;
    }

    // --- 3. Parsing del CSV, Creazione Tabella e Inserimento Dati ---
    
    // a) Leggi l'intestazione (header/prima riga) per i nomi delle colonne
    $header = fgetcsv($handle);
    if ($header === false) {
        echo "Errore: Impossibile leggere l'intestazione del file.\n";
        fclose($handle);
        return false;
    }

    // Pulisci e normalizza i nomi delle colonne per l'uso SQL
    $columns = array_map(fn($col) => preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(trim($col))), $header);
    $tableName = pathinfo($file, PATHINFO_FILENAME); // Usa il nome del file come nome della tabella (es. 'stops')

    // b) Prepara la Query per la Creazione della Tabella
    // In SQLite i tipi di dati sono flessibili, usiamo TEXT per semplicità
    $columnDefinitions = implode(', ', array_map(fn($col) => "$col TEXT", $columns));
    $createTableSql = "CREATE TABLE IF NOT EXISTS $tableName ($columnDefinitions)";
    
    try {
        $pdo->exec($createTableSql);
    } catch (PDOException $e) {
        echo "Errore durante la creazione della tabella '$tableName': " . $e->getMessage() . "\n";
        fclose($handle);
        return false;
    }

    // c) Prepara la Query per l'Inserimento Dati
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $insertSql = "INSERT INTO $tableName VALUES ($placeholders)";
    $stmtInsert = $pdo->prepare($insertSql);

    // d) Inserisci i dati riga per riga (ottimizzato con transazione)
    $pdo->beginTransaction();
    try {
        while (($data = fgetcsv($handle)) !== false) {
            // Assicurati che il numero di colonne corrisponda
            if (count($data) === count($columns)) {
                $stmtInsert->execute($data);
            }
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Errore durante l'inserimento dei dati: " . $e->getMessage() . "\n";
        fclose($handle);
        return false;
    }

    fclose($handle);

    // --- 4. Esecuzione della Query SQL dell'Utente ---
    try {
        $stmtQuery = $pdo->query($sqlQuery);
        $results = $stmtQuery->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (PDOException $e) {
        echo "Errore durante l'esecuzione della query SQL: " . $e->getMessage() . "\n";
        return false;
    }
}
?>