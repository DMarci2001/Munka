// ============================================================
// Ellenőrzésre vár — raktáros munkalistája (összevont lekérdezés):
// - pending check_in: felhasználói leadás, vár fizikai ellenőrzésre
// - rejected_transfer: az átvevő elutasította az átadást, vár raktáros döntésre
// ============================================================

import { pendingCheckins, getDevice, getUser, getDeviceType } from '../state/store.js';
import { navigate } from '../lib/router.js';
import { locationLabel, fmtDateTime, esc } from '../lib/format.js';
import { icons } from '../ui/components.js';
import * as A from '../ui/actions.js';
import { fitTableToWidth, watchFitToWidth } from '../ui/fitToWidth.js';

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

function sortValue(ev, col) {
  const dev = getDevice(ev.device_id);
  const type = getDeviceType(dev?.device_type_id);
  const submitter = getUser(ev.actor_user_id);
  switch (col) {
    case 'typeName':           return (type?.type || '') + ' ' + (dev?.model || '');
    case 'submitter':          return submitter?.full_name || '';
    case 'to_location':        return String(ev.to_locations_id || '') + String(ev.to_departments_id || '');
    case 'event_timestamp':    return ev.event_timestamp instanceof Date ? ev.event_timestamp.toISOString() : String(ev.event_timestamp || '');
    case 'condition_at_event': return ev.condition_at_event || '';
    default:                   return '';
  }
}

export function renderPending(el) {
  el.innerHTML = `
    <div class="content">
      <h3 class="section-title">Ellenőrzésre vár</h3>
      <div class="alert-soft" style="margin-bottom:16px">A felhasználói leadások itt várnak fizikai ellenőrzésre — erősítsd meg, ha az eszköz valóban a megadott helyen van, vagy utasítsd el, ha nincs ott. Az átvevő által elutasított átadásoknál pedig eldöntheted: elfogadod az elutasítást, vagy felülbírálod és mégis végrehajtod az átadást.</div>
      <div id="pending-table"></div>
    </div>`;

  paint(el);
}

function paint(el) {
  const wrap = el.querySelector('#pending-table');
  let items = pendingCheckins();

  if (sortCol) {
    items = [...items].sort((a, b) =>
      sortDir * sortValue(a, sortCol).localeCompare(sortValue(b, sortCol), 'hu', { sensitivity: 'base' })
    );
  }

  wrap.innerHTML = items.length ? `
    <div class="table-wrap">
      <table class="grid">
        <thead><tr>
          <th>Típus</th>
          ${thHTML('typeName', 'Típus / modell')}
          ${thHTML('submitter', 'Kezdeményezte')}
          ${thHTML('to_location', 'Cél / helyiség')}
          ${thHTML('event_timestamp', 'Időpont')}
          <th>Állapot / indok</th>
          <th style="text-align:right">Döntés</th>
        </tr></thead>
        <tbody>
          ${items.map(rowHTML).join('')}
        </tbody>
      </table>
    </div>`
    : `<div class="table-wrap"><div class="empty"><div class="big">${icons.check}</div><div>Nincs ellenőrzésre váró tétel.</div></div></div>`;

  wrap.querySelectorAll('[data-dev]').forEach((r) =>
    r.addEventListener('click', (e) => { if (!e.target.closest('button')) navigate('/device/' + r.dataset.dev); }));
  wrap.querySelectorAll('[data-confirm]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.doConfirmCheckIn(Number(b.dataset.confirm)); }));
  wrap.querySelectorAll('[data-reject]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.dlgRejectCheckIn(Number(b.dataset.reject)); }));
  wrap.querySelectorAll('[data-accept-rejection]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.doAcceptRejection(Number(b.dataset.acceptRejection)); }));
  wrap.querySelectorAll('[data-override]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.dlgOverrideRejection(Number(b.dataset.override)); }));
  wrap.querySelectorAll('th[data-col]').forEach((th) =>
    th.addEventListener('click', () => { sortBy(th.dataset.col); paint(el); }));

  wrap.querySelectorAll('.table-wrap').forEach((tw) => {
    watchFitToWidth(tw);
    fitTableToWidth(tw);
  });
}

function targetLabel(ev) {
  if (ev.kind === 'rejected_transfer') {
    const toUser = getUser(ev.to_user_id);
    return esc(toUser?.full_name || '—');
  }
  return esc(locationLabel(ev.to_locations_id, ev.to_departments_id));
}

function rowHTML(ev) {
  const dev = getDevice(ev.device_id);
  const type = getDeviceType(dev?.device_type_id);
  const submitter = getUser(ev.actor_user_id);
  const isRejectedTransfer = ev.kind === 'rejected_transfer';
  return `
    <tr data-dev="${ev.device_id}">
      <td>${isRejectedTransfer
        ? '<span class="status-badge status-lost">Átadás elutasítva</span>'
        : '<span class="status-badge status-pending">Visszavétel</span>'}</td>
      <td><span class="tag-mono"></span>${esc(type?.type || '')}<div class="cell-sub">${esc(dev?.manufacturer || '')} · ${esc(dev?.model || '')}</div></td>
      <td>${esc(submitter?.full_name || '—')}</td>
      <td>${targetLabel(ev)}</td>
      <td>${fmtDateTime(ev.event_timestamp)}</td>
      <td>${esc((isRejectedTransfer ? ev.notes : ev.condition_at_event) || '—')}</td>
      <td style="text-align:right">
        <div class="row-actions" style="justify-content:flex-end">
          ${isRejectedTransfer ? `
            <button class="btn btn-outline btn-sm" data-accept-rejection="${ev.event_id}">Elutasítás elfogadása</button>
            <button class="btn btn-danger btn-sm" data-override="${ev.event_id}">Felülbírálás</button>
          ` : `
            <button class="btn btn-success btn-sm" data-confirm="${ev.event_id}">${icons.check} Megerősít</button>
            <button class="btn btn-danger btn-sm" data-reject="${ev.event_id}">${icons.x} Elutasít</button>
          `}
        </div>
      </td>
    </tr>`;
}
