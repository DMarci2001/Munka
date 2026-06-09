// ============================================================
// Nálam lévő eszközök + saját foglalások
// ============================================================

import { getDevices, currentUser, activeReservations } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import { statusBadge, locationLabel, fmtRelative, esc } from '../lib/format.js';
import { icons } from '../ui/components.js';
import * as A from '../ui/actions.js';

export function renderMyDevices(el) {
  const me = currentUser();
  const vms = getDevices().map(deviceVM);
  const held = vms.filter((v) => v.holderId === me.id);
  const reserved = vms.filter((v) => v.reservation && v.reservation.reserved_by === me.id);

  el.innerHTML = `
    <div class="content">
      <h3 class="section-title">Eszközök a birtokomban</h3>
      ${held.length ? `
      <div class="table-wrap" style="margin-bottom:26px">
        <table class="grid">
          <thead><tr><th>Azonosító</th><th>Típus / modell</th><th>Hely</th><th>Státusz</th><th style="text-align:right">Művelet</th></tr></thead>
          <tbody>
            ${held.map((v) => `
              <tr data-dev="${v.dev.device_id}">
                <td><span class="tag-mono">${esc(v.dev.asset_tag)}</span></td>
                <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
                <td>${esc(locationLabel(v.locationId, v.departmentId))}</td>
                <td>${statusBadge(v.status)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    ${v.status === 'Deployed' ? `<button class="btn btn-primary btn-sm" data-act="checkin" data-id="${v.dev.device_id}">Leadás</button>
                    <button class="btn btn-outline btn-sm" data-act="transfer" data-id="${v.dev.device_id}">Átadás</button>` : ''}
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>` : `<div class="table-wrap" style="margin-bottom:26px"><div class="empty"><div class="big">${icons.my}</div><div>Jelenleg nincs nálad eszköz.</div><div style="margin-top:12px"><button class="btn btn-outline" id="browse">Eszközök böngészése</button></div></div></div>`}

      <h3 class="section-title">Foglalásaim</h3>
      ${reserved.length ? `
      <div class="table-wrap">
        <table class="grid">
          <thead><tr><th>Azonosító</th><th>Típus / modell</th><th>Lejár</th><th style="text-align:right">Művelet</th></tr></thead>
          <tbody>
            ${reserved.map((v) => `
              <tr data-dev="${v.dev.device_id}">
                <td><span class="tag-mono">${esc(v.dev.asset_tag)}</span></td>
                <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
                <td>${fmtRelative(v.reservation.expires_at)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    <button class="btn btn-primary btn-sm" data-act="checkout" data-id="${v.dev.device_id}">Kivétel</button>
                    <button class="btn btn-outline btn-sm" data-act="cancel" data-id="${v.dev.device_id}">Lemondás</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>` : `<div class="muted" style="font-size:.9rem">Nincs aktív foglalásod.</div>`}
    </div>`;

  el.querySelectorAll('tbody tr').forEach((r) =>
    r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));
  el.querySelectorAll('[data-act]').forEach((b) =>
    b.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = Number(b.dataset.id);
      ({ checkin: A.dlgCheckIn, transfer: A.dlgTransfer, checkout: A.dlgCheckOut, cancel: A.doCancelReservation })[b.dataset.act]?.(id);
    }));
  el.querySelector('#browse')?.addEventListener('click', () => navigate('/inventory'));
}
