import { getDeviceByAssetTag, currentUser, currentRole, roleAtLeast } from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { esc } from '../lib/format.js';
import { icons, toast } from '../ui/components.js';
import * as A from '../ui/actions.js';

function wireScanInput(el) {
  const input = el.querySelector('#scan-input');
  input.focus();
  input.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const raw = input.value.trim();
    input.value = '';
    if (raw) resolveScan(raw);
    input.focus();
  });
}

function resolveScan(tag) {
  const dev = getDeviceByAssetTag(tag);
  if (!dev) return toast(`Ismeretlen azonosító: ${esc(tag)}`, 'error');

  const v = deviceVM(dev);
  const me = currentUser();
  const isStore = roleAtLeast(currentRole(), 'storekeeper');

  if (['Selejtezve', 'Elveszett', 'Szerviz alatt'].includes(v.status))
    return toast(`Nem kezelhető: ${v.status}.`, 'error');
  if (v.pending)
    return toast('Visszavétel folyamatban — raktáros megerősítésére vár.', 'error');

  if (v.holderId === me.id) return A.dlgCheckIn(dev.device_id);
  if (v.holderId !== null)
    return isStore ? A.dlgCheckIn(dev.device_id)
                   : toast(`Másnál van: ${v.holder?.full_name}.`, 'error');

  const resv = v.reservation;
  if (resv && resv.reserved_by !== me.id && !isStore)
    return toast(`Lefoglalva: ${v.reservedBy?.full_name}.`, 'error');

  return A.dlgCheckOut(dev.device_id);
}

export function renderScan(el, { tag } = {}) {
  el.innerHTML = `
    <div class="content">
      <div class="scan-wrap">
        <div class="scan-icon">${icons.qr}</div>
        <input id="scan-input" class="form-control"
          autocomplete="off" spellcheck="false"
          placeholder="Olvasd be vagy gépeld az azonosítót…" />
        <p class="scan-hint">Nyomj 'Enter'-t, vagy olvasd be a vonalkódot.</p>
      </div>
    </div>`;
  wireScanInput(el);
  if (tag) resolveScan(decodeURIComponent(tag));
}
