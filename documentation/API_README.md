# Eszköznyilvántartás API

PHP (8.2) + MariaDB/MySQL backend a hordozható eszköznyilvántartó SPA-hoz.
A `store.js` (frontend) műveleti és származtatott-állapot logikájának
szerveroldali, hiteles megfelelője. Minden számítást a backend végez.

## Architektúra

```
index.php            Front controller — útvonal → kezelő, egységes JSON-burok
config/              config, cors, database (PDO singleton), session
helpers/             Response (json_success/json_error, OpError), Validator
lib/Repo.php         Olvasás + enriched eszköznézet (deviceVM párja)
lib/Lookups.php      Törzsadat + listák (klinikai oszlopok → frontend kontraktus)
lib/Ops.php          Írás: minden store.js művelet, tranzakcióban
lib/Auth.php         Munkamenet-alapú hitelesítés, szerepkör-kapuk
lib/Roles.php        jogosultsag INT (0/1/2) ↔ auth string
db/schema_dev.sql    Helyi séma (users + helyszinek táblát IS létrehoz)
db/schema_integration.sql  Éles: a meglévő klinikai DB-be (users/helyszinek már létezik)
db/seed_dev.php      Fejlesztői mintaadat (a seed.js párja)
```

## Telepítés (helyi, XAMPP)

```bash
# 1) Adatbázis + séma
C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS eszkoznyilvantartas CHARACTER SET utf8mb4 COLLATE utf8mb4_hungarian_ci;"
C:\xampp\mysql\bin\mysql.exe -uroot eszkoznyilvantartas < db/schema_dev.sql

# 2) Mintaadat
C:\xampp\php\php.exe db/seed_dev.php
```

Bejelentkezés (dev): bármely `username` + jelszó `jelszo123`.
Szerepkörök: `kovacs.anna` (user), `szabo.julia` (storekeeper), `toth.gabor` (it_admin).

## Válaszformátum

`{ "ok": true, "data": ... }` vagy `{ "ok": false, "error": "üzenet" }`
Hibakódok: 401 (nincs bejelentkezve), 403 (jogosultság), 404 (nincs ilyen),
422 (üzleti szabály — `OpError`), 500 (szerverhiba).

## Végpontok

| Metódus | Útvonal | Leírás |
|---|---|---|
| POST | `/auth/login` | `{username, password}` → felhasználó |
| POST | `/auth/logout` | kijelentkezés |
| GET  | `/me` | bejelentkezett felhasználó |
| GET  | `/bootstrap` | egy menetben: törzsadat + eszközök + pending + foglalások + me |
| GET  | `/lookups` | locations, departments, users, deviceTypes, attributeDefinitions |
| GET  | `/devices` | összes enriched eszköz |
| GET  | `/devices/{id}` | egy enriched eszköz |
| GET  | `/devices/by-tag/{tag}` | enriched eszköz leltári azonosító alapján (QR) |
| GET  | `/devices/{id}/history` | custody-előzmény |
| GET  | `/pending` | megerősítésre váró visszavételek |
| GET  | `/reservations` | aktív foglalások |
| POST | `/devices/move` | `move_asset` (check_out/check_in/transfer/stock_transfer) |
| POST | `/devices` | új eszköz (storekeeper+) |
| PUT/PATCH | `/devices/{id}` | eszköz szerkesztése (storekeeper+) |
| POST | `/devices/{id}/reserve` | foglalás |
| POST | `/devices/{id}/cancel-reservation` | foglalás lemondása |
| POST | `/devices/{id}/send-to-repair` | szervizbe (storekeeper+) |
| POST | `/devices/{id}/return-from-repair` | szervizből vissza (storekeeper+) |
| POST | `/devices/{id}/mark-lost` | elveszettnek jelöl (storekeeper+) |
| POST | `/devices/{id}/mark-found` | megkerült (storekeeper+) |
| POST | `/devices/{id}/retire` | selejtezés (storekeeper+) |
| POST | `/checkins/{eventId}/confirm` | visszavétel megerősítése (storekeeper+) |
| POST | `/checkins/{eventId}/reject` | visszavétel elutasítása (storekeeper+) |
| POST | `/locations` | új telephely (it_admin) |
| POST | `/departments` | új részleg (storekeeper+) |
| POST | `/device-types` | új eszköztípus (it_admin) |
| POST | `/attribute-definitions` | új attribútum-definíció (it_admin) |

## Éles integráció

A `config/config.php` állítja be a klinikai DB nevét és a `users`/`helyszinek`
oszlop-leképezést (`USER_NAME_COLUMN`, `USER_ROLE_COLUMN`, `USER_PASSWORD_COLUMN`,
`LOCATION_ADDRESS_COLUMN`). Éles rendszerben a `db/schema_integration.sql` fut a
meglévő klinikai adatbázis ellen (a `users`/`helyszinek` táblát NEM hozza létre),
és a klinika saját hitelesítési oszlopát/munkamenetét használd.
```
