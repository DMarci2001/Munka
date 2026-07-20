// ============================================================
// Újrahasznosítható UI elemek: ikonok, toast, modal
// ============================================================

// ---- Egyszerű inline SVG ikonok (stroke-alapú) --------------
const I = (paths, extra = '') =>
  `<svg class="ico-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ${extra}>${paths}</svg>`;

export const icons = {
  dashboard: I('<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>'),
  inventory: I('<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>'),
  my: I('<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/>'),
  pending: I('<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>'),
  register: I('<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>'),
  search: I('<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>'),
  check: I('<path d="M20 6L9 17l-5-5"/>'),
  x: I('<path d="M18 6L6 18M6 6l12 12"/>'),
  arrowRight: I('<path d="M5 12h14M13 6l6 6-6 6"/>'),
  qr: I('<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3M21 14v7M17 21h4M14 21h0"/>'),
  back: I('<path d="M19 12H5M11 18l-6-6 6-6"/>'),
  bookmark: I('<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>'),
  edit: I('<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>'),
  repair: I('<path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.8 2.8-2.1-2.1z"/>'),
  warning: I('<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h0"/>'),
  building: I('<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>'),
  printer: I('<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>'),
};

// ---- Toast --------------------------------------------------
let toastStack;
export function toast(message, type = 'default') {
  if (!toastStack) {
    toastStack = document.createElement('div');
    toastStack.className = 'toast-stack';
    document.body.appendChild(toastStack);
  }
  const el = document.createElement('div');
  el.className = `toast-c ${type}`;
  el.innerHTML = `<span>${type === 'success' ? icons.check : type === 'error' ? icons.warning : ''}</span><span>${message}</span>`;
  toastStack.appendChild(el);
  setTimeout(() => {
    el.style.transition = 'opacity .25s, transform .25s';
    el.style.opacity = '0';
    el.style.transform = 'translateX(20px)';
    setTimeout(() => el.remove(), 260);
  }, 3200);
}

// ---- Modal --------------------------------------------------
// openModal({ title, bodyHTML, confirmText, confirmClass, onConfirm, onMount })
// onConfirm(rootEl) → return false to keep open; throw/reject OpError → toast hiba
// (onConfirm lehet async; futás közben a megerősítő gomb letiltva)
export function openModal({ title, bodyHTML, confirmText = 'Mentés', confirmClass = 'btn-primary', onConfirm, onMount, wide = false }) {
  closeModal();
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop-c';
  backdrop.innerHTML = `
    <div class="modal-c" style="${wide ? 'max-width:680px' : ''}">
      <div class="m-head">${title}<button class="close" data-close>&times;</button></div>
      <div class="m-body">${bodyHTML}</div>
      <div class="m-foot">
        <button class="btn btn-outline" data-close>Mégse</button>
        ${onConfirm ? `<button class="btn ${confirmClass}" data-confirm>${confirmText}</button>` : ''}
      </div>
    </div>`;
  document.body.appendChild(backdrop);
  const root = backdrop.querySelector('.modal-c');

  const close = () => backdrop.remove();
  backdrop.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', close));
  backdrop.addEventListener('mousedown', (e) => { if (e.target === backdrop) close(); });
  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
  });

  const confirmBtn = backdrop.querySelector('[data-confirm]');
  if (confirmBtn && onConfirm) {
    confirmBtn.addEventListener('click', async () => {
      if (confirmBtn.disabled) return;
      confirmBtn.disabled = true;
      try {
        const result = await onConfirm(root);
        if (result !== false) close();
        else confirmBtn.disabled = false;   // validáció bukott → nyitva marad, újra próbálható
      } catch (err) {
        toast(err.message || 'Hiba történt', 'error');
        confirmBtn.disabled = false;        // hiba → nyitva marad
      }
    });
  }
  if (onMount) onMount(root);
  return { close, root };
}

export function closeModal() {
  document.querySelectorAll('.modal-backdrop-c').forEach((m) => m.remove());
}
