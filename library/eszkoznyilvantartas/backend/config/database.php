<?php
// ============================================================
// PDO kapcsolat — egyetlen megosztott példány (singleton)
// ============================================================

require_once __DIR__ . '/config.php';

// A tesztkörnyezet (tests/bootstrap.php) saját getDB()-t definiálhat ELŐBB,
// hogy egy helyi teszt-adatbázisra mutasson — ez a require_once ilyenkor
// no-op-ként fut le, az itteni definíció nem íródik felül.
if (!function_exists('getDB')) {
  function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
      $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]);
    }
    return $pdo;
  }
}
