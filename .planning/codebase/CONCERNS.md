# Codebase Concerns

**Analysis Date:** 2026-06-12

Technical debt, bugs, security gaps, and fragile areas. This is a frontend-only
demo SPA with all state in `localStorage` and no backend.

## Confirmed Bugs

1. **`allDepts` is undefined in checkout/transfer modals**
   `dlgCheckOut` and `dlgTransfer` reference `allDepts` without declaring it →
   `ReferenceError` on modal open. `src/ui/actions.js` (~line 73).

2. **Location field name mismatch silently drops location**
   `dlgCheckOut` / `dlgTransfer` pass `to_location_id` / `to_department_id` to
   `moveAsset`, which expects `to_locations_id` / `to_departments_id`. Location is
   silently stored as `null`. `src/ui/actions.js` (~lines 87, 185).

3. **Repair workshop never pre-selected**
   `dlgSendToRepair` checks `d.kind === 'műhely'` but the field is `d.type`. The
   workshop is never pre-selected. `src/ui/actions.js` (~line 305).

4. **`typeLabel()` reads the wrong field**
   `typeLabel()` reads `.name` on device-type objects that only have `.type`, so it
   always returns `'—'`. `src/lib/format.js` (~line 62).

## Technical Debt

- No `asset_tag` uniqueness check on device registration — duplicates allowed.
- O(n × events) custody scan per render; three redundant `deviceVM` passes per
  inventory render (no memoization).
- Global re-render on every `notify()` (`src/appshell.js` ~line 133) destroys DOM
  state (focus, scroll position) on any action.
- `persist()` silently swallows `localStorage` quota / serialization errors.
- Bootstrap version mismatch: CDN 5.3.3 (`index.html`) vs npm 5.3.8 (`package.json`).
- Google Fonts loads **Inter** but CSS uses **Roboto** — wasted font load / drift.
- No build/lint/test tooling beyond Vite (no ESLint, Prettier, or test runner).

## Security

- Authentication is a **client-side demo select** (user switcher in the top bar) —
  no real session, no server-side authorization. Role checks are advisory only.
- All application state stored in plaintext `localStorage`.
- `attribute_key` is not sanitized before being interpolated into `data-attr` HTML
  attributes — potential attribute-injection vector if attribute keys ever become
  user-supplied.

## Fragile Areas

- Route `'/'` has no `PAGES` entry → `TypeError` on cold load; all `navigate('/')`
  fallbacks land on a blank page.
- Reservation expiry causes `dev.status` drift until the next explicit action
  recomputes effective status (no scheduled reconciliation).

## Missing Infrastructure

- No backend / API — all persistence is `localStorage`.
- No automated tests and no test framework installed.
- No CI configuration.

---

*Concerns analysis: 2026-06-12*
