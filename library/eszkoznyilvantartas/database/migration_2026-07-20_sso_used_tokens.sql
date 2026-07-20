-- ============================================================
-- Migráció — SSO token egyszeri felhasználásának nyilvántartása
-- (replay-védelem: a jelenlegi SSO handshake csak egy 60 másodperces
-- időablakot ellenőriz, de egy elcsípett érvényes token többször is
-- felhasználható lenne az ablakon belül. Ez a tábla ezt zárja le.)
--
-- Futtatás:
--   USE eszkoznyilvantartas;   -- vagy a klinikai adatbázis neve
--   SOURCE migration_2026-07-20_sso_used_tokens.sql;
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS eszkoznyilvantartas_sso_used_tokens (
  token_hash  CHAR(64)  NOT NULL,   -- SHA-256(token) — maga a token nem kerül tárolásra
  used_at     DATETIME  NOT NULL,
  PRIMARY KEY (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
