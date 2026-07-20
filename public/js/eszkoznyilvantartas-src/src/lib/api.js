// ============================================================
// API kliens — vékony fetch-burkoló a PHP backendhez.
//
// A backend válaszburka: { ok:true, data } | { ok:false, error }.
// Itt kibontjuk: siker → data; hiba → OpError(error) dobás, hogy a UI
// a megszokott `e.message` toastolást használhassa.
//
// Bázis-URL:
//   dev   → '/api'  (Vite proxy a vite.config.js-ben → azonos-origin, nincs CORS)
//   éles  → VITE_API_BASE vagy a beágyazott alapértelmezett útvonal
// ============================================================

const API_BASE = import.meta.env.DEV
  ? '/api'
  : (import.meta.env.VITE_API_BASE || '/eszkoznyilvantartas/backend');

// Üzleti / API hiba — a UI ennek a message-ét toastolja.
export class OpError extends Error {}

async function request(method, path, body) {
  const opts = { method, credentials: 'include', headers: {} };
  if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(API_BASE + path, opts);
  } catch (e) {
    throw new OpError('Hálózati hiba — a szerver nem érhető el.');
  }

  let json = null;
  try { json = await res.json(); } catch (e) { /* nem JSON törzs */ }

  if (!json || typeof json.ok !== 'boolean') {
    throw new OpError(`Váratlan szerverválasz (HTTP ${res.status}).`);
  }
  if (!json.ok) {
    throw new OpError(json.error || `Hiba történt (HTTP ${res.status}).`);
  }
  return json.data;
}

export const apiGet  = (path) => request('GET', path);
export const apiSend = (method, path, body) => request(method, path, body);
