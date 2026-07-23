<?php
// ============================================================
// Központi konfiguráció — Eszköznyilvántartás API
// ============================================================

// ---- Adatbázis ---------------------------------------------
// Beágyazott üzem: a public/eszkoznyilvantartas/api/index.php előbb
// betölti a site autoload.php-ját, így a Booking_Constants elérhető.
// Önálló (standalone) fejlesztésnél a fallback értékek élnek.
if (class_exists('Booking_Constants')) {
  define('DB_HOST', Booking_Constants::SQL_HOST);
  define('DB_PORT', 3306);
  define('DB_NAME', Booking_Constants::SQL_DB);
  define('DB_USER', Booking_Constants::SQL_USER);
  define('DB_PASS', Booking_Constants::SQL_PASS);
} else {
  define('DB_HOST', 'bejelentkezes.hungariamed.hu');
  define('DB_PORT', 4450);
  define('DB_NAME', 'hungariamed');   // éles: a klinikai adatbázis neve
  define('DB_USER', 'dugalin.marin');
  define('DB_PASS', 'Aighah5u');
}

// ---- Klinikai users tábla oszlop-leképezése ----------------
// A frontend `full_name`/`auth` mezőket vár; a klinikai tábla `nev`/`jogosultsag`.
// A backend itt fordít, így a frontend szerepkör-logikája változatlan marad.
const USER_NAME_COLUMN     = 'nev';          // → full_name
const USER_ROLE_COLUMN     = 'jogosultsag';  // INT 0/1/2 → auth string
const USER_PASSWORD_COLUMN = 'jelszo';       // bcrypt hash (éles: a klinika oszlopa)

// helyszinek (locations) oszlopa
const LOCATION_ADDRESS_COLUMN = 'cim';       // → address

// jogosultsag (INT) ↔ auth (string) leképezés
const ROLE_INT_TO_STRING = [0 => 'user', 1 => 'storekeeper', 2 => 'it_admin'];

// ---- CORS --------------------------------------------------
// A Vite dev szerver origin-je. Éles azonos-origin esetén nem számít.
const CORS_ALLOWED_ORIGINS = [
  'http://localhost:5173',
  'http://127.0.0.1:5173',
  'http://localhost',
];

// ---- Munkamenet --------------------------------------------
const SESSION_NAME = 'eszkozsession';

// ---- SSO ---------------------------------------------------
// Shared secret for cross-domain SSO handoff from the clinic website.
// Must match the value configured on the clinic website side.
// Replace with a long random string before deploying.
const SSO_SECRET      = '5beaa1029ca92f6215d5c5c6a24b95b9fba2c195800db584a6f48bf7e23d308a';
// A token az admin oldal RENDERELÉSEKOR keletkezik, de csak azután válik be,
// hogy a beágyazott app JS-csomagjai (több chunk) letöltődtek és lefutottak —
// lassú mobilneten (hideg cache, cellás net) ez önmagában meghaladhatja a
// korábbi 60mp-et, ami hamis "Bejelentkezés szükséges" hibát okozott.
const SSO_TTL_SECONDS = 180;

// Add the clinic website's origin here when known, e.g.:
//   'https://klinika.example.hu'

// ---- Üzleti állandók ---------------------------------------
const RESERVATION_DAYS = 3;  // foglalás élettartama (nap)

// ---- Hibakeresés ---------------------------------------------
// Csak az itt felsorolt teszt-hosztokon adjuk vissza a nyers kivétel-
// üzenetet (fájl:sor) az API-válaszban 500 esetén — bármely más hoszton
// (éles) ez MINDIG "Szerverhiba."-ra korlátozódik, hogy ne szivárogjon
// infrastruktúra-részlet. Ide vedd fel a teszt-domaint, ha diagnosztizálni
// akarsz, majd távolítsd el (vagy hagyd itt, csak arra a hosztra hat).
const API_DEBUG_HOSTS = ['dmarciteszt.hungariamed.hu', 'localhost'];
define('API_DEBUG_ERRORS', in_array($_SERVER['HTTP_HOST'] ?? '', API_DEBUG_HOSTS, true));
