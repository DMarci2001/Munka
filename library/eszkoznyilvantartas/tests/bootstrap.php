<?php
// ============================================================
// PHPUnit bootstrap — a valódi (távoli, éles) DB helyett egy helyi
// XAMPP MariaDB-n futó `eszkoznyilvantartas_test` adatbázishoz köt.
//
// A getDB() függvényt ITT, a backend kódjának betöltése ELŐTT
// definiáljuk. A backend/config/database.php a saját getDB()
// definícióját `if (!function_exists('getDB'))`-be csomagolja,
// így az ott lévő (távoli MySQL-re mutató) definíció no-op-ként
// fut le, és MINDIG ez a teszt-DB-re mutató verzió lesz aktív.
// ============================================================

function getDB(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $pdo = new PDO(
      'mysql:host=127.0.0.1;port=3306;dbname=eszkoznyilvantartas_test;charset=utf8mb4',
      'root',
      '',
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]
    );
  }
  return $pdo;
}

require_once __DIR__ . '/../backend/lib/Ops.php';
require_once __DIR__ . '/fixtures.php';
