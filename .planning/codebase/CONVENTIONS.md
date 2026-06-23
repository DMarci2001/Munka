# Coding Conventions

**Analysis Date:** 2026-06-23

## Naming Patterns

**Files:**
- Views use camelCase or snake_case `.js`: `myDevices.js`, `register_device.js`, `register_data.js`, `scan.js`
- Non-view source files use camelCase: `store.js`, `vm.js`, `format.js`, `router.js`, `api.js`
- Main entry point: `appshell.js` (app shell + router init)
- No TypeScript; all source files are plain `.js` ES modules

**Functions:**
- View render functions: `render<ViewName>(el, params?)` — `renderInventory`, `renderDevice`, `renderPending`
- Modal dialog openers: `dlg<ActionName>(deviceId)` — `dlgCheckOut`, `dlgCheckIn`, `dlgTransfer`
- Simple inline actions without a modal: `do<ActionName>` — `doReserve`, `doCancelReservation`
- Store read exports: `get<Entity>()` (all) or `get<Entity>(id)` (single) — `getDevices`, `getDevice`
- Store state queries: `current<Thing>` — `currentUser`, `currentRole`, `currentState`
- Boolean flags on the viewmodel: `is<Condition>` / gerund — `isFree`, `isLost`, `inRepair`
- Private module helpers (not exported): plain camelCase — `notify`, `request`, `wireLocationDept`

**Variables:**
- `camelCase` for locals
- `SCREAMING_SNAKE_CASE` for module-level constants/lookup tables: `STATUS`, `ROLE`,
  `EVENT`, `CONF`, `STATUSES`, `PAGES`, `API_BASE`, `DEV_PASSWORD`
- Hungarian-language domain values (status/event strings) used as keys in ALL-CAPS constant objects

**Data shapes:**
- No TypeScript; the enriched-device shape is produced by the backend
  (`Repo::assemble`) and bound for display by `deviceVM()` in `src/lib/vm.js`

## Code Style

- 2-space indentation, single quotes, trailing semicolons
- Backtick template literals used exclusively for HTML string generation
- Arrow functions for callbacks/short expressions; `function` keyword for named module-level functions
- Optional chaining (`?.`) for safe lookups on potentially-null values
- Short ternary chains for conditional display (e.g. `STATUS[s]?.label || s || '—'`)
- No formatter/linter config (`.prettierrc`, `biome.json`, ESLint) present

## Import Organization

**Conventional order (not enforced):**
1. Store: `../state/store.js`
2. Lib: `../lib/vm.js`, `../lib/format.js`, `../lib/router.js`, `../lib/api.js`
3. UI: `../ui/components.js`, `../ui/actions.js`

**Path aliases:** none — all imports are relative.

**Namespace imports:** `actions.js` is imported as a namespace in views
(`import * as A from '../ui/actions.js'`); everything else uses named specifiers.

**Dynamic imports:** lazy-load on the device detail page —
`import('./register_device.js').then((m) => m.dlgEditDevice(id))` and
`import('../ui/qrLabel.js')`.

## Error Handling

**Domain / API errors:**
- `OpError extends Error` (exported from `src/lib/api.js`, re-exported from the store)
- `api.js` throws `OpError` on network failure, non-JSON responses, or `{ ok:false }`
- Pattern in `src/ui/actions.js`: `onConfirm` is `async`; it `await`s the store
  action and toasts success. Inline `do*` actions wrap the call in
  `try { await action(); toast(...) } catch (e) { toast(e.message, 'error') }`

**Modal confirm errors:**
- `openModal`'s `onConfirm` is awaited inside a try/catch (`src/ui/components.js`);
  a thrown/rejected `OpError` auto-shows as an error toast and keeps the modal open
- Returning `false` from `onConfirm` keeps the modal open (inline validation)

**Null safety:**
- Lookups (`getDevice`, `getUser`, …) return `null` when not found
- Display helpers (`esc`, `fmtDate`) guard `null`/`undefined` and return `'—'`
- Callers use optional chaining: `v.holder?.full_name`

## Logging

- `console` only on the client; no structured logging in production source
- Silent `catch` for non-critical paths (e.g. failed history fetch falls back to `[]`)

## Comments

- Module header: `// ===…` banner with a Hungarian description
- Section dividers within a module: `// ---- Section name ---`
- Business logic explained in Hungarian inline comments; JSDoc not used

## Function Design

- **Size:** view render functions are large (HTML generation + event binding in one);
  store actions are small (call the API, then `refresh()`/`hydrate()`, then `notify()`);
  helpers/formatters are tiny
- **Parameters:** multi-field store actions take destructured objects
  (`moveAsset({ device_id, event_type, … })`); views take `(el, params?)`; dialogs take a scalar id
- **Return values:** store reads → data or `null`; store mutations → `Promise<void>`
  (except `registerDevice`, which resolves to the created device, and master-data
  adders, which resolve to the new id); `openModal` → `{ close, root }`;
  `subscribe` → an unsubscribe function

## Module Design

- **Exports:** named exports throughout; `vm.js` exports `deviceVM`; views export one
  `render<View>` each (`register_device.js` also exports `dlgEditDevice`)
- **Barrel files:** not used
- **Dependency direction:**
  - Views → store, lib, ui (never import other views directly; some are lazy-loaded)
  - `actions.js` → store, components, format
  - `vm.js` / `format.js` → store (lookups for display)
  - `store.js` → `lib/api.js`

---

*Convention analysis: 2026-06-23*
