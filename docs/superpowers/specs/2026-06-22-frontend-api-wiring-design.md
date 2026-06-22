# Frontend ↔ Backend API bekötés — terv

**Dátum:** 2026-06-22
**Állapot:** jóváhagyásra vár
**Érintett repó:** `eszkoznyilvantartas_frontend/webapp` (Vite SPA)
**Backend:** `eszkoznyilvantartas_api` (PHP 8.2 + MariaDB) — kész, tesztelt

## 1. Cél

A frontend ma a `src/state/store.js` memóriában futó demó-store-ját használja
(seed.js + localStorage). Le kell cserélni a kész PHP API-ra úgy, hogy a
meglévő nézet-réteg (szinkron olvasók + `subscribe()` újrarajzolás) a lehető
legkevesebbet változzon.

## 2. Megközelítés — „Hydrate + mirror"

A store **publikus felülete változatlan marad** (szinkron getterek,
`subscribe`/`notify`). Belül:

- A seed/`bootstrap()` helyett `hydrate()` tölti a tükör-állapotot a
  `GET /bootstrap` végpontból.
- A tükör **enriched eszközöket** tárol, ahogy a backend adja (a `status`,
  `holder_id`, `reservation`, `pending`, `last_checkout_at`, stb. már kiszámolt
  — egyezik a `deviceVM` igényeivel).
- Minden **művelet aszinkron** lesz: meghívja az API-t, majd frissíti az
  érintett szeleteket és `notify()`-ol.
- A nézetek továbbra is szinkron olvasók; az egyetlen átfutó változás az, hogy
  a művelet-hívások `await`-elnek.

**Elvetett alternatíva:** minden nézet maga, aszinkron módon tölti az adatait.
Idiomatikusabb, de jóval több churn és felesleges újrarajzolás 18 eszköznél.

## 3. Komponensek

### 3.1 `src/lib/api.js` (új)
Vékony fetch-burkoló:
- `apiGet(path)`, `apiSend(method, path, body)`.
- `credentials: 'include'`, `Content-Type: application/json`.
- A `{ ok, data }` burkot kibontja; `ok:false` esetén `OpError(error)`-t dob
  (a store újraexportálja az `OpError`-t, hogy a UI-kód `e.message`-t toastolhasson).
- Bázis-URL egy konstansból: dev → `/api` (Vite proxy), éles → beágyazott útvonal.

### 3.2 Vite dev proxy (`vite.config.js`)
`/api` → `http://localhost/eszkoznyilvantartas_api`. Így a böngésző
**azonos-origin** `/api/...` hívásokat lát: nincs CORS, a munkamenet-süti
magától működik. Éles, beágyazott build a valódi útvonalat használja.

### 3.3 `src/state/store.js` (belső átírás, publikus API marad)
- Tükör-állapot: `locations, departments, users, deviceTypes,
  attributeDefinitions, devices (enriched), pending, reservations, currentUser`.
- `async hydrate()` → `GET /bootstrap`, feltölti az állapotot, `notify()`.
- `async refresh()` → `devices` + `pending` + `reservations` újratöltése
  (lookups/currentUser nem). Minden művelet ezt hívja sikeres API-válasz után.
- Megmaradó szinkron getterek: `getDevices, getDevice, getDeviceByAssetTag,
  getUsers, getUser, getDepartments, getDepartment, getLocations, getLocation,
  getDeviceTypes, getDeviceType, getAttrDefs,
  currentUser, currentRole, roleAtLeast, isStorageDept`.
- Elhagyva: `getEvents` — a `vm.js` átírása után nincs több fogyasztója.
- `currentState(id)`, `activeReservation(id)`, `pendingCheckinFor(id)`,
  `pendingCheckins()`, `activeReservations()`: az enriched eszköz / tükör
  mezőiből származtatva (nem kliensoldali eseménynaplóból).
- Aszinkron műveletek (mind `await apiSend(...)` + `await refresh()` + `notify()`):
  `moveAsset, reserveDevice, cancelReservation, confirmCheckIn, rejectCheckIn,
  sendToRepair, returnFromRepair, markLost, markFound, registerDevice,
  editDevice, retireDevice, addLocation, addDepartment, addDeviceType, addAttrDef`.
