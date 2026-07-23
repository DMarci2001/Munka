// ============================================================
// Művelet-dialógusok: a UI gombok ezeket hívják, a store-on
// keresztül futtatva a birtoklási és foglalási műveleteket.
// ============================================================

import {
  moveAsset, reserveDevice, cancelReservation, confirmCheckIn, rejectCheckIn,
  confirmTransfer, rejectTransfer, resolveRejectedTransfer,
  sendToRepair, returnFromRepair, markLost, markFound, retireDevice,
  getDepartments, getLocations, getUsers, getDevice, currentUser, currentRole,
  currentState, roleAtLeast, isStorageDept,
} from '../state/store.js';
import { openModal, toast } from './components.js';
import { locationLabel, esc } from '../lib/format.js';
import { enhanceSelects } from './searchableSelect.js';

// --- Form mező-építők ----------------------------------------

function locOptions(selectedId = null) {
  return getLocations()
    .map((l) => `<option value="${l.id}" ${l.id === selectedId ? 'selected' : ''}>${esc(l.address)}</option>`)
    .join('');
}
function userOptions(excludeId = null, selectedId = null) {
  return getUsers()
    .filter((u) => u.id !== excludeId)
    .map((u) => `<option value="${u.id}" ${u.id === selectedId ? 'selected' : ''}>${esc(u.full_name)}</option>`)
    .join('');
}
function conditionField(value = 'Jó') {
  const opts = ['Jó', 'Kopott', 'Hibás', 'Ismeretlen'];
  return `<select class="form-select" name="condition">${opts.map((o) => `<option ${o === value ? 'selected' : ''}>${o}</option>`).join('')}</select>`;
}

// Helyszín→részleg kaszkád: a [name=to_location] változására feltölti a
// [name=to_dept] listát az adott helyszín részlegeivel. A `prefer` predikátum
// jelöli ki az alapértelmezett részleget. Ha `fallbackToFirst` false és a
// `prefer` nem talál egyezést, a részleg üresen marad (nem esik vissza az
// adott helyszín első, esetleg oda nem illő részlegére).
function wireLocationDept(root, prefer = () => false, { fallbackToFirst = true } = {}) {
  const locSel = root.querySelector('[name=to_location]');
  const deptSel = root.querySelector('[name=to_dept]');
  const fill = () => {
    const list = getDepartments().filter((d) => d.locations_id === Number(locSel.value));
    const matched = list.find(prefer);
    const pick = matched || (fallbackToFirst ? list[0] : undefined);
    if (!list.length) {
      deptSel.innerHTML = '<option value="">— nincs részleg ezen a helyszínen —</option>';
    } else if (!pick) {
      deptSel.innerHTML = ['<option value="">— válassz részleget —</option>']
        .concat(list.map((d) => `<option value="${d.id}">${esc(d.name)}</option>`))
        .join('');
    } else {
      deptSel.innerHTML = list.map((d) => `<option value="${d.id}" ${d.id === pick.id ? 'selected' : ''}>${esc(d.name)}</option>`).join('');
    }
  };
  locSel.addEventListener('change', fill);
  fill();
  enhanceSelects(root);
}

