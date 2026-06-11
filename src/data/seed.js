// ============================================================
// Mintaadatok (mock seed) — Hordozható Eszköznyilvántartás
// Hungária Med-M Kft.
//
// Ez egy demonstrációs adathalmaz: memóriában él, nincs backend.
// A szerkezet a PostgreSQL sémát követi (schema.sql + migrációk).
// ============================================================

// ---- Helyszínek -----------------------------------

export const locations = [
  { id: 1, address: '1095 Budapest, Soroksári út 12.' },
  { id: 2, address: '4032 Debrecen, Nagyerdei krt. 98.' },
];

// ---- Helyiségek (custody „hely" = department) -------
export const departments = [
  { id: 1, locations_id: 1, name: 'Központi raktár', type: 'raktár' },
  { id: 2, locations_id: 1, name: 'Kardiológia', type: 'osztály' },
  { id: 3, locations_id: 1, name: 'Radiológia', type: 'osztály' },
  { id: 4, locations_id: 1, name: 'Recepció', type: 'recepció' },
  { id: 5, locations_id: 1, name: 'Labor', type: 'osztály' },
  { id: 6, locations_id: 2, name: 'Fiókraktár', type: 'raktár' },
  { id: 7, locations_id: 2, name: 'Belgyógyászat', type: 'osztály' },
  { id: 8, locations_id: 1, name: 'Szerviz / IT', type: 'műhely' },
];

// ---- Felhasználók (külső users tábla — a klinikai webalkalmazásé) ----
// auth: user | storekeeper | it_admin
export const users = [
  { id: 1, username: 'kovacs.anna',    full_name: 'Dr. Kovács Anna',    auth: 'user',        title: 'Kardiológus' },
  { id: 2, username: 'nagy.peter',     full_name: 'Nagy Péter',         auth: 'user',        title: 'Recepciós' },
  { id: 3, username: 'szabo.julia',    full_name: 'Szabó Júlia',        auth: 'storekeeper', title: 'Raktáros' },
  { id: 4, username: 'toth.gabor',     full_name: 'Tóth Gábor',         auth: 'it_admin',    title: 'IT-admin' },
  { id: 5, username: 'horvath.eszter', full_name: 'Dr. Horváth Eszter', auth: 'user',        title: 'Belgyógyász' },
  { id: 6, username: 'kiss.laszlo',    full_name: 'Dr. Kiss László',    auth: 'user',        title: 'Radiológus' },
];

// ---- Eszköztípusok ------------------------------------------
export const deviceTypes = [
  { id: 1, type: 'Ultrahang', description: 'Hordozható ultrahang eszköz' },
  { id: 2, type: 'Laptop', description: 'Hordozható számítógép' },
  { id: 3, type: 'Nyomtató', description: 'Hordozható / asztali nyomtató' },
  { id: 4, type: 'Router', description: 'Hálózati router' },
  { id: 5, type: 'Vérnyomásmérő', description: 'Digitális vérnyomásmérő' },
  { id: 6, type: 'EKG', description: 'Hordozható EKG eszköz' },
  { id: 7, type: 'USB meghajtó', description: 'Titkosítható USB tároló' },
];

// ---- Attribútum-definíciók (típusonkénti dinamikus mezők) ----
// data_type: text | integer | decimal | date | boolean | enum
let _adId = 0;
const ad = (device_type_id, attribute_key, label, data_type, is_required = false, options = null, sort_order = 0) =>
  ({ id: ++_adId, device_type_id, attribute_key, label, data_type, is_required, options, sort_order });

