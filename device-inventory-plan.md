# Portable Device Inventory — Design Plan

**Context:** Clinic IT, large / multi-site, thousands of devices, many users, audit needs.
**Goal:** One inventory database for all portable devices, each with a unique ID linked to a QR code, where every device shares some common attributes but each *type* (router, printer, ultrasound, …) has its own type-specific sub-attributes.

---

## 1. The core decision: how to model "different sub-attributes per type"

This is the heart of the task. There are three classic patterns:

| Approach | Idea | Verdict for you |
|---|---|---|
| **One table per type** | Separate `routers`, `printers`, `ultrasounds` tables | Rejected — adding a new device type means a schema change and new code every time. |
| **Pure EAV** (entity-attribute-value) | One giant `attribute_values` table holding every value as a row | Rejected — flexible but queries/reporting become painful and there's no real validation. |
| **Hybrid: shared core table + metadata-driven type attributes** ✅ | A common `devices` table for shared fields, plus a small metadata layer that *defines* which sub-attributes each type has | **Recommended.** Add new types and fields as data, not code; still queryable and validated. |

The recommended hybrid is exactly what mature asset systems do, so you have two realistic routes to get there.

---

## 2. Two routes — and my recommendation

### Route A (recommended): Adopt Snipe-IT, an open-source IT asset manager
It already implements the hybrid model and almost every requirement on your list:

- **Custom fieldsets** — define a group of fields (e.g. *expiry date, calibration date, probe count*) and attach it to an asset model/type. This is precisely your "each type has different sub-attributes."
- **QR code labels** — built-in; every asset gets a scannable QR/barcode label that opens the asset's record.
- **Unique asset tags** — auto-generated, with configurable prefixes.
- **Multi-site / locations** — assets check out to people, locations, or other assets.
- **Audit log & history** — full change history per asset, plus scheduled audit reminders.
- Self-hostable (PHP/Laravel + MySQL), free, active community.

*One known limit:* an asset can carry **one** custom fieldset at a time, and fieldsets attach at the model level (not the category level). For your "type drives the sub-attributes" design that's fine — model one device type per fieldset.

**Pick this if** you want to be live in weeks, not months, and your needs are standard inventory + QR + audit.

### Route B: Build a custom database (PostgreSQL)
Choose this only if you need bespoke clinical workflows (e.g. calibration certificates tied to medical-device regulations, integration with your CMMS/EHR) that an off-the-shelf tool can't express. You get full control but own all the build and maintenance. The schema in §3 is what you'd build.

> **My recommendation:** Pilot **Route A (Snipe-IT)** first. It matches the brief almost line-for-line. Keep Route B as the fallback if a hard requirement can't be met. The data model below applies either way — in Snipe-IT it's configuration; in a custom build it's tables.

---

## 3. The data model (applies to both routes)

### Shared core — every device has these
A single `devices` table:

- `id` — internal primary key (UUID).
- `asset_tag` — the human-readable unique identifier encoded in the QR code (see §4).
- `device_type_id` → `device_types` (router, printer, ultrasound, …). **This is the main attribute.**
- `name` / `model`, `manufacturer`, `serial_number`.
- `status` — in use / in storage / in repair / retired / lost.
- `site_id` → `sites`, `department_id`, `room`, `assigned_to` (user).
- `purchase_date`, `purchase_cost`, `warranty_expiry`.
- `created_at`, `updated_at`, `created_by`, `updated_by` (audit basics).

### Type-specific sub-attributes — the metadata layer
Three small tables let you add types and fields **without changing the schema**:

- `device_types` — id, name (router, ultrasound…), description.
- `attribute_definitions` — id, `device_type_id`, `key` (e.g. `calibration_due`), `label`, `data_type` (date/number/text/boolean/list), `required`, `options` (for dropdowns). *This defines what fields each type has.*
- `device_attribute_values` — `device_id`, `attribute_definition_id`, `value`. *This stores the actual values.*

This is the metadata-driven hybrid: the app reads `attribute_definitions` for the chosen type to render the right form and validate input.

> **PostgreSQL shortcut:** instead of the `device_attribute_values` table you can store type-specific values in a single `JSONB` column (`attributes`) on the `devices` row, and keep `attribute_definitions` purely for validation/form-rendering. Fewer joins, still queryable/indexable via JSONB. Either works.

### Supporting tables
`sites`, `departments`, `users/roles` (permissions + audit attribution), and an `audit_log` (who changed what, when, old → new value) — essential for your audit requirement.

### Example sub-attributes per type
- **Router:** IP/MAC address, firmware version, location coverage.
- **Laptop:** OS, CPU/RAM, encryption status, domain-joined?
- **Printer:** connection type, toner model, network address.
- **Ultrasound / ECG / BP meter (medical):** **calibration date + next-calibration-due**, last service date, regulatory class, accessory/probe list, software version.
- **USB drive:** capacity, encrypted?, approved-for-PHI?.

Medical devices are the reason audit and calibration fields matter most — design those in from day one.

---

## 4. Unique identifier & QR strategy

Use **two identifiers, one purpose each**:

1. **`id` (UUID)** — internal primary key. Never changes, never reused, safe for relationships.
2. **`asset_tag`** — the human/QR-facing unique code. Make it structured and readable, e.g. `SITE-TYPE-#####` → `BUD-ULT-00042` (Budapest site, ultrasound, #42). Easy to read aloud, sort, and spot-check.

**What the QR code encodes:** not the bare tag but a **URL** that opens the device's record, e.g. `https://inventory.yourclinic.local/d/BUD-ULT-00042`. Scanning with any phone camera then jumps straight to that device. This is exactly how Snipe-IT's labels work, and it's trivial to replicate in a custom build.

Generate and print labels in batches; for a clinic, durable/asset-grade labels matter for the medical equipment.

---

## 5. Cross-cutting requirements (don't skip for a clinic)

- **Access control & audit:** role-based permissions (read-only vs. editor vs. admin) and an immutable audit log — both are compliance-relevant in a healthcare setting.
- **Soft deletes:** mark devices retired/lost rather than deleting, so history survives.
- **Backups & hosting:** automated daily backups; host on-site or in a compliant environment given PHI-adjacent data.
- **Lifecycle states & reminders:** warranty expiry and **calibration-due** dates should drive notifications.

---

## 6. Suggested roadmap

1. **Finalize the type list & fields.** For each of the ~9 device types, list its sub-attributes (use §3 as a starting point). This is the most important deliverable — get sign-off from whoever owns the medical equipment.
2. **Stand up a Snipe-IT pilot** (Route A) on a test server. Create the device types as models, build a custom fieldset per type, configure sites/locations and roles.
3. **Load ~20 sample devices** across types; print and test QR labels end-to-end (scan → record).
4. **Validate against the brief** with your boss — especially audit log, multi-site, and the medical-device fields. If anything's a hard miss, evaluate Route B for that gap.
5. **Roll out:** bulk-import existing inventory, label everything, train users, turn on calibration/warranty reminders.

---

## 7. Decisions to confirm with your boss / team

- Self-hosted vs. cloud (PHI/compliance posture).
- Who needs edit access vs. read-only, and across which sites.
- Whether calibration/maintenance scheduling must live *in* this system or integrate with an existing CMMS.
- Label hardware (printer + durable label stock) budget.

---

*Sources:* [Snipe-IT custom fields docs](https://snipe-it.readme.io/docs/custom-fields) · [Snipe-IT product features](https://snipeitapp.com/product) · [Fieldset-per-category discussion](https://github.com/snipe/snipe-it/issues/3435)
