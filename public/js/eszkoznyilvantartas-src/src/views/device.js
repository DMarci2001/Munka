// ============================================================
// Eszköz részletei — közös mezők, típusattribútumok,
// custody-előzmény, kontextusfüggő műveletek
// ============================================================

import {
  getDevice, getAttrDefs, currentUser, currentRole, roleAtLeast,
  historyOf, isHistoryLoaded, ensureHistory, getUser,
} from '../state/store.js';
import { deviceVM } from '../lib/vm.js';
import { navigate } from '../lib/router.js';
import {
  statusBadge, locationLabel,
  fmtDateTime, fmtRelative, fmtAttrValue, eventLabel, confLabel, confClass,
  calibrationFlag, esc, splitRejectionNote,
} from '../lib/format.js';
import { icons } from '../ui/components.js';
import * as A from '../ui/actions.js';

export function renderDevice(el, { id }) {
  const dev = getDevice(Number(id));
  if (!dev) {
    el.innerHTML = `<div class="content"><div class="empty"><div class="big">${icons.warning}</div><div>Eszköz nem található.</div><div style="margin-top:14px"><button class="btn btn-outline" id="back">${icons.back} Vissza a listához</button></div></div></div>`;
    el.querySelector('#back').addEventListener('click', () => navigate('/inventory'));
    return;
  }

  const v = deviceVM(dev);
  const me = currentUser();
  const role = currentRole();
  const isStore = roleAtLeast(role, 'storekeeper');
  const defs = getAttrDefs(dev.device_type_id);
  ensureHistory(dev.device_id);   // igény szerinti betöltés → notify → újrarajzol
  const histLoaded = isHistoryLoaded(dev.device_id);
  const hist = historyOf(dev.device_id);

  el.innerHTML = `
    <div class="content">
      <button class="btn btn-ghost btn-sm" id="back" style="margin-bottom:14px">${icons.back} Eszközök</button>

      <div class="detail-head">
        <div class="titleblock">
          <h2>${esc(v.typeName)} — ${esc(dev.manufacturer)} ${esc(dev.model)}</h2>
          <div class="pill-info"><span class="tag-mono" style="font-size:.95rem">${esc(dev.asset_tag)}</span>${statusBadge(v.status)}</div>
        </div>
        <div class="actions" id="actions"></div>
      </div>

      ${noticeHTML(v, isStore)}

      <div class="detail-grid">
        <div style="display:flex; flex-direction:column; gap:18px">
          <div class="panel">
            <div class="panel-head">${esc(v.typeName)}</div>
            <div class="panel-body">
              <dl class="kv">
                <dt>Birtokos</dt><dd>${v.holder ? esc(v.holder.full_name) : '<span class="muted">— raktáron —</span>'}</dd>
                <dt>Hely</dt><dd>${esc(locationLabel(v.locationId, v.departmentId))}</dd>
                <dt>Státusz óta</dt><dd>${v.since ? fmtDateTime(v.since) : '—'}</dd>
                <dt>Állapot</dt><dd>${esc(dev.condition || '—')}</dd>
                <dt>Sorozatszám</dt><dd>${esc(dev.serial_number || '—')}</dd>
                <dt>Gyártó / modell</dt><dd>${esc(dev.manufacturer || '—')} ${esc(dev.model || '')}</dd>
                ${dev.notes ? `<dt>Megjegyzés</dt><dd>${esc(dev.notes)}</dd>` : ''}
                ${defs.map((d) => attrRow(d, dev.attrs?.[d.attribute_key])).join('')}
              </dl>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">Birtoklási előzmény</div>
          <div class="panel-body">
            ${histLoaded ? historyHTML(hist) : '<div class="muted">Előzmény betöltése…</div>'}
          </div>
        </div>
      </div>
    </div>`;

  el.querySelector('#back').addEventListener('click', () => navigate('/inventory'));
  renderActions(el.querySelector('#actions'), v, role, isStore, me);

  // értesítő sávban lévő gombok (megerősítés/elutasítás)
  el.querySelectorAll('[data-confirm-ev]').forEach((b) =>
    b.addEventListener('click', () => A.doConfirmCheckIn(Number(b.dataset.confirmEv))));
  el.querySelectorAll('[data-reject-ev]').forEach((b) =>
    b.addEventListener('click', () => A.dlgRejectCheckIn(Number(b.dataset.rejectEv))));
}

// --- Értesítő sáv (foglalás / függő visszavétel) -------------
function noticeHTML(v, isStore) {
  if (v.pending) {
    const sub = getUser(v.pending.actor_user_id);
    return `<div class="alert-warn-soft" style="margin-bottom:18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap">
      <span>${icons.pending}</span>
      <span><strong>Visszavétel megerősítésre vár.</strong> Leadta: ${esc(sub?.full_name || '—')}, ide: ${esc(locationLabel(v.pending.to_locations_id, v.pending.to_departments_id))}.</span>
      ${isStore ? `<span style="margin-left:auto; display:flex; gap:8px">
        <button class="btn btn-success btn-sm" data-confirm-ev="${v.pending.event_id}">${icons.check} Megerősít</button>
        <button class="btn btn-danger btn-sm" data-reject-ev="${v.pending.event_id}">${icons.x} Elutasít</button>
      </span>` : ''}
    </div>`;
  }
  if (v.reservation) {
    return `<div class="alert-soft" style="margin-bottom:18px; display:flex; align-items:center; gap:12px">
      <span>${icons.bookmark}</span>
      <span><strong>Lefoglalva.</strong> Foglalta: ${esc(v.reservedBy?.full_name || '—')} · lejár ${fmtRelative(v.reservation.expires_at)}.</span>
    </div>`;
  }
  return '';
}