export const attributeDefinitions = [
  // Ultrahang
  ad(1, 'calibration_due', 'Következő kalibráció', 'date', true, null, 1),
  ad(1, 'last_service', 'Utolsó szerviz', 'date', false, null, 2),
  ad(1, 'probe_count', 'Szondák száma', 'integer', false, null, 3),
  ad(1, 'reg_class', 'Szabályozási osztály', 'enum', false, 'I,IIa,IIb,III', 4),
  ad(1, 'software_version', 'Szoftver verzió', 'text', false, null, 5),
  // Laptop
  ad(2, 'os', 'Operációs rendszer', 'enum', true, 'Windows 11,Windows 10,macOS,Linux', 1),
  ad(2, 'cpu', 'CPU', 'text', false, null, 2),
  ad(2, 'ram_gb', 'RAM (GB)', 'integer', false, null, 3),
  ad(2, 'encrypted', 'Titkosított', 'boolean', false, null, 4),
  ad(2, 'domain_joined', 'Tartományhoz csatlakozik', 'boolean', false, null, 5),
  // Nyomtató
  ad(3, 'connection', 'Csatlakozás', 'enum', false, 'USB,Hálózati,WiFi', 1),
  ad(3, 'toner_model', 'Toner modell', 'text', false, null, 2),
  ad(3, 'network_address', 'Hálózati cím', 'text', false, null, 3),
  // Router
  ad(4, 'ip_address', 'IP cím', 'text', false, null, 1),
  ad(4, 'mac_address', 'MAC cím', 'text', false, null, 2),
  ad(4, 'firmware', 'Firmware verzió', 'text', false, null, 3),
  // Vérnyomásmérő
  ad(5, 'calibration_due', 'Következő kalibráció', 'date', true, null, 1),
  ad(5, 'cuff_size', 'Mandzsetta méret', 'enum', false, 'Gyermek,Felnőtt,Nagy', 2),
  // EKG
  ad(6, 'calibration_due', 'Következő kalibráció', 'date', true, null, 1),
  ad(6, 'channels', 'Csatornák száma', 'integer', false, null, 2),
  ad(6, 'last_service', 'Utolsó szerviz', 'date', false, null, 3),
  // USB meghajtó
  ad(7, 'capacity_gb', 'Kapacitás (GB)', 'integer', false, null, 1),
  ad(7, 'encrypted', 'Titkosított', 'boolean', false, null, 2),
  ad(7, 'phi_approved', 'PHI-re engedélyezett', 'boolean', false, null, 3),
];

