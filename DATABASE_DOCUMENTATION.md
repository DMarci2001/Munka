# Hordozható Eszköznyilvántartás — Adatbázis-dokumentáció

**Rendszer:** vagyonnyilvántartás a klinika hordozható eszközeihez (több telephely, központi raktár).
**Adatbázis:** relációs (MySQL/PostgreSQL). **Kapcsolódó fájlok:** `device-inventory-schema.dbml` (ER-import), `view-device_current_state.sql` (nézetek), `SCRIPT_LOGIC_DOCUMENTATION.md` (kezelő-/szkriptlogika).

> Ez a dokumentáció a tényleges, megvalósított modellt írja le (a `webapp/` demó-implementáció állapota szerint). A demó memóriában, localStorage-perzisztenciával fut, de a szerkezet a relációs sémát követi: minden táblanév, oszlopnév és felsorolt érték az itt leírtak szerint.

---

## 1. Cél és hatókör

Az adatbázis minden hordozható eszközt nyilvántart (ultrahang, EKG, vérnyomásmérő, laptop, nyomtató, router, USB-meghajtó és bármely jövőbeli típus), valamint azt, hogy az egyes eszközök **hol vannak** és **kinél**. Minden eszköz egyedi azonosítót hordoz (`asset_tag`), amely QR-kódba kódolható.

