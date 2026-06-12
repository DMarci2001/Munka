# Technology Stack

**Analysis Date:** 2026-06-12

## Languages

**Primary:**
- JavaScript (ES Modules) — all application code in `src/`

**Secondary:**
- SQL (MySQL/MariaDB dialect) — schema definition in `device-inventory-schema.sql`
- CSS — custom theme in `src/styles.css`

## Runtime

**Environment:**
- Node.js v24.2.0 (development tooling only; app runs in browser)

**Package Manager:**
- npm
- Lockfile: `package-lock.json` present (lockfileVersion 3)

## Frameworks

**Core:**
- No UI framework — vanilla JS with hand-written DOM manipulation and innerHTML templating
- Custom hash-based SPA router in `src/lib/router.js`

**CSS/UI:**
- Bootstrap 5.3.3 — loaded via CDN (`https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/`) for layout utilities and form components
- Bootstrap JS bundle (includes Popper) — loaded via CDN in `index.html`
- Custom CSS theme layered on top: `src/styles.css` (medical-green brand `#0a7d2c`, white background)

**Build/Dev:**
- Vite ^8.0.16 — dev server and production bundler
  - Config: no `vite.config.*` file detected; uses Vite defaults
  - Entry point: `index.html`

**Testing:**
- Not detected — no test runner or test files present

## Key Dependencies

**Critical:**
- `bootstrap` ^5.3.8 (npm) — installed locally, but also loaded via CDN in `index.html`; the npm package is present in `node_modules` but Bootstrap CSS/JS are referenced from CDN at runtime

**Infrastructure:**
- `vite` ^8.0.16 (devDependency) — build tooling only

**No other runtime dependencies.** The application uses no npm packages at runtime beyond what the browser loads from CDN.

## Configuration

**Environment:**
- `.env` / `.env.local` / `.env.*.local` are gitignored; no `.env` file present
- No environment variables consumed by the frontend code (fully client-side demo, no API keys or backend URLs)

**Build:**
- `package.json` scripts:
  - `npm run dev` — Vite dev server
  - `npm run build` — production build to `dist/`
  - `npm run preview` — preview production build
- No `vite.config.*` — all Vite defaults apply

## Platform Requirements

**Development:**
- Node.js (v24.x confirmed); npm
- XAMPP environment (project lives under `C:/xampp/htdocs/`)

**Production:**
- Static file hosting only — Vite builds a static bundle; no server-side runtime required
- Designed to be embedded in a clinical web application (Hungária Med-M Kft. admin panel)

## Application Architecture Note

This is a **fully client-side demo application** with no backend:
- All state lives in browser memory (`src/state/store.js`)
- State is persisted to `localStorage` under key `eszkoznyilvantarto_state_v2`
- Seed data in `src/data/seed.js` initialises the in-memory store on first load
- The accompanying SQL schema (`device-inventory-schema.sql`) describes the intended PostgreSQL/MySQL backend that does not yet exist in this repo

---

*Stack analysis: 2026-06-12*
