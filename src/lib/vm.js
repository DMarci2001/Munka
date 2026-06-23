// ============================================================
// View-model segéd: egy eszköz „kibővített" nézete a listákhoz
// és a részletoldalhoz.
//
// A backend Repo::allEnriched / enrichOne MÁR kiszámolja az effektív
// státuszt, az aktuális birtokost/helyet, a foglalást és a függő
// visszavételt. Itt csak a megjelenítéshez kötjük össze a kapcsolódó
// törzsadat-objektumokkal (felhasználó, típus).
// ============================================================

import { getDeviceType, getUser } from '../state/store.js';
import { calibrationFlag } from './format.js';

export function deviceVM(dev) {
  const type = getDeviceType(dev.device_type_id);
  const resv = dev.reservation || null;
  const pending = dev.pending || null;
  const calDue = dev.calibration_due ?? dev.attrs?.calibration_due ?? null;

  return {
    dev,
    type,
    typeName: type?.type || '—',
    // A backend által kiszámolt effektív státusz.
    status: dev.status,
    holderId: dev.holder_id ?? null,
    holder: dev.holder_id ? getUser(dev.holder_id) : null,
    locationId: dev.location_id ?? null,
    departmentId: dev.department_id ?? null,
    since: dev.since ?? null,
    reservation: resv,
    reservedBy: resv ? getUser(resv.reserved_by) : null,
    pending,
    calibrationDue: calDue,
    calibrationFlag: calibrationFlag(calDue),
    lastModified: dev.last_modified ? String(dev.last_modified).slice(0, 10) : null,
    // A korábbi „esemény-objektum" alakot megőrizzük (a táblák ezt várják).
    lastCheckout: dev.last_checkout_at ? { event_timestamp: dev.last_checkout_at } : null,
    lastReserved: resv ? { event_timestamp: resv.reserved_at } : null,
    // Kiadható, ha nincs birtokosa és valahol van.
    isFree: dev.is_free ?? (dev.status === 'Kivehető' && (dev.department_id !== null || dev.location_id !== null)),
    isLost: dev.is_lost ?? (dev.status === 'Elveszett'),
    inRepair: dev.in_repair ?? (dev.status === 'Szerviz alatt'),
  };
}
