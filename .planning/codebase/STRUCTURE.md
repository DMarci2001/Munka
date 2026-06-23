# Directory Structure

**Analysis Date:** 2026-06-23

## Layout

```
webapp/
├── index.html                    # SPA entry; Bootstrap CDN + src/appshell.js
├── package.json                  # Vite; type="module"; dev/build/preview scripts
├── vite.config.js                # base "./"; dev proxy /api → backend
├── jsconfig.json                 # editor JS config
├── public/                       # static assets copied as-is
├── src/
│   ├── appshell.js               # App shell: routes, sidebar, topbar, SSO handoff, re-render loop
│   ├── styles.css                # All CSS (Bootstrap overrides + components)
│   ├── lib/
│   │   ├── api.js                # fetch wrapper around the PHP API; exports OpError
│   │   ├── format.js             # Pure display helpers: labels, dates, esc()
│   │   ├── router.js             # Hash-based client router
│   │   └── vm.js                 # deviceVM() view-model factory
│   ├── state/
│   │   └── store.js              # In-memory mirror of the backend: getters, async mutations, pub/sub
│   ├── ui/
│   │   ├── actions.js            # All action modals (dlgCheckOut, dlgCheckIn, …)
│   │   ├── components.js         # icons, toast(), openModal()
│   │   └── qrLabel.js            # QR-label modal (lazy-loaded, uses `qrcode`)
│   └── views/
│       ├── device.js             # /device/:id detail page
│       ├── inventory.js          # /inventory searchable list
│       ├── myDevices.js          # /my held + reserved
│       ├── pending.js            # /pending storekeeper queue
│       ├── register_data.js      # /register-data master-data forms
│       ├── register_device.js    # /register new device form (+ dlgEditDevice)
│       └── scan.js               # /scan and /scan/:tag QR/keyboard scan entry
├── .planning/codebase/           # GSD codebase analysis docs (this folder)
├── docs/                         # design specs (e.g. frontend API wiring)
├── device-inventory-schema.sql   # Reference schema (authoritative copy in the API repo)
├── device-inventory-schema.dbml  # DBML schema source
├── view-device_current_state.sql # SQL view reference
└── (historical) DATABASE_DOCUMENTATION.md, SCRIPT_LOGIC_DOCUMENTATION.md,
    QR_SCAN_PLAN.md, device-inventory-plan.md, _t.mjs, schema images
```

## Key Locations

- **App bootstrap:** `src/appshell.js`
- **API client:** `src/lib/api.js`
- **State mirror:** `src/state/store.js`
- **Routing:** `src/lib/router.js`
- **Per-route UI:** `src/views/*.js`
- **Action modals:** `src/ui/actions.js`
- **Backend (separate repo):** `eszkoznyilvantartas_api` — the source of truth

## Naming Conventions

- View entry functions: `renderXxx(el, params)`
- Modal dialog functions: `dlgXxx(deviceId)`
- Simple inline actions: `doXxx(deviceId)`
- Store lookups: `getXxx()` / `getXxx(id)`
- Store derived reads: `currentState()`, `pendingCheckins()`, `historyOf()`

## Where to Add New Code

- **New page:** create `src/views/newPage.js`, add to `PAGES` in `src/appshell.js`,
  register the route in `setupRoutes()`, add a nav item in `renderNav()`.
- **New action modal:** add `dlgXxx()` to `src/ui/actions.js`, call it from the
  relevant view (e.g. `src/views/device.js`).
- **New store action:** add an exported async function to `src/state/store.js` that
  calls `apiSend(...)` then `refresh()` (or `hydrate()` for master-data changes).
- **New API call:** route it through `apiGet`/`apiSend` in `src/lib/api.js`.
- **New display helper:** add to `src/lib/format.js`.
- **New per-device computed field:** add to `deviceVM()` in `src/lib/vm.js`.

---

*Structure analysis: 2026-06-23*
