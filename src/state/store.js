// ============================================================
// Állapotkezelő (store) — a PHP backend tükre.
//
// A korábbi memóriában futó demó helyett a store most a szervertől
// tölti az állapotot (GET /bootstrap) és a műveleteket az API-n keresztül
// futtatja. A nézet-réteg felülete VÁLTOZATLAN: szinkron getterek +
// subscribe()/notify() újrarajzolás. A műveletek mostantól aszinkronok.
//
// Az "igazságforrás" a szerver: minden mutáció után frissítjük a dinamikus
// szeleteket (devices + pending + reservations), majd értesítünk.
// ============================================================

import { apiGet, apiSend, OpError } from '../lib/api.js';

export { OpError };

// Dev: a seed felhasználók közös jelszava (csak fejlesztői auto-login / váltó).
const DEV_PASSWORD = 'jelszo123';

// ---- Tükör-állapot ------------------------------------------
const state = {
  locations: [],
  departments: [],
  users: [],
  deviceTypes: [],
  attributeDefinitions: [],
  devices: [],         // enriched eszközök (a backend Repo::allEnriched-jából)
  pending: [],         // megerősítésre váró visszavételek (nyers események)
  reservations: [],    // aktív foglalások
  currentUser: null,   // a /me-ből (a host oldal munkamenetéből)
};

// custody-előzmény gyorsítótár (a részletoldalhoz, igény szerint töltve)
let historyCache = {};
const historyLoading = new Set();

const subscribers = new Set();

// ---- Hidratálás / frissítés ---------------------------------
// Teljes betöltés: törzsadat + dinamikus szeletek + bejelentkezett user.
export async function hydrate() {
  const b = await apiGet('/bootstrap');
  state.locations            = b.locations || [];
  state.departments          = b.departments || [];
  state.users                = b.users || [];
  state.deviceTypes          = b.deviceTypes || [];
  state.attributeDefinitions = b.attributeDefinitions || [];
  state.devices              = b.devices || [];
  state.pending              = b.pending || [];
  state.reservations         = b.reservations || [];
  state.currentUser          = b.currentUser || null;
  historyCache = {};
  notify();
}

// Mutáció utáni frissítés: csak a változó szeletek (lookups/currentUser nem).
export async function refresh() {
  const [devices, pending, reservations] = await Promise.all([
    apiGet('/devices'),
    apiGet('/pending'),
    apiGet('/reservations'),
  ]);
  state.devices = devices || [];
  state.pending = pending || [];
  state.reservations = reservations || [];
  historyCache = {};   // előzmények elavultak a mutáció után
  notify();
}

// ---- Lookups (szinkron olvasók a tükörből) ------------------
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
export const getDeviceByAssetTag = (tag) =>
  state.devices.find((d) => d.asset_tag?.toLowerCase() === String(tag).trim().toLowerCase()) || null;
export const getAttrDefs = (typeId) =>
  state.attributeDefinitions
    .filter((a) => a.device_type_id === typeId || a.device_type_id === null)
    .sort((a, b) => a.sort_order - b.sort_order);

// ---- Aktuális felhasználó / szerepkör ------------------------
export const currentUser = () => state.currentUser;
export const currentRole = () => state.currentUser?.auth || 'user';
const roleRank = { user: 1, storekeeper: 2, it_admin: 3 };
export const roleAtLeast = (role, min) => (roleRank[role] || 1) >= (roleRank[min] || 1);

// DEV: felhasználóváltás — bejelentkezés a kiválasztott seed userként.
// Éles, beágyazott buildben a váltó rejtett (lásd appshell.js), így ez nem hívódik.
export async function setCurrentUser(id) {
  const u = state.users.find((x) => x.id === id);
  if (!u) return;
  await apiSend('POST', '/auth/login', { username: u.username, password: DEV_PASSWORD });
  await hydrate();
}

// ---- Származtatott állapot (az enriched eszközből) -----------
export function currentState(deviceId) {
  const d = getDevice(deviceId);
  if (!d) return { holder: null, location: null, department: null, since: null };
  return {
    holder: d.holder_id ?? null,
    location: d.location_id ?? null,
    department: d.department_id ?? null,
    since: d.since ?? null,
  };
}
export const activeReservation = (deviceId) => getDevice(deviceId)?.reservation || null;
export const pendingCheckinFor = (deviceId) => getDevice(deviceId)?.pending || null;
export const pendingCheckins = () => state.pending;
export const activeReservations = () => state.reservations;

