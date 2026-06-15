# Eszköznyilvántartó — Project Instructions

## Project Overview

Medical device inventory management SPA for Hungária Med-M Kft.
Fully client-side demo (no backend yet). All state lives in browser memory, persisted to `localStorage`. The SQL schema (`device-inventory-schema.sql`) describes the intended future backend.

## Stack

- **Vanilla JS** (ES Modules) — no UI framework
- **Vite** (dev server + build) — run with `npm run dev`
- **Bootstrap 5.3.3** — loaded from CDN in `index.html`; npm package present but not used at runtime
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
- Private mutable counters: underscore prefix (`_eventId`, `_resvId`)

## Architecture

```
src/
  appshell.js       — app shell + router init (entry point)
  data/seed.js      — seed data bootstrapped into store on first load
  state/store.js    — single source of truth; append-only custody event log
  lib/
    router.js       — hash-based SPA router
    vm.js           — deviceVM() viewmodel builder
    format.js       — pure display helpers (esc, fmtDate, etc.)
  ui/
    components.js   — toast, openModal, icons
    actions.js      — all dlg*/do* dialog/action functions; imported as `* as A`
  views/            — one render<View> export per file
    inventory.js, device.js, pending.js, myDevices.js,
    register_device.js, register_data.js
```

**Dependency direction:** views → store/lib/ui. Never import views from other views (except lazy: `import('./register_device.js')`). `format.js` may import from store.

## Domain Rules

- All custody movements go through `moveAsset()` in store.js
- `currentState(deviceId)` derives current holder/location from the latest **confirmed** event
- User role hierarchy: `user < storekeeper < it_admin`
- `OpError extends Error` for all business-rule violations — thrown by store, caught by `actions.js` and shown as error toast
- A `user`-initiated `check_in` gets `confirmation_status: 'pending'`; storekeeper must confirm or reject
- Storage departments (`type === 'raktár'`) never have a holder — device goes to stock

## Comments & Language

- All comments in **Hungarian**
- Module headers use `// ============================================================` banner
- Section dividers: `// ---- Section name ---`
- No JSDoc

## What to Avoid

- Do not add a UI framework, TypeScript, or test runner unless explicitly asked
- Do not mock the store or localStorage in fixes — the store is the single source of truth
- Do not create new files unless necessary; extend existing modules
- Do not refactor code outside the scope of the current task
- Never commit without being explicitly asked
