-- ============================================================
-- Migráció — bejelentkezési kísérletek nyilvántartása (rate limit).
-- Ismételt hibás jelszóval próbálkozás elleni minimális védelem:
-- N egymást követő hiba után rövid ideig zárolja a felhasználónevet.
--
-- Futtatás:
--   USE eszkoznyilvantartas;   -- vagy a klinikai adatbázis neve
--   SOURCE migration_2026-07-20_login_attempts.sql;
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_login_attempts (
  username      VARCHAR(191) NOT NULL,
  fail_count    INT          NOT NULL DEFAULT 0,
  last_attempt  DATETIME     NOT NULL,
  locked_until  DATETIME     NULL,
  PRIMARY KEY (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
