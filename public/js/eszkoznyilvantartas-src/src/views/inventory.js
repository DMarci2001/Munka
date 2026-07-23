// ============================================================
// Eszközlista — keresés, szűrés, böngészés
// ============================================================

import { getDevices, getDeviceTypes, getLocations, getDepartments, currentRole, roleAtLeast, getUsers, currentUser, batchCheckOut } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import { statusBadge, statusLabel, locationLabel, holderLabel, esc } from '../lib/format.js';
import { icons, openModal, toast } from '../ui/components.js';
import { enhanceSelects, refreshSelectDisplay } from '../ui/searchableSelect.js';
import { fitTableToWidth, watchFitToWidth } from '../ui/fitToWidth.js';
import { createBulkSelection, renderActionBar, injectCheckboxColumn, summarizeBatchResults } from '../ui/bulkSelect.js';

// szűrőállapot (megőrződik nézetváltáskor)
const filters = { q: '', type: '', status: '', dept: '', loc: '', holder: '' };

// tömeges kivétel — device_id-alapú kijelölés, a szűrésen túlél
const bulk = createBulkSelection();
let bulkMode = false;

const STATUSES = ['Kivehető', 'Kiadva', 'Lefoglalva', 'Visszavétel folyamatban', 'Átadás folyamatban', 'Szerviz alatt', 'Elveszett', 'Selejtezve'];

// rendezési állapot
let sortCol = null;
let sortDir = 1; // 1 = növekvő, -1 = csökkenő

function sortBy(col) {
  if (sortCol === col) sortDir *= -1;
  else { sortCol = col; sortDir = 1; }
}

function thHTML(col, label) {
  if(col == ' ') {
    return `<th data-col="${col}" ></th>`
  }
  const arrow = sortCol !== col ? '<span style="opacity:.99">↕</span>' : sortDir === 1 ? '↑' : '↓';
  return `<th data-col="${col}" style="cursor:pointer;user-select:none">${label} ${arrow}</th>`;
}

function sortValue(v, col) {
  switch (col) {
    case 'lastModified': return v.lastModified || '';
    case 'assetTag':     return v.dev.asset_tag || '';
    case 'typeName':     return (v.typeName || '') + ' ' + (v.dev.model || '');
    case 'status':       return v.status || '';
    case 'holder':       return v.holder ? v.holder.full_name : '';
    case 'location':     return String(v.locationId || '') + String(v.departmentId || '');
    default:             return '';
  }
}

