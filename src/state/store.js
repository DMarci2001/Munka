// ============================================================
// Állapotkezelő (store) — memóriában futó demó
//
// Egyetlen igazságforrás a custody-eseménynapló (append-only):
// az aktuális birtokos/hely MINDIG a legújabb megerősített
// eseményből származik (device_current_state nézet logikája).
// A devices.status karbantartása a SCRIPT_LOGIC.md §7 szerint.
// ============================================================

import {
  locations, departments, users,
  deviceTypes, attributeDefinitions, devices as seedDevices,
} from '../data/seed.js';

// ---- Belső állapot ------------------------------------------
const state = {
  locations: structuredClone(locations),
  departments: structuredClone(departments),
  users: structuredClone(users),
  deviceTypes: structuredClone(deviceTypes),
  attributeDefinitions: structuredClone(attributeDefinitions),
  devices: [],            // tiszta eszközsorok (status, közös mezők, attrs)
  events: [],             // device_custody_events
  reservations: [],       // device_reservations (csak aktív)
  currentUserId: 1,       // bejelentkezett felhasználó (demó: váltható)
};

let _eventId = 0;
let _resvId = 0;
let _deviceId = 0;
const subscribers = new Set();

// ---- Megőrzés (localStorage) --------------------------------
const STORAGE_KEY = 'eszkoznyilvantarto_state_v2';
const ISO_RE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/;

function persist() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      locations: state.locations, departments: state.departments, users: state.users,
      deviceTypes: state.deviceTypes, attributeDefinitions: state.attributeDefinitions,
      devices: state.devices, events: state.events, reservations: state.reservations,
      currentUserId: state.currentUserId, _eventId, _resvId, _deviceId,
    }));
  } catch (e) { /* localStorage nem elérhető / megtelt */ }
}

// Régi → új státusz-elnevezések migrációja a korábban mentett adatokhoz.
const STATUS_MIGRATIONS = {
  'Javítás alatt': 'Szerviz alatt',
};

function migrateStatuses(data) {
  let changed = false;
  for (const dev of data.devices || []) {
    const next = STATUS_MIGRATIONS[dev.status];
    if (next) { dev.status = next; changed = true; }
  }
  return changed;
}

function loadPersisted() {
  try {
    const raw = typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY);
    if (!raw) return false;
    // ISO dátum-stringeket visszaalakítjuk Date objektummá
    const data = JSON.parse(raw, (k, v) => (typeof v === 'string' && ISO_RE.test(v) ? new Date(v) : v));
    const migrated = migrateStatuses(data);
    Object.assign(state, {
      locations: data.locations, departments: data.departments, users: data.users,
      deviceTypes: data.deviceTypes, attributeDefinitions: data.attributeDefinitions,
      devices: data.devices, events: data.events, reservations: data.reservations,
      currentUserId: data.currentUserId ?? state.currentUserId,
    });
    _eventId = data._eventId || 0;
    _resvId = data._resvId || 0;
    _deviceId = data._deviceId || 0;
    if (migrated) persist();
    return true;
  } catch (e) { return false; }
}

// ---- Idő segéd ----------------------------------------------
const now = () => new Date();
const daysAgo = (d) => new Date(Date.now() - d * 86400000);
const daysFromNow = (d) => new Date(Date.now() + d * 86400000);

