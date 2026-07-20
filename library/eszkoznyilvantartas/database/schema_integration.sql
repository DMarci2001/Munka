-- ============================================================
-- Eszköznyilvántartás — ÉLES integráció a meglévő klinikai adatbázisba
-- MySQL / MariaDB · Beekeeper Studio
--
-- KÜLSŐ, MÁR LÉTEZŐ táblák (NEM hozzuk létre, csak hivatkozunk):
--   users      (id INT UNSIGNED, username, nev, jogosultsag [0=user,1=storekeeper,2=superadmin])
--   helyszinek (id INT UNSIGNED, cim)
--
-- FONTOS: users és helyszinek táblák MyISAM motorúak → FK kényszert nem
-- lehet rájuk hivatkozni. Az *_id oszlopok INT UNSIGNED típussal megegyeznek
-- a klinikai PK-kkal, de a hivatkozás csak logikai (nem DB-szintű kényszer).
--
-- BEJELENTKEZÉS: ez a szkript nem módosítja a users táblát.
-- A backend a klinika meglévő hitelesítési oszlopát/munkamenetét használja
-- (lásd config/config.php → USER_PASSWORD_COLUMN).
-- ============================================================
SET NAMES utf8mb4;

-- eszkoznyilvantartas_departments — helyiségek egy telephelyen (helyszinek). type='raktár' soha nem birtokos.
CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_departments (
  id            INT           NOT NULL AUTO_INCREMENT,
  locations_id  INT UNSIGNED  NOT NULL,
  name          VARCHAR(128)  NOT NULL,
  type          VARCHAR(16)   NOT NULL DEFAULT 'osztály',
  PRIMARY KEY (id),
  KEY idx_departments_location (locations_id),
  CONSTRAINT chk_departments_type CHECK (type IN ('raktár','osztály','recepció','műhely'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_device_types (
  id           INT          NOT NULL AUTO_INCREMENT,
  type         VARCHAR(64)  NOT NULL,
  description  VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_device_types_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_attribute_definitions (
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
  CONSTRAINT fk_attrdef_type FOREIGN KEY (device_type_id) REFERENCES eszkoznyilvantartas_device_types (id),
  CONSTRAINT chk_attrdef_datatype CHECK (data_type IN ('text','integer','decimal','date','boolean','enum'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_devices (
  device_id       INT           NOT NULL AUTO_INCREMENT,
  asset_tag       VARCHAR(64)   NOT NULL,
  device_type_id  INT           NOT NULL,
  manufacturer    VARCHAR(128)  NULL,
  model           VARCHAR(128)  NULL,
  serial_number   VARCHAR(128)  NULL,
  status          VARCHAR(32)   NOT NULL DEFAULT 'Kivehető',
  `condition`     VARCHAR(32)   NULL,
  notes           TEXT          NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by      INT UNSIGNED  NULL,
  updated_by      INT UNSIGNED  NULL,
  retired_date    DATE          NULL,
  PRIMARY KEY (device_id),
  UNIQUE KEY uq_devices_asset_tag (asset_tag),
  KEY idx_devices_type (device_type_id),
  KEY idx_devices_created_by (created_by),
  KEY idx_devices_updated_by (updated_by),
  CONSTRAINT fk_devices_type FOREIGN KEY (device_type_id) REFERENCES eszkoznyilvantartas_device_types (id),
  CONSTRAINT chk_devices_status CHECK (status IN
    ('Kivehető','Kiadva','Lefoglalva','Visszavétel folyamatban',
     'Szerviz alatt','Elveszett','Selejtezve'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_device_attribute_values (
  id                       INT  NOT NULL AUTO_INCREMENT,
  device_id                INT  NOT NULL,
  attribute_definition_id  INT  NOT NULL,
  value                    TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dav_device_attr (device_id, attribute_definition_id),
  KEY idx_dav_attrdef (attribute_definition_id),
  CONSTRAINT fk_dav_device  FOREIGN KEY (device_id)               REFERENCES eszkoznyilvantartas_devices (device_id) ON DELETE CASCADE,
  CONSTRAINT fk_dav_attrdef FOREIGN KEY (attribute_definition_id) REFERENCES eszkoznyilvantartas_attribute_definitions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_device_custody_events (
  event_id              INT           NOT NULL AUTO_INCREMENT,
  device_id             INT           NOT NULL,
  event_type            VARCHAR(24)   NOT NULL,
  actor_user_id         INT UNSIGNED  NOT NULL,
  from_user_id          INT UNSIGNED  NULL,
  from_locations_id     INT UNSIGNED  NULL,
  from_departments_id   INT           NULL,
  to_user_id            INT UNSIGNED  NULL,
  to_locations_id       INT UNSIGNED  NULL,
  to_departments_id     INT           NULL,
  event_timestamp       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expected_return_date  DATE          NULL,
  condition_at_event    VARCHAR(32)   NULL,
  notes                 TEXT          NULL,
  confirmation_status   VARCHAR(12)   NOT NULL DEFAULT 'confirmed',
  confirmed_by          INT UNSIGNED  NULL,
  confirmed_at          DATETIME      NULL,
  pending_device_id     INT           AS (CASE WHEN confirmation_status = 'pending' THEN device_id END) VIRTUAL,
  PRIMARY KEY (event_id),
  KEY idx_custody_device (device_id, event_timestamp),
  KEY idx_custody_status (confirmation_status, event_timestamp),
  KEY idx_custody_actor (actor_user_id),
  KEY idx_custody_from_loc (from_locations_id),
  KEY idx_custody_to_loc (to_locations_id),
  UNIQUE KEY uq_one_pending_checkin_per_device (pending_device_id),
  CONSTRAINT fk_custody_device      FOREIGN KEY (device_id)           REFERENCES eszkoznyilvantartas_devices (device_id),
  CONSTRAINT fk_custody_from_dept   FOREIGN KEY (from_departments_id) REFERENCES eszkoznyilvantartas_departments (id),
  CONSTRAINT fk_custody_to_dept     FOREIGN KEY (to_departments_id)   REFERENCES eszkoznyilvantartas_departments (id),
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

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_device_reservations (
  reservation_id  INT           NOT NULL AUTO_INCREMENT,
  device_id       INT           NOT NULL,
  reserved_by     INT UNSIGNED  NOT NULL,
  reserved_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME      NOT NULL,
  notes           TEXT          NULL,
  PRIMARY KEY (reservation_id),
  UNIQUE KEY uq_resv_one_per_device (device_id),
  KEY idx_resv_expires (expires_at),
  KEY idx_resv_user (reserved_by),
  CONSTRAINT fk_resv_device FOREIGN KEY (device_id) REFERENCES eszkoznyilvantartas_devices (device_id),
  CONSTRAINT chk_resv_expiry CHECK (expires_at > reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SSO token egyszeri felhasználásának nyilvántartása (replay-védelem).
CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_sso_used_tokens (
  token_hash  CHAR(64)  NOT NULL,   -- SHA-256(token) — maga a token nem kerül tárolásra
  used_at     DATETIME  NOT NULL,
  PRIMARY KEY (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bejelentkezési kísérletek nyilvántartása (rate limit / lockout).
CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_login_attempts (
  username      VARCHAR(191) NOT NULL,
  fail_count    INT          NOT NULL DEFAULT 0,
  last_attempt  DATETIME     NOT NULL,
  locked_until  DATETIME     NULL,
  PRIMARY KEY (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================== Nézetek =====================

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
