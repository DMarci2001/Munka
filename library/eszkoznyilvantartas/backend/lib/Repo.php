<?php
// ============================================================
// Repo — olvasási oldal és származtatott állapot.
// A frontend `vm.js` deviceVM + store.js currentState logikájának
// szerveroldali megfelelője. A backend végzi MINDEN számítást.
// ============================================================

require_once __DIR__ . '/../config/database.php';

final class Repo {
  private static ?array $deptCache = null;

  // ---- Törzsadat-segédek ------------------------------------
  public static function departments(): array {
    if (self::$deptCache === null) {
      self::$deptCache = getDB()->query('SELECT id, locations_id, name, type FROM eszkoznyilvantartas_departments')->fetchAll();
    }
    return self::$deptCache;
  }

  public static function isStorageDept(?int $deptId): bool {
    if ($deptId === null) return false;
    foreach (self::departments() as $d) {
      if ((int)$d['id'] === $deptId) return $d['type'] === 'raktár';
    }
    return false;
  }

  // ---- device_current_state (raktár-szabállyal) -------------
  // A nézet nyers to_* értékeit adja; a "raktár sosem birtokos" szabályt itt
  // érvényesítjük (lásd schema megjegyzés + store.js currentState).
  // Közvetlen lekérdezés (nem a nézeten át), hogy az azonos időbélyegű
  // események közt az event_id döntsön — store.js currentState tiebreak.
  public static function currentState(int $deviceId): array {
    $st = getDB()->prepare(
      "SELECT to_user_id, to_locations_id, to_departments_id, event_timestamp
       FROM eszkoznyilvantartas_device_custody_events
       WHERE device_id = ? AND confirmation_status = 'confirmed'
       ORDER BY event_timestamp DESC, event_id DESC LIMIT 1"
    );
    $st->execute([$deviceId]);
    $row = $st->fetch();
    if (!$row) return ['holder' => null, 'location' => null, 'department' => null, 'since' => null];
    $dept = $row['to_departments_id'] !== null ? (int)$row['to_departments_id'] : null;
    $holder = self::isStorageDept($dept) ? null : ($row['to_user_id'] !== null ? (int)$row['to_user_id'] : null);
    return [
      'holder'     => $holder,
      'location'   => $row['to_locations_id'] !== null ? (int)$row['to_locations_id'] : null,
      'department' => $dept,
      'since'      => $row['event_timestamp'],
    ];
  }

  public static function activeReservation(int $deviceId): ?array {
    $st = getDB()->prepare(
      'SELECT reservation_id, device_id, reserved_by, reserved_at, expires_at, notes
       FROM eszkoznyilvantartas_device_reservations WHERE device_id = ? AND expires_at > NOW() LIMIT 1'
    );
    $st->execute([$deviceId]);
    return $st->fetch() ?: null;
  }

  public static function pendingCheckin(int $deviceId): ?array {
    $st = getDB()->prepare(
      "SELECT * FROM eszkoznyilvantartas_device_custody_events
       WHERE device_id = ? AND confirmation_status = 'pending' AND event_type = 'check_in' LIMIT 1"
    );
    $st->execute([$deviceId]);
    return $st->fetch() ?: null;
  }

  public static function pendingTransfer(int $deviceId): ?array {
    $st = getDB()->prepare(
      "SELECT * FROM eszkoznyilvantartas_device_custody_events
       WHERE device_id = ? AND confirmation_status = 'pending' AND event_type = 'transfer' LIMIT 1"
    );
    $st->execute([$deviceId]);
    return $st->fetch() ?: null;
  }

