# Testing Patterns

**Analysis Date:** 2026-06-23

## Test Framework

**Runner:** None — no test framework is installed or configured

**Assertion Library:** None

**Run Commands:** No test scripts in `package.json`

```json
"scripts": {
  "dev": "vite",
  "build": "vite build",
  "preview": "vite preview"
}
```

## Test File Organization

**Test files:** None — no `.test.js`, `.spec.js`, or `__tests__` directories exist.

## Coverage

- **Requirements:** None enforced
- **Tooling:** None configured

## Test Types

- **Unit / Integration / E2E:** Not present.

## What the Codebase Relies On Instead

**Manual browser testing against the live backend:**
- Start XAMPP (Apache + MySQL) so the PHP API (`eszkoznyilvantartas_api`) and DB
  are up, then run the SPA with `npm run dev`. The Vite proxy forwards `/api` to
  the backend (same-origin, no CORS).
- In dev, the app auto-logs-in a seed user and shows a top-bar user switcher to
  exercise the `user` / `storekeeper` / `it_admin` roles.
- Seed data is loaded into the database by the API repo's `db/seed_dev.php`;
  re-running it restores a known state for manual reproduction.

**Suggested smoke flows (manual):**
- Check-out → check-in (user → pending → storekeeper confirm/reject)
- Reserve → cancel / check-out from reservation
- Stock transfer, send-to-repair → return-from-repair, mark-lost → mark-found, retire
- Register a device, edit it, scan its tag (`/scan`)
- Role switch hides/show storekeeper + admin pages
- Error flow: an API `OpError` (e.g. reserving a non-free device) shows an error toast

## Recommendations for Adding Tests

**Highest-value targets (pure functions, no DOM or network):**
- `src/lib/format.js` — `statusLabel`, `statusClass`, `fmtDate`, `fmtDateTime`,
  `fmtRelative`, `fmtAttrValue`, `calibrationFlag`, `esc`
- `src/lib/vm.js` — `deviceVM` is a pure projection of an enriched device; testable
  by stubbing the store lookups it imports
- `src/lib/api.js` — `request()` response/`OpError` handling, testable with a mocked `fetch`

**Backend (separate repo):** the business rules in `lib/Ops.php` (role enforcement,
pending check-in guard, reservation interplay) are the highest-value targets there.

**Suggested framework:** Vitest (zero-config with Vite).

**Suggested co-location:**
```
src/lib/format.test.js
src/lib/vm.test.js
src/lib/api.test.js
```

**Example first test (format.js):**
```javascript
import { describe, it, expect } from 'vitest';
import { esc } from './format.js';

describe('esc', () => {
  it('escapes HTML special chars', () => {
    expect(esc('<b>"x"</b>')).toBe('&lt;b&gt;&quot;x&quot;&lt;/b&gt;');
  });
  it('returns empty string for null', () => {
    expect(esc(null)).toBe('');
  });
});
```

---

*Testing analysis: 2026-06-23*
