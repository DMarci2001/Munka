// ============================================================
// Művelet-dialógusok: a UI gombok ezeket hívják, a store-on
// keresztül futtatva a birtoklási és foglalási műveleteket.
// ============================================================

import {
  moveAsset, reserveDevice, cancelReservation, confirmCheckIn, rejectCheckIn,
  sendToRepair, markLost, retireDevice,
  getDepartments, getLocations, getUsers, getDevice, getDeviceType, currentUser, currentRole,
  currentState, roleAtLeast, activeReservation,
} from '../state/store.js';
import { openModal, toast } from './components.js';
import { locationLabel, holderLabel, statusLabel, esc } from '../lib/format.js';

// --- Form mező-építők ----------------------------------------

function locOptions(selectedId = null) {
  return getLocations()
    .map((l) => `<option value="${l.id}" ${l.id === selectedId ? 'selected' : ''}>${esc(l.address)}</option>`)
    .join('');
}
function deptOptions(selectedId = null) {
  return getDepartments()
    .map((d) => `<option value="${d.id}" ${d.id === selectedId ? 'selected' : ''}>${esc(d.name)}</option>`)
    .join('');
}
function userOptions(excludeId = null, selectedId = null) {
  return getUsers()
    .filter((u) => u.id !== excludeId)
    .map((u) => `<option value="${u.id}" ${u.id === selectedId ? 'selected' : ''}>${esc(u.full_name)} — ${esc(u.title)}</option>`)
    .join('');
}
function conditionField(value = 'Jó') {
  const opts = ['Jó', 'Kopott', 'Hibás', 'Ismeretlen'];
  return `<select class="form-select" name="condition">${opts.map((o) => `<option ${o === value ? 'selected' : ''}>${o}</option>`).join('')}</select>`;
}

// --- Kivétel (check_out) -------------------------------------
export function dlgCheckOut(deviceId) {
  const dev = getDevice(deviceId);
  const role = currentRole();
  const onBehalf = roleAtLeast(role, 'storekeeper');
  const me = currentUser();
  openModal({
    title: `Eszköz kivétele · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    bodyHTML: `
      ${onBehalf ? `
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${userOptions(null, me.id)}</select>
        <div class="hint">Raktárosként más nevében is kiadhatsz eszközt.</div>
      </div>` : `
      <div class="alert-soft" style="margin-bottom:15px">Az eszközt <strong>magadnak</strong> veszed ki: ${esc(me.full_name)}.</div>`}
      <div class="field">
        <label class="form-label">Hová (osztály / felhasználási hely)</label>
        <select class="form-select" name="to_location">${locOptions()}</select>
        <select class="form-select" name="to_dept">${deptOptions()}</select>
      </div>
      <div class="field">
        <label class="form-label">Várható visszahozatal (opcionális)</label>
        <input type="date" class="form-control" name="ret" />
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" placeholder="pl. kihelyezés a Kardiológiára" />
      </div>`,
    confirmText: 'Kivétel',
    onMount: (root) => {
      const locSel = root.querySelector('[name=to_location]');
      const deptSel = root.querySelector('[name=to_dept]');
      const fillDepts = () => {
        const locId = Number(locSel.value);
        const list = allDepts.filter((d) => d.locations_id === locId);
        deptSel.innerHTML = list.length
          ? list.map((d) => `<option value="${d.id}" ${d.type === 'raktár' ? 'selected' : ''}>${esc(d.name)}</option>`).join('')
          : '<option value="">— nincs részleg ezen a helyszínen —</option>';
      };
      locSel.addEventListener('change', fillDepts);
      fillDepts();
    },
    onConfirm: (root) => {
      const to_user_id = onBehalf ? Number(root.querySelector('[name=to_user]').value) : me.id;
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value);
      const ret = root.querySelector('[name=ret]').value;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      moveAsset({ device_id: deviceId, event_type: 'check_out', to_user_id, to_location_id, to_department_id, expected_return_date: ret ? new Date(ret) : null, notes });
      toast('Eszköz kivéve.', 'success');
    },
  });
}

// --- Visszavétel (check_in) ----------------------------------
// --- Visszavétel / Leadás (check_in) -------------------------
export function dlgCheckIn(deviceId) {
  const dev = getDevice(deviceId);
  const role = currentRole();
  const pending = role === 'user';
  const allDepts = getDepartments();
  openModal({
    title: `Eszköz visszavétele · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    bodyHTML: `
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${locOptions()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — raktár / részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Állapot</label>
        ${conditionField(dev.condition)}
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" placeholder="pl. minden tartozékkal" />
      </div>
      ${pending ? `<div class="alert-warn-soft">A leadás <strong>raktáros megerősítésére</strong> vár, mielőtt az eszköz ismét kiadhatóvá válik.</div>` : ''}`,
    confirmText: 'Leadás',
    onMount: (root) => {
      const locSel = root.querySelector('[name=to_location]');
      const deptSel = root.querySelector('[name=to_dept]');
      const fillDepts = () => {
        const locId = Number(locSel.value);
        const list = allDepts.filter((d) => d.locations_id === locId);
        deptSel.innerHTML = list.length
          ? list.map((d) => `<option value="${d.id}" ${d.type === 'raktár' ? 'selected' : ''}>${esc(d.name)}</option>`).join('')
          : '<option value="">— nincs részleg ezen a helyszínen —</option>';
      };
      locSel.addEventListener('change', fillDepts);
      fillDepts();
    },
    onConfirm: (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value);
      if (!to_department_id) { toast('Ezen a helyszínen nincs választható részleg.', 'error'); return false; }
      const condition_at_event = root.querySelector('[name=condition]').value;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      moveAsset({ device_id: deviceId, event_type: 'check_in', to_locations_id: to_location_id, to_departments_id: to_department_id, condition_at_event, notes });
      toast(pending ? 'Leadva — raktáros megerősítésére vár.' : 'Eszköz visszavéve.', 'success');
    },
  });
}

