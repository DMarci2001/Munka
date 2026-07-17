<?php
// Eszköznyilvántartás API — vékony publikus belépési pont.

// IDEIGLENES HIBAKERESÉS — töröld, ha a 500-as hiba oka megvan.
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('X-PHP-Version: ' . PHP_VERSION);

try {
    require_once __DIR__ . '/../../../autoload.php';
    require __DIR__ . '/../../../library/eszkoznyilvantartas/backend/index.php';
} catch (\Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "DEBUG HIBA: " . $e->getMessage() . "\n";
    echo "Fájl: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
}