// ---- Kezdeti események legenerálása a seedből ---------------
function bootstrap() {
  for (const d of seedDevices) {
    const { _holder, _loc, _dept, _pendingReturnTo, _reservedBy, _retired, _lost, attrs, ...rest } = d;
    state.devices.push({ ...rest, attrs: { ...attrs } });
    if (d.device_id > _deviceId) _deviceId = d.device_id;

    // 1) kezdeti elhelyezés a raktárba/osztályba (regisztrációs check_in)
    pushEvent({
      device_id: d.device_id, event_type: 'check_in', actor_user_id: 3,
      from_user_id: null, from_locations_id: null, from_departments_id: null,
      to_user_id: null, to_locations_id: _loc, to_departments_id: _dept,
      event_timestamp: daysAgo(60), notes: 'Regisztrációs elhelyezés.',
      confirmation_status: 'confirmed',
    });

    // 2) ha valakinél van (deployed vagy pending return előtti deploy)
    if (_holder) {
      pushEvent({
        device_id: d.device_id, event_type: 'check_out', actor_user_id: _holder,
        from_user_id: null, from_locations_id: _loc, from_departments_id: _dept,
        to_user_id: _holder, to_locations_id: _loc, to_departments_id: _dept,
        event_timestamp: daysAgo(20), expected_return_date: daysFromNow(10),
        confirmation_status: 'confirmed',
      });
    }

    // 3) függőben lévő visszavétel (pending check_in) — nem érvényesül
    if (_pendingReturnTo) {
      pushEvent({
        device_id: d.device_id, event_type: 'check_in', actor_user_id: _holder,
        from_user_id: _holder, from_locations_id: _loc, from_departments_id: _dept,
        to_user_id: null, to_locations_id: _loc, to_departments_id: _pendingReturnTo,
        event_timestamp: daysAgo(1), condition_at_event: 'Jó',
        notes: 'Felhasználói leadás, raktáros-ellenőrzésre vár.',
        confirmation_status: 'pending',
      });
    }

    // 4) aktív foglalás
    if (_reservedBy) {
      state.reservations.push({
        reservation_id: ++_resvId, device_id: d.device_id, reserved_by: _reservedBy,
        reserved_at: daysAgo(1), expires_at: daysFromNow(2), notes: null,
      });
    }
  }
}

function pushEvent(ev) {
  state.events.push({
    event_id: ++_eventId,
    expected_return_date: null,
    condition_at_event: null,
    notes: null,
    confirmed_by: null,
    confirmed_at: null,
    ...ev,
  });
}

// ---- Lookups -------------------------------------------------
export const getUsers = () => state.users;
export const getUser = (id) => state.users.find((u) => u.id === id) || null;
export const getDepartments = () => state.departments;
export const getDepartment = (id) => state.departments.find((d) => d.id === id) || null;
export const isStorageDept = (id) => getDepartment(id)?.type === 'raktár';
export const getLocations = () => state.locations;
export const getLocation = (id) => state.locations.find((s) => s.id === id) || null;
export const getDeviceTypes = () => state.deviceTypes;
export const getDeviceType = (id) => state.deviceTypes.find((t) => t.id === id) || null;
export const getDevices = () => state.devices;
export const getDevice = (id) => state.devices.find((d) => d.device_id === id) || null;
export const getEvents = () => state.events;
export const getAttrDefs = (typeId) =>
  state.attributeDefinitions
    .filter((a) => a.device_type_id === typeId || a.device_type_id === null)
    .sort((a, b) => a.sort_order - b.sort_order);

// ---- Aktuális felhasználó / szerepkör ------------------------
export const currentUser = () => getUser(state.currentUserId);
export const currentRole = () => currentUser()?.auth || 'user';
export function setCurrentUser(id) {
  state.currentUserId = id;
  notify();
}
const roleRank = { user: 1, storekeeper: 2, it_admin: 3 };
export const roleAtLeast = (role, min) => (roleRank[role] || 1) >= (roleRank[min] || 1);

// ---- Nézet: device_current_state ----------------------------
// Eszközönként a legújabb MEGERŐSÍTETT esemény to-oldala.
export function currentState(deviceId) {
  const confirmed = state.events
    .filter((e) => e.device_id === deviceId && e.confirmation_status === 'confirmed')
    .sort((a, b) => (b.event_timestamp - a.event_timestamp) || (b.event_id - a.event_id));
  const latest = confirmed[0];
  if (!latest) return { holder: null, location: null, department: null, since: null };
  const department = latest.to_departments_id;
  // Raktár sosem birtokos: ha raktárban van, nincs birtokosa.
  const holder = isStorageDept(department) ? null : latest.to_user_id;
  return {
    holder,
    location: latest.to_locations_id,
    department,
    since: latest.event_timestamp,
  };
}