  public static function attrsFor(int $deviceId): array {
    $st = getDB()->prepare(
      'SELECT ad.attribute_key, dav.value, ad.data_type
       FROM eszkoznyilvantartas_device_attribute_values dav
       JOIN eszkoznyilvantartas_attribute_definitions ad ON ad.id = dav.attribute_definition_id
       WHERE dav.device_id = ?'
    );
    $st->execute([$deviceId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
      $out[$r['attribute_key']] = self::castAttr($r['value'], $r['data_type']);
    }
    return $out;
  }

  private static function castAttr(?string $v, string $type) {
    if ($v === null) return null;
    switch ($type) {
      case 'integer': return (int)$v;
      case 'decimal': return (float)$v;
      case 'boolean': return $v === '1' || $v === 'true';
      default:        return $v;
    }
  }

  // ---- Effektív státusz (deviceVM logika) -------------------
  // A "sticky" manuális státuszokat a tárolt status adja; a többit a
  // tényleges birtoklásból/foglalásból vezetjük le.
  public static function effectiveStatus(string $stored, ?int $holder, ?array $resv, ?array $pending, ?array $pendingTransfer = null): string {
    if (in_array($stored, ['Selejtezve', 'Elveszett', 'Szerviz alatt', 'Lefoglalva'], true)) return $stored;
    if ($resv)              return 'Lefoglalva';
    if ($pending)           return 'Visszavétel folyamatban';
    if ($pendingTransfer)   return 'Átadás folyamatban';
    if ($holder !== null)   return 'Kiadva';
    return 'Kivehető';
  }

  // ---- Egy eszköz "kibővített" (enriched) nézete ------------
  public static function enrichOne(int $deviceId): ?array {
    $st = getDB()->prepare('SELECT * FROM eszkoznyilvantartas_devices WHERE device_id = ?');
    $st->execute([$deviceId]);
    $dev = $st->fetch();
    if (!$dev) return null;

    $cur       = self::currentState($deviceId);
    $resv      = self::activeReservation($deviceId);
    $pending   = self::pendingCheckin($deviceId);
    $pendingTr = self::pendingTransfer($deviceId);
    $attrs     = self::attrsFor($deviceId);

    $lc = getDB()->prepare(
      "SELECT MAX(event_timestamp) FROM eszkoznyilvantartas_device_custody_events
       WHERE device_id = ? AND event_type = 'check_out' AND confirmation_status = 'confirmed'"
    );
    $lc->execute([$deviceId]);
    $lastCheckout = $lc->fetchColumn() ?: null;

    $lm = getDB()->prepare('SELECT MAX(event_timestamp) FROM eszkoznyilvantartas_device_custody_events WHERE device_id = ?');
    $lm->execute([$deviceId]);
    $lastModified = $lm->fetchColumn() ?: null;

    return self::assemble($dev, $cur, $resv, $pending, $attrs, $lastCheckout, $lastModified, $pendingTr);
  }

  // ---- Az összes eszköz enriched listája (kötegelt, N+1 nélkül) ----
  public static function allEnriched(): array {
    $db = getDB();
    $devices = $db->query('SELECT * FROM eszkoznyilvantartas_devices ORDER BY device_id')->fetchAll();

    // current_state mindenkire
    $cs = [];
    foreach ($db->query('SELECT * FROM eszkoznyilvantartas_device_current_state')->fetchAll() as $r) {
      $cs[(int)$r['device_id']] = $r;
    }
    // aktív foglalások
    $resvs = [];
    foreach ($db->query('SELECT * FROM eszkoznyilvantartas_device_reservations WHERE expires_at > NOW()')->fetchAll() as $r) {
      $resvs[(int)$r['device_id']] = $r;
    }
    // függő visszavételek
    $pendings = [];
    foreach ($db->query("SELECT * FROM eszkoznyilvantartas_device_custody_events WHERE confirmation_status='pending' AND event_type='check_in'")->fetchAll() as $r) {
      $pendings[(int)$r['device_id']] = $r;
    }
    // függő átadások
    $pendingTransfers = [];
    foreach ($db->query("SELECT * FROM eszkoznyilvantartas_device_custody_events WHERE confirmation_status='pending' AND event_type='transfer'")->fetchAll() as $r) {
      $pendingTransfers[(int)$r['device_id']] = $r;
    }
    // attribútumok
    $attrsByDev = [];
    $allAttrs = $db->query(
      'SELECT dav.device_id, ad.attribute_key, dav.value, ad.data_type
       FROM eszkoznyilvantartas_device_attribute_values dav
       JOIN eszkoznyilvantartas_attribute_definitions ad ON ad.id = dav.attribute_definition_id'
    )->fetchAll();
    foreach ($allAttrs as $r) {
      $attrsByDev[(int)$r['device_id']][$r['attribute_key']] = self::castAttr($r['value'], $r['data_type']);
    }
    // utolsó kivét / utolsó módosítás
    $lastCheckout = [];
    foreach ($db->query("SELECT device_id, MAX(event_timestamp) mx FROM eszkoznyilvantartas_device_custody_events WHERE event_type='check_out' AND confirmation_status='confirmed' GROUP BY device_id")->fetchAll() as $r) {
      $lastCheckout[(int)$r['device_id']] = $r['mx'];
    }
    $lastModified = [];
    foreach ($db->query('SELECT device_id, MAX(event_timestamp) mx FROM eszkoznyilvantartas_device_custody_events GROUP BY device_id')->fetchAll() as $r) {
      $lastModified[(int)$r['device_id']] = $r['mx'];
    }

    $out = [];
    foreach ($devices as $dev) {
      $id = (int)$dev['device_id'];
      $csr = $cs[$id] ?? null;
      $dept = $csr && $csr['current_department_id'] !== null ? (int)$csr['current_department_id'] : null;
      $rawHolder = $csr && $csr['current_holder_user_id'] !== null ? (int)$csr['current_holder_user_id'] : null;
      $cur = [
        'holder'     => self::isStorageDept($dept) ? null : $rawHolder,
        'location'   => $csr && $csr['current_location_id'] !== null ? (int)$csr['current_location_id'] : null,
        'department' => $dept,
        'since'      => $csr['since'] ?? null,
      ];
      $out[] = self::assemble(
        $dev, $cur, $resvs[$id] ?? null, $pendings[$id] ?? null,
        $attrsByDev[$id] ?? [], $lastCheckout[$id] ?? null, $lastModified[$id] ?? null,
        $pendingTransfers[$id] ?? null
      );
    }
    return $out;
  }

  // Egységes enriched szerkezet — a frontend deviceVM ezt fogyasztja.
  private static function assemble(array $dev, array $cur, ?array $resv, ?array $pending, array $attrs, $lastCheckout, $lastModified, ?array $pendingTransfer = null): array {
    $status = self::effectiveStatus($dev['status'], $cur['holder'], $resv, $pending, $pendingTransfer);
    $isFree = $status === 'Kivehető' && ($cur['department'] !== null || $cur['location'] !== null);
    return [
      'device_id'      => (int)$dev['device_id'],
      'asset_tag'      => $dev['asset_tag'],
      'device_type_id' => (int)$dev['device_type_id'],
      'manufacturer'   => $dev['manufacturer'],
      'model'          => $dev['model'],
      'serial_number'  => $dev['serial_number'],
      'status'         => $status,
      'stored_status'  => $dev['status'],
      'condition'      => $dev['condition'],
      'notes'          => $dev['notes'],
      'retired_date'   => $dev['retired_date'],
      'created_at'     => $dev['created_at'],
      'updated_at'     => $dev['updated_at'],
      'attrs'          => (object)$attrs,
      // aktuális állapot
      'holder_id'      => $cur['holder'],
      'location_id'    => $cur['location'],
      'department_id'  => $cur['department'],
      'since'          => $cur['since'],
      // foglalás / függő visszavétel / függő átadás
      'reservation'      => $resv,
      'pending'          => $pending,
      'pending_transfer' => $pendingTransfer,
      // származtatott megjelenítési mezők
      'calibration_due'  => $attrs['calibration_due'] ?? null,
      'last_checkout_at' => $lastCheckout,
      'last_modified'    => $lastModified,
      'is_free'          => $isFree,
      'is_lost'          => $dev['status'] === 'Elveszett',
      'in_repair'        => $dev['status'] === 'Szerviz alatt',
    ];
  }
}
