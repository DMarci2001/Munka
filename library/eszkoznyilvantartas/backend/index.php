<?php
// ============================================================
// Front controller — Eszköznyilvántartás API
//
// Egyetlen belépési pont. Minden kérés ide irányul (.htaccess),
// az útvonalat a metódus + path alapján fejtjük fel.
//
// Válaszburok: { ok: true, data } | { ok: false, error }.
// ============================================================

require_once __DIR__ . '/config/cors.php';      // CORS + OPTIONS preflight (elsőként!)
require_once __DIR__ . '/config/session.php';   // munkamenet indítása
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/lib/Repo.php';
require_once __DIR__ . '/lib/Lookups.php';
require_once __DIR__ . '/lib/Ops.php';
require_once __DIR__ . '/lib/Auth.php';

// ---- Útvonal kinyerése -------------------------------------
// Az alkalmazás bázis-útja a SCRIPT_NAME könyvtára (pl. /eszkoznyilvantartas_api).
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$route = $uriPath;
if ($base !== '' && strpos($route, $base) === 0) $route = substr($route, strlen($base));
$route = preg_replace('#^/?index\.php#', '', $route);   // direkt /index.php/... hívás esetén
$route = trim($route, '/');                              // pl. "devices/5/history"

// PUT/PATCH/DELETE felülírás (egyszerű kliensekhez): ?_method=PATCH
if (isset($_GET['_method'])) $method = strtoupper($_GET['_method']);

// ---- Útvonaltábla ------------------------------------------
// [metódus, minta, kezelő]. A minta {id}/{tag} helyőrzőt fogad.
$routes = [
  ['GET',  '',                               fn() => json_success(['service' => 'eszkoznyilvantartas-api', 'ok' => true])],

  // Hitelesítés
  ['POST', 'auth/login',                     'h_login'],
  ['POST', 'auth/logout',                    'h_logout'],
  ['POST', 'auth/sso',                       'h_sso'],
  ['GET',  'me',                             'h_me'],

  // Olvasás
  ['GET',  'bootstrap',                      'h_bootstrap'],
  ['GET',  'lookups',                        'h_lookups'],
  ['GET',  'pending',                        'h_pending'],
  ['GET',  'reservations',                   'h_reservations'],
  ['GET',  'devices',                        'h_devices'],
  ['GET',  'devices/by-tag/{tag}',           'h_device_by_tag'],
  ['GET',  'devices/{id}/history',           'h_history'],
  ['GET',  'devices/{id}',                   'h_device'],

  // Eszköz-műveletek
  ['POST', 'devices/move',                   'h_move'],
  ['POST', 'devices',                        'h_register'],
  ['PUT',  'devices/{id}',                   'h_edit'],
  ['PATCH','devices/{id}',                   'h_edit'],
  ['POST', 'devices/{id}/reserve',           'h_reserve'],
  ['POST', 'devices/{id}/cancel-reservation','h_cancel_resv'],
  ['POST', 'devices/{id}/send-to-repair',    'h_send_repair'],
  ['POST', 'devices/{id}/return-from-repair','h_return_repair'],
  ['POST', 'devices/{id}/mark-lost',         'h_mark_lost'],
  ['POST', 'devices/{id}/mark-found',        'h_mark_found'],
  ['POST', 'devices/{id}/retire',            'h_retire'],

  // Visszavétel megerősítés
  ['POST', 'checkins/{id}/confirm',          'h_confirm'],
  ['POST', 'checkins/{id}/reject',           'h_reject'],

  // Törzsadat
  ['POST', 'locations',                      'h_add_location'],
  ['POST', 'departments',                    'h_add_department'],
  ['POST', 'device-types',                   'h_add_device_type'],
  ['POST', 'attribute-definitions',          'h_add_attr_def'],
];

