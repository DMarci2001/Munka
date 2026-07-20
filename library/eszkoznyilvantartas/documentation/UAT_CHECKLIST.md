# Eszköznyilvántartás — Manual UAT Checklist

Manual test checklist for the device inventory (eszköznyilvántartás) app. Work through each
section in the real running app. Test with at least three accounts, one per role:

- **user** — regular clinic staff
- **storekeeper** — raktáros
- **it_admin** — IT admin / superadmin

Tick each box as you verify it. Items marked **(regression)** cover bugs found and fixed in a
recent audit — these are the highest-value checks, since they've broken before.

---

## 1. Authentication

- [ ] Log in with valid credentials → lands on the app, correct role/name shown.
- [ ] Log in with wrong password → friendly error, not logged in.
- [ ] Log in with unknown username → friendly error, not logged in.
- [ ] Log out → session ends, protected pages redirect to login.
- [ ] Reload the page while logged in → session persists (still logged in).
- [ ] **(regression, B3)** Enter the wrong password 5 times in a row for one account → 6th
      attempt (even with the *correct* password) is rejected with a lockout message.
- [ ] **(regression, B3)** After a lockout, wait ~15 minutes (or use a fresh account to avoid
      waiting) → login works again with the correct password.
- [ ] **(regression, B3)** One successful login resets the failed-attempt counter (fail twice,
      then succeed, then fail 4 more times without hitting the lockout).

## 2. Device registration (storekeeper / it_admin)

- [ ] Register a new device with all fields filled in → succeeds, appears in inventory with
      status "Kivehető".
- [ ] Try to submit with no asset tag → blocked with a friendly validation message, no request sent.
- [ ] Register a device with an asset tag that already exists (exact match) → friendly
      "already exists" error, no duplicate created.
- [ ] **(regression, A3)** Same as above but with different letter casing (e.g. existing tag
      `ESZ-0001`, try `esz-0001`) → still rejected as duplicate.
- [ ] Pick a device type → type-specific attribute fields render correctly (e.g. Ultrahang
      shows calibration date, Laptop shows OS/RAM).
- [ ] Leave a *required* type-specific attribute empty → blocked with a validation message
      naming the missing field.
- [ ] **(regression, original bug)** Select a location that has **no departments configured**
      as the initial placement → blocked with "Ezen a helyszínen nincs választható részleg."
      instead of a raw server error.
