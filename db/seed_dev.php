<?php
// ============================================================
// Fejlesztői seed — a frontend seed.js szerveroldali megfelelője.
// A store.bootstrap() logikáját tükrözi: törzsadat + eszközök +
// kezdeti custody-események, hogy a device_current_state nézet
// ugyanazt az állapotot adja, mint a demó.
//
// Futtatás (a séma BETÖLTÉSE UTÁN):
//   C:\xampp\php\php.exe db\seed_dev.php
//
// Minden felhasználó jelszava: "jelszo123"
// ============================================================

require_once __DIR__ . '/../config/database.php';

$DEV_PASSWORD = 'jelszo123';
$db = getDB();

function ts(int $daysOffset): string { return date('Y-m-d H:i:s', time() + $daysOffset * 86400); }

$db->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
  'device_reservations', 'device_custody_events', 'device_attribute_values',
  'devices', 'attribute_definitions', 'device_types', 'departments', 'helyszinek', 'users',
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
$ins = $db->prepare('INSERT INTO departments (id, locations_id, name, type) VALUES (?, ?, ?, ?)');
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
$ins = $db->prepare('INSERT INTO device_types (id, type, description) VALUES (?, ?, ?)');
foreach ($deviceTypes as $t) $ins->execute($t);

// ---- attribute_definitions ---------------------------------
// [device_type_id, key, label, data_type, is_required, options, sort_order]
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
  [3, 'connection', 'Csatlakozás', 'enum', 0, 'USB,Hálózati,WiFi', 1],
  [3, 'toner_model', 'Toner modell', 'text', 0, null, 2],
  [3, 'network_address', 'Hálózati cím', 'text', 0, null, 3],
  [4, 'ip_address', 'IP cím', 'text', 0, null, 1],
  [4, 'mac_address', 'MAC cím', 'text', 0, null, 2],
  [4, 'firmware', 'Firmware verzió', 'text', 0, null, 3],
  [5, 'calibration_due', 'Következő kalibráció', 'date', 1, null, 1],
  [5, 'cuff_size', 'Mandzsetta méret', 'enum', 0, 'Gyermek,Felnőtt,Nagy', 2],
  [6, 'calibration_due', 'Következő kalibráció', 'date', 1, null, 1],
  [6, 'channels', 'Csatornák száma', 'integer', 0, null, 2],
  [6, 'last_service', 'Utolsó szerviz', 'date', 0, null, 3],
  [7, 'capacity_gb', 'Kapacitás (GB)', 'integer', 0, null, 1],
  [7, 'encrypted', 'Titkosított', 'boolean', 0, null, 2],
  [7, 'phi_approved', 'PHI-re engedélyezett', 'boolean', 0, null, 3],
];
$ins = $db->prepare(
  'INSERT INTO attribute_definitions (device_type_id, attribute_key, label, data_type, is_required, options, sort_order)
   VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$attrIdByTypeKey = [];   // "typeId:key" → def id
foreach ($attrDefs as $a) {
  $ins->execute($a);
  $attrIdByTypeKey[$a[0] . ':' . $a[1]] = (int) $db->lastInsertId();
}

// ---- devices + attrs + események ---------------------------
// [id, asset_tag, type, model, manuf, serial, status, condition, notes, attrs, holder, loc, dept, extra]
$devices = [
  [1, 'ESZ-0001', 1, 'Logiq E10', 'GE Healthcare', 'GE-LE10-77231', 'Kiadva', 'Jó', '', ['calibration_due' => '2026-09-15', 'last_service' => '2026-03-10', 'probe_count' => 3, 'reg_class' => 'IIb', 'software_version' => 'R2.4.1'], 1, 1, 2, []],
  [2, 'ESZ-0002', 1, 'Vivid iq', 'GE Healthcare', 'GE-VIQ-55102', 'Kivehető', 'Jó', '', ['calibration_due' => '2026-06-20', 'probe_count' => 2, 'reg_class' => 'IIa', 'software_version' => 'R1.8'], null, 1, 1, []],
  [3, 'ESZ-0003', 1, 'Sonosite Edge II', 'Fujifilm', 'FS-EDG2-31188', 'Lefoglalva', 'Jó', 'Kardiológiai kihelyezésre tervezve.', ['calibration_due' => '2026-05-28', 'last_service' => '2026-02-01', 'probe_count' => 4, 'reg_class' => 'IIb', 'software_version' => 'v3.2'], null, 2, 1, ['reservedBy' => 1]],
  [4, 'ESZ-0004', 2, 'ThinkPad T14', 'Lenovo', 'LN-T14-902311', 'Kiadva', 'Jó', '', ['os' => 'Windows 11', 'cpu' => 'Intel i5-1335U', 'ram_gb' => 16, 'encrypted' => true, 'domain_joined' => true], 2, 1, 4, []],
  [5, 'ESZ-0005', 2, 'ThinkPad T14', 'Lenovo', 'LN-T14-902312', 'Visszavétel folyamatban', 'Jó', '', ['os' => 'Windows 11', 'cpu' => 'Intel i5-1335U', 'ram_gb' => 16, 'encrypted' => true, 'domain_joined' => true], 5, 1, 7, ['pendingReturnTo' => 6]],
  [6, 'ESZ-0006', 2, 'EliteBook 840', 'HP', 'HP-EB840-44120', 'Kivehető', 'Jó', '', ['os' => 'Windows 10', 'cpu' => 'Intel i7-1165G7', 'ram_gb' => 32, 'encrypted' => true, 'domain_joined' => false], null, 2, 1, []],
  [7, 'ESZ-0007', 3, 'LaserJet Pro M404', 'HP', 'HP-M404-11023', 'Kiadva', 'Jó', '', ['connection' => 'Hálózati', 'toner_model' => 'CF259A', 'network_address' => '10.0.4.21'], null, 1, 4, []],
  [8, 'ESZ-0008', 4, 'RB4011', 'MikroTik', 'MT-4011-88210', 'Szerviz alatt', 'Hibás', 'Túlmelegedés, szerviz alatt.', ['ip_address' => '10.0.0.1', 'mac_address' => '64:D1:54:AA:BB:01', 'firmware' => '7.13.2'], null, 2, 8, []],
  [9, 'ESZ-0009', 5, 'M3 Comfort', 'Omron', 'OM-M3-77001', 'Kivehető', 'Jó', '', ['calibration_due' => '2026-07-30', 'cuff_size' => 'Felnőtt'], null, 1, 1, []],
  [10, 'ESZ-0010', 5, 'M6 Comfort', 'Omron', 'OM-M6-77044', 'Kiadva', 'Jó', '', ['calibration_due' => '2026-04-15', 'cuff_size' => 'Nagy'], 1, 1, 2, []],
  [11, 'ESZ-0011', 6, 'PageWriter TC30', 'Philips', 'PH-TC30-22115', 'Lefoglalva', 'Jó', '', ['calibration_due' => '2026-08-10', 'channels' => 12, 'last_service' => '2026-01-20'], null, 1, 1, ['reservedBy' => 5]],
  [12, 'ESZ-0012', 6, 'CardioPerfect', 'Welch Allyn', 'WA-CP-33010', 'Kiadva', 'Jó', '', ['calibration_due' => '2026-06-09', 'channels' => 12, 'last_service' => '2025-12-05'], 6, 2, 3, []],
  [13, 'ESZ-0013', 7, 'DataTraveler 64GB', 'Kingston', 'KN-DT64-90021', 'Kivehető', 'Jó', '', ['capacity_gb' => 64, 'encrypted' => true, 'phi_approved' => true], null, 2, 1, []],
  [14, 'ESZ-0014', 2, 'Latitude 5440', 'Dell', 'DL-L5440-12001', 'Kivehető', 'Jó', '', ['os' => 'Windows 11', 'cpu' => 'Intel i5-1345U', 'ram_gb' => 16, 'encrypted' => true, 'domain_joined' => true], null, 1, 1, []],
  [15, 'ESZ-0015', 5, 'M3 Comfort', 'Omron', 'OM-M3-77099', 'Kiadva', 'Jó', '', ['calibration_due' => '2026-10-01', 'cuff_size' => 'Felnőtt'], null, 2, 6, []],
  [16, 'ESZ-0016', 1, 'Acuson P500', 'Siemens', 'SM-P500-66012', 'Selejtezve', 'Selejt', 'Selejtezve: nem javítható tápegység-hiba.', ['calibration_due' => '2025-03-01', 'probe_count' => 2, 'reg_class' => 'IIa', 'software_version' => 'v2.0'], null, 2, 8, ['retired' => true]],
  [17, 'ESZ-0017', 7, 'DataTraveler 32GB', 'Kingston', 'KN-DT32-90050', 'Elveszett', 'Ismeretlen', 'Elveszett bejelentés 2026-05-20.', ['capacity_gb' => 32, 'encrypted' => false, 'phi_approved' => false], null, 1, 4, ['lost' => true]],
  [18, 'ESZ-0018', 3, 'OfficeJet 250', 'HP', 'HP-OJ250-55001', 'Kivehető', 'Jó', 'Hordozható, mobil rendelésekhez.', ['connection' => 'WiFi', 'toner_model' => 'CZ101AE', 'network_address' => '—'], null, 1, 1, []],
];

$insDev = $db->prepare(
  'INSERT INTO devices (device_id, asset_tag, device_type_id, manufacturer, model, serial_number, status, `condition`, notes, created_by, updated_by, retired_date)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insAttr = $db->prepare('INSERT INTO device_attribute_values (device_id, attribute_definition_id, value) VALUES (?, ?, ?)');
$insEv = $db->prepare(
  'INSERT INTO device_custody_events
   (device_id, event_type, actor_user_id, from_user_id, from_locations_id, from_departments_id,
    to_user_id, to_locations_id, to_departments_id, event_timestamp, expected_return_date, condition_at_event, notes, confirmation_status)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insResv = $db->prepare('INSERT INTO device_reservations (device_id, reserved_by, reserved_at, expires_at, notes) VALUES (?, ?, ?, ?, ?)');

foreach ($devices as $d) {
  [$id, $tag, $type, $model, $manuf, $serial, $status, $cond, $notes, $attrs, $holder, $loc, $dept, $extra] = $d;
  $retiredDate = !empty($extra['retired']) ? date('Y-m-d') : null;
  $insDev->execute([$id, $tag, $type, $manuf, $model, $serial, $status, $cond, $notes, 3, 3, $retiredDate]);

  // attribútumok normalizálva
  foreach ($attrs as $k => $v) {
    $defId = $attrIdByTypeKey["$type:$k"] ?? null;
    if ($defId === null) continue;
    $store = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
    $insAttr->execute([$id, $defId, $store]);
  }

  // 1) regisztrációs check_in (raktárba/osztályba)
  $insEv->execute([$id, 'check_in', 3, null, null, null, null, $loc, $dept, ts(-60), null, null, 'Regisztrációs elhelyezés.', 'confirmed']);

  // 2) ha valakinél van: check_out
  if ($holder !== null) {
    $insEv->execute([$id, 'check_out', $holder, null, $loc, $dept, $holder, $loc, $dept, ts(-20), date('Y-m-d', time() + 10 * 86400), null, null, 'confirmed']);
  }

  // 3) függőben lévő visszavétel (pending check_in)
  if (!empty($extra['pendingReturnTo'])) {
    $insEv->execute([$id, 'check_in', $holder, $holder, $loc, $dept, null, $loc, $extra['pendingReturnTo'], ts(-1), null, 'Jó', 'Felhasználói leadás, raktáros-ellenőrzésre vár.', 'pending']);
  }

  // 4) aktív foglalás
  if (!empty($extra['reservedBy'])) {
    $insResv->execute([$id, $extra['reservedBy'], ts(-1), ts(2), null]);
  }
}

echo "Seed kész: " . count($devices) . " eszköz, " . count($users) . " felhasználó.\n";
echo "Bejelentkezés: bármely username + jelszó: '$DEV_PASSWORD'\n";
