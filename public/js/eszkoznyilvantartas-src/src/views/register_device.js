// ============================================================
// Új eszköz regisztrálása + meglévő szerkesztése
// A típusspecifikus mezők az attribute_definitions-ból generálódnak.
// ============================================================

import {
  getDeviceTypes, getAttrDefs, getLocations, getDepartments, getDeviceType,
  registerDevice, editDevice, getDevice, currentRole, roleAtLeast,
} from '../state/store.js';
import { navigate } from '../lib/router.js';
import { openModal, toast } from '../ui/components.js';
import { esc } from '../lib/format.js';
import { icons } from '../ui/components.js';
import { enhanceSelects } from '../ui/searchableSelect.js';

// ---- Dinamikus attribútum-mezők -----------------------------
function attrFieldHTML(def, value) {
  const v = value ?? '';
  const req = def.is_required ? '<span style="color:#c0392b">*</span>' : '';
  const label = `<label class="form-label">${esc(def.label)} ${req}</label>`;
  let input;
  if (def.data_type === 'enum') {
    const opts = (def.options || '').split(',').map((o) => o.trim());
    input = `<select class="form-select" data-attr="${def.attribute_key}">
      <option value="">— válassz —</option>
      ${opts.map((o) => `<option ${o === v ? 'selected' : ''}>${esc(o)}</option>`).join('')}
    </select>`;
  } else if (def.data_type === 'boolean') {
    input = `<select class="form-select" data-attr="${def.attribute_key}">
      <option value="" ${v === '' ? 'selected' : ''}>—</option>
      <option value="true" ${v === true || v === 'true' ? 'selected' : ''}>Igen</option>
      <option value="false" ${v === false || v === 'false' ? 'selected' : ''}>Nem</option>
    </select>`;
  } else if (def.data_type === 'date') {
    input = `<input type="date" class="form-control" data-attr="${def.attribute_key}" value="${esc(v)}" />`;
  } else if (def.data_type === 'integer' || def.data_type === 'decimal') {
    input = `<input type="number" class="form-control" data-attr="${def.attribute_key}" value="${esc(v)}" ${def.data_type === 'integer' ? 'step="1"' : 'step="any"'} />`;
  } else {
    input = `<input type="text" class="form-control" data-attr="${def.attribute_key}" value="${esc(v)}" />`;
  }
  return `<div class="field" data-type="${def.data_type}" data-required="${def.is_required}">${label}${input}</div>`;
}

function collectAttrs(scope) {
  const attrs = {};
  let error = null;
  scope.querySelectorAll('[data-attr]').forEach((inp) => {
    const key = inp.dataset.attr;
    const field = inp.closest('.field');
    const type = field.dataset.type;
    const required = field.dataset.required === 'true';
    let raw = inp.value.trim();
    if (raw === '') {
      if (required) error = error || `Kötelező mező hiányzik: ${field.querySelector('.form-label').textContent.replace('*', '').trim()}`;
      return;
    }
    if (type === 'integer' || type === 'decimal') attrs[key] = Number(raw);
    else if (type === 'boolean') attrs[key] = raw === 'true';
    else attrs[key] = raw;
  });
  return { attrs, error };
}

