// ============================================================
// Táblázat automatikus méretezése a rendelkezésre álló szélességhez,
// hogy sose kelljen vízszintesen görgetni az eszközlistát. A táblázatot
// összezsugorítjuk (transform: scale), amíg ez olvasható marad; egy
// minimum-küszöb alatt visszaváltunk a hagyományos vízszintes scrollra.
// ============================================================

const MIN_SCALE = 0.55; // e alatt inkább scroll, mint olvashatatlan szöveg
const observed = new WeakMap(); // wrapEl -> ResizeObserver

export function fitTableToWidth(wrapEl) {
  const table = wrapEl.querySelector('table');
  if (!table) return;

  // 1) mérés természetes (skálázatlan) méretben
  table.style.transform = 'none';
  wrapEl.style.height = 'auto';
  const naturalWidth = table.scrollWidth;
  const naturalHeight = table.scrollHeight;
  const available = wrapEl.clientWidth;
  if (!naturalWidth || !available) return;
  const scale = Math.min(1, available / naturalWidth);

  if (scale < MIN_SCALE) {
    // Túl keskeny lenne a szöveg — visszaváltás a régi scroll-viselkedésre,
    // a táblázat a saját természetes méretében marad.
    table.style.transform = 'none';
    table.style.width = '';
    wrapEl.style.height = 'auto';
    wrapEl.style.overflowX = 'auto';
    return;
  }

  wrapEl.style.overflowX = 'hidden';
  table.style.transformOrigin = 'top left';
  table.style.transform = `scale(${scale})`;
  // a scale csak vizuálisan zsugorít, a layout-szélesség marad natural —
  // explicit width kell, hogy a wrap ne hagyjon üres helyet a jobb oldalon
  table.style.width = naturalWidth + 'px';
  wrapEl.style.height = (naturalHeight * scale) + 'px';
}

// Figyeli a konténer méretének (és ezzel közvetve a böngésző-zoomnak a)
// változását, és újra igazítja a táblázatot. Egy wrapEl-re csak egyszer
// köti fel a figyelést (ismételt hívás no-op).
export function watchFitToWidth(wrapEl) {
  if (observed.has(wrapEl)) return;
  const ro = new ResizeObserver(() => fitTableToWidth(wrapEl));
  ro.observe(wrapEl);
  window.addEventListener('resize', () => fitTableToWidth(wrapEl));
  observed.set(wrapEl, ro);
}