// ---- Nézet: device_pending_checkins -------------------------
export function pendingCheckins() {
  return state.events
    .filter((e) => e.confirmation_status === 'pending' && e.event_type === 'check_in')
    .sort((a, b) => a.event_timestamp - b.event_timestamp);
}
export const pendingCheckinFor = (deviceId) =>
  state.events.find(
    (e) => e.device_id === deviceId && e.confirmation_status === 'pending' && e.event_type === 'check_in'
  ) || null;

// ---- Nézet: device_active_reservations ----------------------
export function activeReservation(deviceId) {
  return state.reservations.find((r) => r.device_id === deviceId && r.expires_at > now()) || null;
}
export const activeReservations = () => state.reservations.filter((r) => r.expires_at > now());

// ---- Custody-előzmény eszközönként --------------------------
export const historyOf = (deviceId) =>
  state.events
    .filter((e) => e.device_id === deviceId)
    .sort((a, b) => (b.event_timestamp - a.event_timestamp) || (b.event_id - a.event_id));

// ---- Hibatípus ----------------------------------------------
export class OpError extends Error {}

// ============================================================
// Műveletek
// ============================================================

// move_asset — minden birtoklási mozgás egyetlen kapun megy át
export function moveAsset({
  device_id, event_type, to_user_id = null, to_locations_id = null, to_departments_id = null,
  expected_return_date = null, condition_at_event = null, notes = null,
}) {
  const actor = state.currentUserId;
  const role = currentRole();
  const cur = currentState(device_id);
  const from_user_id = cur.holder;
  const from_locations_id = cur.location;
  const from_departments_id = cur.department;

  // engedélyezés
  if (role === 'user') {
    const freeInStock = cur.holder === null && (cur.department !== null || cur.location !== null);
    const heldByActor = cur.holder === actor;
    if (event_type === 'check_out') {
      if (!(freeInStock && to_user_id === actor))
        throw new OpError('Felhasználóként csak szabad eszközt vehet ki, és csak magának.');
    } else if (event_type === 'check_in' || event_type === 'transfer') {
      if (!heldByActor)
        throw new OpError('Felhasználóként csak a nálad lévő eszközt mozgathatod.');
    } else {
      throw new OpError('Ehhez a művelethez raktáros vagy IT-admin szerepkör kell.');
    }
  }

  // cél ellenőrzése
  if (to_user_id === null && to_departments_id === null && to_locations_id === null)
    throw new OpError('Cél (személy vagy osztály) megadása kötelező.');
  if (event_type === 'stock_transfer' && !(to_departments_id !== null && to_user_id === null && to_locations_id !== null))
    throw new OpError('Raktármozgatáshoz cél-osztály kell, személy nélkül.');
  if (event_type === 'check_in' && to_departments_id === null && to_locations_id === null)
    throw new OpError('Visszavételhez cél-osztály kell.');

  // foglalás-interplay a check_out ágban
  if (event_type === 'check_out') {
    const resv = activeReservation(device_id);
    if (resv && !(resv.reserved_by === actor || roleAtLeast(role, 'storekeeper')))
      throw new OpError('Az eszköz másnak van fenntartva (foglalás).');
  }

  // pending check_in: csak egy nyitott lehet
  if (event_type === 'check_in' && role === 'user' && pendingCheckinFor(device_id))
    throw new OpError('Erre az eszközre már van megerősítésre váró visszavétel.');

  // checkpoint
  // Raktár sosem birtokos: ha a cél raktár-részleg, az eszköz a készletbe kerül
  // (nincs birtokos), így továbbra is kivehető marad.
  const toStorage = isStorageDept(to_departments_id);
  if (toStorage) to_user_id = null;

  // checkpoint
  const confirmation = event_type === 'check_in' && role === 'user' ? 'pending' : 'confirmed';

  pushEvent({
    device_id, event_type, actor_user_id: actor,
    from_user_id, from_locations_id, from_departments_id, to_user_id, to_locations_id, to_departments_id,
    event_timestamp: now(), expected_return_date, condition_at_event, notes,
    confirmation_status: confirmation,
  });

  // foglalás teljesült
  if (event_type === 'check_out') deleteReservation(device_id);

  // státusz
  const dev = getDevice(device_id);
  if (confirmation === 'pending') {
    dev.status = 'Visszavétel folyamatban';
  } else if (toStorage) {
    dev.status = 'Kivehető';
  } else {
    dev.status = statusFromEvent(event_type);
  }
  touch(dev);
  notify();
}