export function renderInventory(el) {
  const isStore = roleAtLeast(currentRole(), 'storekeeper');
  const canOut = isStore || !!currentUser()?.can_check_out;
  // azon felhasználók, akiknél jelenleg van eszköz — a „birtokos" szűrőhöz
  const holderIds = new Set(getDevices().map((d) => d.holder_id).filter((id) => id != null));
  const calRows = isStore
    ? getDevices().map(deviceVM)
        .filter((v) => (v.calibrationFlag === 'overdue' || v.calibrationFlag === 'soon') && v.status !== 'Selejtezve')
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
        <div class="select-wrap" style="max-width:170px">
          <select class="form-select" id="f-type">
            <option value="">Minden típus</option>
            ${getDeviceTypes().map((t) => `<option value="${t.id}" ${String(t.id) === filters.type ? 'selected' : ''}>${esc(t.type)}</option>`).join('')}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-status">
            <option value="">Minden státusz</option>
            ${STATUSES.map((s) => `<option value="${s}" ${s === filters.status ? 'selected' : ''}>${esc(statusLabel(s))}</option>`).join('')}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f_loc">
            <option value="">Minden helyszín</option>
            ${getLocations().map((l) => `<option value="${l.id}" ${String(l.id) === filters.loc ? 'selected' : ''}>${esc(l.address)}</option>`).join('')}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-dept">
            <option value="">Minden helyiség</option>
            ${getDepartments().map((d) => `<option value="${d.id}" ${String(d.id) === filters.dept ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}
          </select>
        </div>
        <div class="select-wrap" style="max-width:180px">
          <select class="form-select" id="f-holder">
            <option value="">Minden birtokos</option>
            ${getUsers().filter((u) => holderIds.has(u.id)).map((u) => `<option value="${u.id}" ${String(u.id) === filters.holder ? 'selected' : ''}>${esc(u.full_name)}</option>`).join('')}
          </select>
        </div>

        <button class="btn btn-reset-filters-custom" id="btn-reset-filters">Szűrők törlése</button>
        <button class="btn btn-outline" id="btn-scan">${icons.qr} Beolvasás</button>
        ${canOut ? `<button class="btn btn-outline" id="btn-bulk-toggle">${bulkMode ? 'Tömeges kivétel — kilépés' : 'Tömeges kivétel'}</button>` : ''}
        ${isStore ? `<button class="btn btn-primary" id="btn-new-device">${icons.register} Új eszköz bevitele</button>` : ''}
      </div>
      <div id="bulk-bar" class="bulk-action-bar-slot"></div>
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
  const btnNewDevice = el.querySelector('#btn-new-device');
  if (btnNewDevice) btnNewDevice.addEventListener('click', () => navigate('/register'));
  const btnScan = el.querySelector('#btn-scan');
  if (btnScan) btnScan.addEventListener('click', () => navigate('/scan'));
  const btnResetFilters = el.querySelector('#btn-reset-filters');
  if (btnResetFilters) btnResetFilters.addEventListener('click', () => {
    filters.q = '';
    filters.type = '';
    filters.status = '';
    filters.loc = '';
    filters.dept = '';
    filters.holder = '';
    q.value = '';
    ['#f-type', '#f-status', '#f_loc', '#f-dept', '#f-holder'].forEach((sel) => {
      const node = el.querySelector(sel);
      node.value = '';
      refreshSelectDisplay(node);
    });
    paint(el);
  });
  el.querySelectorAll('.panel [data-dev]').forEach((r) =>
    r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));
  enhanceSelects(el);

  const btnBulkToggle = el.querySelector('#btn-bulk-toggle');
  if (btnBulkToggle) btnBulkToggle.addEventListener('click', () => {
    bulkMode = !bulkMode;
    if (!bulkMode) bulk.clear();
    paint(el);
  });
  if (canOut) {
    renderActionBar(el.querySelector('#bulk-bar'), bulk, {
      label: 'Tömeges kivétel',
      finalizeText: 'Kivétel véglegesítése',
      onFinalize: (ids) => openBulkCheckoutDialog(ids, isStore),
    });
  }
  paint(el);
}

function openBulkCheckoutDialog(deviceIds, isStore) {
  const me = currentUser();
  openModal({
    title: `Tömeges kivétel (${deviceIds.length} eszköz)`,
    closeOnBackdrop: false,
    bodyHTML: `
      ${isStore ? `
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${getUsers().map((u) => `<option value="${u.id}" ${u.id === me.id ? 'selected' : ''}>${esc(u.full_name)}${u.id === me.id ? ' (én)' : ''}</option>`).join('')}</select>
      </div>` : `<div class="alert-soft" style="margin-bottom:15px">Az eszközöket <strong>magadnak</strong> veszed ki: ${esc(me.full_name)}.</div>`}
      <div class="field">
        <label class="form-label">Hová (osztály / felhasználási hely)</label>
        <select class="form-select" name="to_location">${getLocations().map((l) => `<option value="${l.id}">${esc(l.address)}</option>`).join('')}</select>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,
    confirmText: 'Kivétel',
    onMount: (root) => {
      const locSel = root.querySelector('[name=to_location]');
      const deptSel = root.querySelector('[name=to_dept]');
      const fill = () => {
        const list = getDepartments().filter((d) => d.locations_id === Number(locSel.value) && d.type !== 'raktár');
        deptSel.innerHTML = list.length
          ? list.map((d) => `<option value="${d.id}">${esc(d.name)}</option>`).join('')
          : '<option value="">— nincs részleg ezen a helyszínen —</option>';
      };
      locSel.addEventListener('change', fill);
      fill();
      enhanceSelects(root);
    },
    onConfirm: async (root) => {
      const to_user_id = isStore ? Number(root.querySelector('[name=to_user]').value) : currentUser().id;
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      const results = await batchCheckOut(deviceIds, to_user_id, to_location_id, to_department_id, null, notes);
      summarizeBatchResults(results, toast);
      bulk.clear();
      bulkMode = false;
    },
  });
}

