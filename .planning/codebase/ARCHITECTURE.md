# Architecture

**Analysis Date:** 2026-06-12

## Pattern

Vanilla JavaScript single-page application (no framework) built around an
**observable store** pattern. All state lives in one in-memory object backed by
`localStorage`; the UI re-renders in response to store notifications. There is no
backend — this is a frontend demo/prototype of an asset-inventory system.

## Layers

| Layer | Location | Responsibility |
|-------|----------|----------------|
| App shell | `src/appshell.js` | Builds topbar/sidebar, registers routes, global store subscriber + re-render loop |
| Routing | `src/lib/router.js` | Hash-based client router (`#/inventory`, `#/device/:id`) |
| State | `src/state/store.js` | Single `state` object, append-only custody event log, persistence, pub/sub, all mutations |
| View-model | `src/lib/vm.js` | `deviceVM(dev)` enriches raw device records for rendering |
| Display helpers | `src/lib/format.js` | Pure label/date/escape formatting |
| Views | `src/views/*.js` | One `renderXxx(el, params)` per route; writes `innerHTML`, binds listeners |
| Action dialogs | `src/ui/actions.js` | `dlgXxx()` modal flows that collect input and call store actions |
| UI components | `src/ui/components.js` | `icons`, `toast()`, `openModal()`/`closeModal()` |
| Seed data | `src/data/seed.js` | Static demo fixtures (18 devices, 6 users, locations, departments) |

## Data Flow

1. `index.html` loads `src/appshell.js` as an ES module.
2. The shell builds chrome, sets up hash routes, and subscribes to the store.
3. A route resolves → the matching `renderXxx(el, params)` runs, calling
   `deviceVM()` to project store state into render-ready view-models.
4. User triggers an action → a `dlgXxx()` opens a modal → on confirm it calls a
   store mutation (`moveAsset`, `confirmCheckIn`, `registerDevice`, ...).
5. The mutation appends to the custody event log (`state.events`), persists to
   `localStorage`, and calls `notify()`.
6. `notify()` fires the global subscriber → the current view re-renders.

**Custody model:** device status is **derived** from an append-only event log, not
stored directly. `currentState()` / `statusFromEvent()` compute effective status
from the latest events; `deviceVM()` exposes it as `effectiveStatus`.

## Key Abstractions

- **Observable store** — single source of truth with `subscribe()`/`notify()`.
- **Event-sourced custody** — append-only `state.events`; status is a projection.
- **View-model factory** — `deviceVM(dev)` adds `effectiveStatus`, `holder`,
  `isFree`, `calibrationFlag`, `pending`, `reservation`; never persisted.
- **`OpError`** — domain error class (`src/state/store.js`) thrown by mutations,
  caught in `openModal`'s `onConfirm` wrapper (`src/ui/components.js`) → toast.
- **Role hierarchy** — `user < storekeeper < it_admin` enforced by `roleAtLeast()`
  and `requireStorekeeper()` / `requireAdmin()` guards.

## Entry Points

- `index.html` → `src/appshell.js` (app bootstrap).
- Router resolves hash routes to `src/views/*.js` render functions.

## Notable Anti-Pattern

Global re-render on every `notify()` (`src/appshell.js` ~line 133) re-renders the
whole current view, discarding DOM state (focus, scroll) on any action. Acceptable
for a demo, but the main scaling/UX concern if this grows.

---

*Architecture analysis: 2026-06-12*
