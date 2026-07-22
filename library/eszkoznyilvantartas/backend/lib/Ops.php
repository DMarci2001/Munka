<?php
// ============================================================
// Ops — írási oldal: a store.js MŰVELETEINEK szerveroldali párja.
// Minden mutáció tranzakcióban fut, az "aktor" a bejelentkezett
// munkamenet-felhasználó (Auth). A visszatérő érték a frissített,
// kibővített (enriched) eszköz — a frontend ezt fogyasztja.
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/Repo.php';
require_once __DIR__ . '/Roles.php';
require_once __DIR__ . '/Auth.php';

final class Ops {
  // ---- Idő segédek -------------------------------------------
  private static function nowTs(): string { return date('Y-m-d H:i:s'); }
  private static function daysFromNow(int $d): string { return date('Y-m-d H:i:s', time() + $d * 86400); }

  // Egy esemény beszúrása az alapértelmezett NULL mezőkkel (store.pushEvent).
  private static function pushEvent(PDO $db, array $ev): int {
    $cols = [
      'device_id', 'event_type', 'actor_user_id',
      'from_user_id', 'from_locations_id', 'from_departments_id',
      'to_user_id', 'to_locations_id', 'to_departments_id',
      'event_timestamp', 'expected_return_date', 'condition_at_event',
      'notes', 'confirmation_status', 'confirmed_by', 'confirmed_at',
    ];
    $defaults = array_fill_keys($cols, null);
    $defaults['event_timestamp']     = self::nowTs();
    $defaults['confirmation_status'] = 'confirmed';
    $ev = array_merge($defaults, $ev);

    $place = implode(', ', array_map(fn($c) => ':' . $c, $cols));
    $sql = 'INSERT INTO eszkoznyilvantartas_device_custody_events (' . implode(', ', $cols) . ") VALUES ($place)";
    $st = $db->prepare($sql);
    foreach ($cols as $c) $st->bindValue(':' . $c, $ev[$c]);
    $st->execute();
    return (int) $db->lastInsertId();
  }

  // Eszköz státuszának + updated_by frissítése.
  private static function setStatus(PDO $db, int $deviceId, string $status): void {
    $st = $db->prepare('UPDATE eszkoznyilvantartas_devices SET status = ?, updated_by = ? WHERE device_id = ?');
    $st->execute([$status, Auth::userId(), $deviceId]);
  }

  private static function deleteReservation(PDO $db, int $deviceId): void {
    $db->prepare('DELETE FROM eszkoznyilvantartas_device_reservations WHERE device_id = ?')->execute([$deviceId]);
  }

  // store.js statusFromEvent
  private static function statusFromEvent(string $eventType): string {
    switch ($eventType) {
      case 'check_out':      return 'Kiadva';
      case 'check_in':       return 'Kivehető';
      case 'transfer':       return 'Kiadva';
      case 'stock_transfer': return 'Kivehető';
      case 'send_to_repair': return 'Szerviz alatt';
      case 'mark_lost':      return 'Elveszett';
      case 'mark_found':     return 'Kivehető';
      default:               return 'Kivehető';
    }
  }

  private static function requireDevice(int $deviceId): array {
    $st = getDB()->prepare('SELECT * FROM eszkoznyilvantartas_devices WHERE device_id = ?');
    $st->execute([$deviceId]);
    $dev = $st->fetch();
    if (!$dev) throw new OpError('Eszköz nem található.');
    return $dev;
  }

  // Mint requireDevice, de sorzárolással (versenyhelyzetek ellen egy tranzakción belül).
  private static function lockDevice(PDO $db, int $deviceId): array {
    $st = $db->prepare('SELECT * FROM eszkoznyilvantartas_devices WHERE device_id = ? FOR UPDATE');
    $st->execute([$deviceId]);
    $dev = $st->fetch();
    if (!$dev) throw new OpError('Eszköz nem található.');
    return $dev;
  }

  // Idegenkulcs-létezés ellenőrzése barátságos hibaüzenettel (nyers FK-hiba helyett).
  private static function requireExists(PDO $db, string $table, string $col, ?int $id, string $label): void {
    if ($id === null) return;
    $st = $db->prepare("SELECT 1 FROM `$table` WHERE `$col` = ? LIMIT 1");
    $st->execute([$id]);
    if (!$st->fetchColumn()) throw new OpError("Érvénytelen $label.");
  }

