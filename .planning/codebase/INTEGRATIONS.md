# External Integrations

**Analysis Date:** 2026-06-23

## APIs & External Services

**Inventory backend API (PHP + MySQL/MariaDB):**
- Separate repo `eszkoznyilvantartas_api`, served by Apache (XAMPP) under
  `http://localhost/eszkoznyilvantartas_api`
- Called from `src/lib/api.js` (`apiGet` / `apiSend`), `credentials: 'include'`
- Base URL: dev → `/api` (Vite proxy in `vite.config.js`, so requests are
  same-origin and CORS-free); prod → `import.meta.env.VITE_API_BASE` or the
  embedded default `/eszkoznyilvantartas_api`
- Response envelope `{ ok, data } | { ok, error }`; errors become `OpError`
- Key endpoints: `GET /bootstrap`, `GET /devices|/pending|/reservations`,
  `POST /devices/move`, `POST /auth/login|/auth/sso`, `GET /me`

**CDN (runtime asset delivery):**
- jsDelivr CDN — Bootstrap 5.3.x CSS and JS bundle (`index.html`)
- Google Fonts — Inter family (loaded in `index.html`; note `styles.css` uses a
  `'Roboto', 'Segoe UI', Arial` body stack, so Inter is loaded but not primary)

## Data Storage

**Database (via the backend API):**
- MySQL/MariaDB — schema in the API repo (`db/schema_dev.sql`,
  `db/schema_integration.sql`); a reference copy lives here as
  `device-inventory-schema.sql`
  - Tables: `devices`, `device_types`, `attribute_definitions`,
    `device_custody_events`, `device_reservations`, `departments`
  - External tables (owned by the clinic system): `users`, `helyszinek` (locations)
  - Views: `device_current_state`, `device_pending_checkins`,
    `device_active_reservations`

**Browser Storage:**
- None for application data — the store is in-memory and re-hydrated from the API.
  Identity lives in the API session cookie (`eszkozsession`, httpOnly).

**Caching:**
- In-memory only: the store caches lookups and per-device custody history
  (`historyCache`); both are cleared/refreshed after mutations.

## Authentication & Identity

**Auth provider:**
- The PHP API session. Identity is established by either:
  1. **SSO handoff** from the clinic website — `appshell.init()` posts
     `?sso=<token>&u=<username>&t=<timestamp>` to `POST /auth/sso`; the API
     verifies an HMAC token (60s TTL) and starts a session.
  2. **Dev auto-login** — when `import.meta.env.DEV`, the shell logs in a default
     seed user and shows a user-switcher `<select>` (hidden in production builds).
- `GET /me` (inside `/bootstrap`) returns the current user.

**Role system:**
- Three roles: `user` (0), `storekeeper` (1), `it_admin` (2) — stored as the
  clinic `users.jogosultsag` INT, mapped to the `auth` string by the API.
- Enforced server-side (`Auth::requireRole`); the frontend `roleAtLeast()` only
  decides which UI/actions are shown.

## Monitoring & Observability

- **Error tracking:** none (no Sentry/Datadog). API errors surface as toasts; the
  backend logs server errors via `error_log`.
- **Logs:** browser `console` on the client; PHP `error_log` on the server.

## CI/CD & Deployment

- **Frontend hosting:** Vite builds a static bundle to `dist/` (base `./`),
  intended to be embedded in the Hungária Med-M admin panel.
- **Backend hosting:** PHP under Apache/XAMPP; production points at the clinic's
  existing MySQL DB via `config/config.php`.
- **CI pipeline:** none detected.

## Environment Configuration

- `VITE_API_BASE` (optional) — production API base path; defaults to
  `/eszkoznyilvantartas_api`.
- `.env*` files are gitignored.
- The backend's secrets (DB credentials, `SSO_SECRET`) live in the API repo's
  `config/config.php`, not in the frontend.

## External System Boundary

1. **Clinic web application** — owns the `users` and `helyszinek` (locations)
   tables and the primary authentication. This module reads them through the API
   (column mapping configured in the API's `config/config.php`) and hands off
   identity via SSO. `addLocation()` exists but production normally syncs locations
   from the clinic system.
2. **MySQL/MariaDB** — accessed only through the PHP API, never directly from the
   browser.

---

*Integration audit: 2026-06-23*