function statusFromEvent(eventType) {
  switch (eventType) {
    case 'check_out': return 'Kiadva';
    case 'check_in': return 'Kivehető';
    case 'transfer': return 'Kiadva';
    case 'stock_transfer': return 'Kivehető';
    case 'send_to_repair': return 'Szerviz alatt';
    case 'mark_lost': return 'Elveszett';
    case 'mark_found': return 'Kivehető';
    default: return 'Kivehető';
  }
}

// confirm_check_in — storekeeper / it_admin
export function confirmCheckIn(event_id) {
  requireStorekeeper();
  const ev = state.events.find((e) => e.event_id === event_id);
  if (!ev || ev.confirmation_status !== 'pending' || ev.event_type !== 'check_in')
    throw new OpError('Csak függőben lévő visszavétel erősíthető meg.');
  ev.confirmation_status = 'confirmed';
  ev.confirmed_by = state.currentUserId;
  ev.confirmed_at = now();
  const dev = getDevice(ev.device_id);
  dev.status = 'Kivehető'; // check_in után mindig kivehető lesz, függetlenül a check_in céljától
  touch(dev);
  notify();
}

// reject_check_in — storekeeper / it_admin
export function rejectCheckIn(event_id, reason) {
  requireStorekeeper();
  const ev = state.events.find((e) => e.event_id === event_id);
  if (!ev || ev.confirmation_status !== 'pending' || ev.event_type !== 'check_in')
    throw new OpError('Csak függőben lévő visszavétel utasítható el.');
  ev.confirmation_status = 'rejected';
  ev.confirmed_by = state.currentUserId;
  ev.confirmed_at = now();
  ev.notes = (ev.notes ? ev.notes + ' ' : '') + `ELUTASÍTVA: ${reason || 'nincs indok'}`;
  const dev = getDevice(ev.device_id);
  dev.status = 'Kiadva'; // visszaáll a check_in előtti birtoklásra
  touch(dev);
  notify();
}

// reserve_device — bármely bejelentkezett felhasználó
export function reserveDevice(device_id, notes = null) {
  const actor = state.currentUserId;
  // lejárt sor takarítása az eszközre
  state.reservations = state.reservations.filter(
    (r) => !(r.device_id === device_id && r.expires_at <= now())
  );
  const cur = currentState(device_id);
  const dev = getDevice(device_id);
  if (!(cur.holder === null && (cur.department !== null || cur.location !== null) && dev.status === 'Kivehető'))
    throw new OpError('Csak szabad, raktárban lévő eszköz foglalható.');
  if (activeReservation(device_id))
    throw new OpError('Az eszköz már le van foglalva.');
  state.reservations.push({
    reservation_id: ++_resvId, device_id, reserved_by: actor,
    reserved_at: now(), expires_at: daysFromNow(3), notes,
  });
  dev.status = 'Lefoglalva';
  touch(dev);
  notify();
}

// cancel_reservation — a foglaló vagy storekeeper/it_admin
export function cancelReservation(device_id) {
  const actor = state.currentUserId;
  const resv = activeReservation(device_id);
  if (!resv) throw new OpError('Nincs aktív foglalás ezen az eszközön.');
  if (!(resv.reserved_by === actor || roleAtLeast(currentRole(), 'storekeeper')))
    throw new OpError('Csak a foglaló vagy raktáros mondhatja le a foglalást.');
  deleteReservation(device_id);
  const dev = getDevice(device_id);
  dev.status = 'Kivehető';
  touch(dev);
  notify();
}