// ---- Új eszköz oldal ----------------------------------------
export function renderRegister(el) {
  if (!roleAtLeast(currentRole(), 'storekeeper')) {
    el.innerHTML = `<div class="content"><div class="empty"><div class="big">${icons.warning}</div><div>Új eszköz regisztrálásához raktáros vagy IT-admin szerepkör kell.</div></div></div>`;
    return;
  }
  const types = getDeviceTypes();
  const locs = getLocations();
  const depts = getDepartments();

  el.innerHTML = `
    <div class="content" style="max-width:760px">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${icons.back} Vissza</button>
      <div class="panel">
        <div class="panel-head">Új eszköz regisztrálása</div>
        <div class="panel-body">
          <div class="field">
            <label class="form-label">Eszköztípus *</label>
            <select class="form-select" id="r-type">
              <option value="">— válassz típust —</option>
              ${types.map((t) => `<option value="${t.id}">${esc(t.type)}</option>`).join('')}
            </select>
          </div>
          <div id="r-common" style="display:none">
            <div class="divider"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
              <div class="field"><label class="form-label">Leltári azonosító *</label><input class="form-control" id="r-tag" placeholder="pl. BUD-LAP-00099" /></div>
              <div class="field"><label class="form-label">Sorozatszám</label><input class="form-control" id="r-serial" /></div>
              <div class="field"><label class="form-label">Gyártó</label><input class="form-control" id="r-manu" /></div>
              <div class="field"><label class="form-label">Modell</label><input class="form-control" id="r-model" /></div>
              <div class="field"><label class="form-label">Állapot</label>
                <select class="form-select" id="r-cond">${['Jó', 'Kopott', 'Hibás'].map((o) => `<option>${o}</option>`).join('')}</select>
              </div>
              <div class="field"><label class="form-label">Kezdeti elhelyezés *</label>
                <select class="form-select" id="r-loc">${locs.map((l) => `<option value="${l.id}">${esc(l.address)}</option>`).join('')}</select>
                <select class="form-select" id="r-dept"></select>
              </div>
            </div>
            <div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" id="r-notes" /></div>
            <div class="divider"></div>
            <div class="form-label" style="margin-bottom:10px; font-size:.9rem; color:var(--ink)">Típusspecifikus adatok</div>
            <div id="r-attrs" style="display:grid; grid-template-columns:1fr 1fr; gap:14px"></div>
            <div class="divider"></div>
            <div style="display:flex; gap:10px; justify-content:flex-end">
              <button class="btn btn-outline" id="r-cancel">Mégse</button>
              <button class="btn btn-primary" id="r-save">${icons.register} Eszköz létrehozása</button>
            </div>
          </div>
        </div>
      </div>
    </div>`;

  el.querySelector('#back').addEventListener('click', () => navigate('/inventory'));
  el.querySelector('#r-cancel').addEventListener('click', () => navigate('/inventory'));

  const typeSel = el.querySelector('#r-type');
  const common = el.querySelector('#r-common');
  const attrsBox = el.querySelector('#r-attrs');
  // Helyszín → részleg: csak a kiválasztott helyszínhez tartozó részlegek
  const locSel = el.querySelector('#r-loc');
  const deptSel = el.querySelector('#r-dept');
  function fillDepts() {
    const locId = Number(locSel.value);
    const list = depts.filter((d) => d.locations_id === locId);
    deptSel.innerHTML = list.length
      ? list.map((d) => `<option value="${d.id}" ${d.type === 'raktár' ? 'selected' : ''}>${esc(d.name)}</option>`).join('')
      : '<option value="">— nincs részleg ezen a helyszínen —</option>';
  }
  locSel.addEventListener('change', fillDepts);
  fillDepts();
  typeSel.addEventListener('change', () => {
    const tid = Number(typeSel.value);
    if (!tid) { common.style.display = 'none'; return; }
    common.style.display = 'block';
    attrsBox.innerHTML = getAttrDefs(tid).map((d) => attrFieldHTML(d, '')).join('') || '<div class="muted">Nincs típusattribútum.</div>';
    enhanceSelects(attrsBox);
  });
  enhanceSelects(el);

  const saveBtn = el.querySelector('#r-save');
  saveBtn.addEventListener('click', async () => {
    if (saveBtn.disabled) return;
    const tid = Number(typeSel.value);
    const asset_tag = el.querySelector('#r-tag').value.trim();
    if (!asset_tag) { toast('Adj meg leltári azonosítót.', 'error'); return; }
    const { attrs, error } = collectAttrs(attrsBox);
    if (error) { toast(error, 'error'); return; }
    saveBtn.disabled = true;
    try {
      const dev = await registerDevice({
        device_type_id: tid,
        asset_tag,
        serial_number: el.querySelector('#r-serial').value.trim(),
        manufacturer: el.querySelector('#r-manu').value.trim(),
        model: el.querySelector('#r-model').value.trim(),
        condition: el.querySelector('#r-cond').value,
        notes: el.querySelector('#r-notes').value.trim(),
        initial_location: Number(el.querySelector('#r-loc').value),
        initial_department: el.querySelector('#r-dept').value === '' ? null : Number(el.querySelector('#r-dept').value),
        attrs,
      });
      toast('Eszköz létrehozva.', 'success');
      navigate('/device/' + dev.device_id);
    } catch (e) { toast(e.message, 'error'); saveBtn.disabled = false; }
  });
}

// ---- Szerkesztés modal --------------------------------------
export function dlgEditDevice(deviceId) {
  const dev = getDevice(deviceId);
  const defs = getAttrDefs(dev.device_type_id);
  openModal({
    title: `Eszköz szerkesztése · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    wide: true,
    bodyHTML: `
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
        <div class="field"><label class="form-label">Gyártó</label><input class="form-control" id="e-manu" value="${esc(dev.manufacturer || '')}" /></div>
        <div class="field"><label class="form-label">Modell</label><input class="form-control" id="e-model" value="${esc(dev.model || '')}" /></div>
        <div class="field"><label class="form-label">Sorozatszám</label><input class="form-control" id="e-serial" value="${esc(dev.serial_number || '')}" /></div>
        <div class="field"><label class="form-label">Állapot</label>
          <select class="form-select" id="e-cond">${['Jó', 'Kopott', 'Hibás', 'Ismeretlen'].map((o) => `<option ${o === dev.condition ? 'selected' : ''}>${o}</option>`).join('')}</select>
        </div>
      </div>
      <div class="field"><label class="form-label">Megjegyzés</label><input class="form-control" id="e-notes" value="${esc(dev.notes || '')}" /></div>
      <div class="divider"></div>
      <div class="form-label" style="margin-bottom:10px; font-size:.9rem; color:var(--ink)">Típusspecifikus adatok</div>
      <div id="e-attrs" style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
        ${defs.map((d) => attrFieldHTML(d, dev.attrs?.[d.attribute_key])).join('') || '<div class="muted">Nincs típusattribútum.</div>'}
      </div>`,
    confirmText: 'Mentés',
    onMount: (root) => enhanceSelects(root),
    onConfirm: async (root) => {
      const { attrs, error } = collectAttrs(root.querySelector('#e-attrs'));
      if (error) { toast(error, 'error'); return false; }
      await editDevice(deviceId, {
        manufacturer: root.querySelector('#e-manu').value.trim(),
        model: root.querySelector('#e-model').value.trim(),
        serial_number: root.querySelector('#e-serial').value.trim(),
        condition: root.querySelector('#e-cond').value,
        notes: root.querySelector('#e-notes').value.trim(),
        attrs,
      });
      toast('Eszköz frissítve.', 'success');
    },
  });
}