// ---- Dispatcher --------------------------------------------
try {
  foreach ($routes as [$rMethod, $pattern, $handler]) {
    if ($rMethod !== $method) continue;
    $regex = '#^' . preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#';
    if (preg_match($regex, $route, $m)) {
      $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
      $result = call_user_func_array($handler, empty($params) ? [] : [$params]);
      json_success($result);
    }
  }
  json_error(404, 'Ismeretlen végpont: ' . $method . ' /' . $route);
} catch (OpError $e) {
  json_error(422, $e->getMessage());          // üzleti szabály megsértése
} catch (Throwable $e) {
  error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  // IDEIGLENES HIBAKERESÉS — vissza kell állítani 'Szerverhiba.'-ra, ha a hiba oka megvan.
  json_error(500, 'Szerverhiba: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}

// ============================================================
// Kezelők
// ============================================================
function body(): array { return read_json_body(); }

// ---- Hitelesítés ----
function h_login(): array {
  $b = body();
  require_fields($b, ['username', 'password']);
  return Auth::login((string) $b['username'], (string) $b['password']);
}
function h_logout(): array { Auth::logout(); return ['loggedOut' => true]; }
function h_sso(): array {
  $b = body();
  require_fields($b, ['token', 'username', 'timestamp']);
  $ts = (int) $b['timestamp'];
  if (abs(time() - $ts) > SSO_TTL_SECONDS) json_error(401, 'SSO token lejárt.');
  $expected = hash_hmac('sha256', (string) $b['username'] . $ts, SSO_SECRET);
  if (!hash_equals($expected, (string) $b['token'])) json_error(401, 'Érvénytelen SSO token.');
  return Auth::loginByUsername((string) $b['username']);
}
function h_me(): ?array { return Auth::currentUser(); }

// ---- Olvasás ----
function h_lookups(): array {
  return [
    'locations'            => Lookups::locations(),
    'departments'          => Lookups::departments(),
    'users'                => Lookups::users(),
    'deviceTypes'          => Lookups::deviceTypes(),
    'attributeDefinitions' => Lookups::attributeDefinitions(),
  ];
}
function h_bootstrap(): array {
  return [
    'currentUser'          => Auth::currentUser(),
    'locations'            => Lookups::locations(),
    'departments'          => Lookups::departments(),
    'users'                => Lookups::users(),
    'deviceTypes'          => Lookups::deviceTypes(),
    'attributeDefinitions' => Lookups::attributeDefinitions(),
    'devices'              => Repo::allEnriched(),
    'pending'              => Lookups::pendingCheckins(),
    'reservations'         => Lookups::activeReservations(),
  ];
}
function h_devices(): array { return Repo::allEnriched(); }
function h_device(array $p): array {
  $dev = Repo::enrichOne((int) $p['id']);
  if (!$dev) json_error(404, 'Eszköz nem található.');
  return $dev;
}
function h_device_by_tag(array $p): array {
  $st = getDB()->prepare('SELECT device_id FROM eszkoznyilvantartas_devices WHERE LOWER(asset_tag) = LOWER(?)');
  $st->execute([rawurldecode($p['tag'])]);
  $id = $st->fetchColumn();
  if ($id === false) json_error(404, 'Eszköz nem található ezzel a leltári azonosítóval.');
  return Repo::enrichOne((int) $id);
}
function h_history(array $p): array { return Lookups::history((int) $p['id']); }
function h_pending(): array { return Lookups::pendingCheckins(); }
function h_reservations(): array { return Lookups::activeReservations(); }

// ---- Eszköz-műveletek ----
function h_move(): array {
  $b = body();
  return Ops::moveAsset($b);
}
function h_register(): array { return Ops::registerDevice(body()); }
function h_edit(array $p): array { return Ops::editDevice((int) $p['id'], body()); }
function h_reserve(array $p): array { return Ops::reserveDevice((int) $p['id'], body()['notes'] ?? null); }
function h_cancel_resv(array $p): array { return Ops::cancelReservation((int) $p['id']); }
function h_send_repair(array $p): array {
  $b = body();
  return Ops::sendToRepair((int) $p['id'], int_or_null($b['to_locations_id'] ?? null), int_or_null($b['to_departments_id'] ?? null), $b['notes'] ?? null);
}
function h_return_repair(array $p): array {
  $b = body();
  return Ops::returnFromRepair((int) $p['id'], int_or_null($b['to_locations_id'] ?? $b['to_location_id'] ?? null), int_or_null($b['to_departments_id'] ?? $b['to_department_id'] ?? null), $b['notes'] ?? null);
}
function h_mark_lost(array $p): array { return Ops::markLost((int) $p['id'], body()['notes'] ?? null); }
function h_mark_found(array $p): array {
  $b = body();
  return Ops::markFound((int) $p['id'], int_or_null($b['to_locations_id'] ?? $b['to_location_id'] ?? null), int_or_null($b['to_departments_id'] ?? $b['to_department_id'] ?? null), $b['notes'] ?? null);
}
function h_retire(array $p): array { return Ops::retireDevice((int) $p['id'], body()['reason'] ?? null); }

// ---- Visszavétel megerősítés ----
function h_confirm(array $p): array { return Ops::confirmCheckIn((int) $p['id']); }
function h_reject(array $p): array { return Ops::rejectCheckIn((int) $p['id'], body()['reason'] ?? null); }

// ---- Törzsadat ----
function h_add_location(): array { return Ops::addLocation(body()); }
function h_add_department(): array { return Ops::addDepartment(body()); }
function h_add_device_type(): array { return Ops::addDeviceType(body()); }
function h_add_attr_def(): array { return Ops::addAttrDef(body()); }