Az eszközök közös mezőkészleten osztoznak, miközben minden *típus* saját további mezőkkel rendelkezik („alattribútumok"), amelyek nem oszlopként, hanem adatként vannak modellezve (lásd §6). Az eszközök személyek, osztályok és raktárak közötti mozgása **csak hozzáfűzhető (append-only)** eseménynaplóként van rögzítve; az aktuális birtokost és helyet ebből a naplóból vezetjük le (lásd §7).

A rendszert három szerepkör használja — rendes felhasználók (`user`), egy központi raktáros (`storekeeper`) és az IT-csapat (`it_admin`) —, ezeket a `SCRIPT_LOGIC_DOCUMENTATION.md` írja le.

---

## 2. Konvenciók

Az elsődleges kulcsok adatbázis által generált egész azonosítók (a demóban növekvő sorszám; éles rendszerben `AUTO_INCREMENT` / `BIGINT` vagy UUID).

Az időbélyegek dátum+idő típusúak (`timestamp`/`datetime`, UTC ajánlott). Az idő nélküli dátumok `date` típusúak.

A legtöbb tábla **ehhez az adatbázishoz tartozik**, két kivétellel: a `users` és a `locations` **külső** táblák (a meglévő klinikai webalkalmazásé, illetve a klinika weboldaláé). Ezekre idegen kulccsal hivatkozunk, de a rendszer soha nem írja őket (lásd §3). A `departments` ezzel szemben **ehhez** az adatbázishoz tartozik — a klinika weboldala nem tárol helyiségeket.

Egy eszköz *aktuális birtokosa és helye* **nincs** az eszköz során tárolva; ezeket a `device_custody_events` legújabb **megerősített** sorából vezetjük le, és a `device_current_state` nézet teszi elérhetővé (lásd §7).

**Kétszintű hely.** A fizikai elhelyezkedés két szinten értelmezett:
- `locations` — telephely (épület/cím), pl. „1095 Budapest, Soroksári út 12." — **külső**, a klinika weboldaláról;
- `departments` — a telephelyen belüli helyiség/szervezeti egység (raktár, osztály, recepció, műhely) — **ehhez** az adatbázishoz tartozik.

Ezért minden birtoklási esemény **mindkét** szintet rögzítheti: `to_locations_id` (telephely) **és** `to_departments_id` (helyiség).

---

## 3. Integráció a klinikai webalkalmazással

Két **külső** táblára hivatkozunk; ezeket nem ez a rendszer írja:

| Tábla       | Tulajdonos             | Használt kulcsoszlopok                         | Jelentés                                                                                                |
|-------------|------------------------|------------------------------------------------|---------------------------------------------------------------------------------------------------------|
| `users`     | klinikai webalkalmazás | `id`, `username`, `full_name`, `auth`, `title` | Minden rendszerfelhasználó (orvosok, recepciósok, raktáros, IT). Az `auth` adja a jogosultsági szintet. |
| `locations` | klinika weboldala      | `id`, `address`                                | A klinika telephelyei (épület/cím). A törzsadatot a weboldal kezeli.                                    |

A `users` táblát a webalkalmazás birtokolja: a bejelentkezést és a szerepkör (`auth`) kiosztását is az kezeli. Ez a modul az `auth`-ot csak **olvassa**, sosem írja — nincs `grant_role` művelete. A `locations` törzsadatot hasonlóan csak olvassa: új telephelyet nem itt veszünk fel.

A `departments` **nem** külső: mivel a klinika weboldala nem tárol helyiségeket, a raktárakat/osztályokat/műhelyeket ebben az adatbázisban vezetjük (a `departments.locations_id` a külső `locations`-re mutat).

Ha a nyilvántartási táblák **ugyanabban** az adatbázisban élnek, mint a webalkalmazás, a `users`-re és `locations`-re mutató idegen kulcsok közvetlenül feloldódnak. Ha **külön** adatbázisban élnek, tárold az azonosítókat FK-megszorítás nélkül, és az alkalmazásrétegben ellenőrizd a hivatkozásokat.

---

## 4. Felsorolt típusok (enum)

```
device_status       : 'Kivehető' | 'Kiadva' | 'Lefoglalva' | 'Visszavétel folyamatban'
                      | 'Szerviz alatt' | 'Elveszett' | 'Selejtezve'
custody_event_type  : 'check_out' | 'check_in' | 'transfer' | 'stock_transfer'
                      | 'send_to_repair' | 'return_from_repair' | 'mark_lost' | 'mark_found'
confirmation_status : 'pending' | 'confirmed' | 'rejected'
attr_data_type      : 'text' | 'integer' | 'decimal' | 'date' | 'boolean' | 'enum'
auth                : 'user' | 'storekeeper' | 'it_admin'
department_type     : 'raktár' | 'osztály' | 'recepció' | 'műhely'
```

**Eszközstátuszok jelentése:**

| Státusz                   | Jelentés                                                                  |
|---------------------------|---------------------------------------------------------------------------|
| `Kivehető`                | Szabad, raktárban/helyen van, nincs birtokosa — elvihető vagy foglalható. |
| `Kiadva`                  | Éppen egy felhasználónál van.                                             |
| `Lefoglalva`              | Egy felhasználó lefoglalta; az elvitelig fenntartva (lásd §10).           |
| `Visszavétel folyamatban` | Egy felhasználó leadta, de raktáros-megerősítésre vár (checkpoint, §9).   |
| `Szerviz alatt`           | Javításra/szervizbe küldve (lásd §11).                                    |
| `Elveszett`               | Elveszettnek jelölve (lásd §11).                                          |
| `Selejtezve`              | Kivonva (lágy törlés); az előzmény megmarad.                              |

**Eseménytípusok jelentése:**

| Eseménytípus         | Magyar címke              | Hatás                                                   |
|----------------------|---------------------------|---------------------------------------------------------|
| `check_out`          | Kivétel                   | Raktárból/helyről felhasználóhoz.                       |
| `check_in`           | Leadás                    | Felhasználótól vissza raktárba/helyre.                  |
| `transfer`           | Átadás                    | Felhasználótól másik felhasználóhoz.                    |
| `stock_transfer`     | Raktármozgatás            | Raktár/hely → raktár/hely, birtokos nélkül.             |
| `send_to_repair`     | Szervizbe küldés          | Műhelybe/szervizbe; státusz → `Szerviz alatt`.          |
| `return_from_repair` | Szervizből visszahelyezés | Szervizből vissza raktárba/helyre.                      |
| `mark_lost`          | Elveszettnek jelölés      | Státusz → `Elveszett`; az utolsó ismert hely marad cél. |
| `mark_found`         | Megtalálva                | Elveszett eszköz visszavezetése egy helyre.             |

---

## 5. Táblák

**„Kötelező" oszlop:** Igen = NOT NULL; Nem = nullable; Feltételes = szabálytól függ (lásd a megjegyzést).

**„Kitölti" oszlop — ki adja az értéket.** A szerepkörök bővülő jogosultságúak: `it_admin` ⊇ `storekeeper` ⊇ `user`.

- **Rendszer** — automatikusan generált/levezetett: azonosítók (PK), időbélyegek, naplóoszlopok, a `from_*` oldal, a bejelentkezett aktor.
- **IT-admin** — a séma/katalógus (eszköztípusok, attribútum-definíciók). *(A telephelyeket — `locations` — a klinika weboldala adja, lásd §3.)*
- **Raktáros** — eszközök regisztrálása/szerkesztése, helyiségek (`departments`) kezelése, bármilyen birtoklási mozgatás. (`it_admin` is megteheti.)
- **Felhasználó** — **kizárólag birtoklási eseményeket** rögzít a saját jogosultsága keretein belül.

Rövidítések: **PK** elsődleges kulcs, **FK** idegen kulcs, **UQ** egyedi, **alapért.** alapérték.

### 5.1 `locations` — telephelyek *(külső)*

Fizikai telephely (épület/cím). **Külső** tábla: a törzsadatot a klinika weboldala adja; ez a modul csak **olvassa** (lásd §3). *(A demó az egyszerűség kedvéért beágyazva tárol néhány telephelyet.)*

| Oszlop    | Típus | Kötelező | Kitölti         | Megjegyzés                                         |
|-----------|-------|----------|-----------------|----------------------------------------------------|
| `id`      | int   | Igen     | Klinika (külső) | **PK**.                                            |
| `address` | text  | Igen     | Klinika (külső) | Teljes cím, pl. „1095 Budapest, Soroksári út 12.". |

### 5.2 `departments` — helyiségek / szervezeti egységek

Egy telephelyen belüli hely: raktár, osztály, recepció vagy műhely. **Ehhez** az adatbázishoz tartozik (a klinika weboldala nem tárol helyiségeket); a `locations_id` a külső `locations` telephelyre mutat. Ez a custody „hely" finomabb szintje. A `type = 'raktár'` helyiség **soha nem birtokos**: ha egy eszköz ide kerül, nincs birtokosa, és `Kivehető` marad (lásd §7).

| Oszlop         | Típus | Kötelező | Kitölti             | Megjegyzés                                                                |
|----------------|-------|----------|---------------------|---------------------------------------------------------------------------|
| `id`           | int   | Igen     | Rendszer            | **PK**.                                                                   |
| `locations_id` | int   | Igen     | Raktáros / IT-admin | **FK** → `locations.id`. Melyik telephelyen van.                          |
| `name`         | text  | Igen     | Raktáros / IT-admin | pl. „Központi raktár", „Kardiológia", „Szerviz / IT".                     |
| `type`         | text  | Igen     | Raktáros / IT-admin | CHECK in (`raktár`, `osztály`, `recepció`, `műhely`). Alapért. `osztály`. |

### 5.3 `device_types` — eszköztípusok katalógusa

| Oszlop        | Típus | Kötelező | Kitölti  | Megjegyzés                                       |
|---------------|-------|----------|----------|--------------------------------------------------|
| `id`          | int   | Igen     | Rendszer | **PK**.                                          |
| `type`        | text  | Igen     | IT-admin | A típus neve, pl. „Ultrahang", „Router". **UQ**. |
| `description` | text  | Nem      | IT-admin | Opcionális leírás.                               |

### 5.4 `attribute_definitions` — típusonkénti mezősablonok

Egy sor egy típusspecifikus mezőt definiál. Ez *konfigurációs adat*, amelyet az IT hoz létre a típus beállításakor, és minden ilyen típusú eszközhöz újrahasznosul. Lásd §6.

| Oszlop           | Típus          | Kötelező | Kitölti  | Megjegyzés                                                                                                |
|------------------|----------------|----------|----------|-----------------------------------------------------------------------------------------------------------|
| `id`             | int            | Igen     | Rendszer | **PK**.                                                                                                   |
| `device_type_id` | int            | Nem      | IT-admin | **FK** → `device_types.id`. Melyik típushoz tartozik. **NULL = minden típusra érvényes** (globális mező). |
| `attribute_key`  | text           | Igen     | IT-admin | Gépi kulcs, pl. `calibration_due`. Típuson belül egyedi.                                                  |
| `label`          | text           | Igen     | IT-admin | Emberi olvasásra szánt felirat az űrlapon.                                                                |
| `data_type`      | attr_data_type | Igen     | IT-admin | A beviteli mezőt és az ellenőrzést vezérli. Alapért. `'text'`.                                            |
| `is_required`    | boolean        | Igen     | IT-admin | Kötelező-e a mező regisztrációkor. Alapért. `false`.                                                      |
| `options`        | text           | Nem      | IT-admin | `enum` mezők megengedett értékei vesszővel (pl. `I,IIa,IIb,III`); egyébként NULL.                         |
| `sort_order`     | integer        | Nem      | IT-admin | A mező sorrendje az űrlapon.                                                                              |

Megszorítás: **UNIQUE (`device_type_id`, `attribute_key`)**.

### 5.5 `devices` — az eszköznyilvántartás (közös mezők)

A minden eszközre közös tényeket tartalmazza. Szándékosan **nem** tárolja az aktuális birtokost vagy helyet — azok a birtoklási naplóból származnak (§7). Az itteni adatokat az eszközpéldányt regisztráló **Raktáros/IT-admin** viszi be (`register_device` / `edit_device`).

| Oszlop           | Típus         | Kötelező | Kitölti             | Megjegyzés                                                                              |
|------------------|---------------|----------|---------------------|-----------------------------------------------------------------------------------------|
| `device_id`      | int           | Igen     | Rendszer            | **PK**. Megváltoztathatatlan belső azonosító.                                           |
| `asset_tag`      | text          | Igen     | Raktáros / IT-admin | **UQ**. A QR-kódba kódolt leltári azonosító.                                            |
| `device_type_id` | int           | Igen     | Raktáros / IT-admin | **FK** → `device_types.id`. Vezérli a típusspecifikus mezőket.                          |
| `manufacturer`   | text          | Nem      | Raktáros / IT-admin | pl. GE Healthcare, HP, MikroTik.                                                        |
| `model`          | text          | Nem      | Raktáros / IT-admin | Modellnév/-szám.                                                                        |
| `serial_number`  | text          | Nem      | Raktáros / IT-admin | Gyári sorozatszám.                                                                      |
| `status`         | device_status | Igen     | Rendszer / Raktáros | Életciklus-állapot (§4). A rendszer karbantartja a birtoklásból. Alapért. `'Kivehető'`. |
| `condition`      | text          | Nem      | Raktáros / IT-admin | Állapotjelző, pl. Jó / Kopott / Hibás / Ismeretlen.                                     |
| `notes`          | text          | Nem      | Raktáros / IT-admin | Szabad szöveg (a selejtezés/elutasítás okát is ide fűzi a rendszer).                    |
| `created_at`     | timestamp     | Igen     | Rendszer            | Napló. Alapért. `now()`.                                                                |
| `updated_at`     | timestamp     | Igen     | Rendszer            | Napló. Minden módosításkor frissül.                                                     |
| `created_by`     | int           | Nem      | Rendszer            | **FK** → `users.id`. A regisztráló, automatikusan.                                      |
| `updated_by`     | int           | Nem      | Rendszer            | **FK** → `users.id`. Az utolsó szerkesztő, automatikusan.                               |
| `retired_date`   | date          | Nem      | Rendszer            | Selejtezéskor beállítva (lágy törlés; az előzmény megmarad).                            |

Index: `idx_devices_type` a `device_type_id`-n.

### 5.6 `device_attribute_values` — egy eszköz típusspecifikus értékei

Egy sor egy eszköz egy kitöltött típusspecifikus mezőjéhez. A tényleges érték `text`-ként tárolódik; valódi típusát a hozzá tartozó definíció `data_type`-ja rögzíti.

| Oszlop                    | Típus | Kötelező   | Kitölti             | Megjegyzés                                                        |
|---------------------------|-------|------------|---------------------|-------------------------------------------------------------------|
| `id`                      | int   | Igen       | Rendszer            | **PK**.                                                           |
| `device_id`               | int   | Igen       | Rendszer            | **FK** → `devices.device_id` (ON DELETE CASCADE).                 |
| `attribute_definition_id` | int   | Igen       | Rendszer            | **FK** → `attribute_definitions.id`. Melyik mezőre válaszol.      |
| `value`                   | text  | Feltételes | Raktáros / IT-admin | A bevitt érték szövegként. Kötelezősége az `is_required` szerint. |

Megszorítás: **UNIQUE (`device_id`, `attribute_definition_id`)**.

> **Megvalósítási megjegyzés:** a `webapp/` demó ezeket az értékeket kényelmi okból egy beágyazott `attrs` JSON-objektumként tárolja az eszközön (kulcs = `attribute_key`). Relációs adatbázisban a fenti normalizált tábla a kanonikus forma; a kettő tartalmilag ekvivalens.

### 5.7 `device_custody_events` — a birtoklási napló (csak hozzáfűzhető)

Az egyetlen igazságforrás arra, hogy hol és kinél van az egyes eszköz. Egy sor egy mozgáshoz. A sorok soha nem módosulnak és nem törlődnek (kivétel: a `confirmation_status` lezárása, lásd §9); az aktuális állapot az eszközönkénti legújabb **megerősített** sor. **Ezt a táblát írja a `user` szerepkör is** — a felhasználó adja a mozgás típusát és célját, a `from_*` oldalt és az aktort a rendszer tölti ki.

| Oszlop                 | Típus               | Kötelező   | Kitölti                | Megjegyzés                                                                                                      |
|------------------------|---------------------|------------|------------------------|-----------------------------------------------------------------------------------------------------------------|
| `event_id`             | int                 | Igen       | Rendszer               | **PK**.                                                                                                         |
| `device_id`            | int                 | Igen       | Felhasználó / Raktáros | **FK** → `devices.device_id`.                                                                                   |
| `event_type`           | custody_event_type  | Igen       | Felhasználó / Raktáros | Lásd §4.                                                                                                        |
| `actor_user_id`        | int                 | Igen       | Rendszer               | **FK** → `users.id`. Ki végezte/naplózta a mozgást. **Mindig kitöltve**, a raktár→raktár esetben is.            |
| `from_user_id`         | int                 | Nem        | Rendszer               | **FK** → `users.id`. Korábbi birtokos, ha személy. Levezetett.                                                  |
| `from_locations_id`    | int                 | Nem        | Rendszer               | **FK** → `locations.id`. Forrás telephely. Levezetett.                                                          |
| `from_departments_id`  | int                 | Nem        | Rendszer               | **FK** → `departments.id`. Forrás helyiség. Levezetett.                                                         |
| `to_user_id`           | int                 | Feltételes | Felhasználó / Raktáros | **FK** → `users.id`. Új birtokos, ha személy.                                                                   |
| `to_locations_id`      | int                 | Feltételes | Felhasználó / Raktáros | **FK** → `locations.id`. Cél telephely.                                                                         |
| `to_departments_id`    | int                 | Feltételes | Felhasználó / Raktáros | **FK** → `departments.id`. Cél helyiség.                                                                        |
| `event_timestamp`      | timestamp           | Igen       | Rendszer               | Mikor történt. Alapért. `now()`.                                                                                |
| `expected_return_date` | date                | Nem        | Felhasználó / Raktáros | Opcionális határidő → lejárt tételek riportja.                                                                  |
| `condition_at_event`   | text                | Nem        | Felhasználó / Raktáros | Átadáskor/leadáskor megállapított állapot.                                                                      |
| `notes`                | text                | Nem        | Felhasználó / Raktáros | Szabad szöveg.                                                                                                  |
| `confirmation_status`  | confirmation_status | Igen       | Rendszer / Raktáros    | `pending` \                                                                                                     |
| `confirmed_by`         | int                 | Feltételes | Rendszer / Raktáros    | **FK** → `users.id`. Aki megerősítette/elutasította. `pending` alatt NULL; automatikus `confirmed` esetén NULL. |
| `confirmed_at`         | timestamp           | Feltételes | Rendszer               | A megerősítés/elutasítás időbélyege. A `confirmed_by`-jal együtt mozog.                                         |

Megszorítások:

- **`chk_to_holder` CHECK (`to_user_id IS NOT NULL OR to_departments_id IS NOT NULL OR to_locations_id IS NOT NULL`)** — minden eseménynek van célja (személy és/vagy hely).
- **`chk_confirmation_status` CHECK** — `confirmation_status` ∈ {`pending`, `confirmed`, `rejected`}.
- **`chk_confirmation_resolution` CHECK** — koherencia: `pending` → `confirmed_by`/`confirmed_at` NULL; `rejected` → mindkettő kötelező; `confirmed` → vagy mindkettő NULL (automatikus), vagy mindkettő kitöltött (raktáros-ellenőrzés után).
- **`chk_pending_only_checkin` CHECK** — csak `event_type = 'check_in'` lehet `pending`/`rejected`; minden más mindig `confirmed`.

Indexek:

- `idx_custody_device` (`device_id`, `event_timestamp DESC`) — „legújabb esemény ehhez az eszközhöz".
- `idx_custody_confirmed` részleges index (`device_id`, `event_timestamp DESC`) WHERE `confirmation_status = 'confirmed'` — a `device_current_state` nézet gyorsítása.
- `idx_custody_pending` részleges index (`event_timestamp`) WHERE `confirmation_status = 'pending'` — a raktáros ellenőrzési listája.
- **`uq_one_pending_checkin_per_device` részleges UNIQUE index (`device_id`) WHERE `confirmation_status = 'pending'`** — eszközönként legfeljebb egy nyitott visszavétel (§9).

### 5.8 `device_reservations` — aktív foglalások

A foglalás **nem** birtoklási mozgás (az eszköz nem mozdul), és lejár — ezért külön táblában él. A sor a foglalás megszűnésekor (elvitel / lemondás / lejárat) **törlődik**, így a tábla mindig csak az **éppen aktív** foglalásokat tartalmazza.

| Oszlop           | Típus     | Kötelező | Kitölti     | Megjegyzés                                                              |
|------------------|-----------|----------|-------------|-------------------------------------------------------------------------|
| `reservation_id` | int       | Igen     | Rendszer    | **PK**.                                                                 |
| `device_id`      | int       | Igen     | Felhasználó | **FK** → `devices.device_id`. **UQ** — eszközönként egy aktív foglalás. |
| `reserved_by`    | int       | Igen     | Rendszer    | **FK** → `users.id`. A foglaló (a bejelentkezett aktor).                |
| `reserved_at`    | timestamp | Igen     | Rendszer    | A foglalás időpontja. Alapért. `now()`.                                 |
| `expires_at`     | timestamp | Igen     | Rendszer    | Lejárat. Alapért. `reserved_at + 3 nap`. Lejárat után a sor törlendő.   |
| `notes`          | text      | Nem      | Felhasználó | Szabad szöveg.                                                          |

Megszorítások:

- **`uq_resv_one_per_device` UNIQUE (`device_id`)** — exkluzivitás.
- **`chk_resv_expiry` CHECK (`expires_at > reserved_at`)**.

Indexek: `idx_resv_expires` (`expires_at`) a takarításhoz; `idx_resv_user` (`reserved_by`).

---

## 6. A metaadat-attribútumrendszer

A típusspecifikus mezők adatként, nem oszlopként vannak tárolva, így új eszköztípusok és mezők nem igényelnek sémamódosítást.

- Az `attribute_definitions` típusonként (`device_type_id`) deklarálja a mezőket (kulcs, címke, adattípus, kötelezőség, lehetőségek, sorrend). A `device_type_id = NULL` definíció **minden** típusra érvényes (globális mező).
- A `device_attribute_values` tárolja az egyes eszközök tényleges válaszait, az `attribute_definition_id`-n keresztül a definícióra mutatva.

Egy típus űrlapjának megjelenítéséhez az alkalmazás beolvassa az `attribute_definitions WHERE device_type_id = <típus> OR device_type_id IS NULL` sorokat, `sort_order` szerint rendezve. Új típus hozzáadása tiszta adatbevitel a `device_types` + `attribute_definitions` táblákba.

---

## 7. A birtoklási modell és a `device_current_state` nézet

A birtokos és a hely soha nincs a `devices` során tárolva; ezek a legújabb **megerősített** birtoklási esemény célját jelentik. A nézet úgy teszi elérhetővé őket, mintha oszlopok lennének:

```sql
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
    WHERE confirmation_status = 'confirmed'   -- checkpoint: csak megerősített esemény számít
    GROUP BY device_id
) latest ON latest.device_id = e.device_id AND latest.mx = e.event_timestamp
WHERE e.confirmation_status = 'confirmed';
```

> **Raktár sosem birtokos:** ha a legújabb esemény cél-helyisége `type = 'raktár'`, az eszköznek **nincs** birtokosa (a `current_holder_user_id` effektíve NULL), és `Kivehető` marad. Az alkalmazásréteg ezt a `device_current_state` olvasásakor érvényesíti.

> **Checkpoint:** a `WHERE confirmation_status = 'confirmed'` szűrő miatt egy függőben lévő (`pending`) felhasználói visszavétel nem változtatja meg az aktuális állapotot — a nézet a check_in *előtti* birtokost/helyet mutatja, amíg a raktáros meg nem erősíti. Az elutasított (`rejected`) esemény sosem érvényesül. Lásd §9.

| Nézet oszlop             | Forrás                                    | Jelentés                                         |
|--------------------------|-------------------------------------------|--------------------------------------------------|
| `device_id`              | esemény                                   | Az eszköz.                                       |
| `current_holder_user_id` | a legújabb esemény `to_user_id`-ja        | Kinél van most (NULL, ha raktárban / helyen ül). |
| `current_location_id`    | a legújabb esemény `to_locations_id`-ja   | Melyik telephelyen van.                          |
| `current_department_id`  | a legújabb esemény `to_departments_id`-ja | Melyik helyiségben van.                          |
| `since`                  | `event_timestamp`                         | Mikor érte el ezt az állapotot.                  |

Mivel a „to" oldal **egyszerre** hordozhat felhasználót és helyet is, egy személyhez kiadott eszköz továbbra is jelenthet helyet: amikor egy orvos ultrahangot visz a Kardiológiára, az esemény `to_user_id = orvos`, `to_locations_id = Budapest`, `to_departments_id = Kardiológia` — így a nézet azt mutatja, hogy az orvosnál van *és* a Kardiológián található.

A napló által támogatott mozgásformák:

| Folyamat                  | from_*                | to_*                                  | event_type           | actor                          |
|---------------------------|-----------------------|---------------------------------------|----------------------|--------------------------------|
| Raktár → felhasználó      | `from_departments_id` | `to_user_id` (+ telephely + helyiség) | `check_out`          | felhasználó / raktáros         |
| Felhasználó → raktár      | `from_user_id`        | `to_departments_id` (+ telephely)     | `check_in`           | felhasználó / raktáros         |
| Felhasználó → felhasználó | `from_user_id`        | `to_user_id` (+ telephely + helyiség) | `transfer`           | felhasználó / raktáros         |
| Raktár → raktár           | `from_departments_id` | `to_departments_id` (+ telephely)     | `stock_transfer`     | **raktáros (mindig naplózva)** |
| Szervizbe                 | aktuális hely         | műhely (`to_departments_id`)          | `send_to_repair`     | raktáros                       |
| Szervizből vissza         | műhely                | `to_departments_id` (+ telephely)     | `return_from_repair` | raktáros                       |
| Elveszettnek jelölés      | aktuális hely         | utolsó ismert hely (változatlanul)    | `mark_lost`          | raktáros                       |
| Megtalálva                | (üres — nincs forrás) | `to_departments_id` (+ telephely)     | `mark_found`         | raktáros                       |

---

## 8. Integritás-összefoglaló

- Minden eszköztípusnak egyedi neve van; minden típusspecifikus mező egyedi típusonként (`device_type_id` + `attribute_key`).
- Minden eszköz legfeljebb egyszer válaszol egy mezőre (`device_id` + `attribute_definition_id` UNIQUE).
- Minden birtoklási esemény megnevez egy célt (CHECK) és mindig rögzít egy aktort.
- A `from_*` / `to_*` mind valódi idegen kulcs (nincs polimorf oszlop), így az adatbázis validálja őket.
- A `device_current_state` csak a `confirmed` eseményeket olvassa; a `pending`/`rejected` check_in nem érvényesül (checkpoint, §9).
- Eszközönként legfeljebb egy nyitott (pending) visszavétel (`uq_one_pending_checkin_per_device`) és legfeljebb egy aktív foglalás (`uq_resv_one_per_device`).
- A selejtezés lágy törlés (`retired_date` + `status = 'Selejtezve'`); a sorok és az előzmény megmaradnak.

---

## 9. Checkpoint — raktáros-megerősítés a visszavételeknél

Cél: a fizikai valóság és a nyilvántartás összhangban tartása. Amikor egy felhasználó visszavesz egy eszközt a raktárba, ez nem jelenti automatikusan, hogy az eszköz tényleg ott van. A felhasználói `check_in` ezért kétfázisú.

**1. fázis — a felhasználó leadja a visszavételt (`pending`).**
A felhasználói `check_in` esemény `confirmation_status = 'pending'` értékkel keletkezik. Mivel a `device_current_state` nézet csak a `confirmed` sorokat olvassa, ez az esemény **még nem** mozdítja el az eszközt: a nézet továbbra is a felhasználónál mutatja. A `devices.status` `'Visszavétel folyamatban'`-ra vált.

**2. fázis — a raktáros ellenőriz, majd dönt.**

- **Megerősítés** (`confirmed`): az eszköz tényleg ott van. A `confirmation_status` `confirmed`, a `confirmed_by`/`confirmed_at` kitöltődik. Ettől a nézet a raktárban / célhelyen mutatja, a `devices.status` pedig `'Kivehető'`.
- **Elutasítás** (`rejected`): az eszköz nincs ott. A `confirmation_status` `rejected`, a `confirmed_by`/`confirmed_at` kitöltődik, az ok a `notes`-hoz fűzve („ELUTASÍTVA: …"). Az esemény sosem érvényesül, így az eszköz a check_in **előtti** birtoklásnál marad (a felhasználónál); a `devices.status` visszaáll `'Kiadva'`-ra.

Hatókör: **csak a felhasználói `check_in`** megy át ezen. A raktáros/it_admin saját mozgatásai (és minden más eseménytípus) azonnal `confirmed` — ezt az alapérték és a `chk_pending_only_checkin` CHECK biztosítja.

**Egy nyitott ellenőrzés eszközönként:** a `uq_one_pending_checkin_per_device` részleges UNIQUE index DB-szinten kizárja, hogy egy eszközre egyszerre két, megerősítésre váró visszavétel keletkezzen.

A raktáros munkalistáját a `device_pending_checkins` nézet adja:

```sql
CREATE VIEW device_pending_checkins AS
SELECT event_id,
       device_id,
       actor_user_id   AS submitted_by,
       from_user_id    AS returning_user,
       to_locations_id   AS claimed_location_id,
       to_departments_id AS claimed_department_id,
       event_timestamp AS submitted_at,
       condition_at_event,
       notes
FROM device_custody_events
WHERE confirmation_status = 'pending'
ORDER BY event_timestamp;
```

---

## 10. Felhasználói foglalás (reservation)

A felhasználók maguk foglalhatnak le egy szabad eszközt, mielőtt elvinnék. A foglalás a fizikai elvitelig (`check_out`) tartja fenn az eszközt a foglalónak.

### 10.1 `device_active_reservations` nézet

A még le nem járt foglalásokat adja — akkor is helyesen, ha a lejárt sorok takarítása még nem futott:

```sql
CREATE VIEW device_active_reservations AS
SELECT reservation_id, device_id, reserved_by, reserved_at, expires_at, notes
FROM device_reservations
WHERE expires_at > now();
```

### 10.2 Életciklus

1. **Foglalás** — a felhasználó lefoglal egy szabad eszközt (`status = 'Kivehető'`, nincs birtokosa, nincs aktív foglalása). Egy sor jön létre, `expires_at = now() + 3 nap`; `devices.status = 'Lefoglalva'`.
2. **Exkluzivitás** — amíg a sor él, más nem foglalhatja le (UNIQUE), és nem is veheti ki — lefoglalt eszközt **csak a foglaló vagy a raktáros/it_admin** vihet el.
3. **Elvitel** — a foglaló (vagy a raktáros) `check_out`-ja teljesíti a foglalást: a sor **törlődik**, `devices.status = 'Kiadva'`.
4. **Lemondás** — a foglaló vagy a raktáros lemondhatja: a sor **törlődik**, `devices.status = 'Kivehető'`.
5. **Lejárat (3 nap)** — ütemezett takarító job: `DELETE FROM device_reservations WHERE expires_at <= now()`; `devices.status = 'Kivehető'`.

---

## 11. Szerviz, elveszett és megtalált életciklus

A három „manuális" állapotot (`Szerviz alatt`, `Elveszett`, `Selejtezve`) a raktáros állítja be, és ezeket a custody-naplóból nem lehet levezetni — ezért a tárolt `devices.status` adja őket. A megfelelő események:

| Művelet                   | event_type           | from_*         | to_*                                       | Eredő státusz   |
|---------------------------|----------------------|----------------|--------------------------------------------|-----------------|
| Szervizbe küldés          | `send_to_repair`     | aktuális hely  | műhely-helyiség (alapért. `type='műhely'`) | `Szerviz alatt` |
| Szervizből visszahelyezés | `return_from_repair` | műhely         | választott telephely + helyiség            | `Kivehető`      |
| Elveszettnek jelölés      | `mark_lost`          | aktuális hely  | az utolsó ismert hely (változatlanul)      | `Elveszett`     |
| Megtalálva                | `mark_found`         | (nincs forrás) | választott telephely + helyiség            | `Kivehető`      |

- **Szervizbe küldés** csak `Szerviz alatt`-on kívüli eszközre értelmes. Ha a hívó nem ad meg cél-helyiséget, a rendszer a `type = 'műhely'` helyiséget választja.
- **Szervizből visszahelyezés** kizárólag `Szerviz alatt` státuszú eszközre engedélyezett; ezt a kezelő ellenőrzi.
- **Elveszettnek jelölés** megőrzi az utolsó ismert birtokost/helyet a cél oldalon, így a napló nyomon követhető marad; az állapot `Elveszett`.
- **Megtalálva** egy elveszett eszközt egy konkrét helyre vezet vissza (forrás nélkül, mert nem ismert, honnan került elő); az állapot `Kivehető`.

A kezelőlogikát (`send_to_repair` / `return_from_repair` / `mark_lost` / `mark_found`) a `SCRIPT_LOGIC_DOCUMENTATION.md` §11 írja le.
