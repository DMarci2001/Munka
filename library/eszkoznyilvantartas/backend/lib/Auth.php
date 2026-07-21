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
  private const LOGIN_MAX_ATTEMPTS = 5;
  private const LOGIN_LOCKOUT_SECONDS = 900; // 15 perc

  // ---- Bejelentkezés -----------------------------------------
  // A klinikai users táblából azonosít username + jelszó alapján.
  // A jelszó-oszlop a config-ban állítható (USER_PASSWORD_COLUMN).
  public static function login(string $username, string $password): array {
    $db = getDB();
    self::checkLoginLock($db, $username);

    $col = USER_PASSWORD_COLUMN;
    $st = $db->prepare(
      "SELECT id, username, " . USER_NAME_COLUMN . " AS full_name,
              " . USER_ROLE_COLUMN . " AS jogosultsag, permissions, `$col` AS pwhash
       FROM users WHERE username = ? LIMIT 1"
    );
    $st->execute([$username]);
    $row = $st->fetch();

    $hash = $row['pwhash'] ?? '';
    if (!$row || $hash === null || $hash === '' || !password_verify($password, $hash)) {
      self::registerLoginFailure($db, $username);
      throw new OpError('Hibás felhasználónév vagy jelszó.');
    }
    self::clearLoginFailures($db, $username);

    // Munkamenet rögzítése (session fixation ellen: új azonosító).
    session_regenerate_id(true);
    $_SESSION['uid']  = (int) $row['id'];
    $_SESSION['role'] = Roles::fromUserRow($row);
    return self::publicUser($row);
  }

  // ---- Bejelentkezési rate limit -------------------------------
  private static function checkLoginLock(PDO $db, string $username): void {
    $st = $db->prepare('SELECT locked_until FROM eszkoznyilvantartas_login_attempts WHERE username = ?');
    $st->execute([$username]);
    $lockedUntil = $st->fetchColumn();
    if ($lockedUntil !== false && $lockedUntil !== null && strtotime($lockedUntil) > time()) {
      throw new OpError('Túl sok sikertelen bejelentkezési kísérlet. Próbáld újra néhány perc múlva.');
    }
  }

  private static function registerLoginFailure(PDO $db, string $username): void {
    $now = date('Y-m-d H:i:s');
    $st = $db->prepare('SELECT fail_count FROM eszkoznyilvantartas_login_attempts WHERE username = ?');
    $st->execute([$username]);
    $failCount = ((int) $st->fetchColumn()) + 1;
    $lockedUntil = $failCount >= self::LOGIN_MAX_ATTEMPTS
      ? date('Y-m-d H:i:s', time() + self::LOGIN_LOCKOUT_SECONDS)
      : null;
    $db->prepare(
      'INSERT INTO eszkoznyilvantartas_login_attempts (username, fail_count, last_attempt, locked_until)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE fail_count = VALUES(fail_count), last_attempt = VALUES(last_attempt), locked_until = VALUES(locked_until)'
    )->execute([$username, $failCount, $now, $lockedUntil]);
  }

  private static function clearLoginFailures(PDO $db, string $username): void {
    $db->prepare('DELETE FROM eszkoznyilvantartas_login_attempts WHERE username = ?')->execute([$username]);
  }

  // SSO: log in by username only (password check skipped — caller must have verified HMAC token).
  public static function loginByUsername(string $username): array {
    $st = getDB()->prepare(
      "SELECT id, username, " . USER_NAME_COLUMN . " AS full_name,
              " . USER_ROLE_COLUMN . " AS jogosultsag, permissions
       FROM users WHERE username = ? LIMIT 1"
    );
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row) throw new OpError('Felhasználó nem található.');
    session_regenerate_id(true);
    $_SESSION['uid']  = (int) $row['id'];
    $_SESSION['role'] = Roles::fromUserRow($row);
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
      "SELECT id, username, " . USER_NAME_COLUMN . " AS full_name, " . USER_ROLE_COLUMN . " AS jogosultsag, permissions
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
      'auth'      => Roles::fromUserRow($row),
    ];
  }
}
