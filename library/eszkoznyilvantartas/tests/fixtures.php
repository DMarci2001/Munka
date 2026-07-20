<?php
// ============================================================
// Teszt-fixture betöltő — a database/seed_dev.php adatkészletének
// újrafelhasználható változata. Minden teszt reseed_fixtures()-t hív
// a setUp()-jában, hogy egy ismert, rögzített-ID-jű állapotból induljon.
//
// Rögzített azonosítók (a teszteknek ezekre kell hivatkozniuk):
//   users:       1,2,5,6 = 'user' (jogosultsag 0); 3 = 'storekeeper' (1); 4 = 'it_admin' (2)
//   locations:   1 = Budapest, 2 = Debrecen
//   departments: 1 = Központi raktár (raktár, loc 1), 2 = Kardiológia (osztály, loc 1),
//                6 = Fiókraktár (raktár, loc 2), 8 = Szerviz/IT (műhely, loc 1)
//   device_types: 1 = Ultrahang, 2 = Laptop, ...
//   devices: lásd a $devices tömböt lent — pl. device 2 (ESZ-0002) szabad/raktárban (Kivehető),
//            device 16 (ESZ-0016) Selejtezve, device 17 (ESZ-0017) Elveszett, device 8 Szerviz alatt.
// ============================================================

