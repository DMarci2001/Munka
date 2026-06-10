// ============================================================
// Ellenőrzésre váró visszavételek (raktáros munkalistája)
// device_pending_checkins → megerősít / elutasít
// ============================================================

import { pendingCheckins, getDevice, getUser, getDeviceType } from '../state/store.js';
import { navigate } from '../lib/router.js';
import { locationLabel, fmtDateTime, esc } from '../lib/format.js';
import { icons } from '../ui/components.js';
import * as A from '../ui/actions.js';

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

export function renderPending(el) {
  const items = pendingCheckins();

  el.innerHTML = `
    <div class="content">
      <h3 class="section-title">Ellenőrzésre váró visszavételek</h3>
      <div class="alert-soft" style="margin-bottom:16px">A felhasználói leadások itt várnak fizikai ellenőrzésre. Erősítsd meg, ha az eszköz valóban a megadott helyen van; utasítsd el, ha nincs ott — ekkor a birtoklás a felhasználónál marad.</div>
      ${items.length ? `
      <div class="table-wrap">
        <table class="grid">
          <thead><tr>${thHTML('asset_tag', 'Azonosító')}${thHTML('submitter', 'Leadta')}${thHTML('to_location', 'Helyiség')}${thHTML('event_timestamp', 'Leadás időpontja')}${thHTML('condition_at_event', 'Állapot')}<th style="text-align:right">Döntés</th></tr></thead>
          <tbody>
            ${items.map(rowHTML).join('')}
          </tbody>
        </table>
      </div>` : `<div class="table-wrap"><div class="empty"><div class="big">${icons.check}</div><div>Nincs ellenőrzésre váró visszavétel.</div></div></div>`}
    </div>`;

  el.querySelectorAll('[data-dev]').forEach((r) =>
    r.addEventListener('click', (e) => { if (!e.target.closest('button')) navigate('/device/' + r.dataset.dev); }));
  el.querySelectorAll('[data-confirm]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.doConfirmCheckIn(Number(b.dataset.confirm)); }));
  el.querySelectorAll('[data-reject]').forEach((b) =>
    b.addEventListener('click', (e) => { e.stopPropagation(); A.dlgRejectCheckIn(Number(b.dataset.reject)); }));
  el.querySelectorAll('th[data-col]').forEach((th) =>
    th.addEventListener('click', () => { sortBy(th.dataset.col); paint(el); }));
}

function rowHTML(ev) {
  const dev = getDevice(ev.device_id);
  const type = getDeviceType(dev.device_type_id);
  const submitter = getUser(ev.actor_user_id);
  return `
    <tr data-dev="${ev.device_id}">
      <td><span class="tag-mono">${esc(dev.asset_tag)}</span><div class="cell-sub">${esc(type?.name || '')} · ${esc(dev.model)}</div></td>
      <td>${esc(submitter?.full_name || '—')}</td>
      <td>${esc(locationLabel(ev.to_location_id, ev.to_department_id))}</td>
      <td>${fmtDateTime(ev.event_timestamp)}</td>
      <td>${esc(ev.condition_at_event || '—')}</td>
      <td style="text-align:right">
        <div class="row-actions" style="justify-content:flex-end">
          <button class="btn btn-success btn-sm" data-confirm="${ev.event_id}">${icons.check} Megerősít</button>
          <button class="btn btn-danger btn-sm" data-reject="${ev.event_id}">${icons.x} Elutasít</button>
        </div>
      </td>
    </tr>`;
}
