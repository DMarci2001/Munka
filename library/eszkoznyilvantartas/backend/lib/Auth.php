<?php
// ============================================================
// Auth — munkamenet-alapú hitelesítés és szerepkör-kapuk.
// A store.js `currentUserId` / `currentRole()` szerveroldali megfelelője:
// az "aktor" mindig a bejelentkezett munkamenet-felhasználó.
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/Roles.php';

final class Auth {
  // ---- Bejelentkezés -----------------------------------------
  // A klinikai users táblából azonosít username + jelszó alapján.
  // A jelszó-oszlop a config-ban állítható (USER_PASSWORD_COLUMN).
  public static function login(string $username, string $password): array {
    $col = USER_PASSWORD_COLUMN;
    $st = getDB()->prepare(
      "SELECT id, username, " . USER_NAME_COLUMN . " AS full_name,
              " . USER_ROLE_COLUMN . " AS jogosultsag, `$col` AS pwhash
       FROM users WHERE username = ? LIMIT 1"
    );
    $st->execute([$username]);
    $row = $st->fetch();

    $hash = $row['pwhash'] ?? '';
    if (!$row || $hash === null || $hash === '' || !password_verify($password, $hash)) {
      throw new OpError('Hibás felhasználónév vagy jelszó.');
    }

    // Munkamenet rögzítése (session fixation ellen: új azonosító).
    session_regenerate_id(true);
    $_SESSION['uid']  = (int) $row['id'];
    $_SESSION['role'] = Roles::intToString($row['jogosultsag']);
    return self::publicUser($row);
  }

  // SSO: log in by username only (password check skipped — caller must have verified HMAC token).
  public static function loginByUsername(string $username): array {
    $st = getDB()->prepare(
      "SELECT id, username, " . USER_NAME_COLUMN . " AS full_name,
              " . USER_ROLE_COLUMN . " AS jogosultsag
       FROM users WHERE username = ? LIMIT 1"
    );
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row) throw new OpError('Felhasználó nem található.');
    session_regenerate_id(true);
    $_SESSION['uid']  = (int) $row['id'];
    $_SESSION['role'] = Roles::intToString($row['jogosultsag']);
    return self::publicUser($row);
  }

  public static function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
  }

  // ---- Munkamenet-olvasók ------------------------------------
  public static function userId(): ?int {
    return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
  }

  public static function role(): string {
    return $_SESSION['role'] ?? 'user';
  }

  public static function isLoggedIn(): bool {
    return self::userId() !== null;
  }

  // Bejelentkezett felhasználó publikus adatai (frontend `full_name`/`auth`).
  public static function currentUser(): ?array {
    $id = self::userId();
    if ($id === null) return null;
    $st = getDB()->prepare(
      "SELECT id, username, " . USER_NAME_COLUMN . " AS full_name, " . USER_ROLE_COLUMN . " AS jogosultsag
       FROM users WHERE id = ? LIMIT 1"
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? self::publicUser($row) : null;
  }

  // ---- Kapuk -------------------------------------------------
  public static function requireLogin(): void {
    if (!self::isLoggedIn()) json_error(401, 'Bejelentkezés szükséges.');
  }

  public static function requireRole(string $min): void {
    self::requireLogin();
    if (!Roles::atLeast(self::role(), $min)) {
      json_error(403, 'Ehhez nincs jogosultságod.');
    }
  }

  private static function publicUser(array $row): array {
    return [
      'id'        => (int) $row['id'],
      'username'  => $row['username'],
      'full_name' => $row['full_name'],
      'auth'      => Roles::intToString($row['jogosultsag']),
    ];
  }
}
