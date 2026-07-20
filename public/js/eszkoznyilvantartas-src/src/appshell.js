// ============================================================
// Belépési pont — alkalmazás-héj, navigáció, route-kezelés
// Hordozható Eszköznyilvántartás · Hungária Med-M Kft.
//
// Adatforrás: a PHP backend (store.hydrate). Az identitást a host oldal
// munkamenete adja (/me). A demó felhasználóváltó CSAK fejlesztésben látszik.
// ============================================================

import { route, setNotFound, startRouter, navigate } from './lib/router.js';
import { apiSend } from './lib/api.js';
import {
  subscribe, hydrate, getUsers, currentUser, currentRole, roleAtLeast, setCurrentUser,
  pendingCheckins,
} from './state/store.js';
import { roleLabel, esc } from './lib/format.js';
import { icons } from './ui/components.js';

import { renderInventory } from './views/inventory.js';
import { renderDevice } from './views/device.js';
import { renderMyDevices } from './views/myDevices.js';
import { renderPending } from './views/pending.js';
import { renderRegister } from './views/register_device.js';
import { renderRegisterData } from './views/register_data.js';
import { renderScan } from './views/scan.js';

const IS_DEV = import.meta.env.DEV;
const DEV_DEFAULT_USER = 'szabo.julia';   // dev auto-login (raktáros)

// ---- Route-tábla --------------------------------------------
const PAGES = {
  '/inventory': { title: 'Eszközlista',                        nav: 'inventory', render: renderInventory },
  '/my':        { title: 'Nálam',             nav: 'my',        render: renderMyDevices },
  '/pending':   { title: 'Ellenőrzésre vár', nav: 'pending',   render: renderPending, role: 'storekeeper' },
  '/register-data':  { title: 'Adatbevitel',      nav: 'register-data', render: renderRegisterData, role: 'storekeeper' },
  '/register':  { title: 'Új eszköz bevitele', nav: 'register', render: renderRegister, role: 'storekeeper' },
  '/device/:id':{ title: 'Készülék részletei',                nav: 'inventory', render: renderDevice },
  '/scan':           { title: 'Beolvasás',            nav: 'scan',         render: renderScan },
  '/scan/:tag':      { title: 'Beolvasás',            nav: 'scan',         render: renderScan },
};

let active = { key: '/', params: {} };

// ---- Héj felépítése -----------------------------------------
function buildShell() {
  document.getElementById('app').innerHTML = `
    <div class="app-shell">
      <div class="sidebar-overlay" id="sidebar-overlay"></div>
      <aside class="sidebar" id="sidebar">
        <nav class="nav-section" id="nav"></nav>
      </aside>
      <main class="main">
        <div class="topbar">
          <button class="btn-hamburger" id="btn-hamburger" aria-label="Menü">
            <span></span><span></span><span></span>
          </button>
          <h1 id="page-title">Irányítópult</h1>
          <div class="spacer"></div>
          <div class="user-switch" title="${IS_DEV ? 'Demó: válts felhasználót a szerepkörök kipróbálásához' : ''}">
            <span class="avatar" id="avatar"></span>
            ${IS_DEV ? '<select id="user-select"></select>' : '<span class="user-name" id="user-name"></span>'}
            <span class="role-pill" id="role-pill"></span>
          </div>
        </div>
        <div id="content"></div>
      </main>
    </div>`;

  // Felhasználóváltó — CSAK fejlesztésben (éles: a host oldal kezeli az identitást)
  if (IS_DEV) {
    const sel = document.getElementById('user-select');
    sel.innerHTML = getUsers().map((u) => `<option value="${u.id}">${esc(u.full_name)}</option>`).join('');
    sel.addEventListener('change', async () => {
      try {
        await setCurrentUser(Number(sel.value)); // hydrate → notify → re-render
      } catch (e) { /* a hibát a store dobja; dev-ben elnyeljük */ }
      const page = PAGES[active.key];
      if (page?.role && !roleAtLeast(currentRole(), page.role)) navigate('/');
    });
  }

  // Hamburger — mobil oldalsáv megnyitás/zárás
  const hamburger = document.getElementById('btn-hamburger');
  const overlay = document.getElementById('sidebar-overlay');
  function closeSidebar() { document.getElementById('app').querySelector('.app-shell').classList.remove('sidebar-open'); }
  hamburger.addEventListener('click', () => {
    document.getElementById('app').querySelector('.app-shell').classList.toggle('sidebar-open');
  });
  overlay.addEventListener('click', closeSidebar);
}

