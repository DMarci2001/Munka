# Codebase Concerns

**Analysis Date:** 2026-06-23

Technical debt, fragile areas, and notes. The SPA is now backed by the PHP API
(`eszkoznyilvantartas_api`); the backend is the source of truth and enforces all
business rules and roles.

## Previously Confirmed Bugs — now resolved

The 2026-06-12 analysis listed four frontend bugs in the action modals. All are
fixed in the current code:

1. **`allDepts` undefined in modals** — the location→department cascade is now a
   single shared helper `wireLocationDept(root, prefer)` in `src/ui/actions.js`.
2. **Location field-name mismatch** — the modals build the payload with the correct
   `to_locations_id` / `to_departments_id` keys; `dlgTransfer` inherits the current
   location/department from `currentState()`.
3. **Repair workshop pre-select** — `dlgSendToRepair` correctly prefers
   `d.type === 'műhely'`.
4. **`typeLabel()` wrong field** — the buggy, unused `typeLabel` was removed; views
   use `deviceVM().typeName`.

Backend-side, device-tag uniqueness is now enforced in `Ops::registerDevice`
(case-insensitive), closing the old "duplicate asset_tag" debt.

## Technical Debt

- Global re-render on every `notify()` (`src/appshell.js`, `renderCurrent`) rebuilds
  the whole current view, discarding focus/scroll on any action.
- `src/lib/format.js` imports lookups from the store — display layer coupled to state.
- `dlgReturnFromRepair` / `dlgMarkFound` render a flat department list (not the
  location→department cascade used by the other dialogs); a department from another
  location can be picked.
- No build/lint/test tooling beyond Vite (no ESLint, Prettier, or test runner).
- Several historical design artifacts remain in the repo root (`*_DOCUMENTATION.md`,
  `device-inventory-plan.md`, `_t.mjs`, schema images) and are not all current.

## Security

- Authentication and authorization are enforced **server-side** (session cookie +
  `Auth::requireRole`). The frontend `roleAtLeast()` only gates UI; it is not a
  security boundary.
- `attribute_key` is interpolated into a `data-attr` HTML attribute in
  `register_device.js` without escaping — low risk (admin-supplied), but worth
  escaping if attribute keys ever become less trusted.
- The API's `SSO_SECRET` is a placeholder in `config/config.php` and must be set to
  a long random value before production.

## Fragile Areas

- Reservation expiry is reflected lazily: a device's effective status updates when
  the backend next recomputes it (no scheduled reconciliation).
- The `device_current_state` SQL view has no tiebreaker for events sharing an exact
  timestamp; write paths use `Repo::currentState()` (ordered by `event_id`) which does.

## Missing Infrastructure

- No automated tests and no test framework installed.
- No CI configuration.

---

*Concerns analysis: 2026-06-23*
