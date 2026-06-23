# Architecture

**Analysis Date:** 2026-06-23

## Pattern

Vanilla JavaScript single-page application (no framework) backed by a **PHP +
MySQL/MariaDB API** (separate repo `eszkoznyilvantartas_api`). The browser store
is an in-memory **mirror** of the backend: it hydrates from `GET /bootstrap`,
runs every mutation through the API, and re-fetches the changed slices afterwards.
The UI re-renders in response to store notifications. The backend is the single
source of truth — there is no `localStorage` persistence.

## Layers

| Layer | Location | Responsibility |
|-------|----------|----------------|
| App shell | `src/appshell.js` | Builds topbar/sidebar, registers routes, SSO handoff + dev auto-login, global store subscriber + re-render loop |
| Routing | `src/lib/router.js` | Hash-based client router (`#/inventory`, `#/device/:id`) |
| API client | `src/lib/api.js` | `apiGet`/`apiSend` fetch wrapper; unwraps `{ok,data}`; throws `OpError` |
| State mirror | `src/state/store.js` | In-memory `state` object, sync getters, async mutations (call API + `refresh()`), pub/sub |
| View-model | `src/lib/vm.js` | `deviceVM(dev)` binds backend-computed fields to lookup objects for rendering |
| Display helpers | `src/lib/format.js` | Pure label/date/escape formatting |
| Views | `src/views/*.js` | One `renderXxx(el, params)` per route; writes `innerHTML`, binds listeners |
| Action dialogs | `src/ui/actions.js` | `dlgXxx()`/`doXxx()` flows that collect input and call store actions |
| UI components | `src/ui/components.js` | `icons`, `toast()`, `openModal()` (async `onConfirm`)/`closeModal()` |

## Data Flow

1. `index.html` loads `src/appshell.js` as an ES module.
2. `init()` performs the optional SSO handoff (`?sso=<token>&u=<user>&t=<ts>` →
   `POST /auth/sso`), then `hydrate()` → `GET /bootstrap`. In dev, if no session
   exists it auto-logs-in a seed user.
3. The shell builds chrome, sets up hash routes, and subscribes to the store.
4. A route resolves → the matching `renderXxx(el, params)` runs, calling
   `deviceVM()` to project store state into render-ready view-models.
5. User triggers an action → a `dlgXxx()` opens a modal → on confirm it `await`s a
   store mutation (`moveAsset`, `confirmCheckIn`, `registerDevice`, …).
6. The store mutation calls the API, then `refresh()`es the dynamic slices
   (`/devices`, `/pending`, `/reservations`) and calls `notify()`.
7. `notify()` fires the global subscriber → the current view re-renders.

**Custody model:** device status is **derived on the backend** from an append-only
custody event log (`device_custody_events`), not stored on the frontend.
`Repo::effectiveStatus()` computes it server-side; the enriched device carries the
result, and `deviceVM()` simply exposes `dev.status` plus the bound holder/location.

## Key Abstractions

- **Backend mirror store** — `state` object with `subscribe()`/`notify()`; async
  mutations keep it in sync with the authoritative API.
- **Hydrate + refresh** — `hydrate()` loads everything (lookups + dynamic slices +
  current user); `refresh()` reloads only devices/pending/reservations after a mutation.
- **View-model factory** — `deviceVM(dev)` binds `holder`, `reservedBy`, `type`,
  `calibrationFlag`, etc.; never persisted.
- **`OpError`** — error class (`src/lib/api.js`, re-exported from store) thrown on
  API/business-rule failures, caught in `openModal`'s `onConfirm` wrapper → toast.
- **Role hierarchy** — `user < storekeeper < it_admin`. Authoritatively enforced by
  the API; `roleAtLeast()` on the frontend only gates which UI is shown.

## Entry Points

- `index.html` → `src/appshell.js` (app bootstrap).
- Router resolves hash routes to `src/views/*.js` render functions.

## Notable Anti-Pattern

Global re-render on every `notify()` (`src/appshell.js`, `renderCurrent`)
re-renders the whole current view, discarding DOM state (focus, scroll) on any
action. Acceptable at this size; the main UX concern if the app grows.

---

*Architecture analysis: 2026-06-23*
