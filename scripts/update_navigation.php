<?php

// Aggiornamento indipendente del feed Navigazione: un errore qui non tocca la
// cache automobilistica gia pubblicata.
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/services/GTFSParser.php';
require_once BASE_PATH . '/app/services/ConnectionCacheBuilder.php';

try {
    $parser = new GTFSParser(null, null, 'navigation');
    if (!$parser->isCacheValid(86400) || in_array('--force', $argv ?? [], true)) {
        $parser->parseAll();
        $plannerCache = new ConnectionCacheBuilder();
        $today = new DateTimeImmutable('today');
        $plannerCache->ensure($today->format('Y-m-d'));
        $plannerCache->ensure($today->modify('+1 day')->format('Y-m-d'));
        echo "Cache Navigazione aggiornata\n";
    } else {
        echo "Cache Navigazione ancora valida\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Aggiornamento Navigazione fallito: {$e->getMessage()}\n");
    exit(1);
}