// --- Átadás (transfer) ---------------------------------------
export function dlgTransfer(deviceId) {
  const dev = getDevice(deviceId);
  const cur = currentState(deviceId);
  openModal({
    title: `Eszköz átadása · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    bodyHTML: `
      <div class="field">
        <label class="form-label">Kinek</label>
        <select class="form-select" name="to_user">${userOptions(cur.holder)}</select>
      </div>
      <div class="field">
        <label class="form-label">Hol (osztály)</label>
        <select class="form-select" name="to_location">${locOptions(cur.location)}</select>
        <select class="form-select" name="to_dept">${deptOptions(cur.department)}</select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,
    confirmText: 'Átadás',
    onConfirm: (root) => {
      const to_user_id = Number(root.querySelector('[name=to_user]').value);
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value);
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      moveAsset({ device_id: deviceId, event_type: 'transfer', to_user_id, to_location_id, to_department_id, notes });
      toast('Eszköz átadva.', 'success');
    },
  });
}

// --- Raktármozgatás (stock_transfer) — storekeeper -----------
export function dlgStockTransfer(deviceId) {
  const dev = getDevice(deviceId);
  const cur = currentState(deviceId);
  const allDepts = getDepartments();
  openModal({
    title: `Raktármozgatás · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    bodyHTML: `
      <div class="field">
        <label class="form-label">Honnan</label>
        <input class="form-control" value="${esc(locationLabel(cur.location, cur.department))}" disabled />
      </div>
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${locOptions(cur.location)}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,
    confirmText: 'Mozgatás',
    onMount: (root) => {
      const locSel = root.querySelector('[name=to_location]');
      const deptSel = root.querySelector('[name=to_dept]');
      const fillDepts = () => {
        const locId = Number(locSel.value);
        const list = allDepts.filter((d) => d.locations_id === locId);
        deptSel.innerHTML = list.length
          ? list.map((d) => `<option value="${d.id}" ${d.type === 'raktár' ? 'selected' : ''}>${esc(d.name)}</option>`).join('')
          : '<option value="">— nincs részleg ezen a helyszínen —</option>';
      };
      locSel.addEventListener('change', fillDepts);
      fillDepts();
    },
    onConfirm: (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value);
      if (!to_department_id) { toast('Ezen a helyszínen nincs választható részleg.', 'error'); return false; }
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      moveAsset({ device_id: deviceId, event_type: 'stock_transfer', to_locations_id: to_location_id, to_departments_id: to_department_id, notes });
      toast('Készlet áthelyezve.', 'success');
    },
  });
}

