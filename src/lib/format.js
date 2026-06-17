// ============================================================
// Megjelenítési segédek — címkék, badge-ek, dátumok
// ============================================================

import { getDepartment, getLocation, getUser, getDeviceType } from '../state/store.js';

// ---- Státusz: DB-érték → magyar címke + badge szín ----------
const STATUS = {
  'Kiadva':        { label: 'Kiadva',        cls: 'status-deployed' },
  'Kivehető': { label: 'Kivehető',          cls: 'status-ready' },
  'Lefoglalva':        { label: 'Lefoglalva',        cls: 'status-reserved' },
  'Visszavétel folyamatban':  { label: 'Visszavétel folyamatban', cls: 'status-pending' },
  'Szerviz alatt':       { label: 'Szerviz alatt',     cls: 'status-repair' },
  'Elveszett':            { label: 'Elveszett',          cls: 'status-lost' },
  'Selejtezve':         { label: 'Selejtezve',         cls: 'status-retired' },
};

export const statusLabel = (s) => STATUS[s]?.label || s || '—';
export const statusClass = (s) => STATUS[s]?.cls || 'status-default';
export function statusBadge(s) {
  return `<span class="status-badge ${statusClass(s)}">${statusLabel(s)}</span>`;
}

// ---- Szerepkör ----------------------------------------------
const ROLE = { user: 'Felhasználó', storekeeper: 'Raktáros', it_admin: 'IT-admin' };
export const roleLabel = (r) => ROLE[r] || r;

// ---- Eseménytípus -------------------------------------------
const EVENT = {
  check_out: 'Kivétel',
  check_in: 'Leadás',
  transfer: 'Átadás',
  stock_transfer: 'Raktármozgatás',
  send_to_repair: 'Szervizbe küldés',
  return_from_repair: 'Szervizből visszahelyezés',
  mark_lost: 'Elveszettnek jelölés',
  mark_found: 'Megtalálva',
};
export const eventLabel = (e) => EVENT[e] || e;

// ---- Megerősítési állapot -----------------------------------
const CONF = { pending: 'Függőben', confirmed: 'Megerősítve', rejected: 'Elutasítva' };
export const confLabel = (c) => CONF[c] || c;

// ---- location_label: department → olvasható hely ------------
export function locationLabel(locationId, departmentId) {
  const dep = departmentId ? getDepartment(departmentId) : null;
  const loc = locationId ? getLocation(locationId) : null;
  if (dep && loc) return `${dep.name} · ${loc.address}`;
  if (dep) return dep.name;
  if (loc) return loc.address;
  return '—';
}

// ---- Aktuális hely/birtokos olvashatóan ---------------------
export function holderLabel(holderId) {
  if (!holderId) return '—';
  const u = getUser(holderId);
  return u ? u.full_name : '—';
}

export const typeLabel = (typeId) => getDeviceType(typeId)?.name || '—';

// ---- Dátum --------------------------------------------------
export function fmtDate(d) {
  if (!d) return '—';
  const date = d instanceof Date ? d : new Date(d);
  if (isNaN(date)) return '—';
  return date.toLocaleDateString('hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit' });
}
export function fmtDateTime(d) {
  if (!d) return '—';
  const date = d instanceof Date ? d : new Date(d);
  if (isNaN(date)) return '—';
  return date.toLocaleString('hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
// relatív „hátralévő idő" foglaláshoz
export function fmtRelative(d) {
  if (!d) return '—';
  const date = d instanceof Date ? d : new Date(d);
  if (isNaN(date)) return '—';
  const diffMs = date - Date.now();
  const hours = Math.round(diffMs / 3600000);
  if (hours <= 0) return 'lejárt';
  if (hours < 24) return `${hours} óra múlva`;
  return `${Math.round(hours / 24)} nap múlva`;
}

// ---- Attribútum érték megjelenítése -------------------------
export function fmtAttrValue(def, value) {
  if (value === undefined || value === null || value === '') return '—';
  if (def.data_type === 'boolean') return value ? 'Igen' : 'Nem';
  if (def.data_type === 'date') return fmtDate(value);
  return String(value);
}

// ---- Kalibráció lejárat jelzés ------------------------------
// visszatér: 'overdue' | 'soon' | 'ok' | null
export function calibrationFlag(dateStr) {
  if (!dateStr) return null;
  const due = new Date(dateStr);
  if (isNaN(due)) return null;
  const days = Math.round((due - Date.now()) / 86400000);
  if (days < 0) return 'overdue';
  if (days <= 30) return 'soon';
  return 'ok';
}

// ---- HTML escape --------------------------------------------
export function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