// --- Attribútum-sor lejárat-jelzéssel ------------------------
function attrRow(def, value) {
  let flag = '';
  if (def.attribute_key === 'calibration_due') {
    const f = calibrationFlag(value);
    if (f === 'overdue') flag = `<span class="attr-flag overdue">Lejárt</span>`;
    else if (f === 'soon') flag = `<span class="attr-flag soon">Hamarosan</span>`;
  }
  return `<dt>${esc(def.label)}</dt><dd>${esc(fmtAttrValue(def, value))}${flag}</dd>`;
}

// --- Custody history lapozó ----------------------------------
function historyHTML(hist) {
  if (!hist.length) return `<div class="muted">Nincs előzmény.</div>`;
  const PS = 8;
  const pages = [];
  for (let i = 0; i < hist.length; i += PS) pages.push(hist.slice(i, i + PS));
  if (pages.length === 1) return `<div class="timeline">${hist.map(tlItem).join('')}</div>`;
  const style = pages.map((_, i) =>
    `.hist-pager:has(#hist-p${i+1}:checked) .page-section[data-page="${i+1}"]{display:block}` +
    `.hist-pager:has(#hist-p${i+1}:checked) label[for="hist-p${i+1}"]{background:var(--brand);color:#fff;border-color:var(--brand-dark)}`
  ).join('');
  return `
    <div class="hist-pager pager-root">
      <style>${style}</style>
      ${pages.map((_, i) => `<input type="radio" name="hist-page" id="hist-p${i+1}" class="page-radio"${i === 0 ? ' checked' : ''}>`).join('')}
      ${pages.map((evs, i) => `<div class="page-section" data-page="${i+1}"><div class="timeline">${evs.map(tlItem).join('')}</div></div>`).join('')}
      <div class="pager-nav">${pages.map((_, i) => `<label for="hist-p${i+1}" class="pager-btn">${i+1}</label>`).join('')}</div>
    </div>`;
}

// --- Timeline elem -------------------------------------------
function tlItem(ev) {
  const actor = getUser(ev.actor_user_id);
  const dotCls = ev.confirmation_status === 'pending' ? 'pending' : ev.confirmation_status === 'rejected' ? 'rejected' : '';
  const noFrom = ev.event_type === 'mark_lost' || ev.event_type === 'mark_found';
  const fromTo = [];
  if (!noFrom && ev.from_user_id) fromTo.push(getUser(ev.from_user_id)?.full_name);
  else if (!noFrom && (ev.from_departments_id || ev.from_locations_id)) fromTo.push(locationLabel(ev.from_locations_id, ev.from_departments_id));
  const toLabel = ev.to_user_id ? getUser(ev.to_user_id)?.full_name : locationLabel(ev.to_locations_id, ev.to_departments_id);
  const confTag = ev.confirmation_status !== 'confirmed'
    ? `<span class="status-badge ${confClass(ev.confirmation_status)}">${confLabel(ev.confirmation_status)}</span>` : '';
  const { note, reason } = splitRejectionNote(ev.notes);
  return `
    <div class="tl-item">
      <span class="tl-dot ${dotCls}"></span>
      <div class="tl-head">${esc(eventLabel(ev.event_type))}${confTag}</div>
      <div class="tl-route">${fromTo.length ? esc(fromTo.join(', ')) + ' → ' : ''}${esc(toLabel || '—')}</div>
      <div class="tl-meta">Végrehajtó: ${esc(actor?.full_name || '—')} · ${fmtDateTime(ev.event_timestamp)}</div>
      ${note ? `<div class="tl-note">${esc(note)}</div>` : ''}
      ${reason ? `<div class="tl-reason">Indok: ${esc(reason)}</div>` : ''}
    </div>`;
}

