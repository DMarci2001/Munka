# Directory Structure

**Analysis Date:** 2026-06-12

## Layout

```
webapp/
├── index.html                    # SPA entry; Bootstrap CDN + src/appshell.js
├── package.json                  # Vite; type="module"; dev/build/preview scripts
├── _t.mjs                        # Ad-hoc Node debug script (not a test)
├── src/
│   ├── appshell.js               # App shell: routes, sidebar, topbar, re-render loop
│   ├── styles.css                # All CSS (Bootstrap overrides + components)
│   ├── data/
│   │   └── seed.js               # Static demo fixtures (18 devices, 6 users, etc.)
│   ├── lib/
│   │   ├── format.js             # Pure display helpers: labels, dates, esc()
│   │   ├── router.js             # Hash-based client router
│   │   └── vm.js                 # deviceVM() view-model factory
│   ├── state/
│   │   └── store.js              # Single store: state, actions, persist, pub/sub
│   ├── ui/
│   │   ├── actions.js            # All action modals (dlgCheckOut, dlgCheckIn, ...)
│   │   └── components.js         # icons, toast(), openModal()
│   └── views/
│       ├── device.js             # /device/:id detail page
│       ├── inventory.js          # /inventory searchable list
│       ├── myDevices.js          # /my held + reserved
│       ├── pending.js            # /pending storekeeper queue
│       ├── register_data.js      # /register-data master data forms
│       └── register_device.js    # /register new device form
├── .planning/codebase/           # GSD codebase analysis docs
├── DATABASE_DOCUMENTATION.md     # Full DB schema reference
├── SCRIPT_LOGIC_DOCUMENTATION.md # Store/action logic docs
├── device-inventory-schema.sql   # Target PostgreSQL schema
├── device-inventory-schema.dbml  # DBML schema source
├── device-inventory-plan.md      # Inventory data model plan
└── view-device_current_state.sql # SQL view for derived device state
```

## Key Locations

- **App bootstrap:** `src/appshell.js`
- **State & business logic:** `src/state/store.js`
- **Routing:** `src/lib/router.js`
- **Per-route UI:** `src/views/*.js`
- **Action modals:** `src/ui/actions.js`
- **Demo data:** `src/data/seed.js`
- **Schema/design reference (for a future backend):** `device-inventory-schema.sql`,
  `device-inventory-schema.dbml`, `DATABASE_DOCUMENTATION.md`

## Naming Conventions

- View entry functions: `renderXxx(el, params)`
- Modal dialog functions: `dlgXxx(deviceId)`
- Simple inline actions: `doXxx(deviceId)`
- Store lookups: `getXxx()` / `getXxx(id)`
- Store derived views: `currentState()`, `pendingCheckins()`, `historyOf()`

## Where to Add New Code

- **New page:** create `src/views/newPage.js`, add to `PAGES` in `src/appshell.js`,
  register the route in `setupRoutes()`, add a nav item in `renderNav()`.
- **New action modal:** add `dlgXxx()` to `src/ui/actions.js`, call it from
  `src/views/device.js` (or the relevant view).
- **New store action:** add an exported function to `src/state/store.js`, call
  `pushEvent()` + `notify()`.
- **New display helper:** add to `src/lib/format.js`.
- **New per-device computed field:** add to `deviceVM()` in `src/lib/vm.js`.

---

*Structure analysis: 2026-06-12*
