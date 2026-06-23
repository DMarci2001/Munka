-- ============================================================
-- Eszköznyilvántartás — FEJLESZTŐI séma (helyi XAMPP)
-- MySQL / MariaDB · Beekeeper Studio
--
-- Ez a HELYI fejlesztéshez van: létrehozza a klinikai webalkalmazás
-- tábláit IS (users, helyszinek) utánzó formában, hogy az app
-- önállóan fusson. ÉLES integrációban a `schema_integration.sql`
-- fut a valódi klinikai adatbázis ellen (ott a users/helyszinek már
-- létezik, és NEM hozzuk létre újra).
--
-- Futtatás:
--   CREATE DATABASE IF NOT EXISTS eszkoznyilvantartas
--     CHARACTER SET utf8mb4 COLLATE utf8mb4_hungarian_ci;
--   USE eszkoznyilvantartas;  -- majd ezt a fájlt
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS device_active_reservations;
DROP VIEW  IF EXISTS device_pending_checkins;
DROP VIEW  IF EXISTS device_current_state;
DROP TABLE IF EXISTS device_reservations;
DROP TABLE IF EXISTS device_custody_events;
DROP TABLE IF EXISTS device_attribute_values;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS attribute_definitions;
DROP TABLE IF EXISTS device_types;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS helyszinek;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- KÜLSŐ (klinikai) táblák — itt CSAK a fejlesztéshez hozzuk létre.
-- A valódi oszlopnevek: users(id, username, nev, jogosultsag), helyszinek(id, cim).
-- A `jelszo` (bcrypt hash) a helyi bejelentkezéshez kell; éles rendszerben
-- a klinika saját hitelesítési oszlopát/munkamenetét használd.
-- jogosultsag: 0 = felhasználó, 1 = raktáros, 2 = superadmin (it_admin)
-- ------------------------------------------------------------
CREATE TABLE users (
  id           INT          NOT NULL AUTO_INCREMENT,
  username     VARCHAR(64)  NOT NULL,
  nev          VARCHAR(128) NOT NULL,
  jogosultsag  TINYINT      NOT NULL DEFAULT 0,
  jelszo       VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE helyszinek (
  id    INT          NOT NULL AUTO_INCREMENT,
  cim   VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Eszköznyilvántartás táblái (EHHEZ az adatbázishoz tartoznak)
-- ------------------------------------------------------------

-- departments — helyiségek egy telephelyen (helyszinek). type='raktár' soha nem birtokos.
CREATE TABLE departments (
  id            INT          NOT NULL AUTO_INCREMENT,
  locations_id  INT          NOT NULL,
  name          VARCHAR(128) NOT NULL,
  type          VARCHAR(16)  NOT NULL DEFAULT 'osztály',
  PRIMARY KEY (id),
  KEY idx_departments_location (locations_id),
  CONSTRAINT fk_departments_location FOREIGN KEY (locations_id) REFERENCES helyszinek (id),
  CONSTRAINT chk_departments_type CHECK (type IN ('raktár','osztály','recepció','műhely'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_types (
  id           INT          NOT NULL AUTO_INCREMENT,
  type         VARCHAR(64)  NOT NULL,
  description  VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_device_types_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attribute_definitions (
  id              INT          NOT NULL AUTO_INCREMENT,
  device_type_id  INT          NULL,
  attribute_key   VARCHAR(64)  NOT NULL,
  label           VARCHAR(128) NOT NULL,
  data_type       VARCHAR(16)  NOT NULL DEFAULT 'text',
  is_required     TINYINT(1)   NOT NULL DEFAULT 0,
  options         VARCHAR(255) NULL,
  sort_order      INT          NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attrdef_type_key (device_type_id, attribute_key),
  CONSTRAINT fk_attrdef_type FOREIGN KEY (device_type_id) REFERENCES device_types (id),
  CONSTRAINT chk_attrdef_datatype CHECK (data_type IN ('text','integer','decimal','date','boolean','enum'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE devices (
  device_id       INT          NOT NULL AUTO_INCREMENT,
  asset_tag       VARCHAR(64)  NOT NULL,
  device_type_id  INT          NOT NULL,
  manufacturer    VARCHAR(128) NULL,
  model           VARCHAR(128) NULL,
  serial_number   VARCHAR(128) NULL,
  status          VARCHAR(32)  NOT NULL DEFAULT 'Kivehető',
  `condition`     VARCHAR(32)  NULL,
  notes           TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by      INT          NULL,
  updated_by      INT          NULL,
  retired_date    DATE         NULL,
  PRIMARY KEY (device_id),
  UNIQUE KEY uq_devices_asset_tag (asset_tag),
  KEY idx_devices_type (device_type_id),
  CONSTRAINT fk_devices_type       FOREIGN KEY (device_type_id) REFERENCES device_types (id),
  CONSTRAINT fk_devices_created_by FOREIGN KEY (created_by)     REFERENCES users (id),
  CONSTRAINT fk_devices_updated_by FOREIGN KEY (updated_by)     REFERENCES users (id),
  CONSTRAINT chk_devices_status CHECK (status IN
    ('Kivehető','Kiadva','Lefoglalva','Visszavétel folyamatban',
     'Szerviz alatt','Elveszett','Selejtezve'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_attribute_values (
  id                       INT  NOT NULL AUTO_INCREMENT,
  device_id                INT  NOT NULL,
  attribute_definition_id  INT  NOT NULL,
  value                    TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dav_device_attr (device_id, attribute_definition_id),
  KEY idx_dav_attrdef (attribute_definition_id),
  CONSTRAINT fk_dav_device  FOREIGN KEY (device_id)               REFERENCES devices (device_id) ON DELETE CASCADE,
  CONSTRAINT fk_dav_attrdef FOREIGN KEY (attribute_definition_id) REFERENCES attribute_definitions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_custody_events (
  event_id              INT          NOT NULL AUTO_INCREMENT,
  device_id             INT          NOT NULL,
  event_type            VARCHAR(24)  NOT NULL,
  actor_user_id         INT          NOT NULL,
  from_user_id          INT          NULL,
  from_locations_id     INT          NULL,
  from_departments_id   INT          NULL,
  to_user_id            INT          NULL,
  to_locations_id       INT          NULL,
  to_departments_id     INT          NULL,
  event_timestamp       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expected_return_date  DATE         NULL,
  condition_at_event    VARCHAR(32)  NULL,
  notes                 TEXT         NULL,
  confirmation_status   VARCHAR(12)  NOT NULL DEFAULT 'confirmed',
  confirmed_by          INT          NULL,
  confirmed_at          DATETIME     NULL,
  pending_device_id     INT          AS (CASE WHEN confirmation_status = 'pending' THEN device_id END) VIRTUAL,
  PRIMARY KEY (event_id),
  KEY idx_custody_device (device_id, event_timestamp),
  KEY idx_custody_status (confirmation_status, event_timestamp),
  UNIQUE KEY uq_one_pending_checkin_per_device (pending_device_id),
  CONSTRAINT fk_custody_device      FOREIGN KEY (device_id)           REFERENCES devices (device_id),
  CONSTRAINT fk_custody_actor       FOREIGN KEY (actor_user_id)       REFERENCES users (id),
  CONSTRAINT fk_custody_from_user   FOREIGN KEY (from_user_id)        REFERENCES users (id),
  CONSTRAINT fk_custody_from_loc    FOREIGN KEY (from_locations_id)   REFERENCES helyszinek (id),
  CONSTRAINT fk_custody_from_dept   FOREIGN KEY (from_departments_id) REFERENCES departments (id),
  CONSTRAINT fk_custody_to_user     FOREIGN KEY (to_user_id)          REFERENCES users (id),
  CONSTRAINT fk_custody_to_loc      FOREIGN KEY (to_locations_id)     REFERENCES helyszinek (id),
  CONSTRAINT fk_custody_to_dept     FOREIGN KEY (to_departments_id)   REFERENCES departments (id),
  CONSTRAINT fk_custody_confirmedby FOREIGN KEY (confirmed_by)        REFERENCES users (id),
  CONSTRAINT chk_custody_event_type CHECK (event_type IN
    ('check_out','check_in','transfer','stock_transfer',
     'send_to_repair','return_from_repair','mark_lost','mark_found')),
  CONSTRAINT chk_custody_to_holder CHECK
    (to_user_id IS NOT NULL OR to_departments_id IS NOT NULL OR to_locations_id IS NOT NULL),
  CONSTRAINT chk_custody_conf_status CHECK
    (confirmation_status IN ('pending','confirmed','rejected')),
  CONSTRAINT chk_custody_conf_resolution CHECK (
    (confirmation_status = 'pending'   AND confirmed_by IS NULL     AND confirmed_at IS NULL) OR
    (confirmation_status = 'rejected'  AND confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL) OR
    (confirmation_status = 'confirmed' AND
       ((confirmed_by IS NULL AND confirmed_at IS NULL) OR
        (confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL)))
  ),
  CONSTRAINT chk_custody_pending_only_checkin CHECK
    (confirmation_status = 'confirmed' OR event_type = 'check_in')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_reservations (
  reservation_id  INT       NOT NULL AUTO_INCREMENT,
  device_id       INT       NOT NULL,
  reserved_by     INT       NOT NULL,
  reserved_at     DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME  NOT NULL,
  notes           TEXT      NULL,
  PRIMARY KEY (reservation_id),
  UNIQUE KEY uq_resv_one_per_device (device_id),
  KEY idx_resv_expires (expires_at),
  KEY idx_resv_user (reserved_by),
  CONSTRAINT fk_resv_device FOREIGN KEY (device_id)   REFERENCES devices (device_id),
  CONSTRAINT fk_resv_user   FOREIGN KEY (reserved_by) REFERENCES users (id),
  CONSTRAINT chk_resv_expiry CHECK (expires_at > reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Nézetek =====================

CREATE VIEW device_current_state AS
SELECT e.device_id,
       e.to_user_id        AS current_holder_user_id,
       e.to_locations_id   AS current_location_id,
       e.to_departments_id AS current_department_id,
       e.event_timestamp   AS since
FROM device_custody_events e
JOIN (
    SELECT device_id, MAX(event_timestamp) AS mx
    FROM device_custody_events
    WHERE confirmation_status = 'confirmed'
    GROUP BY device_id
) latest ON latest.device_id = e.device_id AND latest.mx = e.event_timestamp
WHERE e.confirmation_status = 'confirmed';

CREATE VIEW device_pending_checkins AS
SELECT event_id, device_id,
       actor_user_id     AS submitted_by,
       from_user_id      AS returning_user,
       to_locations_id   AS claimed_location_id,
       to_departments_id AS claimed_department_id,
       event_timestamp   AS submitted_at,
       condition_at_event, notes
FROM device_custody_events
WHERE confirmation_status = 'pending'
ORDER BY event_timestamp;

CREATE VIEW device_active_reservations AS
SELECT reservation_id, device_id, reserved_by, reserved_at, expires_at, notes
FROM device_reservations
WHERE expires_at > NOW();
