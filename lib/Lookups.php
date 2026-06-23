<?php
// ============================================================
// Lookups — read-only törzsadat és listák a frontendnek.
// A klinikai oszlopneveket a frontend-kontraktusra fordítja
// (users.nev → full_name, jogosultsag → auth; helyszinek.cim → address).
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Roles.php';

final class Lookups {
  public static function locations(): array {
    $rows = getDB()->query('SELECT id, cim FROM helyszinek ORDER BY id')->fetchAll();
    return array_map(fn($r) => ['id' => (int) $r['id'], 'address' => $r['cim']], $rows);
  }

  public static function departments(): array {
    $rows = getDB()->query('SELECT id, locations_id, name, type FROM departments ORDER BY id')->fetchAll();
    return array_map(fn($r) => [
      'id' => (int) $r['id'], 'locations_id' => (int) $r['locations_id'],
      'name' => $r['name'], 'type' => $r['type'],
    ], $rows);
  }

  public static function users(): array {
    $rows = getDB()->query(
      'SELECT id, username, ' . USER_NAME_COLUMN . ' AS full_name, ' . USER_ROLE_COLUMN . ' AS jogosultsag FROM users ORDER BY id'
    )->fetchAll();
    return array_map(fn($r) => [
      'id' => (int) $r['id'], 'username' => $r['username'],
      'full_name' => $r['full_name'], 'auth' => Roles::intToString($r['jogosultsag']),
    ], $rows);
  }

  public static function deviceTypes(): array {
    $rows = getDB()->query('SELECT id, type, description FROM device_types ORDER BY id')->fetchAll();
    return array_map(fn($r) => ['id' => (int) $r['id'], 'type' => $r['type'], 'description' => $r['description']], $rows);
  }

  public static function attributeDefinitions(): array {
    $rows = getDB()->query(
      'SELECT id, device_type_id, attribute_key, label, data_type, is_required, options, sort_order
       FROM attribute_definitions ORDER BY device_type_id, sort_order'
    )->fetchAll();
    return array_map(fn($r) => [
      'id' => (int) $r['id'],
      'device_type_id' => $r['device_type_id'] !== null ? (int) $r['device_type_id'] : null,
      'attribute_key' => $r['attribute_key'], 'label' => $r['label'],
      'data_type' => $r['data_type'], 'is_required' => (bool) $r['is_required'],
      'options' => $r['options'], 'sort_order' => (int) $r['sort_order'],
    ], $rows);
  }

  // device_custody_events egy eszközre, legújabb elöl (store.historyOf).
  public static function history(int $deviceId): array {
    $st = getDB()->prepare(
      'SELECT * FROM device_custody_events WHERE device_id = ? ORDER BY event_timestamp DESC, event_id DESC'
    );
    $st->execute([$deviceId]);
    return $st->fetchAll();
  }

  // Megerősítésre váró visszavételek (store.pendingCheckins).
  // Nyers eseménymezőkkel — ugyanaz a forma, mint az enriched eszköz `pending`
  // mezője (Repo::pendingCheckin), így a frontend egységesen kezeli mindkettőt.
  public static function pendingCheckins(): array {
    return getDB()->query(
      "SELECT event_id, device_id, event_type, actor_user_id, from_user_id, from_locations_id,
              from_departments_id, to_user_id, to_locations_id, to_departments_id,
              event_timestamp, condition_at_event, notes, confirmation_status
       FROM device_custody_events
       WHERE confirmation_status = 'pending' AND event_type = 'check_in'
       ORDER BY event_timestamp"
    )->fetchAll();
  }

  public static function activeReservations(): array {
    return getDB()->query(
      'SELECT reservation_id, device_id, reserved_by, reserved_at, expires_at, notes
       FROM device_reservations WHERE expires_at > NOW()'
    )->fetchAll();
  }
}
