# External Integrations

**Analysis Date:** 2026-06-12

## APIs & External Services

**CDN (runtime asset delivery):**
- jsDelivr CDN — Bootstrap 5.3.3 CSS and JS bundle
  - CSS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css`
  - JS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js`
  - Auth: none
  - Referenced in: `index.html` lines 9, 19

**Google Fonts (runtime):**
- Google Fonts API — Inter typeface family (weights 400, 500, 600, 700)
  - URL: `https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap`
  - Preconnect: `fonts.googleapis.com`, `fonts.gstatic.com`
  - Auth: none
  - Referenced in: `index.html` lines 12–14
  - Note: `styles.css` uses `'Roboto', 'Segoe UI', Arial` as the body font-stack — Inter is loaded but not the primary body font

**Backend API (planned, not yet implemented):**
- No HTTP API calls exist in the current codebase
- The application is a fully in-memory demo; all data operations run against `localStorage`
- The intended backend is a MySQL/MariaDB database described in `device-inventory-schema.sql`

## Data Storage

**Databases:**
- No live database connection exists in this frontend application
- Planned: MySQL/MariaDB — schema at `device-inventory-schema.sql`
  - Tables: `devices`, `device_types`, `attribute_definitions`, `device_custody_events`, `device_reservations`, `departments`
  - External tables (owned by clinic web app, referenced only): `users`, `locations`
  - Views: `device_current_state`, `device_pending_checkins`, `device_active_reservations`

**Browser Storage:**
- `localStorage` — primary persistence for the demo
  - Key: `eszkoznyilvantarto_state_v2`
  - Stores: full application state (devices, events, reservations, users, departments, locations, counters)
  - Managed by: `src/state/store.js` — `persist()` and `loadPersisted()` functions
  - Migration: `migrateStatuses()` handles status renames on load

**File Storage:**
- Local filesystem only — no cloud file storage

**Caching:**
- None — no service worker, no cache API usage

## Authentication & Identity

**Auth Provider:**
- None — authentication is explicitly out of scope for this module
- Design intent (documented in `SCRIPT_LOGIC_DOCUMENTATION.md` §2): authentication is handled by an external clinic web application which passes an authenticated `user_id` and `auth` role
- Demo substitute: `index.html` renders a `<select>` dropdown (`#user-select`) to simulate switching users; `src/appshell.js` manages this via `setCurrentUser()` from `src/state/store.js`

**Role system (internal, not an external provider):**
- Three roles: `user` (1), `storekeeper` (2), `it_admin` (3)
- Stored in seed data: `src/data/seed.js` → `users[].auth`
- Enforced in: `src/state/store.js` — `roleAtLeast()`, `requireStorekeeper()`, `requireAdmin()`
- Role ranks compared via `roleRank` map in `src/state/store.js` line 174

## Monitoring & Observability

**Error Tracking:**
- None detected — no Sentry, Datadog, or similar SDK

**Logs:**
- Browser `console` only; errors from store operations are caught and surfaced as toast notifications via `src/ui/components.js` — `toast()`

## CI/CD & Deployment

**Hosting:**
- Development: XAMPP local server (`C:/xampp/htdocs/`)
- Production target: static file hosting (Vite outputs to `dist/`); intended to be embedded in the Hungária Med-M admin panel

**CI Pipeline:**
- None detected — no GitHub Actions, no CI config files

## Environment Configuration

**Required env vars:**
- None — the application has no environment variable dependencies in its current demo state
- `.env` files are gitignored as a precaution for future backend integration

**Secrets location:**
- No secrets present — pure frontend demo with no API keys or credentials

## Webhooks & Callbacks

**Incoming:**
- None

**Outgoing:**
- None

## External System Boundary (Planned Integration)

The SQL schema and documentation describe two external systems that this inventory module will integrate with when a real backend is built:

1. **Clinic web application** — owns the `users` table (authentication, user profiles) and `locations` table (clinic site/address data). This inventory system will read these as foreign references only, never write them.
   - Note in `src/state/store.js` line 512–516: `addLocation()` is demo-only; production integration syncs from the clinic system.

2. **MySQL/MariaDB database** — the full schema is defined in `device-inventory-schema.sql`; the frontend store (`src/state/store.js`) mirrors this schema structure in-memory, making the eventual API integration straightforward.

---

*Integration audit: 2026-06-12*