- `setCurrentUser(id)` (**csak dev**): `POST /api/auth/login` az adott seed
  userként (dev jelszó), majd `hydrate()`, `notify()`.
- Megszűnik: localStorage perzisztálás, seed bootstrap, `resetToSeed`.

### 3.4 `src/lib/vm.js`
`deviceVM(dev)` közvetlenül az enriched eszközből dolgozik:
`status` (már effektív), `holder_id`/`location_id`/`department_id`, `since`,
`reservation`, `pending`, `calibration_due`, `last_checkout_at`,
`last_modified`, `is_free`, `is_lost`, `in_repair`. A `holder`/`reservedBy`/
`type` objektumokat a tükörből oldja fel (`getUser`, `getDeviceType`).

### 3.5 `src/ui/components.js`
`openModal` confirm-kezelője `async`: `await onConfirm(root)`. Elutasításkor
toastolja a hibát és **nyitva tartja** a modált (a mostani viselkedés, async-aware).
A `// t0oast` elgépelés javítása.

### 3.6 Művelet-hívási helyek
`src/ui/actions.js`, `src/views/register_device.js`,
`src/views/register_data.js`: a hívások `await`-elnek `async` kezelőkön belül.
A siker-toast csak az await feloldása után fut. (A `registerDevice` által
visszaadott eszközt a navigációhoz használjuk.)

### 3.7 `src/appshell.js` + auth
- Az indítás aszinkron: betöltő állapot → `await hydrate()` → render.
- Identitás a `/me`-ből (a bootstrap része).
- A demó user-váltó **csak dev**-ben látszik (`import.meta.env.DEV`); a
  beágyazott buildben rejtett. Dev-ben `setCurrentUser` hajtja.
- **Dev kezdeti munkamenet:** ha betöltéskor nincs session, automatikus
  bejelentkezés egy alapértelmezett seed userként (storekeeper: `szabo.julia`),
  hogy az app azonnal működjön.
- **Éles:** ha `/me` null → „Jelentkezz be a fő oldalon" üzenet.

## 4. Adatfolyam

```
betöltés:  appshell → store.hydrate() → GET /api/bootstrap → tükör + notify → render
olvasás:   nézet → getDevices()/deviceVM() → tükör (szinkron)
művelet:   gomb → actions onConfirm → await store.moveAsset()
             → POST /api/devices/move → await refresh() (devices+pending+reservations)
             → notify() → render;  hiba → OpError → toast, modal nyitva marad
dev váltás: dropdown → setCurrentUser(id) → POST /api/auth/login → hydrate() → render
```

## 5. Hibakezelés

- API `ok:false` → `OpError(error)` → a UI `e.message`-t toastol (HU üzenetek a
  backendből).
- 401 (nincs session): dev-ben az auto-login előzi meg; éles-ben a `/me` null →
  bejelentkezési üzenet. Művelet közbeni 401 → toast + (opcionálisan) újratöltés.
- Hálózati hiba: `apiSend` dob → a hívó toastol; a modal nyitva marad.

## 6. Hatókörön kívül (YAGNI)

Offline támogatás, optimista UI, valódi login-képernyő, history gyorsítótár
(a részletoldal igény szerint kéri a `/devices/{id}/history`-t), telephely-/
részleg-szerkesztés a meglévőkön túl.

## 7. Tesztelési terv

Manuális, futó Apache + Vite dev szerver ellen:
1. Betöltés → auto-login (szabo.julia) → eszközlista feltöltődik.
2. Kivétel/leadás/átadás/raktármozgatás → státusz frissül, toast.
3. Foglalás/lemondás; pending megerősítés/elutasítás (raktáros).
4. Szerviz/elveszett/megkerült; regisztráció + szerkesztés; selejtezés.
5. Szerepkör-váltás a dropdownnal → jogosultsági gombok változnak.
6. Hibaág: pl. foglalt eszköz kivétele másként → toast, modal nyitva.
7. `import.meta.env.DEV=false` build: a switcher rejtett (kód-ellenőrzés).
