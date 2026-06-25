# Eszköznyilvántartó — Project Instructions

## Project Overview

Medical device inventory management SPA for Hungária Med-M Kft.
The SPA is backed by a **PHP + MySQL/MariaDB API** (the `backend/` directory of
this monorepo, served under `C:/xampp/htdocs/eszkoznyilvantartas/backend`).
The backend is the single source of truth; the browser store is an in-memory
**mirror** that hydrates from `GET /bootstrap` and re-fetches the dynamic slices
after every mutation. There is no `localStorage` persistence.

## Stack

- **Vanilla JS** (ES Modules) — no UI framework
- **Vite** (dev server + build) — `npm run dev`; config in `vite.config.js`
  (dev proxy `/api → http://localhost/eszkoznyilvantartas/backend`, base `./`)
- **Bootstrap 5.3.x** — loaded from CDN in `index.html`; npm package present but
  not used at runtime
- **qrcode** — lazy-imported only by `src/ui/qrLabel.js`
- **Hash-based SPA router** — `src/lib/router.js`
- **No TypeScript, no test suite, no linter config**

## Code Style

- 2-space indentation, single quotes, trailing semicolons
- Template literals for all HTML generation
- Arrow functions for callbacks; `function` keyword for named module-level functions
- Optional chaining (`?.`) for all store lookups that may return null

## Naming Conventions

- View render functions: `render<ViewName>(el, params?)`
- Modal openers: `dlg<ActionName>(deviceId)`
- Inline actions (no modal): `do<ActionName>`
- Store reads: `get<Entity>()` / `get<Entity>(id)`
- Module-level constants: `SCREAMING_SNAKE_CASE`

## Architecture

```
src/
  appshell.js       — app shell + router init (entry point); SSO handoff + dev auto-login
  lib/
    api.js          — thin fetch wrapper around the PHP API; exports OpError
    router.js       — hash-based SPA router
    vm.js           — deviceVM() viewmodel builder (binds backend fields to lookups)
    format.js       — pure display helpers (esc, fmtDate, statusBadge, …)
  state/store.js    — in-memory mirror of the backend; async mutations + sync getters + pub/sub
  ui/
    components.js   — toast, openModal (async onConfirm), icons
    actions.js      — all dlg*/do* dialog/action functions; imported as `* as A`
    qrLabel.js      — QR-label modal (lazy-loaded)
  views/            — one render<View> export per file
    inventory.js, device.js, pending.js, myDevices.js,
    register_device.js, register_data.js, scan.js
```

**Dependency direction:** views → store/lib/ui. Never import views from other
views (except lazy: `import('./register_device.js')`). `format.js` and `vm.js`
may import lookups from store. `store.js` → `lib/api.js`.

## Data Flow

1. `init()` runs the optional SSO handoff (`?sso=…`), then `hydrate()` → `GET /bootstrap`.
2. A route resolves → the matching `render<View>(el, params)` runs, projecting
   store state through `deviceVM()`.
3. A user action opens a `dlg<Action>()` modal → `onConfirm` (async) calls a store
   mutation (`moveAsset`, `confirmCheckIn`, `registerDevice`, …).
4. The store mutation `await`s the API call, then `refresh()`es the changed slices
   (devices + pending + reservations) and calls `notify()` → re-render.

## Domain Rules

- All custody movements go through `moveAsset()` (store → `POST /devices/move`)
- The backend derives current holder/location/status from the latest **confirmed**
  custody event; the store exposes those fields and `deviceVM()` binds them
- User role hierarchy: `user < storekeeper < it_admin`. The API enforces roles;
  the frontend's `roleAtLeast()` only gates what UI is shown
- `OpError extends Error` (in `lib/api.js`) for all API/business-rule errors —
  surfaced as an error toast by `actions.js` / `openModal`
- A `user`-initiated `check_in` is `pending`; a storekeeper must confirm or reject
- Storage departments (`type === 'raktár'`) never have a holder — device goes to stock

## Comments & Language

- All comments in **Hungarian**
- Module headers use `// ============================================================` banner
- Section dividers: `// ---- Section name ---`
- No JSDoc

## What to Avoid

- Do not add a UI framework, TypeScript, or test runner unless explicitly asked
- Do not reintroduce `localStorage` persistence — the API is the source of truth
- Do not create new files unless necessary; extend existing modules
- Do not refactor code outside the scope of the current task
- Never commit without being explicitly asked
