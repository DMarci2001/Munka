// ============================================================
// Irányítópult — gyors áttekintés
// ============================================================

import { getDevices, currentUser, currentRole, roleAtLeast, pendingCheckins, activeReservations } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import { statusBadge, statusLabel, typeLabel, locationLabel, fmtRelative, esc } from '../lib/format.js';
import { icons } from '../ui/components.js';

export function renderDashboard(el) {
  const me = currentUser();
  const role = currentRole();
  const vms = getDevices().map(deviceVM);

  const total = vms.length;
  const ready = vms.filter((v) => v.status === 'Ready to deploy').length;
  const deployed = vms.filter((v) => v.status === 'Deployed').length;
  const reserved = vms.filter((v) => v.status === 'Reserved').length;
  const pendingCount = pendingCheckins().length;
  const myCount = vms.filter((v) => v.holderId === me.id).length;
  const overdueCal = vms.filter((v) => v.calibrationFlag === 'overdue' && v.status !== 'Retired').length;

  const isStore = roleAtLeast(role, 'storekeeper');

  // figyelendő eszközök: lejárt kalibráció
  const calRows = vms
    .filter((v) => (v.calibrationFlag === 'overdue' || v.calibrationFlag === 'soon') && v.status !== 'Retired')
    .sort((a, b) => new Date(a.calibrationDue) - new Date(b.calibrationDue))
    .slice(0, 6);

  el.innerHTML = `
    <div class="content">
      <div class="stat-grid">
        <button class="stat accent"><div class="num">${total}</div><div class="lbl">Összes eszköz</div></button>
        <button class="stat"><div class="num">${ready}</div><div class="lbl">Kiadható</div></button>
        <button class="stat"><div class="num">${deployed}</div><div class="lbl">Kihelyezve</div></button>
        <button class="stat"><div class="num">${reserved}</div><div class="lbl">Lefoglalva</div></button>
        ${isStore
          ? `<button class="stat ${pendingCount ? 'warn' : ''}"><div class="num">${pendingCount}</div><div class="lbl">Leadott</div></button>`
          : `<button class="stat"><div class="num">${myCount}</div><div class="lbl">Nálam van</div></button>`}
        <button class="stat ${overdueCal ? 'danger' : ''}"><div class="num">${overdueCal}</div><div class="lbl">Lejárt kalibráció</div></button>
      </div>

        <div class="panel">
          <div class="panel-head">Figyelendő — kalibráció</div>
          <div class="panel-body" style="padding:0">
            ${calRows.length ? `
            <table class="grid">
              <tbody>
                ${calRows.map((v) => `
                  <tr data-dev="${v.dev.device_id}">
                    <td><span class="tag-mono">${esc(v.dev.model)}</span><div class="cell-sub">${esc(v.typeName)}</div></td>
                    <td>${statusBadge(v.status)}</td>
                    <td style="text-align:right">
                      <span class="attr-flag ${v.calibrationFlag}">${v.calibrationFlag === 'overdue' ? 'Lejárt' : 'Hamarosan'}</span>
                      <div class="cell-sub">${esc(v.calibrationDue)}</div>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>` : `<div class="empty" style="padding:32px"><div>Nincs közelgő kalibráció.</div></div>`}
          </div>
        </div>
      </div>
    </div>`;

  el.querySelectorAll('[data-go]').forEach((b) => b.addEventListener('click', () => navigate(b.dataset.go)));
  el.querySelectorAll('[data-dev]').forEach((r) => r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));
}