// --- Kivétel (check_out) -------------------------------------
export function dlgCheckOut(deviceId) {
  const dev = getDevice(deviceId);
  const role = currentRole();
  const onBehalf = roleAtLeast(role, 'storekeeper');
  const me = currentUser();
  openModal({
    title: `Eszköz kivétele · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    closeOnBackdrop: false,
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
        <select class="form-select" name="to_dept"></select>
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
    // Használati helyet (nem raktárt) választunk előre: raktárba téve a moveAsset
    // birtokos nélkül készletbe rakná, és az eszköz tévesen „Kivehető" maradna.
    onMount: (root) => wireLocationDept(root, (d) => d.type !== 'raktár'),
    onConfirm: async (root) => {
      const to_user_id = onBehalf ? Number(root.querySelector('[name=to_user]').value) : me.id;
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      if (isStorageDept(to_department_id)) {
        toast('Kivételkor használati helyet (nem raktárt) válassz — a raktár a készletet jelenti.', 'error');
        return false;
      }
      const ret = root.querySelector('[name=ret]').value;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      await moveAsset({ device_id: deviceId, event_type: 'check_out', to_user_id, to_locations_id: to_location_id, to_departments_id: to_department_id, expected_return_date: ret || null, notes });
      toast('Eszköz kivéve.', 'success');
    },
  });
}

// --- Visszavétel (check_in) ----------------------------------
// --- Visszavétel / Leadás (check_in) -------------------------
export function dlgCheckIn(deviceId) {
  const dev = getDevice(deviceId);
  const pending = currentRole() === 'user';
  openModal({
    title: `Eszköz leadása · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    closeOnBackdrop: false,
    bodyHTML: `
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${locOptions()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — raktár / részleg (opcionális)</label>
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
    onMount: (root) => wireLocationDept(root, (d) => d.type === 'raktár'),
    onConfirm: async (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      const condition_at_event = root.querySelector('[name=condition]').value;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      await moveAsset({ device_id: deviceId, event_type: 'check_in', to_locations_id: to_location_id, to_departments_id: to_department_id, condition_at_event, notes });
      toast(pending ? 'Visszavétel folyamatban — raktáros megerősítésére vár.' : 'Eszköz visszavéve.', 'success');
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
        <div class="hint">Az eszköz közvetlenül az új birtokoshoz kerül; a helye változatlan marad.</div>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés (opcionális)</label>
        <input type="text" class="form-control" name="notes" />
      </div>`,
    confirmText: 'Átadás',
    onMount: (root) => enhanceSelects(root),
    onConfirm: async (root) => {
      const to_user_id = Number(root.querySelector('[name=to_user]').value);
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      const pending = currentRole() === 'user';
      // Átadás = birtokosváltás: a hely/részleg az aktuális állapotból öröklődik (nem raktár),
      // így a moveAsset megőrzi a birtokost és „Kiadva" marad — nem esik vissza „Kivehető"-re.
      await moveAsset({ device_id: deviceId, event_type: 'transfer', to_user_id, to_locations_id: cur.location, to_departments_id: cur.department, notes });
      toast(pending ? 'Átadás folyamatban — az átvevő megerősítésére vár.' : 'Eszköz átadva.', 'success');
    },
  });
}

// --- Raktármozgatás (stock_transfer) — storekeeper -----------
export function dlgStockTransfer(deviceId) {
  const dev = getDevice(deviceId);
  const cur = currentState(deviceId);
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
    onMount: (root) => wireLocationDept(root, (d) => d.type === 'raktár'),
    onConfirm: async (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      await moveAsset({ device_id: deviceId, event_type: 'stock_transfer', to_locations_id: to_location_id, to_departments_id: to_department_id, notes });
      toast('Készlet áthelyezve.', 'success');
    },
  });
}

// --- Foglalás ------------------------------------------------
export async function doReserve(deviceId) {
  try {
    await reserveDevice(deviceId);
    toast('Eszköz lefoglalva (3 napig).', 'success');
  } catch (e) { toast(e.message, 'error'); }
}
export async function doCancelReservation(deviceId) {
  try {
    await cancelReservation(deviceId);
    toast('Foglalás lemondva.', 'success');
  } catch (e) { toast(e.message, 'error'); }
}

// --- Checkpoint: megerősítés / elutasítás --------------------
export async function doConfirmCheckIn(eventId) {
  try { await confirmCheckIn(eventId); toast('Visszavétel megerősítve.', 'success'); }
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
    onConfirm: async (root) => {
      const reason = root.querySelector('[name=reason]').value.trim();
      if (!reason) { toast('Adj meg indokot.', 'error'); return false; }
      await rejectCheckIn(eventId, reason);
      toast('Visszavétel elutasítva.', 'success');
    },
  });
}

// --- Átadás: megerősítés / elutasítás (az átvevő oldaláról) --
export async function doConfirmTransfer(eventId) {
  try { await confirmTransfer(eventId); toast('Átadás megerősítve.', 'success'); }
  catch (e) { toast(e.message, 'error'); }
}
export function dlgRejectTransfer(eventId) {
  openModal({
    title: 'Átadás elutasítása',
    bodyHTML: `
      <p class="muted" style="margin-top:0">Nem vetted át fizikailag az eszközt? Az elutasítással a birtoklás a küldőnél marad — a raktáros dönthet felülbírálásról.</p>
      <div class="field">
        <label class="form-label">Indok (kötelező)</label>
        <input type="text" class="form-control" name="reason" placeholder="pl. nem kaptam meg az eszközt" />
      </div>`,
    confirmText: 'Elutasítás',
    confirmClass: 'btn-danger',
    onConfirm: async (root) => {
      const reason = root.querySelector('[name=reason]').value.trim();
      if (!reason) { toast('Adj meg indokot.', 'error'); return false; }
      await rejectTransfer(eventId, reason);
      toast('Átadás elutasítva.', 'success');
    },
  });
}