// --- Kontextusfüggő műveletgombok ----------------------------
function renderActions(container, v, role, isStore, me) {
  const dev = v.dev;
  const btns = [];
  const heldByMe = v.holderId === me.id;
  const reservedByMe = v.reservation && v.reservation.reserved_by === me.id;
  const reservedByOther = v.reservation && v.reservation.reserved_by !== me.id;
  const retired = dev.status === 'Selejtezve';
  const lost = dev.status === 'Elveszett';
  // Sima usernek alapból nincs Kivétel joga — jogosultsághoz kötött
  // (l. jog_eszkoznyilvantartas_kivetel); raktáros+ mindig kiveheti.
  const canOut = isStore || !!me.can_check_out;

  if (!retired && !lost) {
    // Kivétel: szabad eszköz (vagy nekem foglalt), vagy raktáros bármikor szabadra
    if (v.isFree && !reservedByOther) {
      if (canOut) btns.push(`<button class="btn btn-primary" data-act="checkout">${icons.arrowRight} Kivétel</button>`);
      if (!v.reservation)
        btns.push(`<button class="btn btn-outline" data-act="reserve">${icons.bookmark} Foglalás</button>`);
    }
    if (reservedByMe) {
      if (canOut) btns.push(`<button class="btn btn-primary" data-act="checkout">${icons.arrowRight} Kivétel</button>`);
      btns.push(`<button class="btn btn-outline" data-act="cancel-resv">Foglalás lemondása</button>`);
    }
    if (reservedByOther && isStore) {
      btns.push(`<button class="btn btn-primary" data-act="checkout">${icons.arrowRight} Kivétel (felülírás)</button>`);
      btns.push(`<button class="btn btn-outline" data-act="cancel-resv">Foglalás lemondása</button>`);
    }
    // Birtokosi műveletek
    if (heldByMe && !v.pending) {
      btns.push(`<button class="btn btn-primary" data-act="checkin">${icons.back} Leadás</button>`);
      btns.push(`<button class="btn btn-outline" data-act="transfer">Átadás</button>`);
    }
    // Raktáros: bármely birtokolt eszközre
    if (isStore && v.holderId && !heldByMe) {
      btns.push(`<button class="btn btn-outline" data-act="checkin">${icons.back} Kényszerített visszavétel</button>`);
      btns.push(`<button class="btn btn-outline" data-act="transfer">Átadás</button>`);
    }
    // Raktáros: raktáron lévő eszköz mozgatása / javítás
    if (isStore && v.isFree) {
      btns.push(`<button class="btn btn-outline" data-act="stock">${icons.building} Raktármozgatás</button>`);
    }
    if (isStore && v.inRepair) {
      btns.push(`<button class="btn btn-outline" data-act="return-from-repair">${icons.back} Visszahelyezés</button>`);
      btns.push(`<button class="btn btn-outline" data-act="edit">${icons.edit} Szerkesztés</button>`);
    }
    if (isStore && !v.inRepair) {
      btns.push(`<button class="btn btn-outline" data-act="repair">${icons.repair} Szervizbe</button>`);
      btns.push(`<button class="btn btn-outline" data-act="edit">${icons.edit} Szerkesztés</button>`);
      btns.push(`<button class="btn btn-danger" data-act="more">⋯</button>`);
    }
  } else if (isStore && !retired) {
    if (v.isLost)
      btns.push(`<button class="btn btn-primary" data-act="mark-found">${icons.back} Visszahelyezés</button>`);
    btns.push(`<button class="btn btn-outline" data-act="edit">${icons.edit} Szerkesztés</button>`);
  }

  btns.push(`<button class="btn btn-ghost btn-sm" data-act="qr-label" style="margin-left:auto">${icons.qr} QR címke</button>`);

  container.innerHTML = btns.join('') || `<span class="muted" style="font-size:.85rem">Nincs elérhető művelet ehhez az állapothoz.</span>`;

  const id = dev.device_id;
  const handlers = {
    checkout: () => A.dlgCheckOut(id),
    checkin: () => A.dlgCheckIn(id),
    transfer: () => A.dlgTransfer(id),
    reserve: () => A.doReserve(id),
    'cancel-resv': () => A.doCancelReservation(id),
    stock: () => A.dlgStockTransfer(id),
    repair: () => A.dlgSendToRepair(id),
    'return-from-repair': () => A.dlgReturnFromRepair(id),
    'mark-found': () => A.dlgMarkFound(id),
    edit: () => import('./register_device.js').then((m) => m.dlgEditDevice(id)),
    more: () => showMore(container, id),
    'qr-label': () => import('../ui/qrLabel.js').then((m) => m.dlgQrLabel(id)),
  };
  container.querySelectorAll('[data-act]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); handlers[b.dataset.act]?.(); }));
}

function showMore(container, id) {
  // egyszerű választó: elveszett / selejt
  const wrap = document.createElement('div');
  wrap.style.cssText = 'position:absolute; margin-top:6px; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:var(--shadow); padding:6px; z-index:50; display:flex; flex-direction:column; gap:2px';
  wrap.innerHTML = `
    <button class="btn btn-ghost btn-sm" data-m="lost" style="justify-content:flex-start">${icons.warning} Elveszettnek jelölés</button>
    <button class="btn btn-ghost btn-sm" data-m="retire" style="justify-content:flex-start; color:#c0392b">Selejtezés</button>`;
  container.appendChild(wrap);
  wrap.querySelector('[data-m=lost]').addEventListener('click', () => { wrap.remove(); A.dlgMarkLost(id); });
  wrap.querySelector('[data-m=retire]').addEventListener('click', () => { wrap.remove(); A.dlgRetire(id); });
  setTimeout(() => document.addEventListener('click', function off() { wrap.remove(); document.removeEventListener('click', off); }), 0);
}