function deleteReservation(device_id) {
  state.reservations = state.reservations.filter((r) => r.device_id !== device_id);
}

// send_to_repair / mark_lost — storekeeper+
export function sendToRepair(device_id, notes = null) {
  requireStorekeeper();
  const repairDept = state.departments.find((d) => d.kind === 'műhely');
  moveAssetInternal({
    device_id, event_type: 'send_to_repair',
    to_departments_id: repairDept ? repairDept.id : null, notes,
  });
}

export function returnFromRepair(device_id, to_location_id, to_department_id, notes = null) {
  requireStorekeeper();
  const cur = currentState(device_id);
  if (cur.department === null)
    throw new OpError('Szervizből csak osztályra vagy helyszínre lehet visszahelyezni.');
  moveAssetInternal({
    device_id, event_type: 'check_in',
    to_locations_id: to_location_id, to_departments_id: to_department_id, notes,
  });
}

export function markLost(device_id, notes = null) {
  requireStorekeeper();
  const cur = currentState(device_id);
  moveAssetInternal({
    device_id, event_type: 'mark_lost',
    to_locations_id: cur.location, to_departments_id: cur.department, to_user_id: cur.holder, notes,
  });
}

export function markFound(device_id, to_location_id, to_department_id, notes = null) {
  requireStorekeeper();
  moveAssetInternal({
    device_id, event_type: 'mark_found',
    to_locations_id: to_location_id, to_departments_id: to_department_id, notes,
  });
}

// belső változat ami megkerüli a user-korlátozást (csak storekeeper+ hívja)
function moveAssetInternal({ device_id, event_type, to_user_id = null, to_locations_id = null, to_departments_id = null, notes = null }) {
  const cur = currentState(device_id);
  pushEvent({
    device_id, event_type, actor_user_id: state.currentUserId,
    from_user_id: cur.holder, from_locations_id: cur.location, from_departments_id: cur.department,
    to_user_id, to_locations_id, to_departments_id, event_timestamp: now(), notes,
    confirmation_status: 'confirmed',
  });
  deleteReservation(device_id);
  const dev = getDevice(device_id);
  dev.status = statusFromEvent(event_type);
  touch(dev);
  notify();
}

// register_device — storekeeper / it_admin
export function registerDevice({ device_type_id, model, manufacturer, serial_number, asset_tag, condition, notes, attrs, initial_location, initial_department }) {
  requireStorekeeper();
  const id = ++_deviceId;
  const dev = {
    device_id: id, asset_tag, device_type_id,
    model, manufacturer, serial_number,
    status: 'Kivehető', condition: condition || 'Jó',
    notes: notes || '', attrs: attrs || {},
    created_by: state.currentUserId, updated_by: state.currentUserId,
    created_at: now(), updated_at: now(),
  };
  
  state.devices.push(dev);
  // kezdeti elhelyezés
  pushEvent({
    device_id: id, event_type: 'check_in', actor_user_id: state.currentUserId,
    from_user_id: null, from_locations_id: null, from_departments_id: null,
    to_user_id: null, to_locations_id: initial_location, to_departments_id: initial_department,
    event_timestamp: now(), notes: 'Regisztrációs elhelyezés.',
    confirmation_status: 'confirmed'
  });
  notify();
  return dev;
}

// edit_device — storekeeper / it_admin
export function editDevice(device_id, changes) {
  requireStorekeeper();
  const dev = getDevice(device_id);
  if (!dev) throw new OpError('Eszköz nem található.');
  const { attrs, ...common } = changes;
  Object.assign(dev, common);
  if (attrs) dev.attrs = { ...dev.attrs, ...attrs };
  touch(dev);
  notify();
}

