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

  // users sor → tényleges eszköznyilvántartás-szerepkör.
  // A jogosultsag oszlopot NEM használjuk önmagában — az a fő admin
  // rendszerben cég-szintű tier (recepció/céguser/cégadmin), más jelentéssel.
  // A storekeeper szintet a jog_eszkoznyilvantartas_admin permission-flag adja
  // (users.permissions JSON — lásd AdminUser::buildPermissions a fő rendszerben).
  // it_admin csak akkor, ha emellett jogosultsag is a legfelső (2) cég-tier —
  // nincs önálló dedikált flag a legfelső szinthez.
  public static function fromUserRow(array $row): string {
    $perms = self::permissionsOf($row);
    if (empty($perms['jog_eszkoznyilvantartas_admin'])) {
      return 'user';
    }
    return ((int) ($row['jogosultsag'] ?? 0) === 2) ? 'it_admin' : 'storekeeper';
  }

  private static function permissionsOf(array $row): array {
    if (empty($row['permissions'])) return [];
    $decoded = json_decode($row['permissions'], true);
    return $decoded['permissions'] ?? [];
  }

  // Kivétel (check_out) jogosultság: storekeeper+ mindig szabad, sima
  // usernél a jog_eszkoznyilvantartas_kivetel flag dönt (l.
  // AdminUser::$jogosultsagLista — a fő rendszer admin "Jogosultságok"
  // oldalán szerkeszthető, per-user checkbox-listával).
  public static function canCheckOut(array $row): bool {
    if (self::fromUserRow($row) !== 'user') return true;
    return !empty(self::permissionsOf($row)['jog_eszkoznyilvantartas_kivetel']);
  }

  // role >= min ?
  public static function atLeast(string $role, string $min): bool {
    return (self::RANK[$role] ?? 1) >= (self::RANK[$min] ?? 1);
  }
}