// ---- Sidebar navigáció --------------------------------------
function renderNav() {
  const isStore = roleAtLeast(currentRole(), 'storekeeper');
  const pendingCount = pendingCheckins().length;
  const cur = PAGES[active.key]?.nav;

  const items = [
    { key: 'inventory', path: '/inventory', label: 'Eszközlista',     ico: icons.inventory },
    { key: 'my',        path: '/my',        label: 'Nálam',        ico: icons.my },
  ];
  const storeItems = [
    { key: 'pending',       path: '/pending',       label: 'Leadott eszközök', ico: icons.pending,   badge: pendingCount || null },
    { key: 'register-data', path: '/register-data', label: 'Adatbevitel', ico: icons.building },
  ];

  const itemHTML = (it) => `
    <a class="nav-item ${it.key === cur ? 'active' : ''}" data-path="${it.path}">
      <span class="ico">${it.ico}</span><span>${it.label}</span>
      ${it.badge ? `<span class="badge-count">${it.badge}</span>` : ''}
    </a>`;

  const nav = document.getElementById('nav');
  nav.innerHTML =
    `<div class="nav-label">Eszközök</div>` + items.map(itemHTML).join('') +
    (isStore ? `<div class="nav-label">Raktárkezelés</div>` + storeItems.map(itemHTML).join('') : '');
  nav.querySelectorAll('[data-path]').forEach((a) =>
    a.addEventListener('click', () => {
      navigate(a.dataset.path);
      document.getElementById('app').querySelector('.app-shell').classList.remove('sidebar-open');
    }));
}

// ---- Topbar -------------------------------------------------
function renderTopbar() {
  const u = currentUser();
  const role = currentRole();
  document.getElementById('page-title').textContent = PAGES[active.key]?.title || 'Eszköz';
  document.getElementById('avatar').textContent = initials(u.full_name);
  const sel = document.getElementById('user-select');
  if (sel && sel.value !== String(u.id)) sel.value = String(u.id);
  const nameEl = document.getElementById('user-name');
  if (nameEl) nameEl.textContent = u.full_name;
  const pill = document.getElementById('role-pill');
  pill.textContent = roleLabel(role);
  pill.className = 'role-pill ' + role;
}

function initials(name) {
  return name.replace(/^Dr\.?\s*/, '').split(/\s+/).map((s) => s[0]).slice(0, 2).join('').toUpperCase();
}

// ---- Aktuális nézet -----------------------------------------
function renderCurrent() {
  if (!document.getElementById('content')) return;   // héj még nem áll
  const page = PAGES[active.key];
  if (!page) { navigate('/inventory'); return; }
  if (page?.role && !roleAtLeast(currentRole(), page.role)) { navigate('/'); return; }
  renderNav();
  renderTopbar();
  const content = document.getElementById('content');
  content.innerHTML = '';
  page.render(content, active.params);
}

// ---- Route-ok -----------------------------------------------
function setupRoutes() {
  route('/', () => navigate('/inventory'));
  route('/inventory', () => { active = { key: '/inventory', params: {} }; renderCurrent(); });
  route('/my', () => { active = { key: '/my', params: {} }; renderCurrent(); });
  route('/pending', () => { active = { key: '/pending', params: {} }; renderCurrent(); });
  route('/register', () => { active = { key: '/register', params: {} }; renderCurrent(); });
  route('/register-data', () => { active = { key: '/register-data', params: {} }; renderCurrent(); });
  route('/device/:id', (params) => { active = { key: '/device/:id', params }; renderCurrent(); });
  route('/scan', () => { active = { key: '/scan', params: {} }; renderCurrent(); });
  route('/scan/:tag', (params) => { active = { key: '/scan/:tag', params }; renderCurrent(); });
  setNotFound(() => navigate('/'));
}

// ---- Egyszerű teljes-képernyős állapotok --------------------
function fullScreen(html) {
  document.getElementById('app').innerHTML =
    `<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
       <div style="text-align:center;max-width:420px;color:var(--ink,#333)">${html}</div>
     </div>`;
}

// ---- Indítás ------------------------------------------------
async function init() {
  fullScreen('<div class="muted">Betöltés…</div>');

  // SSO handoff from clinic website: ?sso=<token>&u=<username>&t=<timestamp>
  const ssoParams = new URLSearchParams(location.search);
  if (ssoParams.has('sso')) {
    try {
      await apiSend('POST', '/auth/sso', {
        token: ssoParams.get('sso'),
        username: ssoParams.get('u'),
        timestamp: Number(ssoParams.get('t')),
      });
    } catch (e) { /* expired or invalid token — fall through to normal auth check */ }
    history.replaceState(null, '', location.pathname + location.hash);
  }

  try {
    await hydrate();
  } catch (e) {
    fullScreen(`<div class="big">${icons.warning}</div>
      <h2>A szerver nem érhető el</h2>
      <p class="muted">Nem sikerült betölteni az adatokat. Ellenőrizd, hogy fut-e a backend.</p>`);
    return;
  }

  // Dev: ha nincs munkamenet, automatikus bejelentkezés egy alapértelmezett seed userrel.
  if (IS_DEV && !currentUser()) {
    const def = getUsers().find((u) => u.username === DEV_DEFAULT_USER) || getUsers()[0];
    if (def) { try { await setCurrentUser(def.id); } catch (e) { /* marad a login-üzenet */ } }
  }

  // Éles (vagy ha a dev auto-login nem sikerült): nincs bejelentkezett user.
  if (!currentUser()) {
    fullScreen(`<div class="big">${icons.my}</div>
      <h2>Bejelentkezés szükséges</h2>
      <p class="muted">Jelentkezz be a főoldalon az eszköznyilvántartó használatához.</p>`);
    return;
  }

  buildShell();
  setupRoutes();
  subscribe(() => renderCurrent());   // csak a héj felépítése UTÁN iratkozunk fel
  startRouter();
}

init();
