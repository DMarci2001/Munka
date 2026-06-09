import {
  getDeviceTypes, getLocations,
  addLocation, addDepartment, addDeviceType, addAttrDef,
  currentRole, roleAtLeast,
} from '../state/store.js';
import { navigate } from '../lib/router.js';
import { toast } from '../ui/components.js';
import { esc } from '../lib/format.js';
import { icons } from '../ui/components.js';

// ---- Törzsadat bevitele ------------------------------------
export function renderRegisterData(el) {
  if (!roleAtLeast(currentRole(), 'storekeeper')) {
    el.innerHTML = `<div class="content"><div class="empty"><div class="big">${icons.warning}</div><div>Ehhez raktáros vagy IT-admin szerepkör kell.</div></div></div>`;
    return;
  }

  const types = getDeviceTypes();
  const locs = getLocations();

  el.innerHTML = `
    <div class="content" style="max-width:760px">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${icons.back} Vissza</button>
      <div class="panel">
        <div class="panel-head">Törzsadat bevitele</div>
        <div class="panel-body">
          <div class="field">
            <label class="form-label">Kategória *</label>
            <select class="form-select" id="rd-cat">
              <option value="">— válassz kategóriát —</option>
              <option value="location">Új helyszín</option>
              <option value="department">Új részleg / helyiség</option>
              <option value="device_type">Új eszköztípus</option>
              <option value="attr_general">Általános eszközattribútum</option>
              <option value="attr_type">Típusspecifikus eszközattribútum</option>
            </select>
          </div>

          <div id="form-location" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Cím *</label>
              <input class="form-control" id="loc-address" placeholder="pl. 1095 Budapest, Soroksári út 12." />
            </div>
            ${saveBar('Helyszín mentése')}
          </div>

          <div id="form-department" style="display:none">
            <div class="divider"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
              <div class="field">
                <label class="form-label">Helyszín *</label>
                <select class="form-select" id="dept-loc">
                  ${locs.map((l) => `<option value="${l.id}">${esc(l.address)}</option>`).join('')}
                </select>
              </div>
              <div class="field">
                <label class="form-label">Típus *</label>
                <select class="form-select" id="dept-type">
                  <option value="osztály">Osztály</option>
                  <option value="raktár">Raktár</option>
                  <option value="recepció">Recepció</option>
                  <option value="műhely">Műhely</option>
                </select>
              </div>
              <div class="field" style="grid-column:1/-1">
                <label class="form-label">Név *</label>
                <input class="form-control" id="dept-name" placeholder="pl. Kardiológia" />
              </div>
            </div>
            ${saveBar('Részleg mentése')}
          </div>

          <div id="form-device_type" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Típus neve *</label>
              <input class="form-control" id="dtype-name" placeholder="pl. Véroxigénmérő" />
            </div>
            <div class="field">
              <label class="form-label">Leírás</label>
              <input class="form-control" id="dtype-desc" placeholder="pl. Pulzoximeter készülék" />
            </div>
            ${saveBar('Eszköztípus mentése')}
          </div>

          <div id="form-attr_general" style="display:none">
            <div class="divider"></div>
            ${attrFormHTML('ag')}
            ${saveBar('Attribútum mentése')}
          </div>

          <div id="form-attr_type" style="display:none">
            <div class="divider"></div>
            <div class="field">
              <label class="form-label">Eszköztípus *</label>
              <select class="form-select" id="attr-type-sel">
                ${types.map((t) => `<option value="${t.id}">${esc(t.type)}</option>`).join('')}
              </select>
            </div>
            ${attrFormHTML('at')}
            ${saveBar('Attribútum mentése')}
          </div>
        </div>
      </div>
    </div>`;

  el.querySelector('#back').addEventListener('click', () => navigate('/'));

  const cat = el.querySelector('#rd-cat');
  const formIds = ['location', 'department', 'device_type', 'attr_general', 'attr_type'];
  cat.addEventListener('change', () => {
    formIds.forEach((f) => { el.querySelector(`#form-${f}`).style.display = 'none'; });
    if (cat.value) el.querySelector(`#form-${cat.value}`).style.display = 'block';
  });

  // Show/hide options field when data_type switches to/from enum
  ['ag', 'at'].forEach((pfx) => {
    const dtSel = el.querySelector(`#${pfx}-data-type`);
    const optRow = el.querySelector(`#${pfx}-options-row`);
    dtSel.addEventListener('change', () => {
      optRow.style.display = dtSel.value === 'enum' ? 'block' : 'none';
    });
  });

  el.querySelector('#form-location .btn-primary').addEventListener('click', () => {
    try {
      addLocation({ address: el.querySelector('#loc-address').value });
      toast('Helyszín hozzáadva.', 'success');
      el.querySelector('#loc-address').value = '';
    } catch (e) { toast(e.message, 'error'); }
  });

  el.querySelector('#form-department .btn-primary').addEventListener('click', () => {
    try {
      addDepartment({
        locations_id: Number(el.querySelector('#dept-loc').value),
        name: el.querySelector('#dept-name').value,
        type: el.querySelector('#dept-type').value,
      });
      toast('Részleg hozzáadva.', 'success');
      el.querySelector('#dept-name').value = '';
    } catch (e) { toast(e.message, 'error'); }
  });

  el.querySelector('#form-device_type .btn-primary').addEventListener('click', () => {
    try {
      addDeviceType({
        type: el.querySelector('#dtype-name').value,
        description: el.querySelector('#dtype-desc').value,
      });
      toast('Eszköztípus hozzáadva.', 'success');
      el.querySelector('#dtype-name').value = '';
      el.querySelector('#dtype-desc').value = '';
    } catch (e) { toast(e.message, 'error'); }
  });

  [['ag', null], ['at', 'attr-type-sel']].forEach(([pfx, typeSelId]) => {
    el.querySelector(`#form-${pfx === 'ag' ? 'attr_general' : 'attr_type'} .btn-primary`).addEventListener('click', () => {
      try {
        const device_type_id = typeSelId ? Number(el.querySelector(`#${typeSelId}`).value) : null;
        addAttrDef({
          device_type_id,
          attribute_key: el.querySelector(`#${pfx}-key`).value,
          label: el.querySelector(`#${pfx}-label`).value,
          data_type: el.querySelector(`#${pfx}-data-type`).value,
          is_required: el.querySelector(`#${pfx}-required`).value === 'true',
          options: el.querySelector(`#${pfx}-options`).value,
          sort_order: el.querySelector(`#${pfx}-sort`).value,
        });
        toast('Attribútum hozzáadva.', 'success');
        el.querySelector(`#${pfx}-key`).value = '';
        el.querySelector(`#${pfx}-label`).value = '';
        el.querySelector(`#${pfx}-options`).value = '';
        el.querySelector(`#${pfx}-sort`).value = '0';
      } catch (e) { toast(e.message, 'error'); }
    });
  });
}