// ---- Custody-előzmény (igény szerint töltve) ----------------
export const historyOf = (deviceId) => historyCache[deviceId] || [];
export const isHistoryLoaded = (deviceId) =>
  Object.prototype.hasOwnProperty.call(historyCache, deviceId);

export async function ensureHistory(deviceId) {
  if (isHistoryLoaded(deviceId) || historyLoading.has(deviceId)) return;
  historyLoading.add(deviceId);
  try {
    historyCache[deviceId] = (await apiGet(`/devices/${deviceId}/history`)) || [];
  } catch (e) {
    historyCache[deviceId] = [];
  } finally {
    historyLoading.delete(deviceId);
    notify();
  }
}

// ============================================================
// Műveletek — aszinkron API-hívás + frissítés + értesítés
// (a hibát OpError-ként dobják; a UI a megszokott módon toastolja)
// ============================================================

// move_asset — minden birtoklási mozgás egy kapun
export async function moveAsset(payload) {
  await apiSend('POST', '/devices/move', payload);
  await refresh();
}

export async function confirmCheckIn(eventId) {
  await apiSend('POST', `/checkins/${eventId}/confirm`);
  await refresh();
}
export async function rejectCheckIn(eventId, reason) {
  await apiSend('POST', `/checkins/${eventId}/reject`, { reason });
  await refresh();
}

export async function reserveDevice(deviceId, notes = null) {
  await apiSend('POST', `/devices/${deviceId}/reserve`, { notes });
  await refresh();
}
export async function cancelReservation(deviceId) {
  await apiSend('POST', `/devices/${deviceId}/cancel-reservation`);
  await refresh();
}

export async function sendToRepair(deviceId, to_locations_id = null, to_departments_id = null, notes = null) {
  await apiSend('POST', `/devices/${deviceId}/send-to-repair`, { to_locations_id, to_departments_id, notes });
  await refresh();
}
export async function returnFromRepair(deviceId, to_locations_id, to_departments_id, notes = null) {
  await apiSend('POST', `/devices/${deviceId}/return-from-repair`, { to_locations_id, to_departments_id, notes });
  await refresh();
}
export async function markLost(deviceId, notes = null) {
  await apiSend('POST', `/devices/${deviceId}/mark-lost`, { notes });
  await refresh();
}
export async function markFound(deviceId, to_locations_id, to_departments_id, notes = null) {
  await apiSend('POST', `/devices/${deviceId}/mark-found`, { to_locations_id, to_departments_id, notes });
  await refresh();
}

// register_device — a létrehozott enriched eszközt adja vissza (navigációhoz)
export async function registerDevice(payload) {
  const dev = await apiSend('POST', '/devices', payload);
  await refresh();
  return dev;
}
export async function editDevice(deviceId, changes) {
  await apiSend('PATCH', `/devices/${deviceId}`, changes);
  await refresh();
}
export async function retireDevice(deviceId, reason) {
  await apiSend('POST', `/devices/${deviceId}/retire`, { reason });
  await refresh();
}

// ---- Törzsadat műveletek ------------------------------------
export async function addLocation({ address }) {
  const r = await apiSend('POST', '/locations', { address });
  await hydrate();   // törzsadat változott → teljes újratöltés
  return r?.id;
}
export async function addDepartment({ locations_id, name, type }) {
  const r = await apiSend('POST', '/departments', { locations_id, name, type });
  await hydrate();
  return r?.id;
}
export async function addDeviceType({ type, description }) {
  const r = await apiSend('POST', '/device-types', { type, description });
  await hydrate();
  return r?.id;
}
export async function addAttrDef(payload) {
  const r = await apiSend('POST', '/attribute-definitions', payload);
  await hydrate();
  return r?.id;
}

// ---- Feliratkozás (re-render) -------------------------------
export function subscribe(fn) {
  subscribers.add(fn);
  return () => subscribers.delete(fn);
}
function notify() {
  for (const fn of subscribers) fn();
}