function reseed_fixtures(?PDO $db = null): void {
  $db = $db ?? getDB();
  $DEV_PASSWORD = 'jelszo123';

  $ts = fn(int $daysOffset) => date('Y-m-d H:i:s', time() + $daysOffset * 86400);

  $db->exec('SET FOREIGN_KEY_CHECKS = 0');
  foreach ([
    'eszkoznyilvantartas_login_attempts', 'eszkoznyilvantartas_sso_used_tokens',
    'eszkoznyilvantartas_device_reservations', 'eszkoznyilvantartas_device_custody_events', 'eszkoznyilvantartas_device_attribute_values',
    'eszkoznyilvantartas_devices', 'eszkoznyilvantartas_attribute_definitions', 'eszkoznyilvantartas_device_types', 'eszkoznyilvantartas_departments', 'helyszinek', 'users',
  ] as $t) {
    $db->exec("TRUNCATE TABLE `$t`");
  }
  $db->exec('SET FOREIGN_KEY_CHECKS = 1');

  // ---- helyszinek --------------------------------------------
  $locations = [
    [1, '1095 Budapest, Soroksári út 12.'],
    [2, '4032 Debrecen, Nagyerdei krt. 98.'],
  ];
  $ins = $db->prepare('INSERT INTO helyszinek (id, cim) VALUES (?, ?)');
  foreach ($locations as $l) $ins->execute($l);

  // ---- users (jogosultsag: 0=user, 1=storekeeper, 2=it_admin) ----
  $pw = password_hash($DEV_PASSWORD, PASSWORD_BCRYPT);
  $users = [
    [1, 'kovacs.anna',    'Dr. Kovács Anna',    0],
    [2, 'nagy.peter',     'Nagy Péter',         0],
    [3, 'szabo.julia',    'Szabó Júlia',        1],
    [4, 'toth.gabor',     'Tóth Gábor',         2],
    [5, 'horvath.eszter', 'Dr. Horváth Eszter', 0],
    [6, 'kiss.laszlo',    'Dr. Kiss László',    0],
  ];
  $ins = $db->prepare('INSERT INTO users (id, username, nev, jogosultsag, jelszo) VALUES (?, ?, ?, ?, ?)');
  foreach ($users as $u) $ins->execute([$u[0], $u[1], $u[2], $u[3], $pw]);

  // ---- departments -------------------------------------------
  $departments = [
    [1, 1, 'Központi raktár', 'raktár'],
    [2, 1, 'Kardiológia', 'osztály'],
    [3, 1, 'Radiológia', 'osztály'],
    [4, 1, 'Recepció', 'recepció'],
    [5, 1, 'Labor', 'osztály'],
    [6, 2, 'Fiókraktár', 'raktár'],
    [7, 2, 'Belgyógyászat', 'osztály'],
    [8, 1, 'Szerviz / IT', 'műhely'],
  ];
  $ins = $db->prepare('INSERT INTO eszkoznyilvantartas_departments (id, locations_id, name, type) VALUES (?, ?, ?, ?)');
  foreach ($departments as $d) $ins->execute($d);

  // ---- device_types ------------------------------------------
  $deviceTypes = [
    [1, 'Ultrahang', 'Hordozható ultrahang eszköz'],
    [2, 'Laptop', 'Hordozható számítógép'],
    [3, 'Nyomtató', 'Hordozható / asztali nyomtató'],
    [4, 'Router', 'Hálózati router'],
    [5, 'Vérnyomásmérő', 'Digitális vérnyomásmérő'],
    [6, 'EKG', 'Hordozható EKG eszköz'],
    [7, 'USB meghajtó', 'Titkosítható USB tároló'],
  ];
  $ins = $db->prepare('INSERT INTO eszkoznyilvantartas_device_types (id, type, description) VALUES (?, ?, ?)');
  foreach ($deviceTypes as $t) $ins->execute($t);

  // ---- attribute_definitions ---------------------------------
  $attrDefs = [
    [1, 'calibration_due', 'Következő kalibráció', 'date', 1, null, 1],
    [1, 'last_service', 'Utolsó szerviz', 'date', 0, null, 2],
    [1, 'probe_count', 'Szondák száma', 'integer', 0, null, 3],
    [1, 'reg_class', 'Szabályozási osztály', 'enum', 0, 'I,IIa,IIb,III', 4],
    [1, 'software_version', 'Szoftver verzió', 'text', 0, null, 5],
    [2, 'os', 'Operációs rendszer', 'enum', 1, 'Windows 11,Windows 10,macOS,Linux', 1],
    [2, 'cpu', 'CPU', 'text', 0, null, 2],
    [2, 'ram_gb', 'RAM (GB)', 'integer', 0, null, 3],
    [2, 'encrypted', 'Titkosított', 'boolean', 0, null, 4],
    [2, 'domain_joined', 'Tartományhoz csatlakozik', 'boolean', 0, null, 5],
  ];
  $ins = $db->prepare(
    'INSERT INTO eszkoznyilvantartas_attribute_definitions (device_type_id, attribute_key, label, data_type, is_required, options, sort_order)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
  );
  $attrIdByTypeKey = [];
  foreach ($attrDefs as $a) {
    $ins->execute($a);
    $attrIdByTypeKey[$a[0] . ':' . $a[1]] = (int) $db->lastInsertId();
  }

  // ---- devices + attrs + események ---------------------------
  // [id, asset_tag, type, model, manuf, serial, status, condition, notes, attrs, holder, loc, dept, extra]
  $devices = [
    [1, 'ESZ-0001', 1, 'Logiq E10', 'GE Healthcare', 'GE-LE10-77231', 'Kiadva', 'Jó', '', ['calibration_due' => '2026-09-15'], 1, 1, 2, []],
    [2, 'ESZ-0002', 1, 'Vivid iq', 'GE Healthcare', 'GE-VIQ-55102', 'Kivehető', 'Jó', '', ['calibration_due' => '2026-06-20'], null, 1, 1, []],
    [3, 'ESZ-0003', 1, 'Sonosite Edge II', 'Fujifilm', 'FS-EDG2-31188', 'Lefoglalva', 'Jó', 'Kardiológiai kihelyezésre tervezve.', [], null, 2, 1, ['reservedBy' => 1]],
    [4, 'ESZ-0004', 2, 'ThinkPad T14', 'Lenovo', 'LN-T14-902311', 'Kiadva', 'Jó', '', ['os' => 'Windows 11', 'ram_gb' => 16], 2, 1, 4, []],
    [5, 'ESZ-0005', 2, 'ThinkPad T14', 'Lenovo', 'LN-T14-902312', 'Visszavétel folyamatban', 'Jó', '', ['os' => 'Windows 11'], 5, 1, 7, ['pendingReturnTo' => 6]],
    [6, 'ESZ-0006', 2, 'EliteBook 840', 'HP', 'HP-EB840-44120', 'Kivehető', 'Jó', '', [], null, 2, 1, []],
    [7, 'ESZ-0007', 3, 'LaserJet Pro M404', 'HP', 'HP-M404-11023', 'Kiadva', 'Jó', '', [], null, 1, 4, []],
    [8, 'ESZ-0008', 4, 'RB4011', 'MikroTik', 'MT-4011-88210', 'Szerviz alatt', 'Hibás', 'Túlmelegedés, szerviz alatt.', [], null, 2, 8, []],
    [16, 'ESZ-0016', 1, 'Acuson P500', 'Siemens', 'SM-P500-66012', 'Selejtezve', 'Selejt', 'Selejtezve: nem javítható tápegység-hiba.', [], null, 2, 8, ['retired' => true]],
    [17, 'ESZ-0017', 7, 'DataTraveler 32GB', 'Kingston', 'KN-DT32-90050', 'Elveszett', 'Ismeretlen', 'Elveszett bejelentés.', [], null, 1, 4, ['lost' => true]],
  ];

  $insDev = $db->prepare(
    'INSERT INTO eszkoznyilvantartas_devices (device_id, asset_tag, device_type_id, manufacturer, model, serial_number, status, `condition`, notes, created_by, updated_by, retired_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $insAttr = $db->prepare('INSERT INTO eszkoznyilvantartas_device_attribute_values (device_id, attribute_definition_id, value) VALUES (?, ?, ?)');
  $insEv = $db->prepare(
    'INSERT INTO eszkoznyilvantartas_device_custody_events
     (device_id, event_type, actor_user_id, from_user_id, from_locations_id, from_departments_id,
      to_user_id, to_locations_id, to_departments_id, event_timestamp, expected_return_date, condition_at_event, notes, confirmation_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $insResv = $db->prepare('INSERT INTO eszkoznyilvantartas_device_reservations (device_id, reserved_by, reserved_at, expires_at, notes) VALUES (?, ?, ?, ?, ?)');

  foreach ($devices as $d) {
    [$id, $tag, $type, $model, $manuf, $serial, $status, $cond, $notes, $attrs, $holder, $loc, $dept, $extra] = $d;
    $retiredDate = !empty($extra['retired']) ? date('Y-m-d') : null;
    $insDev->execute([$id, $tag, $type, $manuf, $model, $serial, $status, $cond, $notes, 3, 3, $retiredDate]);

    foreach ($attrs as $k => $v) {
      $defId = $attrIdByTypeKey["$type:$k"] ?? null;
      if ($defId === null) continue;
      $store = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
      $insAttr->execute([$id, $defId, $store]);
    }

    // 1) regisztrációs check_in (raktárba/osztályba)
    $insEv->execute([$id, 'check_in', 3, null, null, null, null, $loc, $dept, $ts(-60), null, null, 'Regisztrációs elhelyezés.', 'confirmed']);

    // 2) ha valakinél van: check_out
    if ($holder !== null) {
      $insEv->execute([$id, 'check_out', $holder, null, $loc, $dept, $holder, $loc, $dept, $ts(-20), date('Y-m-d', time() + 10 * 86400), null, null, 'confirmed']);
    }

    // 3) függőben lévő visszavétel (pending check_in)
    if (!empty($extra['pendingReturnTo'])) {
      $insEv->execute([$id, 'check_in', $holder, $holder, $loc, $dept, null, $loc, $extra['pendingReturnTo'], $ts(-1), null, 'Jó', 'Felhasználói leadás, raktáros-ellenőrzésre vár.', 'pending']);
    }

    // 4) aktív foglalás
    if (!empty($extra['reservedBy'])) {
      $insResv->execute([$id, $extra['reservedBy'], $ts(-1), $ts(2), null]);
    }
  }
}

// Bejelentkezett felhasználó szimulálása munkamenet nélkül (Auth::userId()/role() csak $_SESSION-t olvas).
function login_as(int $userId, string $role): void {
  $_SESSION['uid']  = $userId;
  $_SESSION['role'] = $role;
}

function logout_session(): void {
  $_SESSION = [];
}
