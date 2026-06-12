# Testing Patterns

**Analysis Date:** 2026-06-12

## Test Framework

**Runner:** None — no test framework is installed or configured

**Assertion Library:** None

**Run Commands:** No test scripts defined in `package.json`

```json
"scripts": {
  "dev": "vite",
  "build": "vite build",
  "preview": "vite preview"
}
```

No `test` script is present.

## Test File Organization

**Test files:** None found in the repository — no `.test.js`, `.spec.js`, or `__tests__` directories exist

## Manual Debug Script

The only test-like artifact is `_t.mjs` at the project root:

```javascript
// _t.mjs — ad-hoc dev debug script (not a test suite)
const store = await import('./src/state/store.js');
const { deviceVM } = await import('./src/lib/vm.js');
const vms = store.getDevices().map(deviceVM);
const counts = {};
for (const v of vms) counts[v.status] = (counts[v.status]||0)+1;
console.log('statuses in list:', counts);
console.log('total:', vms.length);
```

This script is run manually with Node (`node _t.mjs`) to spot-check the in-memory state. It is not an automated test.

## Coverage

**Requirements:** None enforced

**Coverage tooling:** None configured

## Test Types

**Unit Tests:** Not present

**Integration Tests:** Not present

**E2E Tests:** Not present

## What the Codebase Relies On Instead

**Manual browser testing:**
- The app runs entirely in the browser with Vite dev server (`npm run dev`)
- A built-in user switcher in the top bar lets testers switch between `user`, `storekeeper`, and `it_admin` roles manually
- `resetToSeed()` export in `src/state/store.js` provides one-click reset of all state to seed data: `localStorage.removeItem(STORAGE_KEY); location.reload()`

**In-memory determinism:**
- All state is in-memory (no network calls); the store's `bootstrap()` function produces a fully deterministic initial state from `src/data/seed.js`
- This makes the app manually reproducible: clearing localStorage always restores the seed state

**Status migration smoke test:**
- `src/state/store.js` includes a `migrateStatuses` function with a `STATUS_MIGRATIONS` map, used as a data-integrity guard on `loadPersisted()`

## Recommendations for Adding Tests

**Highest-value test targets (pure functions with no DOM dependency):**
- `src/lib/format.js` — all functions are pure: `statusLabel`, `statusClass`, `fmtDate`, `fmtDateTime`, `fmtRelative`, `fmtAttrValue`, `calibrationFlag`, `esc`
- `src/lib/vm.js` — `deviceVM` is a pure projection of store state; testable by mocking store functions
- `src/state/store.js` — `currentState`, `pendingCheckins`, `activeReservation`, `statusFromEvent`, `migrateStatuses` are pure or near-pure logic functions
- Business rules in `moveAsset` — role enforcement, pending check-in guard, reservation interplay

**Suggested framework:** Vitest (already compatible with Vite, zero config needed — add `vitest` to devDependencies)

**Suggested co-location pattern:**
```
src/lib/format.test.js      (alongside format.js)
src/lib/vm.test.js
src/state/store.test.js
```

**Example first test (format.js):**
```javascript
import { describe, it, expect } from 'vitest';
import { esc, fmtDate, calibrationFlag } from './format.js';

describe('esc', () => {
  it('escapes HTML special chars', () => {
    expect(esc('<b>"hello"</b>')).toBe('&lt;b&gt;&quot;hello&quot;&lt;/b&gt;');
  });
  it('returns empty string for null', () => {
    expect(esc(null)).toBe('');
  });
});
```

---

*Testing analysis: 2026-06-12*