// ---- Eszközök -----------------------------------------------
// status: Kiadva | Visszavétel folyamatban | Ready to deploy | Lefoglalva |
//         In repair | Lost | Retired
//
// A `_holder` / `_dept` mezők csak a kezdeti custody-események
// legenerálásához kellenek (lásd lentebb); a tényleges aktuális
// állapotot a store a device_current_state nézetből vezeti le.
export const devices = [
  {
    device_id: 1, device_type_id: 1,
    model: 'Logiq E10', manufacturer: 'GE Healthcare', serial_number: 'GE-LE10-77231',
    status: 'Kiadva', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-09-15', last_service: '2026-03-10', probe_count: 3, reg_class: 'IIb', software_version: 'R2.4.1' },
    _holder: 1, _loc: 1, _dept: 2,
  },
  {
    device_id: 2, device_type_id: 1,
    model: 'Vivid iq', manufacturer: 'GE Healthcare', serial_number: 'GE-VIQ-55102',
    status: 'Kivehető', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-06-20', probe_count: 2, reg_class: 'IIa', software_version: 'R1.8' },
    _holder: null, _loc: 1, _dept: 1,
  },
  {
    device_id: 3, device_type_id: 1,
    model: 'Sonosite Edge II', manufacturer: 'Fujifilm', serial_number: 'FS-EDG2-31188',
    status: 'Lefoglalva', condition: 'Jó', notes: 'Kardiológiai kihelyezésre tervezve.',
    attrs: { calibration_due: '2026-05-28', last_service: '2026-02-01', probe_count: 4, reg_class: 'IIb', software_version: 'v3.2' },
    _holder: null, _loc: 2, _dept: 1, _reservedBy: 1,
  },
  {
    device_id: 4, device_type_id: 2,
    model: 'ThinkPad T14', manufacturer: 'Lenovo', serial_number: 'LN-T14-902311',
    status: 'Kiadva', condition: 'Jó', notes: '',
    attrs: { os: 'Windows 11', cpu: 'Intel i5-1335U', ram_gb: 16, encrypted: true, domain_joined: true },
    _holder: 2, _loc: 1, _dept: 4,
  },
  {
    device_id: 5, device_type_id: 2,
    model: 'ThinkPad T14', manufacturer: 'Lenovo', serial_number: 'LN-T14-902312',
    status: 'Visszavétel folyamatban', condition: 'Jó', notes: '',
    attrs: { os: 'Windows 11', cpu: 'Intel i5-1335U', ram_gb: 16, encrypted: true, domain_joined: true },
    _holder: 5, _loc: 1, _dept: 7, _pendingReturnTo: 6,
  },
  {
    device_id: 6, device_type_id: 2,
    model: 'EliteBook 840', manufacturer: 'HP', serial_number: 'HP-EB840-44120',
    status: 'Kivehető', condition: 'Jó', notes: '',
    attrs: { os: 'Windows 10', cpu: 'Intel i7-1165G7', ram_gb: 32, encrypted: true, domain_joined: false },
    _holder: null, _loc: 2, _dept: 1,
  },
  {
    device_id: 7, device_type_id: 3,
    model: 'LaserJet Pro M404', manufacturer: 'HP', serial_number: 'HP-M404-11023',
    status: 'Kiadva', condition: 'Jó', notes: '',
    attrs: { connection: 'Hálózati', toner_model: 'CF259A', network_address: '10.0.4.21' },
    _holder: null, _loc: 1, _dept: 4,
  },
  {
    device_id: 8, device_type_id: 4,
    model: 'RB4011', manufacturer: 'MikroTik', serial_number: 'MT-4011-88210',
    status: 'Szerviz alatt', condition: 'Hibás', notes: 'Túlmelegedés, szerviz alatt.',
    attrs: { ip_address: '10.0.0.1', mac_address: '64:D1:54:AA:BB:01', firmware: '7.13.2' },
    _holder: null, _loc: 2, _dept: 8,
  },
  {
    device_id: 9, device_type_id: 5,
    model: 'M3 Comfort', manufacturer: 'Omron', serial_number: 'OM-M3-77001',
    status: 'Kivehető', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-07-30', cuff_size: 'Felnőtt' },
    _holder: null, _loc: 1, _dept: 1,
  },
  {
    device_id: 10, device_type_id: 5,
    model: 'M6 Comfort', manufacturer: 'Omron', serial_number: 'OM-M6-77044',
    status: 'Kiadva', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-04-15', cuff_size: 'Nagy' },
    _holder: 1, _loc: 1, _dept: 2,
  },
  {
    device_id: 11, device_type_id: 6,
    model: 'PageWriter TC30', manufacturer: 'Philips', serial_number: 'PH-TC30-22115',
    status: 'Lefoglalva', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-08-10', channels: 12, last_service: '2026-01-20' },
    _holder: null, _loc: 1, _dept: 1, _reservedBy: 5,
  },
  {
    device_id: 12, device_type_id: 6,
    model: 'CardioPerfect', manufacturer: 'Welch Allyn', serial_number: 'WA-CP-33010',
    status: 'Kiadva', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-06-09', channels: 12, last_service: '2025-12-05' },
    _holder: 6, _loc: 2, _dept: 3,
  },
  {
    device_id: 13, device_type_id: 7,
    model: 'DataTraveler 64GB', manufacturer: 'Kingston', serial_number: 'KN-DT64-90021',
    status: 'Kivehető', condition: 'Jó', notes: '',
    attrs: { capacity_gb: 64, encrypted: true, phi_approved: true },
    _holder: null, _loc: 2, _dept: 1,
  },
  {
    device_id: 14, device_type_id: 2,
    model: 'Latitude 5440', manufacturer: 'Dell', serial_number: 'DL-L5440-12001',
    status: 'Kivehető', condition: 'Jó', notes: '',
    attrs: { os: 'Windows 11', cpu: 'Intel i5-1345U', ram_gb: 16, encrypted: true, domain_joined: true },
    _holder: null, _loc: 1
  },
  {
    device_id: 15, device_type_id: 5,
    model: 'M3 Comfort', manufacturer: 'Omron', serial_number: 'OM-M3-77099',
    status: 'Kiadva', condition: 'Jó', notes: '',
    attrs: { calibration_due: '2026-10-01', cuff_size: 'Felnőtt' },
    _holder: null, _loc: 2
  },
  {
    device_id: 16, device_type_id: 1,
    model: 'Acuson P500', manufacturer: 'Siemens', serial_number: 'SM-P500-66012',
    status: 'Selejtezve', condition: 'Selejt', notes: 'Selejtezve: nem javítható tápegység-hiba.',
    attrs: { calibration_due: '2025-03-01', probe_count: 2, reg_class: 'IIa', software_version: 'v2.0' },
    _holder: null, _loc: 2, _dept: 8, _retired: true,
  },
  {
    device_id: 17, device_type_id: 7,
    model: 'DataTraveler 32GB', manufacturer: 'Kingston', serial_number: 'KN-DT32-90050',
    status: 'Elveszett', condition: 'Ismeretlen', notes: 'Elveszett bejelentés 2026-05-20.',
    attrs: { capacity_gb: 32, encrypted: false, phi_approved: false },
    _holder: null, _loc: 1, _dept: 4, _lost: true,
  },
  {
    device_id: 18, device_type_id: 3,
    model: 'OfficeJet 250', manufacturer: 'HP', serial_number: 'HP-OJ250-55001',
    status: 'Kivehető', condition: 'Jó', notes: 'Hordozható, mobil rendelésekhez.',
    attrs: { connection: 'WiFi', toner_model: 'CZ101AE', network_address: '—' },
    _holder: null, _loc: 1, _dept: 1,
  },
];

// ---- Aktív foglalások ---------------------------------------
// A reserve műveletet a store kezeli; itt a kezdeti aktív
// foglalásokat a devices._reservedBy mezőből generáljuk.
