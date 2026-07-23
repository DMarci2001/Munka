-- ============================================================
-- Átadás megerősítési workflow (feature plan #3):
-- - a "pending" (megerősítésre váró) állapot mostantól transfer
--   eseményre is engedélyezett, nem csak check_in-re
-- - resolved_by/resolved_at oszlopok az elutasított átadások
--   lezárásának nyomon követésére (storekeeper felülbírálás vagy
--   az elutasítás elfogadása esetén)
-- - új eszközstátusz: 'Átadás folyamatban'
-- ============================================================

ALTER TABLE eszkoznyilvantartas_device_custody_events
  DROP CONSTRAINT chk_custody_pending_only_checkin;

ALTER TABLE eszkoznyilvantartas_device_custody_events
  ADD CONSTRAINT chk_custody_pending_only_checkin CHECK
    (confirmation_status = 'confirmed' OR event_type IN ('check_in','transfer'));

ALTER TABLE eszkoznyilvantartas_device_custody_events
  ADD COLUMN resolved_by INT NULL AFTER confirmed_at,
  ADD COLUMN resolved_at DATETIME NULL AFTER resolved_by;

ALTER TABLE eszkoznyilvantartas_device_custody_events
  ADD CONSTRAINT fk_custody_resolvedby FOREIGN KEY (resolved_by) REFERENCES users (id);

ALTER TABLE eszkoznyilvantartas_devices
  DROP CONSTRAINT chk_devices_status;

ALTER TABLE eszkoznyilvantartas_devices
  ADD CONSTRAINT chk_devices_status CHECK (status IN
    ('Kivehető','Kiadva','Lefoglalva','Visszavétel folyamatban','Átadás folyamatban',
     'Szerviz alatt','Elveszett','Selejtezve'));