// --- Átadás: raktáros döntése egy már elutasított átadásról ---
export async function doAcceptRejection(eventId) {
  try { await resolveRejectedTransfer(eventId, true); toast('Elutasítás elfogadva.', 'success'); }
  catch (e) { toast(e.message, 'error'); }
}
export function dlgOverrideRejection(eventId) {
  openModal({
    title: 'Átadás felülbírálása',
    bodyHTML: `
      <p class="muted" style="margin-top:0">Az átvevő elutasította az átadást, de raktárosként felülbírálhatod: az átadás ekkor mégis végbemegy, az átvevő jóváhagyása nélkül.</p>`,
    confirmText: 'Felülbírálás — átadás végrehajtása',
    confirmClass: 'btn-danger',
    onConfirm: async () => {
      await resolveRejectedTransfer(eventId, false);
      toast('Átadás felülbírálva és végrehajtva.', 'success');
    },
  });
}

// --- Javítás / elveszett / selejt — storekeeper --------------
export function dlgSendToRepair(deviceId) {
  openModal({
    title: 'Szervizbe küldés',
    bodyHTML: `
      <div class="field">
        <label class="form-label">Hibaleírás</label>
        <input class="form-control" name="notes" placeholder="pl. nem kapcsol be" />
      </div>
      <div class="field">
        <label class="form-label">Hová — helyszín</label>
        <select class="form-select" name="to_location">${locOptions()}</select>
      </div>
      <div class="field">
        <label class="form-label">Hová — részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>`,
    confirmText: 'Szervizbe',
    onMount: (root) => wireLocationDept(root, (d) => d.type === 'műhely', { fallbackToFirst: false }),
    onConfirm: async (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]').value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      await sendToRepair(deviceId, to_location_id, to_department_id, notes);
      toast('Szervizbe küldve.', 'success');
    },
  });
}

export function dlgReturnFromRepair(deviceId) {
  openModal({
    title: 'Szervizelve',
    bodyHTML: `
      <div class="field">
        <label class="form-label">Helyszín</label>
        <select class="form-select" name="to_location">${locOptions()}</select>
      </div>
      <div class="field">
        <label class="form-label">Részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés</label>
        <input class="form-control" name="notes" placeholder="pl. javítva" />
      </div>`,
    confirmText: 'Visszahelyezés',
    onMount: (root) => wireLocationDept(root),
    onConfirm: async (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      await returnFromRepair(deviceId, to_location_id, to_department_id, notes);
      toast('Javítva visszahelyezve.', 'success');
    },
  });
}

export function dlgMarkLost(deviceId) {
  openModal({
    title: 'Elveszettnek jelölés',
    bodyHTML: `<div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" name="notes" placeholder="pl. nem található 2 hete" /></div>`,
    confirmText: 'Elveszett',
    confirmClass: 'btn-danger',
    onConfirm: async (root) => { await markLost(deviceId, root.querySelector('[name=notes]').value.trim() || null); toast('Elveszettnek jelölve.', 'success'); },
  });
}

export function dlgMarkFound(deviceId) {
  openModal({
    title: 'Találtnak jelölés',
    bodyHTML: `<div class="field">
        <label class="form-label">Helyszín</label>
        <select class="form-select" name="to_location">${locOptions()}</select>
      </div>
      <div class="field">
        <label class="form-label">Részleg</label>
        <select class="form-select" name="to_dept"></select>
      </div>
      <div class="field">
        <label class="form-label">Megjegyzés</label>
        <input class="form-control" name="notes" placeholder="pl. javítva" />
      </div>`,
    confirmText: 'Visszahelyezés',
    onMount: (root) => wireLocationDept(root),
    onConfirm: async (root) => {
      const to_location_id = Number(root.querySelector('[name=to_location]')?.value);
      const to_department_id = Number(root.querySelector('[name=to_dept]').value) || null;
      const notes = root.querySelector('[name=notes]').value.trim() || null;
      await markFound(deviceId, to_location_id, to_department_id, notes);
      toast('Találtnak jelölve.', 'success'); },
  });
}
export function dlgRetire(deviceId) {
  openModal({
    title: 'Eszköz selejtezése',
    bodyHTML: `<p class="muted" style="margin-top:0">Lágy törlés: az előzmény megmarad, az eszköz „Selejtezve" státuszba kerül.</p>
      <div class="field"><label class="form-label">Indok</label><input class="form-control" name="reason" placeholder="pl. nem javítható" /></div>`,
    confirmText: 'Selejtezés',
    confirmClass: 'btn-danger',
    onConfirm: async (root) => { await retireDevice(deviceId, root.querySelector('[name=reason]').value.trim() || null); toast('Eszköz selejtezve.', 'success'); },
  });
}