- [ ] **(regression, A7)** Rapidly double-click "Eszköz létrehozása" → only **one** device is
      created, not two (check inventory count / try registering the same tag again afterward
      to confirm it's now taken exactly once).

## 3. Device editing (storekeeper / it_admin)

- [ ] Edit manufacturer/model/serial/notes on an existing device → saves and displays correctly.
- [ ] Change condition to each valid option (Jó / Kopott / Hibás / Ismeretlen) → saves.
- [ ] **(regression, A5)** Confirm there is no way to set device *status* directly through the
      edit dialog (only condition/manufacturer/model/serial/notes/type are editable) — status
      should only change via checkout/checkin/retire/lost/found/repair actions.
- [ ] Change a device's type → the type-specific attribute fields shown update to match the
      new type.
- [ ] **(regression, A4)** If reachable via the UI, attempt to set an invalid/unknown device
      type → blocked with a friendly "Érvénytelen eszköztípus." message.

## 4. Checkout

- [ ] **user**: check out a free device to yourself → succeeds, device now shows you as holder,
      status "Kiadva".
- [ ] **user**: attempt to check out a free device *for someone else* (if the UI somehow allows
      selecting another person) → blocked.
- [ ] **user**: attempt to check out a device someone else is currently holding → blocked.
- [ ] **storekeeper**: check out a free device on behalf of another user → succeeds, correct
      holder recorded.
- [ ] Check out a device that's reserved by someone else, as a plain **user** → blocked
      ("Az eszköz másnak van fenntartva").
- [ ] Check out that same reserved device as **storekeeper** → succeeds (override).
- [ ] Attempt to check a device out *into a storage/raktár department* → blocked with a message
      steering you to pick a usage location instead.
- [ ] Check out with an expected-return date and a note → both are saved and visible on the
      device detail/history.

## 5. Check-in

- [ ] **user**: check in a device you're currently holding → device status becomes
      "Visszavétel folyamatban" (pending), not immediately "Kivehető".
- [ ] **user**: attempt to submit a *second* check-in while one is still pending on the same
      device → blocked ("már van megerősítésre váró visszavétel").
- [ ] **storekeeper/it_admin**: check in a device you're holding → auto-confirmed immediately,
      status goes straight to "Kivehető" (no pending step).
- [ ] Check in with a condition value and a note → both captured and visible afterward.

## 6. Pending queue / confirm & reject

- [ ] As **storekeeper**, open the pending check-ins queue → see the pending item from §5.
- [ ] Confirm a pending check-in → device becomes "Kivehető", event marked confirmed.
- [ ] Reject a pending check-in with a reason → device reverts to "Kiadva" (back with the
      original holder), reason recorded in notes.
- [ ] **(regression, A2)** Create a new pending check-in, then — *before* confirming/rejecting
      it — mark that same device "lost" (or send it to repair) from another action. Now try to
      confirm (or reject) the original pending check-in → blocked with a message that the
      device's status changed in the meantime, instead of silently overwriting the lost/repair
      state.

## 7. Transfer (user-to-user handoff)

- [ ] Transfer a device you're holding directly to another user → new holder recorded,
      location/department stay exactly the same as before.

## 8. Stock transfer (storekeeper, storage-to-storage)

- [ ] Move a device from one storage department to another (different location's raktár) →
      succeeds, device now shows the new location/department, status "Kivehető".
- [ ] Attempt a stock transfer targeting a location with **no departments** → blocked with a
      friendly message, not a raw error.

## 9. Reservation

- [ ] Reserve a free, in-stock device → status becomes "Lefoglalva", reservation visible.
- [ ] Cancel your own reservation → device returns to "Kivehető".
- [ ] Reserve a device as user A, then cancel it as **storekeeper** (not the original reserver)
      → succeeds (override).
- [ ] Try to reserve a device that's already reserved (by someone else) → blocked.
- [ ] Try to reserve a device that's currently checked out (not free) → blocked.
- [ ] **(regression, A3)** If you can test with two browser sessions/tabs as two different
      users: both attempt to reserve the *same* free device at nearly the same time → exactly
      one reservation succeeds, the other gets a friendly "already reserved" error (not a raw
      SQL error).

## 10. Send to repair / return from repair (storekeeper / it_admin)

- [ ] Send a device to repair, picking a location and department explicitly → status becomes
      "Szerviz alatt".
- [ ] Send a device to repair *without* picking a department → auto-assigned to a "műhely"
      department if one exists.
- [ ] **(regression, A6)** In the send-to-repair dialog, change the location dropdown → the
      department dropdown updates to only show departments belonging to that location (not a
      full unfiltered list).
- [ ] Return a device from repair (must currently be "Szerviz alatt") → status becomes
      "Kivehető" at the chosen location/department.
- [ ] Attempt to "return from repair" a device that is **not** currently in repair → blocked.
- [ ] **(regression, A6)** In the return-from-repair dialog, confirm the department dropdown is
      filtered by the selected location (cascades correctly), and that picking a location with
      no departments blocks submission with a friendly message instead of sending an invalid ID.

## 11. Mark lost / mark found (storekeeper / it_admin)

- [ ] Mark a device lost → status becomes "Elveszett".
- [ ] Mark that device found again, picking a location and department → status becomes
      "Kivehető" at the chosen location.
- [ ] **(regression, A6)** In the mark-found dialog, confirm the department dropdown cascades
      from the selected location (not an unfiltered full list), and a location with no
      departments is blocked with a friendly message.

## 12. Retire device (storekeeper / it_admin)

- [ ] Retire a device with a reason → status becomes "Selejtezve", reason appended to notes,
      any active reservation on it is cleared.
- [ ] Retired device shows correctly in inventory (e.g. filterable/visibly marked as retired).

## 13. Terminal-status guard (regression, A1)

For a device that is **Selejtezve**, one that is **Elveszett**, and one that is
**Szerviz alatt**, as both **user** and **storekeeper**:

- [ ] Attempt check_out → blocked with a message referencing the device's current status.
- [ ] Attempt check_in → blocked.
- [ ] Attempt transfer → blocked.
- [ ] Attempt stock_transfer → blocked.

(These devices must go through their dedicated recovery flow — return-from-repair,
mark-found, or re-registration — before normal custody moves work again.)

## 14. Master data entry

- [ ] **it_admin**: add a new location → appears in location dropdowns app-wide.
- [ ] **storekeeper**: add a new department, picking a location and type → appears filtered
      correctly under that location everywhere departments are listed.
- [ ] **(regression, A4)** Attempt to add a department with an invalid/unknown location (if
      reachable) → blocked with "Érvénytelen helyszín."
- [ ] Attempt to add a department with an invalid type (not one of raktár/osztály/recepció/
      műhely, if reachable) → blocked.
- [ ] **(regression, A8)** Try submitting the department form with only whitespace in the name
      field → blocked as if empty, not saved as a blank-looking department.
- [ ] **(regression, A8)** If no locations exist yet, try adding a department → blocked with a
      friendly "nincs választható helyszín" message instead of sending an invalid location ID.
- [ ] **it_admin**: add a new device type → appears in device-type dropdowns app-wide.
- [ ] **it_admin**: add a general attribute definition (no device type) → appears as an
      attribute option on all device types.
- [ ] **it_admin**: add a type-specific attribute definition → appears only on that device type's
      registration/edit forms.
- [ ] **(regression, A4)** Attempt to add a type-specific attribute with an invalid/unknown
      device type (if reachable) → blocked with "Érvénytelen eszköztípus."
- [ ] **(regression, A7)** Rapidly double-click any of the master-data "save" buttons → only one
      record is created, not two.

## 15. Role-based visibility

- [ ] Log in as **user** → confirm storekeeper-only actions (send to repair, mark lost/found,
      retire, register device, edit device, stock transfer, confirm/reject check-in) are not
      visible/reachable in the UI, not just blocked server-side.
- [ ] Log in as **storekeeper** → confirm it_admin-only actions (add location, add device type,
      add attribute definitions) are not visible/reachable.
- [ ] Log in as **it_admin** → confirm all actions across all roles are visible and usable.

## 16. Inventory list / filters / search

- [ ] Filter inventory by status (e.g. only "Kivehető") → results match.
- [ ] Filter by device type → results match.
- [ ] Filter/search by location or department → results match.
- [ ] Search by asset tag (partial match) → finds the right device(s).

## 17. My devices view

- [ ] As **user**, open "my devices" → shows only devices currently held by you, no others.
- [ ] Check out a new device → it appears in this view; check it back in → it disappears from
      this view.

## 18. Device detail / history timeline

- [ ] Open a device with a rich history (multiple checkouts, a rejected check-in, a repair
      cycle if you've built one up during this testing pass) → the full custody event timeline
      renders in the correct chronological order with correct actors/timestamps.
- [ ] Current attributes, reservation status, and pending check-in (if any) all display
      correctly on the detail page.

## 19. QR scan flow

*(Skip this section if no barcode/QR scanner or camera is available for testing — note that
here rather than leaving it silently unchecked.)*

- [ ] Scan a free device's QR/asset tag → dispatches to the check-out flow.
- [ ] Scan a checked-out device you're holding → dispatches to the check-in flow.
- [ ] Scan an unknown/invalid code → friendly "not found" message.

## 20. QR label printing

- [ ] Generate/print a QR label for a device → label shows the correct asset tag and renders
      a scannable code.

---

## Sign-off

- [ ] All applicable sections above completed.
- [ ] Any failures logged as issues with: section #, exact steps, expected vs. actual result.
- [ ] Tester name / date: ____________________
