// ============================================================
// Egyszerű hash-alapú router
//   #/                     → irányítópult
//   #/inventory            → eszközlista
//   #/device/:id           → eszköz részletei
//   #/my                   → nálam lévő eszközök
//   #/pending              → ellenőrzésre váró visszavételek (raktáros)
//   #/register             → új eszköz (raktáros)
// ============================================================

const routes = [];
let notFound = null;

export function route(pattern, handler) {
  // pattern pl. '/device/:id'
  const keys = [];
  const regex = new RegExp(
    '^' + pattern.replace(/:[^/]+/g, (m) => {
      keys.push(m.slice(1));
      return '([^/]+)';
    }) + '$'
  );
  routes.push({ regex, keys, handler });
}

export function setNotFound(handler) { notFound = handler; }

export function navigate(path) {
  if (location.hash !== '#' + path) location.hash = '#' + path;
  else resolve();
}

export function currentPath() {
  const h = location.hash.replace(/^#/, '');
  return h || '/';
}

function resolve() {
  const path = currentPath();
  for (const r of routes) {
    const m = path.match(r.regex);
    if (m) {
      const params = {};
      r.keys.forEach((k, i) => (params[k] = decodeURIComponent(m[i + 1])));
      r.handler(params);
      return;
    }
  }
  if (notFound) notFound();
}

export function startRouter() {
  window.addEventListener('hashchange', resolve);
  resolve();
}
