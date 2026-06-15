# Coding Conventions

**Analysis Date:** 2026-06-12

## Naming Patterns

**Files:**
- Views use camelCase or snake_case `.js`: `myDevices.js`, `register_device.js`, `register_data.js`
- Non-view source files use camelCase: `store.js`, `vm.js`, `format.js`, `router.js`
- Main entry point: `appshell.js` (kebab-case, app shell + router init)
- Seed data: `seed.js`
- No TypeScript; all source files are plain `.js` ES modules

**Functions:**
- View render functions: `render<ViewName>(el, params?)` — `renderInventory`, `renderDevice`, `renderPending`
- Modal dialog openers: `dlg<ActionName>(deviceId)` — `dlgCheckOut`, `dlgCheckIn`, `dlgTransfer`
- Simple inline actions without a modal: `do<ActionName>` — `doReserve`, `doCancelReservation`
- Store read exports: `get<Entity>()` (all) or `get<Entity>(id)` (single) — `getDevices`, `getDevice`
- Store state queries: `current<Thing>` — `currentUser`, `currentRole`, `currentState`
- Boolean flags on viewmodel: `is<Condition>` or gerund — `isFree`, `isLost`, `inRepair`
- Role guard helpers (throw on failure): `require<Role>` — `requireStorekeeper`, `requireAdmin`
- Private module helpers (not exported): plain camelCase — `pushEvent`, `touch`, `notify`, `persist`

**Variables:**
- `camelCase` for all local variables
- `SCREAMING_SNAKE_CASE` for module-level lookup tables and constants: `STATUS`, `ROLE`, `EVENT`, `CONF`, `STATUSES`, `PAGES`, `STORAGE_KEY`
- Private mutable counters: underscore prefix — `_eventId`, `_resvId`, `_deviceId`
- Hungarian-language domain values (status/event strings) stored as keys in ALL-CAPS constant objects

**Data shapes:**
- No TypeScript; shapes defined implicitly by seed data and `pushEvent` defaults in `src/state/store.js`
- ViewModel shape defined by the return of `deviceVM()` in `src/lib/vm.js`

## Code Style

**Formatting:**
- No formatter config (no `.prettierrc`, no `biome.json`) present
- 2-space indentation used consistently
- Single quotes for JS strings
- Backtick template literals used exclusively for HTML string generation
- Trailing semicolons present
- Arrow functions for callbacks and short expressions; `function` keyword for named module-level functions
- Optional chaining (`?.`) used throughout for safe lookup on potentially-null values
- Ternary chains used for short conditional display returns (e.g., `STATUS[s]?.label || s || '—'`)

**Linting:**
- No ESLint config present; linting not enforced

## Import Organization

**Conventional order (not enforced by tooling):**
1. Store: `../state/store.js`
2. Lib: `../lib/vm.js`, `../lib/format.js`, `../lib/router.js`
3. UI: `../ui/components.js`, `../ui/actions.js`

**Path aliases:** None — all imports use relative paths

**Namespace imports:**
- `actions.js` imported as namespace in all views: `import * as A from '../ui/actions.js'`
- All other imports use named specifiers: `import { foo, bar } from '...'`

**Dynamic imports:**
- Used once for lazy-loading on device detail page: `import('./register_device.js').then((m) => m.dlgEditDevice(id))`

## Error Handling

**Domain errors:**
- Custom class `OpError extends Error` exported from `src/state/store.js`
- All business-rule violations throw `new OpError('Hungarian message')`
- Role guards (`requireStorekeeper`, `requireAdmin`) throw `OpError` directly
- Pattern in `src/ui/actions.js`: `try { storeAction(); toast('success', 'success'); } catch (e) { toast(e.message, 'error'); }`

**Modal confirm errors:**
- `openModal`'s `onConfirm` callback is wrapped in try/catch inside `src/ui/components.js`; any thrown `OpError` auto-shows as an error toast
- Returning `false` from `onConfirm` keeps the modal open (used for inline validation before calling store)

**Infrastructure errors:**
- `persist()` in `src/state/store.js` wraps `localStorage.setItem` in a silent `try/catch`
- `loadPersisted()` returns `false` on any error and falls back to `bootstrap()`

**Null safety:**
- Lookup functions (`getDevice`, `getUser`, etc.) return `null` when not found
- Display helpers (`esc`, `fmtDate`) guard `null`/`undefined` and return `'—'`
- Callers use optional chaining for all store lookups: `v.holder?.full_name`

## Logging

**Framework:** `console.log` only, used only in `_t.mjs` (dev-only debug script at project root)

**Patterns:**
- No structured logging in production source
- Silent `catch` blocks for non-critical infrastructure errors (localStorage)

## Comments

**Style:**
- Module header: `// ============================================================` banner with Hungarian description
- Section dividers within a module: `// ---- Section name ---` pattern
- Business logic and SQL-equivalent view logic explained in Hungarian inline comments
- JSDoc not used anywhere

**Language:** All comments are in Hungarian

## Function Design

**Size:**
- View render functions (`renderInventory`, `renderDevice`): large (50–150 lines), combining HTML generation and event binding in one function
- Store action functions: medium (10–40 lines) — validate then mutate then `notify()`
- Helpers and formatters: small (1–10 lines)

**Parameters:**
- Multi-field store actions use destructured objects: `moveAsset({ device_id, event_type, to_user_id, ... })`
- View render functions: `(el, params?)` where `el` is the DOM container element
- Dialog functions: single `deviceId` or `eventId` scalar

**Return values:**
- Store reads: data or `null`
- Store mutations: `void` (side effects + `notify()`)
- Exception: `registerDevice` returns the created device object
- `openModal` returns `{ close, root }` for programmatic control
- `subscribe` returns an unsubscribe function

## Module Design

**Exports:**
- `src/state/store.js`: named exports only — all getters, mutations, `OpError`
- `src/lib/format.js`: named exports only — pure display helpers
- `src/lib/vm.js`: single named export `deviceVM`
- `src/lib/router.js`: named exports — `route`, `navigate`, `startRouter`, `currentPath`, `setNotFound`
- `src/ui/components.js`: named exports — `icons`, `toast`, `openModal`, `closeModal`
- `src/ui/actions.js`: named exports — all `dlg*` and `do*` dialog/action functions
- `src/views/*.js`: named export `render<View>` per file; `register_device.js` also exports `dlgEditDevice`

**Barrel files:** Not used; direct named imports from source files

**Dependency direction:**
- Views → store, lib, ui (never import other views directly; `register_device.js` lazy-loaded from `device.js`)
- `actions.js` → store, components, format (never imports views)
- `format.js` → store (lookups for display) — creates coupling between display and state layers
- `vm.js` → store, format

---

*Convention analysis: 2026-06-12*
