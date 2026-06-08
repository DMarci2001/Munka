// ============================================================
// Eszközlista — keresés, szűrés, böngészés
// ============================================================

import { getDevices, getDeviceTypes, getLocations, getDepartments } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import { statusBadge, statusLabel, locationLabel, holderLabel, esc } from '../lib/format.js';
import { icons } from '../ui/components.js';

// szűrőállapot (megőrződik nézetváltáskor)
const filters = { q: '', type: '', status: '', dept: '', loc: '' };

const STATUSES = ['Ready to deploy', 'Deployed', 'Reserved', 'Pending return', 'In repair', 'Lost', 'Retired'];

export function renderInventory(el) {
  el.innerHTML = `
    <div class="content">
      <div class="toolbar">
        <div class="search">
          <span class="ico">${icons.search}</span>
          <input class="form-control" id="f-q" placeholder="Keresés: azonosító, modell, gyártó, sorozatszám…" value="${esc(filters.q)}" />
        </div>
        <select class="form-select" id="f-type" style="max-width:170px">
          <option value="">Minden típus</option>
          ${getDeviceTypes().map((t) => `<option value="${t.id}" ${String(t.id) === filters.type ? 'selected' : ''}>${esc(t.type)}</option>`).join('')}
        </select>
        <select class="form-select" id="f-status" style="max-width:180px">
          <option value="">Minden státusz</option>
          ${STATUSES.map((s) => `<option value="${s}" ${s === filters.status ? 'selected' : ''}>${esc(statusLabel(s))}</option>`).join('')}
        </select>
        <select class="form-select" id="f_loc" style="max-width:180px">
          <option value="">Minden hely</option>
          ${getLocations().map((l) => `<option value="${l.id}" ${String(l.id) === filters.loc ? 'selected' : ''}>${esc(l.address)}</option>`).join('')}
        </select>
        <select class="form-select" id="f-dept" style="max-width:180px">
          <option value="">Minden hely</option>
          ${getDepartments().map((d) => `<option value="${d.id}" ${String(d.id) === filters.dept ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}
        </select>
        
      </div>
      <div id="inv-table"></div>
    </div>`;

  const q = el.querySelector('#f-q');
  q.addEventListener('input', () => { filters.q = q.value; paint(el); });
  el.querySelector('#f-type').addEventListener('change', (e) => { filters.type = e.target.value; paint(el); });
  el.querySelector('#f-status').addEventListener('change', (e) => { filters.status = e.target.value; paint(el); });
  el.querySelector('#f_loc').addEventListener('change', (e) => { filters.loc = e.target.value; paint(el); });
  el.querySelector('#f-dept').addEventListener('change', (e) => { filters.dept = e.target.value; paint(el); });
  paint(el);
}

function paint(el) {
  const wrap = el.querySelector('#inv-table');
  let vms = getDevices().map(deviceVM);

  const q = filters.q.trim().toLowerCase();
  if (q) vms = vms.filter((v) =>
    [v.dev.asset_tag, v.dev.model, v.dev.manufacturer, v.dev.serial_number, v.typeName]
      .filter(Boolean).some((s) => s.toLowerCase().includes(q)));
  if (filters.type) vms = vms.filter((v) => String(v.dev.device_type_id) === filters.type);
  if (filters.status) vms = vms.filter((v) => v.status === filters.status);
  if (filters.loc) vms = vms.filter((v) => String(v.locationId) === filters.loc);
  if (filters.dept) vms = vms.filter((v) => String(v.departmentId) === filters.dept);

  if (!vms.length) {
    wrap.innerHTML = `<div class="table-wrap"><div class="empty"><div class="big">${icons.search}</div><div>Nincs a szűrőnek megfelelő eszköz.</div></div></div>`;
    return;
  }

  wrap.innerHTML = `
    <div class="muted" style="font-size:.82rem; margin-bottom:10px">${vms.length} eszköz</div>
    <div class="table-wrap">
      <table class="grid">
        <thead>
          <tr>
            <th>Azonosító</th><th>Típus / modell</th><th>Státusz</th>
            <th>Birtokos</th><th>Hely</th><th></th>
          </tr>
        </thead>
        <tbody>
          ${vms.map(rowHTML).join('')}
        </tbody>
      </table>
    </div>`;

  wrap.querySelectorAll('tbody tr').forEach((r) =>
    r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));
}

function rowHTML(v) {
  const holder = v.holder ? esc(v.holder.full_name) : '<span class="muted">— raktáron —</span>';
  const resvNote = v.reservation ? `<div class="cell-sub">Foglalta: ${esc(v.reservedBy?.full_name || '')}</div>` : '';
  return `
    <tr data-dev="${v.dev.device_id}">
      <td><span class="tag-mono">${esc(v.dev.asset_tag)}</span></td>
      <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
      <td>${statusBadge(v.status)}${resvNote}</td>
      <td>${holder}</td>
      <td>${esc(locationLabel(v.locationId, v.departmentId))}</td>
      <td style="text-align:right">${icons.arrowRight}</td>
    </tr>`;
}
