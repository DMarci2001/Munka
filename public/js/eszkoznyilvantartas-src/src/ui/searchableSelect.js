// ============================================================
// Kereshető, ABC-sorrendbe rendezett select-widget.
// Az eredeti <select class="form-select"> elemet vizuálisan
// elrejti, és egy input+lenyíló listát épít fölé, ami a
// select .value / .selectedIndex mezőjét vezérli, és 'change'
// eseményt vált ki — a meglévő kód (change-listenerek, .value
// olvasás) változtatás nélkül tovább működik.
// ============================================================
import { esc } from '../lib/format.js';

let seq = 0;

export function enhanceSelects(root) {
  root.querySelectorAll('select.form-select').forEach(mount);
}

// Direkt .value= beállítás után (pl. szűrők törlése) hívandó,
// hogy a látható input szöveg is frissüljön — a MutationObserver
// csak az opciólista változását figyeli, az érték-változást nem.
export function refreshSelectDisplay(select) {
  select?._sselSync?.();
}

function mount(select) {
  if (select._sselMounted) return;
  select._sselMounted = true;

  const id = 'ssel-' + (++seq);
  select.style.display = 'none';
  select.setAttribute('tabindex', '-1');
  select.setAttribute('aria-hidden', 'true');

  const wrap = document.createElement('div');
  wrap.className = 'ssel';
  wrap.innerHTML = `
    <input type="text" class="form-control ssel-input" id="${id}" autocomplete="off" spellcheck="false" />
    <span class="ssel-caret">▾</span>`;
  select.insertAdjacentElement('afterend', wrap);

  const input = wrap.querySelector('.ssel-input');
  const menu = document.createElement('div');
  menu.className = 'ssel-menu';
  menu.hidden = true;
  document.body.appendChild(menu);

  let filtered = [];
  let activeIdx = -1;

  const labelFor = (opt) => (opt ? opt.text : '');

  function sortedOptions() {
    const opts = Array.from(select.options);
    const placeholders = opts.filter((o) => o.value === '');
    const rest = opts.filter((o) => o.value !== '');
    rest.sort((a, b) => a.text.localeCompare(b.text, 'hu', { sensitivity: 'base', numeric: true }));
    return [...placeholders, ...rest];
  }

  function syncInputToSelection() {
    input.value = labelFor(select.options[select.selectedIndex]);
    input.disabled = select.disabled;
  }
  select._sselSync = syncInputToSelection;

  function reposition() {
    const r = input.getBoundingClientRect();
    menu.style.left = r.left + window.scrollX + 'px';
    menu.style.top = r.bottom + window.scrollY + 2 + 'px';
    menu.style.width = r.width + 'px';
  }

  function renderMenu(filterText) {
    const q = filterText.trim().toLocaleLowerCase('hu');
    const all = sortedOptions();
    filtered = q ? all.filter((o) => o.text.toLocaleLowerCase('hu').includes(q)) : all;
    activeIdx = filtered.indexOf(select.options[select.selectedIndex]);
    if (!filtered.length) {
      menu.innerHTML = '<div class="ssel-empty">Nincs találat</div>';
    } else {
      menu.innerHTML = filtered
        .map((o, i) => `<div class="ssel-item${i === activeIdx ? ' active' : ''}" data-idx="${i}">${esc(o.text)}</div>`)
        .join('');
    }
  }

  function highlightActive() {
    Array.from(menu.children).forEach((c, i) => c.classList.toggle('active', i === activeIdx));
    const activeEl = menu.children[activeIdx];
    if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
  }

  function chooseOption(opt) {
    if (!opt) return;
    const idx = Array.prototype.indexOf.call(select.options, opt);
    if (idx !== select.selectedIndex) {
      select.selectedIndex = idx;
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    closeMenu();
  }

  function onDocMouseDown(e) {
    if (wrap.contains(e.target) || menu.contains(e.target)) return;
    closeMenu();
  }
  // 'scroll' events don't bubble, but they do fire during the capturing
  // phase on ancestor listeners — so scrolling *inside* the menu (wheel,
  // scrollbar drag) also reaches this window-level listener. Only close
  // for scrolling elsewhere on the page (e.g. the modal body, the page
  // itself), not for scrolling the menu's own option list.
  function onScrollOrResize(e) {
    if (e.target === menu || (e.target.nodeType === 1 && menu.contains(e.target))) return;
    closeMenu();
  }

  function openMenu() {
    if (select.disabled) return;
    reposition();
    renderMenu('');
    menu.hidden = false;
    wrap.classList.add('open');
    document.addEventListener('mousedown', onDocMouseDown, true);
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);
  }
  function closeMenu() {
    menu.hidden = true;
    wrap.classList.remove('open');
    activeIdx = -1;
    document.removeEventListener('mousedown', onDocMouseDown, true);
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
    syncInputToSelection();
  }

  input.addEventListener('focus', () => { input.select(); openMenu(); });
  input.addEventListener('click', () => { if (menu.hidden) openMenu(); });
  input.addEventListener('input', () => {
    if (menu.hidden) openMenu();
    renderMenu(input.value);
  });
  input.addEventListener('keydown', (e) => {
    if (menu.hidden) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); openMenu(); }
      return;
    }
    if (e.key === 'ArrowDown') { e.preventDefault(); if (filtered.length) { activeIdx = (activeIdx + 1) % filtered.length; highlightActive(); } }
    else if (e.key === 'ArrowUp') { e.preventDefault(); if (filtered.length) { activeIdx = (activeIdx - 1 + filtered.length) % filtered.length; highlightActive(); } }
    else if (e.key === 'Enter') { e.preventDefault(); if (activeIdx >= 0) chooseOption(filtered[activeIdx]); }
    else if (e.key === 'Escape') { e.preventDefault(); closeMenu(); }
  });
  menu.addEventListener('mousedown', (e) => e.preventDefault());
  menu.addEventListener('click', (e) => {
    const item = e.target.closest('.ssel-item[data-idx]');
    if (!item) return;
    chooseOption(filtered[Number(item.dataset.idx)]);
  });

  // Az opciólista (pl. helyszín→részleg kaszkád) külső kódból
  // frissül (innerHTML csere) — ilyenkor kövessük az új kiválasztást.
  new MutationObserver(() => {
    syncInputToSelection();
    if (!menu.hidden) renderMenu('');
  }).observe(select, { childList: true });

  syncInputToSelection();
}