// retire_device — storekeeper / it_admin
export function retireDevice(device_id, reason) {
  requireStorekeeper();
  const dev = getDevice(device_id);
  if (!dev) throw new OpError('Eszköz nem található.');
  dev.status = 'Selejtezve';
  dev.retired_date = now();
  dev.notes = (dev.notes ? dev.notes + ' ' : '') + `Selejtezve: ${reason || 'nincs indok'}`;
  deleteReservation(device_id);
  touch(dev);
  notify();
}

function touch(dev) {
  dev.updated_by = state.currentUserId;
  dev.updated_at = now();
}

function requireStorekeeper() {
  if (!roleAtLeast(currentRole(), 'storekeeper'))
    throw new OpError('Ehhez raktáros vagy IT-admin szerepkör kell.');
}

function requireAdmin() {
  if (!roleAtLeast(currentRole(), 'it_admin'))
    throw new OpError('Ehhez IT-admin szerepkör kell.');
}

// ---- Törzsadat műveletek ------------------------------------

export function addLocation({ address }) {
  requireAdmin();
  if (!address?.trim()) throw new OpError('Add meg a helyszín címét.');
  const id = Math.max(0, ...state.locations.map((l) => l.id)) + 1;
  state.locations.push({ id, address: address.trim() });
  notify();
  return id;
}

export function addDepartment({ locations_id, name, type }) {
  requireStorekeeper();
  if (!name?.trim()) throw new OpError('Add meg a részleg nevét.');
  if (!locations_id) throw new OpError('Válassz helyszínt.');
  const id = Math.max(0, ...state.departments.map((d) => d.id)) + 1;
  state.departments.push({ id, locations_id: Number(locations_id), name: name.trim(), type: type || 'osztály' });
  notify();
  return id;
}

export function addDeviceType({ type, description }) {
  requireAdmin();
  if (!type?.trim()) throw new OpError('Add meg az eszköztípus nevét.');
  const id = Math.max(0, ...state.deviceTypes.map((t) => t.id)) + 1;
  state.deviceTypes.push({ id, type: type.trim(), description: (description || '').trim() });
  notify();
  return id;
}

export function addAttrDef({ device_type_id, attribute_key, label, data_type, is_required, options, sort_order }) {
  requireAdmin();
  if (!attribute_key?.trim()) throw new OpError('Add meg az attribútum kulcsát.');
  if (!label?.trim()) throw new OpError('Add meg az attribútum feliratát.');
  if (!data_type) throw new OpError('Válassz adattípust.');
  const id = Math.max(0, ...state.attributeDefinitions.map((a) => a.id)) + 1;
  state.attributeDefinitions.push({
    id,
    device_type_id: device_type_id ? Number(device_type_id) : null,
    attribute_key: attribute_key.trim(),
    label: label.trim(),
    data_type,
    is_required: !!is_required,
    options: data_type === 'enum' ? (options || '').trim() || null : null,
    sort_order: Number(sort_order) || 0,
  });
  notify();
  return id;
}

// ---- Feliratkozás (re-render) -------------------------------
export function subscribe(fn) {
  subscribers.add(fn);
  return () => subscribers.delete(fn);
}
function notify() {
  persist();
  for (const fn of subscribers) fn();
}

// ---- Init ----------------------------------------------------
if (!loadPersisted()) {
  bootstrap();
  // Tárolt státusz összhangba hozása a tényleges birtoklással (seed/elcsúszás javítása).
  for (const dev of state.devices) {
    if (['Selejtezve', 'Elveszett', 'Szerviz alatt'].includes(dev.status)) continue;
    const cur = currentState(dev.device_id);
    if (activeReservation(dev.device_id)) dev.status = 'Lefoglalva';
    else if (pendingCheckinFor(dev.device_id)) dev.status = 'Visszavétel folyamatban';
    else if (cur.holder !== null) dev.status = 'Kiadva';
    else dev.status = 'Kivehető';
  }
  persist();
}

// Visszaállítás a kiinduló mintaadatokra (törli a mentést).
export function resetToSeed() {
  try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
  location.reload();
}