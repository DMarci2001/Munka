// ============================================================
// Belépési pont — alkalmazás-héj, navigáció, route-kezelés
// Hordozható Eszköznyilvántartás · Hungária Med-M Kft.
// ============================================================

import { route, setNotFound, startRouter, navigate } from './lib/router.js';
import {
  subscribe, getUsers, currentUser, currentRole, roleAtLeast, setCurrentUser,
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

// ---- Route-tábla --------------------------------------------
const PAGES = {
  '/inventory': { title: 'Eszközlista',                        nav: 'inventory', render: renderInventory },
  '/my':        { title: 'Nálam',             nav: 'my',        render: renderMyDevices },
  '/pending':   { title: 'Ellenőrzésre vár', nav: 'pending',   render: renderPending, role: 'storekeeper' },
  '/register-data':  { title: 'Adatbevitel',      nav: 'register-data', render: renderRegisterData, role: 'storekeeper' },
  '/register':  { title: 'Új eszköz bevitele', nav: 'register', render: renderRegister, role: 'storekeeper' },
  '/device/:id':{ title: 'Készülék részletei',                nav: 'inventory', render: renderDevice },
  '/scan':           { title: 'Beolvasás',            nav: 'scan',         render: renderScan },        // ← új
  '/scan/:tag':      { title: 'Beolvasás',            nav: 'scan',         render: renderScan },        // ← új

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
          <div class="user-switch" title="Demó: válts felhasználót a szerepkörök kipróbálásához">
            <span class="avatar" id="avatar"></span>
            <select id="user-select"></select>
            <span class="role-pill" id="role-pill"></span>
          </div>
        </div>
        <div id="content"></div>
      </main>
    </div>`;

  const sel = document.getElementById('user-select');
  sel.innerHTML = getUsers().map((u) => `<option value="${u.id}">${esc(u.full_name)}</option>`).join('');
  sel.addEventListener('change', () => {
    setCurrentUser(Number(sel.value)); // notify → re-render
    const page = PAGES[active.key];
    if (page?.role && !roleAtLeast(currentRole(), page.role)) navigate('/');
  });

  // Hamburger — mobil oldalsáv megnyitás/zárás
  const hamburger = document.getElementById('btn-hamburger');
  const overlay = document.getElementById('sidebar-overlay');
  const sidebar = document.getElementById('sidebar');
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
      // mobil: navigáció után zárjuk be az oldalsávot
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
  if (sel.value !== String(u.id)) sel.value = String(u.id);
  const pill = document.getElementById('role-pill');
  pill.textContent = roleLabel(role);
  pill.className = 'role-pill ' + role;
}

function initials(name) {
  return name.replace(/^Dr\.?\s*/, '').split(/\s+/).map((s) => s[0]).slice(0, 2).join('').toUpperCase();
}

// ---- Aktuális nézet -----------------------------------------
function renderCurrent() {
  const page = PAGES[active.key];
  if (page?.role && !roleAtLeast(currentRole(), page.role)) { navigate('/'); return; }
  renderNav();
  renderTopbar();
  const content = document.getElementById('content');
  content.innerHTML = '';
  page.render(content, active.params);
}

// ---- Route-ok -----------------------------------------------
function setupRoutes() {
  route('/', () => { active = { key: '/', params: {} }; renderCurrent(); });
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

// ---- Store-változásra újrarajzolás --------------------------
subscribe(() => renderCurrent());

// ---- Indítás ------------------------------------------------
buildShell();
setupRoutes();
startRouter();
