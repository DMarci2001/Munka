# Technology Stack

**Analysis Date:** 2026-06-23

## Languages

**Primary:**
- JavaScript (ES Modules) — all application code in `src/`

**Secondary:**
- SQL (MySQL/MariaDB dialect) — schema reference in `device-inventory-schema.sql`
  (authoritative copies live in the API repo's `db/`)
- CSS — custom theme in `src/styles.css`

## Runtime

**Environment:**
- Node.js (development tooling only; the app runs in the browser)
- Backend: PHP 8.x + MySQL/MariaDB (separate repo `eszkoznyilvantartas_api`)

**Package Manager:**
- npm — lockfile `package-lock.json` (lockfileVersion 3)

## Frameworks

**Core:**
- No UI framework — vanilla JS with hand-written DOM manipulation and innerHTML templating
- Custom hash-based SPA router in `src/lib/router.js`

**CSS/UI:**
- Bootstrap 5.3.x — loaded via CDN in `index.html` for layout utilities and form components
- Custom CSS theme on top: `src/styles.css` (medical-green brand `#0a7d2c`)

**Build/Dev:**
- Vite ^8.0.16 — dev server and production bundler
  - Config: `vite.config.js` — base `./`, dev proxy `/api → http://localhost/eszkoznyilvantartas_api`
  - Entry point: `index.html`

**Testing:**
- None — no test runner or test files present

## Key Dependencies

**Runtime:**
- `qrcode` ^1.5.x — lazy-imported only by `src/ui/qrLabel.js` to render device QR labels
- `bootstrap` ^5.3.8 (npm) — installed locally, but Bootstrap CSS/JS are loaded
  from CDN at runtime (the npm package is effectively unused at runtime)

**Infrastructure:**
- `vite` ^8.0.16 (devDependency) — build tooling

## Configuration

**Environment:**
- `VITE_API_BASE` (optional) — production API base path; defaults to `/eszkoznyilvantartas_api`
- `.env*` files are gitignored

**Build:**
- `package.json` scripts: `dev` (Vite dev server), `build` (→ `dist/`), `preview`
- `vite.config.js` present (base path + `/api` dev proxy)

## Platform Requirements

**Development:**
- Node.js + npm for the frontend; XAMPP (Apache + MySQL + PHP) for the backend API
- Project lives under `C:/xampp/htdocs/`

**Production:**
- Frontend: static file hosting (Vite `dist/`), embedded in the clinical web app
- Backend: PHP under Apache, pointed at the clinic's MySQL DB

---

*Stack analysis: 2026-06-23*
