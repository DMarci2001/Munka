// ============================================================
// View-model segéd: egy eszköz „kibővített" nézete a listákhoz
// és a részletoldalhoz (aktuális hely/birtokos, foglalás, stb.)
// ============================================================

import {
  currentState, activeReservation, pendingCheckinFor, getDeviceType, getUser, getEvents,
} from '../state/store.js';
import { calibrationFlag } from './format.js';
import { isStorageDept } from '../state/store.js';

export function deviceVM(dev) {
  const cur = currentState(dev.device_id);
  const type = getDeviceType(dev.device_type_id);
  const resv = activeReservation(dev.device_id);
  const pending = pendingCheckinFor(dev.device_id);
  const calDue = dev.attrs?.calibration_due || null;
  const lastEvent = getEvents()
    .filter((e) => e.device_id === dev.device_id)
    .sort((a, b) => new Date(b.event_timestamp) - new Date(a.event_timestamp))[0];
  const lastModified = lastEvent
  ? new Date(lastEvent.event_timestamp).toISOString().slice(0, 10)
  : null;
  // Legutóbbi megerősített kivétel (a „Kivétel időpontja" oszlophoz).
  const lastCheckout = getEvents()
    .filter((e) => e.device_id === dev.device_id && e.event_type === 'check_out' && e.confirmation_status === 'confirmed')
    .sort((a, b) => new Date(b.event_timestamp) - new Date(a.event_timestamp))[0] || null;
  // Foglalás dátuma a foglalási rekordból (nincs külön „reserve" esemény).
  const lastReserved = resv ? { event_timestamp: resv.reserved_at } : null;

  // Effektív státusz a tényleges birtoklásból/foglalásból (a tárolt dev.status elcsúszhat).
  // A „manuális" státuszokat (selejt, elveszett, javítás, foglalás) a tárolt dev.status
  // adja — ezeket nem lehet a custody-naplóból levezetni, és a foglalás lejárta sem
  // írhatja felül a megjelenített státuszt. A többit a tényleges birtoklásból vezetjük le.
  let effectiveStatus;
  if (['Selejtezve', 'Elveszett', 'Szerviz alatt', 'Lefoglalva'].includes(dev.status)) effectiveStatus = dev.status;
  else if (resv) effectiveStatus = 'Lefoglalva';
  else if (pending) effectiveStatus = 'Visszavétel folyamatban';
  else if (cur.holder !== null) effectiveStatus = 'Kiadva';
  else effectiveStatus = 'Kivehető';

  return {
    dev,
    type,
    typeName: type?.type || '—',
    status: effectiveStatus,
    holderId: cur.holder,
    holder: cur.holder ? getUser(cur.holder) : null,
    locationId: cur.location,
    departmentId: cur.department,
    since: cur.since,
    reservation: resv,
    reservedBy: resv ? getUser(resv.reserved_by) : null,
    pending,
    calibrationDue: calDue,
    calibrationFlag: calibrationFlag(calDue),
    lastModified,
    lastCheckout,
    lastReserved,
    // Kiadható, ha nincs birtokosa és valahol van — a tárolt státusztól függetlenül.
    isFree: effectiveStatus === 'Kivehető' && (cur.department !== null || cur.location !== null),
    inRepair: dev.status === 'Szerviz alatt',
  };
}
