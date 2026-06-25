-- ============================================================
-- Migráció — eszköznyilvántartás táblák átnevezése csoportosító
-- prefixre, hogy a klinikai adatbázisban egy csoportban (egymás
-- alatt) jelenjenek meg: eszkoznyilvantartas_*.
--
-- A KÜLSŐ klinikai táblák (users, helyszinek) NEM változnak.
-- Az adatok megőrződnek (RENAME TABLE, nem DROP).
--
-- Futtatás:
--   USE eszkoznyilvantartas;   -- vagy a klinikai adatbázis neve
--   SOURCE migration_2026-06-24_prefix_tables.sql;
-- Vagy:
--   C:\xampp\mysql\bin\mysql.exe -u root eszkoznyilvantartas < db/migration_2026-06-24_prefix_tables.sql
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- A nézetek törzse a régi táblanevekre hivatkozik — előbb eldobjuk őket.
DROP VIEW IF EXISTS device_active_reservations;
DROP VIEW IF EXISTS device_pending_checkins;
DROP VIEW IF EXISTS device_current_state;

-- Alaptáblák átnevezése (az InnoDB az FK-kat automatikusan átvezeti).
RENAME TABLE
  departments             TO eszkoznyilvantartas_departments,
  device_types            TO eszkoznyilvantartas_device_types,
  attribute_definitions   TO eszkoznyilvantartas_attribute_definitions,
  devices                 TO eszkoznyilvantartas_devices,
  device_attribute_values TO eszkoznyilvantartas_device_attribute_values,
  device_custody_events   TO eszkoznyilvantartas_device_custody_events,
  device_reservations     TO eszkoznyilvantartas_device_reservations;

SET FOREIGN_KEY_CHECKS = 1;

-- Nézetek újra-létrehozása az új nevekkel és az új tábla-hivatkozásokkal.
CREATE VIEW eszkoznyilvantartas_device_current_state AS
SELECT e.device_id,
       e.to_user_id        AS current_holder_user_id,
       e.to_locations_id   AS current_location_id,
       e.to_departments_id AS current_department_id,
       e.event_timestamp   AS since
FROM eszkoznyilvantartas_device_custody_events e
JOIN (
    SELECT device_id, MAX(event_timestamp) AS mx
    FROM eszkoznyilvantartas_device_custody_events
    WHERE confirmation_status = 'confirmed'
    GROUP BY device_id
) latest ON latest.device_id = e.device_id AND latest.mx = e.event_timestamp
WHERE e.confirmation_status = 'confirmed';

CREATE VIEW eszkoznyilvantartas_device_pending_checkins AS
SELECT event_id, device_id,
       actor_user_id     AS submitted_by,
       from_user_id      AS returning_user,
       to_locations_id   AS claimed_location_id,
       to_departments_id AS claimed_department_id,
       event_timestamp   AS submitted_at,
       condition_at_event, notes
FROM eszkoznyilvantartas_device_custody_events
WHERE confirmation_status = 'pending'
ORDER BY event_timestamp;

CREATE VIEW eszkoznyilvantartas_device_active_reservations AS
SELECT reservation_id, device_id, reserved_by, reserved_at, expires_at, notes
FROM eszkoznyilvantartas_device_reservations
WHERE expires_at > NOW();