// --- Foglalás ------------------------------------------------
export function doReserve(deviceId) {
  try {
    reserveDevice(deviceId);
    toast('Eszköz lefoglalva (3 napig).', 'success');
  } catch (e) { toast(e.message, 'error'); }
}
export function doCancelReservation(deviceId) {
  try {
    cancelReservation(deviceId);
    toast('Foglalás lemondva.', 'success');
  } catch (e) { toast(e.message, 'error'); }
}

// --- Checkpoint: megerősítés / elutasítás --------------------
export function doConfirmCheckIn(eventId) {
  try { confirmCheckIn(eventId); toast('Visszavétel megerősítve.', 'success'); }
  catch (e) { toast(e.message, 'error'); }
}
export function dlgRejectCheckIn(eventId) {
  openModal({
    title: 'Visszavétel elutasítása',
    bodyHTML: `
      <p class="muted" style="margin-top:0">Az eszköz nincs fizikailag a megadott helyen? Az elutasítással a birtoklás a felhasználónál marad.</p>
      <div class="field">
        <label class="form-label">Indok (kötelező)</label>
        <input type="text" class="form-control" name="reason" placeholder="pl. nincs a raktárban" />
      </div>`,
    confirmText: 'Elutasítás',
    confirmClass: 'btn-danger',
    onConfirm: (root) => {
      const reason = root.querySelector('[name=reason]').value.trim();
      if (!reason) { toast('Adj meg indokot.', 'error'); return false; }
      rejectCheckIn(eventId, reason);
      toast('Visszavétel elutasítva.', 'success');
    },
  });
}

// --- Javítás / elveszett / selejt — storekeeper --------------
export function dlgSendToRepair(deviceId) {
  openModal({
    title: 'Javításba küldés',
    bodyHTML: `<div class="field"><label class="form-label">Hibaleírás</label><input class="form-control" name="notes" placeholder="pl. nem kapcsol be" /></div>`,
    confirmText: 'Javításba',
    onConfirm: (root) => { sendToRepair(deviceId, root.querySelector('[name=notes]').value.trim() || null); toast('Javításba küldve.', 'success'); },
  });
}
export function dlgMarkLost(deviceId) {
  openModal({
    title: 'Elveszettnek jelölés',
    bodyHTML: `<div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" name="notes" placeholder="pl. nem található 2 hete" /></div>`,
    confirmText: 'Elveszett',
    confirmClass: 'btn-danger',
    onConfirm: (root) => { markLost(deviceId, root.querySelector('[name=notes]').value.trim() || null); toast('Elveszettnek jelölve.', 'success'); },
  });
}
export function dlgRetire(deviceId) {
  openModal({
    title: 'Eszköz selejtezése',
    bodyHTML: `<p class="muted" style="margin-top:0">Lágy törlés: az előzmény megmarad, az eszköz „Selejtezve" státuszba kerül.</p>
      <div class="field"><label class="form-label">Indok</label><input class="form-control" name="reason" placeholder="pl. nem javítható" /></div>`,
    confirmText: 'Selejtezés',
    confirmClass: 'btn-danger',
    onConfirm: (root) => { retireDevice(deviceId, root.querySelector('[name=reason]').value.trim() || null); toast('Eszköz selejtezve.', 'success'); },
  });
}