  private const TERMINAL_STATUSES = ['Selejtezve', 'Elveszett', 'Szerviz alatt'];

  // ============================================================
  // move_asset — minden birtoklási mozgás egyetlen kapun megy át
  // (store.js moveAsset). A felhasználói (user) korlátozásokkal.
  // ============================================================
  public static function moveAsset(array $in): array {
    Auth::requireLogin();
    require_fields($in, ['device_id', 'event_type']);
    $deviceId  = (int) $in['device_id'];
    $eventType = $in['event_type'];
    enum_in($eventType, ['check_out', 'check_in', 'transfer', 'stock_transfer'], 'eseménytípus');

    $toUser = int_or_null($in['to_user_id'] ?? null);
    $toLoc  = int_or_null($in['to_locations_id'] ?? null);
    $toDept = int_or_null($in['to_departments_id'] ?? null);
    $expectedReturn = $in['expected_return_date'] ?? null;
    $condition      = $in['condition_at_event'] ?? null;
    $notes          = $in['notes'] ?? null;

    $db = getDB();
    $db->beginTransaction();
    try {
      $dev = self::lockDevice($db, $deviceId);
      if (in_array($dev['status'], self::TERMINAL_STATUSES, true))
        throw new OpError('Az eszköz állapota (' . $dev['status'] . ') miatt előbb a megfelelő visszaállítási műveletet kell elvégezni.');
      $actor = Auth::userId();
      $role  = Auth::role();
      $cur   = Repo::currentState($deviceId);

      // engedélyezés (store.js) — allowlist: csak storekeeper+ mozgathat
      // szabadon; mindenki más (bármi, ami nem legalább storekeeper) a
      // lenti szűkített user-szabályok alá esik.
      if (!Roles::atLeast($role, 'storekeeper')) {
        $freeInStock = $cur['holder'] === null && ($cur['department'] !== null || $cur['location'] !== null);
        $heldByActor = $cur['holder'] === $actor;
        if ($eventType === 'check_out') {
          if (!($freeInStock && $toUser === $actor))
            throw new OpError('Felhasználóként csak szabad eszközt vehet ki, és csak magának.');
        } elseif ($eventType === 'check_in' || $eventType === 'transfer') {
          if (!$heldByActor)
            throw new OpError('Felhasználóként csak a nálad lévő eszközt mozgathatod.');
        } else {
          throw new OpError('Ehhez a művelethez raktáros vagy IT-admin szerepkör kell.');
        }
      }

      // cél ellenőrzése
      if ($toUser === null && $toDept === null && $toLoc === null)
        throw new OpError('Cél (személy vagy osztály) megadása kötelező.');
      // Részleg megadása opcionális — csak a cél-helyszín kötelező (személy nélkül).
      if ($eventType === 'stock_transfer' && !($toUser === null && $toLoc !== null))
        throw new OpError('Raktármozgatáshoz cél-helyszín kell, személy nélkül.');
      if ($eventType === 'check_in' && $toDept === null && $toLoc === null)
        throw new OpError('Visszavételhez cél-osztály kell.');

      // foglalás-interplay a check_out ágban
      if ($eventType === 'check_out') {
        $resv = Repo::activeReservation($deviceId);
        if ($resv && !((int) $resv['reserved_by'] === $actor || Roles::atLeast($role, 'storekeeper')))
          throw new OpError('Az eszköz másnak van fenntartva (foglalás).');
      }

      // pending check_in: csak egy nyitott lehet (user ág)
      if ($eventType === 'check_in' && $role === 'user' && Repo::pendingCheckin($deviceId))
        throw new OpError('Erre az eszközre már van megerősítésre váró visszavétel.');

      // Raktár sosem birtokos: ha a cél raktár-részleg, az eszköz a készletbe kerül.
      $toStorage = Repo::isStorageDept($toDept);
      if ($toStorage) $toUser = null;

      $confirmation = ($eventType === 'check_in' && $role === 'user') ? 'pending' : 'confirmed';

      self::pushEvent($db, [
        'device_id' => $deviceId, 'event_type' => $eventType, 'actor_user_id' => $actor,
        'from_user_id' => $cur['holder'], 'from_locations_id' => $cur['location'], 'from_departments_id' => $cur['department'],
        'to_user_id' => $toUser, 'to_locations_id' => $toLoc, 'to_departments_id' => $toDept,
        'event_timestamp' => self::nowTs(), 'expected_return_date' => $expectedReturn,
        'condition_at_event' => $condition, 'notes' => $notes,
        'confirmation_status' => $confirmation,
      ]);

      // foglalás teljesült
      if ($eventType === 'check_out') self::deleteReservation($db, $deviceId);

      // státusz
      if ($confirmation === 'pending')      $status = 'Visszavétel folyamatban';
      elseif ($toStorage)                   $status = 'Kivehető';
      else                                  $status = self::statusFromEvent($eventType);
      self::setStatus($db, $deviceId, $status);

      $db->commit();
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
    return Repo::enrichOne($deviceId);
  }

  // ---- confirm_check_in — storekeeper / it_admin -------------
  public static function confirmCheckIn(int $eventId): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      $st = $db->prepare('SELECT * FROM eszkoznyilvantartas_device_custody_events WHERE event_id = ?');
      $st->execute([$eventId]);
      $ev = $st->fetch();
      if (!$ev || $ev['confirmation_status'] !== 'pending' || $ev['event_type'] !== 'check_in')
        throw new OpError('Csak függőben lévő visszavétel erősíthető meg.');

      $dev = self::lockDevice($db, (int) $ev['device_id']);
      if ($dev['status'] !== 'Visszavétel folyamatban')
        throw new OpError('Az eszköz állapota közben megváltozott (' . $dev['status'] . '), a visszavétel már nem erősíthető meg.');

      $db->prepare(
        "UPDATE eszkoznyilvantartas_device_custody_events SET confirmation_status = 'confirmed', confirmed_by = ?, confirmed_at = ? WHERE event_id = ?"
      )->execute([Auth::userId(), self::nowTs(), $eventId]);

      // check_in után mindig kivehető, a check_in céljától függetlenül
      self::setStatus($db, (int) $ev['device_id'], 'Kivehető');
      $db->commit();
      return Repo::enrichOne((int) $ev['device_id']);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- reject_check_in — storekeeper / it_admin --------------
  public static function rejectCheckIn(int $eventId, ?string $reason): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      $st = $db->prepare('SELECT * FROM eszkoznyilvantartas_device_custody_events WHERE event_id = ?');
      $st->execute([$eventId]);
      $ev = $st->fetch();
      if (!$ev || $ev['confirmation_status'] !== 'pending' || $ev['event_type'] !== 'check_in')
        throw new OpError('Csak függőben lévő visszavétel utasítható el.');

      $dev = self::lockDevice($db, (int) $ev['device_id']);
      if ($dev['status'] !== 'Visszavétel folyamatban')
        throw new OpError('Az eszköz állapota közben megváltozott (' . $dev['status'] . '), a visszavétel már nem utasítható el.');

      $notes = ($ev['notes'] ? $ev['notes'] . ' ' : '') . 'ELUTASÍTVA: ' . ($reason ?: 'nincs indok');
      $db->prepare(
        "UPDATE eszkoznyilvantartas_device_custody_events SET confirmation_status = 'rejected', confirmed_by = ?, confirmed_at = ?, notes = ? WHERE event_id = ?"
      )->execute([Auth::userId(), self::nowTs(), $notes, $eventId]);

      // visszaáll a check_in előtti birtoklásra
      self::setStatus($db, (int) $ev['device_id'], 'Kiadva');
      $db->commit();
      return Repo::enrichOne((int) $ev['device_id']);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- reserve_device — bármely bejelentkezett felhasználó ---
  public static function reserveDevice(int $deviceId, ?string $notes): array {
    Auth::requireLogin();
    $db = getDB();
    $db->beginTransaction();
    try {
      $actor = Auth::userId();
      // lejárt foglalás takarítása az eszközre
      $db->prepare('DELETE FROM eszkoznyilvantartas_device_reservations WHERE device_id = ? AND expires_at <= NOW()')->execute([$deviceId]);

      $dev = self::lockDevice($db, $deviceId);
      $cur = Repo::currentState($deviceId);
      $free = $cur['holder'] === null && ($cur['department'] !== null || $cur['location'] !== null) && $dev['status'] === 'Kivehető';
      if (!$free) throw new OpError('Csak szabad, raktárban lévő eszköz foglalható.');
      if (Repo::activeReservation($deviceId)) throw new OpError('Az eszköz már le van foglalva.');

      $db->prepare(
        'INSERT INTO eszkoznyilvantartas_device_reservations (device_id, reserved_by, reserved_at, expires_at, notes) VALUES (?, ?, ?, ?, ?)'
      )->execute([$deviceId, $actor, self::nowTs(), self::daysFromNow(RESERVATION_DAYS), $notes]);

      self::setStatus($db, $deviceId, 'Lefoglalva');
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- cancel_reservation — a foglaló vagy storekeeper+ ------
  public static function cancelReservation(int $deviceId): array {
    Auth::requireLogin();
    $db = getDB();
    $db->beginTransaction();
    try {
      $resv = Repo::activeReservation($deviceId);
      if (!$resv) throw new OpError('Nincs aktív foglalás ezen az eszközön.');
      if (!((int) $resv['reserved_by'] === Auth::userId() || Roles::atLeast(Auth::role(), 'storekeeper')))
        throw new OpError('Csak a foglaló vagy raktáros mondhatja le a foglalást.');
      self::deleteReservation($db, $deviceId);
      self::setStatus($db, $deviceId, 'Kivehető');
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // belső változat ami megkerüli a user-korlátozást (csak storekeeper+ hívja)
  // $statusOverride: ha adott, ez kerül beállításra a szokásos
  // statusFromEvent($eventType) eredmény helyett (lásd markFound/returnFromRepair).
  private static function moveAssetInternal(PDO $db, array $a, ?string $statusOverride = null): void {
    $deviceId  = (int) $a['device_id'];
    $eventType = $a['event_type'];
    $cur = Repo::currentState($deviceId);
    self::pushEvent($db, [
      'device_id' => $deviceId, 'event_type' => $eventType, 'actor_user_id' => Auth::userId(),
      'from_user_id' => $cur['holder'], 'from_locations_id' => $cur['location'], 'from_departments_id' => $cur['department'],
      'to_user_id' => $a['to_user_id'] ?? null,
      'to_locations_id' => $a['to_locations_id'] ?? null,
      'to_departments_id' => $a['to_departments_id'] ?? null,
      'event_timestamp' => self::nowTs(), 'notes' => $a['notes'] ?? null,
      'confirmation_status' => 'confirmed',
    ]);
    self::deleteReservation($db, $deviceId);
    self::setStatus($db, $deviceId, $statusOverride ?? self::statusFromEvent($eventType));
  }

  // ---- send_to_repair — storekeeper+ -------------------------
  public static function sendToRepair(int $deviceId, ?int $toLoc, ?int $toDept, ?string $notes): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      self::requireDevice($deviceId);
      if ($toDept === null) {
        $repair = $db->query("SELECT id FROM eszkoznyilvantartas_departments WHERE type = 'műhely' LIMIT 1")->fetchColumn();
        $toDept = $repair !== false ? (int) $repair : null;
      }
      self::requireExists($db, 'helyszinek', 'id', $toLoc, 'helyszín');
      self::requireExists($db, 'eszkoznyilvantartas_departments', 'id', $toDept, 'részleg');
      self::moveAssetInternal($db, [
        'device_id' => $deviceId, 'event_type' => 'send_to_repair',
        'to_locations_id' => $toLoc, 'to_departments_id' => $toDept, 'notes' => $notes,
      ]);
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- return_from_repair — storekeeper+ ---------------------
  public static function returnFromRepair(int $deviceId, ?int $toLoc, ?int $toDept, ?string $notes): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      $dev = self::requireDevice($deviceId);
      if ($dev['status'] !== 'Szerviz alatt')
        throw new OpError('Csak szerviz alatt lévő eszköz helyezhető vissza.');
      self::requireExists($db, 'helyszinek', 'id', $toLoc, 'helyszín');
      self::requireExists($db, 'eszkoznyilvantartas_departments', 'id', $toDept, 'részleg');
      // Ha a szervizbe küldés egy megerősítésre váró visszavétel ALATT történt,
      // a visszahelyezés állítsa vissza a "Visszavétel folyamatban" állapotot —
      // így az eredeti visszavétel ismét megerősíthető/elutasítható, nem ragad
      // örökre a confirmCheckIn/rejectCheckIn "állapot közben megváltozott" hibája mögött.
      $statusOverride = Repo::pendingCheckin($deviceId) ? 'Visszavétel folyamatban' : null;
      self::moveAssetInternal($db, [
        'device_id' => $deviceId, 'event_type' => 'return_from_repair',
        'to_locations_id' => $toLoc, 'to_departments_id' => $toDept, 'notes' => $notes,
      ], $statusOverride);
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- mark_lost — storekeeper+ ------------------------------
  public static function markLost(int $deviceId, ?string $notes): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      self::requireDevice($deviceId);
      $cur = Repo::currentState($deviceId);
      self::moveAssetInternal($db, [
        'device_id' => $deviceId, 'event_type' => 'mark_lost',
        'to_locations_id' => $cur['location'], 'to_departments_id' => $cur['department'],
        'to_user_id' => $cur['holder'], 'notes' => $notes,
      ]);
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- mark_found — storekeeper+ -----------------------------
  public static function markFound(int $deviceId, ?int $toLoc, ?int $toDept, ?string $notes): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      self::requireDevice($deviceId);
      self::requireExists($db, 'helyszinek', 'id', $toLoc, 'helyszín');
      self::requireExists($db, 'eszkoznyilvantartas_departments', 'id', $toDept, 'részleg');
      // Ld. returnFromRepair megjegyzése: ha a "elveszettnek jelölés" egy
      // megerősítésre váró visszavétel ALATT történt, a "megkerült" állítsa
      // vissza a "Visszavétel folyamatban" állapotot ahelyett, hogy a
      // pending visszavétel örökre blokkolva maradna.
      $statusOverride = Repo::pendingCheckin($deviceId) ? 'Visszavétel folyamatban' : null;
      self::moveAssetInternal($db, [
        'device_id' => $deviceId, 'event_type' => 'mark_found',
        'to_locations_id' => $toLoc, 'to_departments_id' => $toDept, 'notes' => $notes,
      ], $statusOverride);
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ============================================================
  // Eszköz CRUD
  // ============================================================

  // attribútum-kulcs → attribute_definitions.id leképezés egy típushoz
  private static function attrDefMap(PDO $db, int $deviceTypeId): array {
    $st = $db->prepare(
      'SELECT id, attribute_key FROM eszkoznyilvantartas_attribute_definitions WHERE device_type_id = ? OR device_type_id IS NULL'
    );
    $st->execute([$deviceTypeId]);
    $map = [];
    foreach ($st->fetchAll() as $r) $map[$r['attribute_key']] = (int) $r['id'];
    return $map;
  }

  // attrs {kulcs: érték} normalizált beírása device_attribute_values-ba (upsert)
  private static function writeAttrs(PDO $db, int $deviceId, int $deviceTypeId, array $attrs): void {
    if (!$attrs) return;
    $map = self::attrDefMap($db, $deviceTypeId);
    $up = $db->prepare(
      'INSERT INTO eszkoznyilvantartas_device_attribute_values (device_id, attribute_definition_id, value)
       VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    foreach ($attrs as $key => $val) {
      if (!isset($map[$key])) continue; // ismeretlen attribútum kihagyva
      if ($val === null || $val === '') {
        $db->prepare('DELETE FROM eszkoznyilvantartas_device_attribute_values WHERE device_id = ? AND attribute_definition_id = ?')
           ->execute([$deviceId, $map[$key]]);
        continue;
      }
      $store = is_bool($val) ? ($val ? '1' : '0') : (string) $val;
      $up->execute([$deviceId, $map[$key], $store]);
    }
  }

  // ---- register_device — storekeeper / it_admin -------------
  public static function registerDevice(array $in): array {
    Auth::requireRole('storekeeper');
    require_fields($in, ['device_type_id', 'asset_tag']);
    $db = getDB();
    $db->beginTransaction();
    try {
      $deviceTypeId = (int) $in['device_type_id'];
      $assetTag = trim((string) $in['asset_tag']);
      self::requireExists($db, 'eszkoznyilvantartas_device_types', 'id', $deviceTypeId, 'eszköztípus');

      $exists = $db->prepare('SELECT 1 FROM eszkoznyilvantartas_devices WHERE LOWER(asset_tag) = LOWER(?)');
      $exists->execute([$assetTag]);
      if ($exists->fetchColumn()) throw new OpError("Ez a leltári azonosító már létezik: $assetTag");

      $uid = Auth::userId();
      $st = $db->prepare(
        'INSERT INTO eszkoznyilvantartas_devices (asset_tag, device_type_id, manufacturer, model, serial_number, status, `condition`, notes, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
      );
      try {
        $st->execute([
          $assetTag, $deviceTypeId,
          $in['manufacturer'] ?? null, $in['model'] ?? null, $in['serial_number'] ?? null,
          'Kivehető', $in['condition'] ?? 'Jó', $in['notes'] ?? '', $uid, $uid,
        ]);
      } catch (\PDOException $e) {
        if ($e->getCode() === '23000') throw new OpError("Ez a leltári azonosító már létezik: $assetTag");
        throw $e;
      }
      $deviceId = (int) $db->lastInsertId();

      if (!empty($in['attrs']) && is_array($in['attrs'])) {
        self::writeAttrs($db, $deviceId, $deviceTypeId, $in['attrs']);
      }

      // kezdeti elhelyezés (regisztrációs check_in)
      self::pushEvent($db, [
        'device_id' => $deviceId, 'event_type' => 'check_in', 'actor_user_id' => $uid,
        'to_locations_id' => int_or_null($in['initial_location'] ?? null),
        'to_departments_id' => int_or_null($in['initial_department'] ?? null),
        'event_timestamp' => self::nowTs(), 'notes' => 'Regisztrációs elhelyezés.',
        'confirmation_status' => 'confirmed',
      ]);

      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- edit_device — storekeeper / it_admin -----------------
  public static function editDevice(int $deviceId, array $changes): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      $dev = self::requireDevice($deviceId);

      if (!empty($changes['asset_tag'])) {
        $tag = trim((string) $changes['asset_tag']);
        $st = $db->prepare('SELECT device_id FROM eszkoznyilvantartas_devices WHERE LOWER(asset_tag) = LOWER(?)');
        $st->execute([$tag]);
        $other = $st->fetchColumn();
        if ($other !== false && (int) $other !== $deviceId)
          throw new OpError("Ez a leltári azonosító már létezik: $tag");
      }

      if (array_key_exists('device_type_id', $changes))
        self::requireExists($db, 'eszkoznyilvantartas_device_types', 'id', (int) $changes['device_type_id'], 'eszköztípus');
      if (array_key_exists('condition', $changes))
        enum_in($changes['condition'], ['Jó', 'Kopott', 'Hibás', 'Ismeretlen'], 'állapot');

      // csak engedélyezett közös oszlopok módosíthatók — a "status" szándékosan
      // NEM szerkeszthető itt: kizárólag a dedikált műveleteken (moveAsset,
      // retireDevice, markLost/Found, sendToRepair/returnFromRepair,
      // confirmCheckIn/rejectCheckIn) keresztül változhat.
      $allowed = ['asset_tag', 'device_type_id', 'manufacturer', 'model', 'serial_number', 'condition', 'notes'];
      $set = [];
      $vals = [];
      foreach ($allowed as $col) {
        if (array_key_exists($col, $changes)) {
          $set[] = "`$col` = ?";
          $vals[] = $changes[$col];
        }
      }
      $set[] = 'updated_by = ?';
      $vals[] = Auth::userId();
      $vals[] = $deviceId;
      $db->prepare('UPDATE eszkoznyilvantartas_devices SET ' . implode(', ', $set) . ' WHERE device_id = ?')->execute($vals);

      if (!empty($changes['attrs']) && is_array($changes['attrs'])) {
        $typeId = isset($changes['device_type_id']) ? (int) $changes['device_type_id'] : (int) $dev['device_type_id'];
        self::writeAttrs($db, $deviceId, $typeId, $changes['attrs']);
      }

      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ---- retire_device — storekeeper / it_admin ---------------
  public static function retireDevice(int $deviceId, ?string $reason): array {
    Auth::requireRole('storekeeper');
    $db = getDB();
    $db->beginTransaction();
    try {
      $dev = self::requireDevice($deviceId);
      $notes = ($dev['notes'] ? $dev['notes'] . ' ' : '') . 'Selejtezve: ' . ($reason ?: 'nincs indok');
      $db->prepare('UPDATE eszkoznyilvantartas_devices SET status = ?, retired_date = ?, notes = ?, updated_by = ? WHERE device_id = ?')
         ->execute(['Selejtezve', date('Y-m-d'), $notes, Auth::userId(), $deviceId]);
      self::deleteReservation($db, $deviceId);
      $db->commit();
      return Repo::enrichOne($deviceId);
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  // ============================================================
  // Törzsadat műveletek
  // ============================================================
  public static function addLocation(array $in): array {
    Auth::requireRole('it_admin');
    $address = trim((string) ($in['address'] ?? ''));
    if ($address === '') throw new OpError('Add meg a helyszín címét.');
    $db = getDB();
    $db->prepare('INSERT INTO helyszinek (cim) VALUES (?)')->execute([$address]);
    $id = (int) $db->lastInsertId();
    return ['id' => $id, 'address' => $address];
  }

  public static function addDepartment(array $in): array {
    Auth::requireRole('storekeeper');
    $name = trim((string) ($in['name'] ?? ''));
    $locId = int_or_null($in['locations_id'] ?? null);
    if ($name === '') throw new OpError('Add meg a részleg nevét.');
    if ($locId === null) throw new OpError('Válassz helyszínt.');
    $type = $in['type'] ?? 'osztály';
    enum_in($type, ['raktár', 'osztály', 'recepció', 'műhely'], 'részleg-típus');
    $db = getDB();
    self::requireExists($db, 'helyszinek', 'id', $locId, 'helyszín');
    $db->prepare('INSERT INTO eszkoznyilvantartas_departments (locations_id, name, type) VALUES (?, ?, ?)')->execute([$locId, $name, $type]);
    $id = (int) $db->lastInsertId();
    return ['id' => $id, 'locations_id' => $locId, 'name' => $name, 'type' => $type];
  }

  public static function addDeviceType(array $in): array {
    Auth::requireRole('it_admin');
    $type = trim((string) ($in['type'] ?? ''));
    if ($type === '') throw new OpError('Add meg az eszköztípus nevét.');
    $desc = trim((string) ($in['description'] ?? ''));
    $db = getDB();
    $db->prepare('INSERT INTO eszkoznyilvantartas_device_types (type, description) VALUES (?, ?)')->execute([$type, $desc]);
    $id = (int) $db->lastInsertId();
    return ['id' => $id, 'type' => $type, 'description' => $desc];
  }

  public static function addAttrDef(array $in): array {
    Auth::requireRole('it_admin');
    $key = trim((string) ($in['attribute_key'] ?? ''));
    $label = trim((string) ($in['label'] ?? ''));
    $dataType = $in['data_type'] ?? '';
    if ($key === '') throw new OpError('Add meg az attribútum kulcsát.');
    if ($label === '') throw new OpError('Add meg az attribútum feliratát.');
    if ($dataType === '') throw new OpError('Válassz adattípust.');
    enum_in($dataType, ['text', 'integer', 'decimal', 'date', 'boolean', 'enum'], 'adattípus');

    $deviceTypeId = int_or_null($in['device_type_id'] ?? null);
    $options = $dataType === 'enum' ? (trim((string) ($in['options'] ?? '')) ?: null) : null;
    $db = getDB();
    self::requireExists($db, 'eszkoznyilvantartas_device_types', 'id', $deviceTypeId, 'eszköztípus');
    $db->prepare(
      'INSERT INTO eszkoznyilvantartas_attribute_definitions (device_type_id, attribute_key, label, data_type, is_required, options, sort_order)
       VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
      $deviceTypeId, $key, $label, $dataType,
      !empty($in['is_required']) ? 1 : 0, $options, (int) ($in['sort_order'] ?? 0),
    ]);
    return ['id' => (int) $db->lastInsertId()];
  }
}
