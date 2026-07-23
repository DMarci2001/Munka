// ============================================================
// Közös "kijelölés-mód" komponens tömeges műveletekhez
// (batch checkout / batch átadás / batch check-in).
//
// A kijelölt eszközök halmaza device_id szerint van tárolva — FÜGGETLENÜL
// attól, mely sorok látszanak épp a szűrt/rendezett/lapozott nézetben, hogy
// szűrés közben ne vesszenek el a korábbi kijelölések.
// ============================================================

import { esc } from '../lib/format.js';
import { getDevice } from '../state/store.js';

export function createBulkSelection() {
  const selected = new Set();
  const listeners = new Set();
  function notify() { listeners.forEach((fn) => fn(selected)); }
  return {
    isSelected: (id) => selected.has(id),
    toggle(id) { selected.has(id) ? selected.delete(id) : selected.add(id); notify(); },
    clear() { selected.clear(); notify(); },
    all: () => [...selected],
    size: () => selected.size,
    subscribe(fn) { listeners.add(fn); return () => listeners.delete(fn); },
  };
}

// Sticky akciósáv: mindig az ÖSSZES kijelölt eszközt mutatja (nem csak az
// aktuálisan látható szűrt részhalmazt), egy Véglegesítés + Mégse gombbal.
export function renderActionBar(container, bulk, { label, finalizeText = 'Véglegesítés', onFinalize, extraHTML = () => '' }) {
  function paint() {
    const ids = bulk.all();
    if (!ids.length) { container.innerHTML = ''; container.classList.remove('bulk-action-bar-visible'); return; }
    container.classList.add('bulk-action-bar-visible');
    container.innerHTML = `
      <div class="bulk-action-bar">
        <div class="bulk-action-bar-label">${esc(label)} — ${ids.length} kiválasztva</div>
        <div class="bulk-action-bar-chips">
          ${ids.map((id) => {
            const dev = getDevice(id);
            return `<span class="chip">${esc(dev?.asset_tag || ('#' + id))}<button type="button" data-remove="${id}">&times;</button></span>`;
          }).join('')}
        </div>
        ${extraHTML()}
        <div class="bulk-action-bar-buttons">
          <button type="button" class="btn btn-outline btn-sm" data-cancel>Mégse</button>
          <button type="button" class="btn btn-primary btn-sm" data-finalize>${esc(finalizeText)}</button>
        </div>
      </div>`;
    container.querySelectorAll('[data-remove]').forEach((b) =>
      b.addEventListener('click', () => bulk.toggle(Number(b.dataset.remove))));
    container.querySelector('[data-cancel]')?.addEventListener('click', () => bulk.clear());
    container.querySelector('[data-finalize]')?.addEventListener('click', () => onFinalize(bulk.all(), container));
  }
  bulk.subscribe(paint);
  paint();
  return paint;
}

// Checkbox-oszlop beszúrása egy már renderelt táblázatba. A szűrés/rendezés/
// lapozás logikáját nem érinti — csak egy plusz <td>-t told be minden
// data-dev sor elejére, a filterFn eldönti, mely sorok jelölhetők ki
// (pl. csak a 'Kivehető' státuszúak).
export function injectCheckboxColumn(tableWrapEl, bulk, filterFn = () => true) {
  tableWrapEl.querySelectorAll('table').forEach((table) => {
    const headRow = table.querySelector('thead tr');
    if (headRow && !headRow.querySelector('.bulk-th')) {
      const th = document.createElement('th');
      th.className = 'bulk-th';
      th.style.width = '32px';
      headRow.insertBefore(th, headRow.firstChild);
    }
    table.querySelectorAll('tbody tr[data-dev]').forEach((tr) => {
      const id = Number(tr.dataset.dev);
      if (tr.querySelector('.bulk-td')) return;
      const td = document.createElement('td');
      td.className = 'bulk-td';
      if (filterFn(id)) {
        td.innerHTML = `<input type="checkbox" ${bulk.isSelected(id) ? 'checked' : ''} />`;
        td.addEventListener('click', (e) => e.stopPropagation());
        td.querySelector('input').addEventListener('change', () => bulk.toggle(id));
      }
      tr.insertBefore(td, tr.firstChild);
    });
  });
}

// Soronkénti batch-eredmény toast-összegzés ([{device_id, ok, error}, ...]).
export function summarizeBatchResults(results, toast) {
  const ok = results.filter((r) => r.ok).length;
  const failed = results.filter((r) => !r.ok);
  if (!failed.length) {
    toast(`${ok}/${results.length} eszköz sikeresen feldolgozva.`, 'success');
    return;
  }
  const detail = failed.map((r) => {
    const dev = getDevice(r.device_id);
    return `${dev?.asset_tag || ('#' + r.device_id)}: ${r.error}`;
  }).join(' · ');
  toast(`${ok}/${results.length} sikeres. Hiba: ${detail}`, 'error');
}
