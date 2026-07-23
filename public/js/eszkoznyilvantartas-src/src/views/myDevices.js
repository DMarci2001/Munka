// ============================================================
// Nálam lévő eszközök + saját foglalások
// ============================================================

import { getDevices, currentUser, currentRole, roleAtLeast, activeReservations, myPendingTransfers, getDevice, getUser, getUsers, getLocations, getDepartments, batchTransfer, batchCheckIn } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import { statusBadge, locationLabel, fmtRelative, fmtDateTime, esc } from '../lib/format.js';
import { icons, openModal, toast } from '../ui/components.js';
import { enhanceSelects } from '../ui/searchableSelect.js';
import * as A from '../ui/actions.js';
import { fitTableToWidth, watchFitToWidth } from '../ui/fitToWidth.js';
import { createBulkSelection, renderActionBar, injectCheckboxColumn, summarizeBatchResults } from '../ui/bulkSelect.js';

// tömeges átadás/leadás — device_id-alapú kijelölés, csak a nálam lévő eszközökre
const bulk = createBulkSelection();
let bulkMode = false;

export function renderMyDevices(el) {
  const me = currentUser();
  const canOut = roleAtLeast(currentRole(), 'storekeeper') || !!me.can_check_out;
  const vms = getDevices().map(deviceVM);
  const held = vms.filter((v) => v.holderId === me.id);
  const heldIds = new Set(held.map((v) => v.dev.device_id));
  const reserved = vms.filter((v) => v.reservation && v.reservation.reserved_by === me.id);
  const incoming = myPendingTransfers();

  el.innerHTML = `
    <div class="content">
      ${incoming.length ? `
      <h3 class="section-title">Rám váró átvételek</h3>
      <div class="table-wrap" style="margin-bottom:26px">
        <table class="grid">
          <thead><tr><th>Típus / modell</th><th>Küldő</th><th>Átadás időpontja</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${incoming.map((ev) => {
              const dev = getDevice(ev.device_id);
              const sender = getUser(ev.from_user_id);
              return `
              <tr data-dev="${ev.device_id}">
                <td>${esc(dev?.asset_tag || '')}<div class="cell-sub">${esc(dev?.manufacturer || '')} ${esc(dev?.model || '')}</div></td>
                <td>${esc(sender?.full_name || '—')}</td>
                <td>${fmtDateTime(ev.event_timestamp)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    <button class="btn btn-success btn-sm" data-transfer-confirm="${ev.event_id}">${icons.check} Elfogad</button>
                    <button class="btn btn-danger btn-sm" data-transfer-reject="${ev.event_id}">${icons.x} Elutasít</button>
                  </div>
                </td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>` : ''}

      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <h3 class="section-title" style="margin:0">Eszközök a birtokomban</h3>
        ${held.length ? `<button class="btn btn-outline btn-sm" id="btn-bulk-toggle">${bulkMode ? 'Tömeges átadás/leadás — kilépés' : 'Tömeges átadás / leadás'}</button>` : ''}
      </div>
      <div id="bulk-bar" class="bulk-action-bar-slot"></div>
      ${held.length ? `
      <div class="table-wrap" style="margin-bottom:26px">
        <table class="grid">
          <thead><tr><th>Kivétel időpontja</th><th>Típus / modell</th><th>Hely</th><th>Státusz</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${held.map((v) => `
              <tr data-dev="${v.dev.device_id}">
                <td><span class="tag-mono">${esc(v.lastCheckout ? new Date(v.lastCheckout.event_timestamp).toISOString().slice(0, 10) : null)}</span></td>
                <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
                <td>${esc(locationLabel(v.locationId, v.departmentId))}</td>
                <td>${statusBadge(v.status)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    ${v.status === 'Kiadva' ? `<button class="btn btn-primary btn-sm" data-act="checkin" data-id="${v.dev.device_id}">Leadás</button>
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
          <thead><tr><th>Foglalás időpontja</th><th>Típus / modell</th><th>Lejár</th><th style="text-align:right"> </th></tr></thead>
          <tbody>
            ${reserved.map((v) => `
              <tr data-dev="${v.dev.device_id}">
                <td><span class="tag-mono">${esc(v.lastReserved ? new Date(v.lastReserved.event_timestamp).toISOString().slice(0, 10) : null)}</span></td>
                <td>${esc(v.typeName)}<div class="cell-sub">${esc(v.dev.manufacturer)} ${esc(v.dev.model)}</div></td>
                <td>${fmtRelative(v.reservation.expires_at)}</td>
                <td style="text-align:right">
                  <div class="row-actions" style="justify-content:flex-end">
                    ${canOut ? `<button class="btn btn-primary btn-sm" data-act="checkout" data-id="${v.dev.device_id}">Kivétel</button>` : ''}
                    <button class="btn btn-outline btn-sm" data-act="cancel" data-id="${v.dev.device_id}">Lemondás</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>` : `<div class="muted" style="font-size:.9rem">Nincs aktív foglalásod.</div>`}
    </div>`;

  el.querySelectorAll('tbody tr').forEach((r) =>
    r.addEventListener('click', (e) => { if (!e.target.closest('button')) navigate('/device/' + r.dataset.dev); }));
  el.querySelectorAll('[data-act]').forEach((b) =>
    b.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = Number(b.dataset.id);
      ({ checkin: A.dlgCheckIn, transfer: A.dlgTransfer, checkout: A.dlgCheckOut, cancel: A.doCancelReservation })[b.dataset.act]?.(id);
    }));
  el.querySelectorAll('[data-transfer-confirm]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.doConfirmTransfer(Number(b.dataset.transferConfirm)); }));
  el.querySelectorAll('[data-transfer-reject]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.dlgRejectTransfer(Number(b.dataset.transferReject)); }));
  el.querySelector('#browse')?.addEventListener('click', () => navigate('/inventory'));

  const btnBulkToggle = el.querySelector('#btn-bulk-toggle');
  if (btnBulkToggle) btnBulkToggle.addEventListener('click', () => {
    bulkMode = !bulkMode;
    if (!bulkMode) bulk.clear();
    renderMyDevices(el);
  });
  const bulkBar = el.querySelector('#bulk-bar');
  if (bulkBar) {
    renderActionBar(bulkBar, bulk, {
      label: 'Tömeges átadás / leadás',
      finalizeText: 'Következő',
      onFinalize: (ids) => openBulkTransferOrCheckinDialog(ids),
    });
  }

  if (bulkMode) {
    el.querySelectorAll('.table-wrap').forEach((tw) => injectCheckboxColumn(tw, bulk, (id) => heldIds.has(id)));
  }

  el.querySelectorAll('.table-wrap').forEach((tw) => {
    watchFitToWidth(tw);
    fitTableToWidth(tw);
  });
}

function openBulkTransferOrCheckinDialog(deviceIds) {
  openModal({
    title: `Tömeges átadás / leadás (${deviceIds.length} eszköz)`,
    closeOnBackdrop: false,
    bodyHTML: `
      <div class="field">
        <label class="form-label">Művelet</label>
        <select class="form-select" name="mode">
          <option value="transfer">Átadás — másik felhasználónak</option>
          <option value="checkin">Leadás — raktárba / helyiségbe</option>
        </select>
      </div>
      <div data-mode-fields="transfer" class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user"></select>
      </div>
      <div data-mode-fields="checkin" class="field" style="display:none">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location"></select>
        <label class="form-label" style="margin-top:8px">Hová — raktár / részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,
    confirmText: 'Véglegesítés',
    onMount: (root) => {
      const modeSel = root.querySelector('[name=mode]');
      const userSel = root.querySelector('[name=to_user]');
      const locSel = root.querySelector('[name=to_location]');
      const deptSel = root.querySelector('[name=to_dept]');
      const me = currentUser();
      userSel.innerHTML = getUsers().filter((u) => u.id !== me.id).map((u) => `<option value="${u.id}">${esc(u.full_name)}</option>`).join('');
      locSel.innerHTML = getLocations().map((l) => `<option value="${l.id}">${esc(l.address)}</option>`).join('');
      const fillDept = () => {
        const list = getDepartments().filter((d) => d.locations_id === Number(locSel.value) && d.type === 'raktár');
        deptSel.innerHTML = list.length
          ? list.map((d) => `<option value="${d.id}">${esc(d.name)}</option>`).join('')
          : '<option value="">— nincs raktár-részleg ezen a helyszínen —</option>';
      };
      locSel.addEventListener('change', fillDept);
      fillDept();
      const syncMode = () => {
        root.querySelectorAll('[data-mode-fields]').forEach((f) => {
          f.style.display = f.dataset.modeFields === modeSel.value ? '' : 'none';
        });
      };
      modeSel.addEventListener('change', syncMode);
      syncMode();
      enhanceSelects(root);
    },
    onConfirm: async (root) => {
      const mode = root.querySelector('[name=mode]').value;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      let results;
      if (mode === 'transfer') {
        const to_user_id = Number(root.querySelector('[name=to_user]').value);
        results = await batchTransfer(deviceIds, to_user_id, notes);
      } else {
        const to_location_id = Number(root.querySelector('[name=to_location]').value);
        const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
        results = await batchCheckIn(deviceIds, to_location_id, to_department_id, null, notes);
      }
      summarizeBatchResults(results, toast);
      bulk.clear();
      bulkMode = false;
    },
  });
}