function saveBar(label) {
  return `<div style="display:flex; gap:10px; justify-content:flex-end; margin-top:14px">
    <button class="btn btn-primary">${icons.register} ${esc(label)}</button>
  </div>`;
}

function attrFormHTML(pfx) {
  return `
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
      <div class="field">
        <label class="form-label">Attribútum kulcs *</label>
        <input class="form-control" id="${pfx}-key" placeholder="pl. calibration_due" />
      </div>
      <div class="field">
        <label class="form-label">Felirat *</label>
        <input class="form-control" id="${pfx}-label" placeholder="pl. Következő kalibráció" />
      </div>
      <div class="field">
        <label class="form-label">Adattípus *</label>
        <select class="form-select" id="${pfx}-data-type">
          <option value="text">Szöveg</option>
          <option value="integer">Egész szám</option>
          <option value="decimal">Tizedes szám</option>
          <option value="date">Dátum</option>
          <option value="boolean">Igen/Nem</option>
          <option value="enum">Felsorolás (enum)</option>
        </select>
      </div>
      <div class="field">
        <label class="form-label">Kötelező?</label>
        <select class="form-select" id="${pfx}-required">
          <option value="false">Nem</option>
          <option value="true">Igen</option>
        </select>
      </div>
      <div class="field" id="${pfx}-options-row" style="grid-column:1/-1; display:none">
        <label class="form-label">Lehetséges értékek (vesszővel elválasztva)</label>
        <input class="form-control" id="${pfx}-options" placeholder="pl. Jó,Közepes,Rossz" />
      </div>
      <div class="field">
        <label class="form-label">Sorrend</label>
        <input type="number" class="form-control" id="${pfx}-sort" value="0" min="0" step="1" />
      </div>
    </div>`;
}