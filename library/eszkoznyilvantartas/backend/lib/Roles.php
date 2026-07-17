<?php
// ============================================================
// Szerepkör-leképezés és rangsor.
// A klinikai users.jogosultsag INT (0/1/2) ↔ app auth string.
// ============================================================

require_once __DIR__ . '/../config/config.php';

final class Roles {
  private const RANK = ['user' => 1, 'storekeeper' => 2, 'it_admin' => 3];

  // jogosultsag INT → auth string ('user'|'storekeeper'|'it_admin')
  public static function intToString($jogosultsag): string {
    $i = (int) $jogosultsag;
    return ROLE_INT_TO_STRING[$i] ?? 'user';
  }

  // role >= min ?
  public static function atLeast(string $role, string $min): bool {
    return (self::RANK[$role] ?? 1) >= (self::RANK[$min] ?? 1);
  }
}