function paint(el) {
  const wrap = el.querySelector('#inv-table');
  let vms = getDevices().map(deviceVM);

  if (!roleAtLeast(currentRole(), 'storekeeper')) vms = vms.filter((v) => v.status === 'Kivehető');

  const q = filters.q.trim().toLowerCase();
  if (q) vms = vms.filter((v) =>
    [v.dev.asset_tag, v.dev.model, v.dev.manufacturer, v.dev.serial_number, v.typeName, holderLabel(v.holderId), locationLabel(v.locationId, v.departmentId)]
      .filter(Boolean).some((s) => s.toLowerCase().includes(q)));
  if (filters.type) vms = vms.filter((v) => String(v.dev.device_type_id) === filters.type);
  if (filters.status) vms = vms.filter((v) => v.status === filters.status);
  if (filters.loc) vms = vms.filter((v) => String(v.locationId) === filters.loc);
  if (filters.dept) vms = vms.filter((v) => String(v.departmentId) === filters.dept);
  if (filters.holder) vms = vms.filter((v) => String(v.holderId) === filters.holder);

  if (sortCol) {
    vms = [...vms].sort((a, b) =>
      sortDir * sortValue(a, sortCol).localeCompare(sortValue(b, sortCol), 'hu', { sensitivity: 'base' })
    );
  }

  if (!vms.length) {
    wrap.innerHTML = `<div class="table-wrap"><div class="empty"><div class="big">${icons.search}</div><div>Nincs a szűrőnek megfelelő eszköz.</div></div></div>`;
    return;
  }

  const PS = 25;
  const pages = [];
  for (let i = 0; i < vms.length; i += PS) pages.push(vms.slice(i, i + PS));

  const tableHTML = (pageVms) => `
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          ${thHTML('lastModified', 'Utoljára módosítva')}${thHTML('assetTag', 'Leltári azonosító')}${thHTML('typeName', 'Típus / modell')}${thHTML('status', 'Státusz')}
          ${thHTML('holder', 'Birtokos')}${thHTML('location', 'Hely')}${thHTML(' ', ' ')}<th></th>
        </tr></thead>
        <tbody>${pageVms.map(rowHTML).join('')}</tbody>
      </table>
    </div>`;

  const style = pages.map((_, i) =>
    `.inv-pager:has(#inv-p${i+1}:checked) .page-section[data-page="${i+1}"]{display:block}` +
    `.inv-pager:has(#inv-p${i+1}:checked) label[for="inv-p${i+1}"]{background:var(--brand);color:#fff;border-color:var(--brand-dark)}`
  ).join('');

  wrap.innerHTML = `
    <div class="muted" style="font-size:.82rem;margin-bottom:10px">${vms.length} eszköz</div>
    <div class="inv-pager pager-root">
      <style>${style}</style>
      ${pages.map((_, i) => `<input type="radio" name="inv-page" id="inv-p${i+1}" class="page-radio"${i === 0 ? ' checked' : ''}>`).join('')}
      ${pages.map((pVms, i) => `<div class="page-section" data-page="${i+1}">${tableHTML(pVms)}</div>`).join('')}
      ${pages.length > 1 ? `<div class="pager-nav">${pages.map((_, i) => `<label for="inv-p${i+1}" class="pager-btn">${i+1}</label>`).join('')}</div>` : ''}
    </div>`;

  wrap.querySelectorAll('tbody tr').forEach((r) =>
    r.addEventListener('click', () => navigate('/device/' + r.dataset.dev)));
  wrap.querySelectorAll('th[data-col]').forEach((th) =>
    th.addEventListener('click', () => { sortBy(th.dataset.col); paint(el); }));
  wrap.querySelectorAll('[data-act="qr-label"]').forEach((btn) =>
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = Number(e.currentTarget.closest('[data-dev]').dataset.dev);
      import('../ui/qrLabel.js').then((m) => m.printQrLabel(id));
    }));

  if (bulkMode) {
    wrap.querySelectorAll('.table-wrap').forEach((tw) => injectCheckboxColumn(tw, bulk, (id) => getDeviceStatus(id) === 'Kivehető'));
  }

  wrap.querySelectorAll('.table-wrap').forEach((tw) => {
    watchFitToWidth(tw);
    fitTableToWidth(tw);
  });
  wrap.querySelectorAll('.page-radio').forEach((radio) =>
    radio.addEventListener('change', () => {
      const section = wrap.querySelector(`.page-section[data-page="${radio.id.replace('inv-p', '')}"]`);
      const tw = section?.querySelector('.table-wrap');
      if (tw) fitTableToWidth(tw);
      if (bulkMode && tw) injectCheckboxColumn(tw, bulk, (id) => getDeviceStatus(id) === 'Kivehető');
    }));
}

function getDeviceStatus(deviceId) {
  return getDevices().find((d) => d.device_id === deviceId)?.status;
}

function rowHTML(v) {
  const holder = v.holder ? esc(v.holder.full_name) : '<span class="muted">— raktáron —</span>';
  const resvNote = v.reservation ? `<div class="cell-sub">Foglalta: ${esc(v.reservedBy?.full_name || '')}</div>` : '';
  return `
    <tr data-dev="${v.dev.device_id}">
      <td><span class="tag-mono">${esc(v.lastModified) || '—'}</span></td>
      <td><span class="tag-mono">${esc(v.dev.asset_tag)}</span></td>
      <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
      <td>${statusBadge(v.status)}${resvNote}</td>
      <td>${holder}</td>
      <td>${esc(locationLabel(v.locationId, v.departmentId))}</td>
      <td style="text-align:right">${icons.arrowRight}</td>
      <td><button class="btn btn-outline" data-act="qr-label">${icons.printer} Nyomtatás</button></td>
    </tr>`;
}