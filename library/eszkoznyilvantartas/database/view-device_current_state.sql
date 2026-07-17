-- ============================================================
-- Nézet — eszkoznyilvantartas_device_current_state
-- Hordozható Eszköznyilvántartás (Hungária Med-M Kft.)
-- Dátum: 2026-06-02
--
-- Eszközönként az aktuális birtokost és helyet adja: a legújabb
-- birtoklási esemény „to" oldala. A birtokos és a hely soha nincs
-- a eszkoznyilvantartas_devices során tárolva — ez a nézet az egyetlen igazságforrás
-- (lásd DATABASE_DOCUMENTATION.md §7).
--
-- A current_location_id NYERS locations.id idegen kulcs: a nézet
-- nem köti össze a locations/sites táblákkal, és nem nézi a type-ot.
-- Az olvasható helynév előállítása (köztük a type = 'site' →
-- telephelynév feloldás) az alkalmazásréteg feladata
-- (SCRIPT_LOGIC.md §10, location_label).
--
-- CHECKPOINT (2026-06): a nézet KIZÁRÓLAG a megerősített eseményeket
-- veszi figyelembe (confirmation_status = 'confirmed'). Egy felhasználói
-- check_in először 'pending' állapotban keletkezik, és NEM változtatja
-- meg az aktuális állapotot, amíg a raktáros fizikailag meg nem erősíti.
-- Az elutasított ('rejected') esemény sosem érvényesül. Így egy függőben
-- lévő visszavétel alatt a nézet még a check_in ELŐTTI birtokost/helyet
-- (jellemzően a felhasználót) mutatja.
--
-- Idempotens: CREATE OR REPLACE. PostgreSQL.
-- Előfeltétel: a eszkoznyilvantartas_device_custody_events tábla már létezik, és a
-- migration-2026-06-add-checkpoint-confirmation.sql lefutott.
-- ============================================================

CREATE OR REPLACE VIEW eszkoznyilvantartas_device_current_state AS
SELECT DISTINCT ON (device_id)
       device_id,
       to_user_id      AS current_holder_user_id,
       to_location_id  AS current_location_id,
       event_timestamp AS since
FROM eszkoznyilvantartas_device_custody_events
WHERE confirmation_status = 'confirmed'
ORDER BY device_id, event_timestamp DESC;

-- Megjegyzés a teljesítményhez: a DISTINCT ON (device_id) ... WHERE
-- confirmation_status = 'confirmed' ... ORDER BY device_id,
-- event_timestamp DESC a részleges idx_custody_confirmed indexet
-- használja (device_id, event_timestamp DESC) WHERE confirmed —
-- gyors „legújabb megerősített esemény eszközönként".


-- ------------------------------------------------------------
-- Nézet — eszkoznyilvantartas_device_pending_checkins (a raktáros ellenőrzési listája)
--
-- Minden függőben lévő (pending) felhasználói visszavétel, amelyet a
-- raktárosnak fizikailag ellenőriznie és megerősítenie/elutasítania
-- kell. A „cél" oldal (to_location_id) az a hely, ahova a felhasználó
-- állítása szerint visszavitte az eszközt.
-- ------------------------------------------------------------

CREATE OR REPLACE VIEW eszkoznyilvantartas_device_pending_checkins AS
SELECT event_id,
       device_id,
       actor_user_id   AS submitted_by,   -- ki adta le a visszavételt
       from_user_id    AS returning_user, -- kinél volt (visszavétel előtt)
       to_location_id  AS claimed_location_id,
       event_timestamp AS submitted_at,
       condition_at_event,
       notes
FROM eszkoznyilvantartas_device_custody_events
WHERE confirmation_status = 'pending'
ORDER BY event_timestamp;


-- ------------------------------------------------------------
-- OPCIONÁLIS — feloldó nézet (kényelmi réteg)
--
-- Ha a megjelenített helynevet az adatbázisban szeretné feloldani
-- (a §10 location_label SQL-megfelelője), használja az alábbi
-- kibővített nézetet. A eszkoznyilvantartas_device_current_state változatlan marad;
-- ez csak egy kényelmi réteg fölötte. Vegye ki a kommentből, ha kell.
--
-- CREATE OR REPLACE VIEW device_current_state_resolved AS
-- SELECT s.device_id,
--        s.current_holder_user_id,
--        s.current_location_id,
--        CASE
--            WHEN l.type = 'site' THEN si.name      -- „a telephely mint egész"
--            ELSE l.name                            -- konkrét szoba / raktár / osztály
--        END AS current_location_label,
--        s.since
-- FROM eszkoznyilvantartas_device_current_state s
-- LEFT JOIN locations l ON l.id = s.current_location_id
-- LEFT JOIN sites     si ON si.id = l.site_id;
-- ------------------------------------------------------------
