// ============================================================
// Eszközlista — keresés, szűrés, böngészés
// ============================================================

import { getDevices, getDeviceTypes, getLocations, getDepartments, currentRole, roleAtLeast, getUsers } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import { statusBadge, statusLabel, locationLabel, holderLabel, esc } from '../lib/format.js';
import { icons } from '../ui/components.js';

// szűrőállapot (megőrződik nézetváltáskor)
const filters = { q: '', type: '', status: '', dept: '', loc: '', holder: ''
};

const STATUSES = ['Ready to deploy', 'Deployed', 'Reserved', 'Pending return', 'In repair', 'Lost', 'Retired'];

// rendezési állapot
let sortCol = null;
let sortDir = 1; // 1 = növekvő, -1 = csökkenő

function sortBy(col) {
  if (sortCol === col) sortDir *= -1;
  else { sortCol = col; sortDir = 1; }
}

function thHTML(col, label) {
  const arrow = sortCol !== col ? '<span style="opacity:.99">↕</span>' : sortDir === 1 ? '↑' : '↓';
  return `<th data-col="${col}" style="cursor:pointer;user-select:none">${label} ${arrow}</th>`;
}

function sortValue(v, col) {
  switch (col) {
    case 'lastModified': return v.lastModified || '';
    case 'typeName':     return (v.typeName || '') + ' ' + (v.dev.model || '');
    case 'status':       return v.status || '';
    case 'holder':       return v.holder ? v.holder.full_name : '';
    case 'location':     return String(v.locationId || '') + String(v.departmentId || '');
    default:             return '';
  }
}

export function renderInventory(el) {
  const isStore = roleAtLeast(currentRole(), 'storekeeper');
  const calRows = isStore
    ? getDevices().map(deviceVM)
        .filter((v) => (v.calibrationFlag === 'overdue' || v.calibrationFlag === 'soon') && v.status !== 'Retired')
        .sort((a, b) => new Date(a.calibrationDue) - new Date(b.calibrationDue))
        .slice(0, 6)
    : [];

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
          <option value="">Minden helyszín</option>
          ${getLocations().map((l) => `<option value="${l.id}" ${String(l.id) === filters.loc ? 'selected' : ''}>${esc(l.address)}</option>`).join('')}
        </select>
        <select class="form-select" id="f-dept" style="max-width:180px">
          <option value="">Minden helyiség</option>
          ${getDepartments().map((d) => `<option value="${d.id}" ${String(d.id) === filters.dept ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}
        </select>
        <select class="form-select" id="f-holder" style="max-width:180px">
          <option value="">Minden birtokos</option>
          ${getUsers().filter((u) => getDevices().map(deviceVM).some((v) => v.holderId === u.id)).map((u) => `<option value="${u.id}" ${String(u.id) === filters.holder ? 'selected' : ''}>${esc(u.full_name)}</option>`).join('')}
        </select>


        ${isStore ? `<button class="btn btn-primary" id="btn-new-device">${icons.register} Új eszköz bevitele</button>` : ''}
      </div>
      ${isStore ? `
        <div class="panel" style="margin-bottom:16px">
          <div class="panel-head">Felülvizsgálandó eszközök</div>
          <div class="panel-body" style="padding:0">
            ${calRows.length ? `
            <table class="grid">
              <tbody>
                ${calRows.map((v) => `
                  <tr data-dev="${v.dev.device_id}" style="cursor:pointer">
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
        </div>` : ''}
      <div id="inv-table"></div>
    </div>`;

  const q = el.querySelector('#f-q');
  q.addEventListener('input', () => { filters.q = q.value; paint(el); });
  el.querySelector('#f-type').addEventListener('change', (e) => { filters.type = e.target.value; paint(el); });
  el.querySelector('#f-status').addEventListener('change', (e) => { filters.status = e.target.value; paint(el); });
  el.querySelector('#f_loc').addEventListener('change', (e) => { filters.loc = e.target.value; paint(el); });
  el.querySelector('#f-dept').addEventListener('change', (e) => { filters.dept = e.target.value; paint(el); });
  el.querySelector('#f-holder').addEventListener('change', (e) => { filters.holder = e.target.value; paint(el); });
  const btnNew = el.querySelector('#btn-new-device');
  if (btnNew) btnNew.addEventListener('click', () => navigate('/register'));
  el.querySelectorAll('.panel [data-dev]').forEach((r) =>
    r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));
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
  if (filters.holder) vms = vms.filter((v) => String(v.holderId) === filters.holder);

  if (sortCol) {
    vms = [...vms].sort((a, b) => {
      const av = sortValue(a, sortCol).toLowerCase();
      const bv = sortValue(b, sortCol).toLowerCase();
      if (av < bv) return -sortDir;
      if (av > bv) return sortDir;
      return 0;
    });
  }

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
            ${thHTML('lastModified', 'Utoljára módosítva')}${thHTML('typeName', 'Típus / modell')}${thHTML('status', 'Státusz')}
            ${thHTML('holder', 'Birtokos')}${thHTML('location', 'Hely')}<th></th>
          </tr>
        </thead>
        <tbody>
          ${vms.map(rowHTML).join('')}
        </tbody>
      </table>
    </div>`;

  
  wrap.querySelectorAll('tbody tr').forEach((r) =>
    r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));

  wrap.querySelectorAll('th[data-col]').forEach((th) =>
    th.addEventListener('click', () => { sortBy(th.dataset.col); paint(el); }));
}

function rowHTML(v) {
  const holder = v.holder ? esc(v.holder.full_name) : '<span class="muted">— raktáron —</span>';
  const resvNote = v.reservation ? `<div class="cell-sub">Foglalta: ${esc(v.reservedBy?.full_name || '')}</div>` : '';
  return `
    <tr data-dev="${v.dev.device_id}">
      <td><span class="tag-mono">${esc(v.lastModified) || '—'}</span></td>
      <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
      <td>${statusBadge(v.status)}${resvNote}</td>
      <td>${holder}</td>
      <td>${esc(locationLabel(v.locationId, v.departmentId))}</td>
      <td style="text-align:right">${icons.arrowRight}</td>
    </tr>`;
}