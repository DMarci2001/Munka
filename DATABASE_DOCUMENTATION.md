# Hordozható Eszköznyilvántartás — Adatbázis-dokumentáció

**Rendszer:** vagyonnyilvántartás a klinika hordozható eszközeihez (központ + telephelyek).
**Adatbázis:** MySQL. **Kapcsolódó fájlok:** `device-inventory-schema.sql` (DDL), `device-inventory-schema.dbml` (DrawSQL/dbdiagram import), `device-inventory-schema.svg` (ER-diagram), `APPLICATION_LOGIC.md` (kezelő/szkript dokumentáció).

---

## 1. Cél és hatókör

Az adatbázis minden hordozható eszközt nyilvántart (router, laptop, egér, billentyűzet, nyomtató, ultrahang, EKG, vérnyomásmérő, USB-meghajtó és bármely jövőbeli típus), valamint azt, hogy az egyes eszközök hol vannak és kinél. Minden eszköz egyedi azonosítót hordoz, QR-kódba kódolva. Az eszközök közös mezőkészleten osztoznak, miközben minden *típus* saját további mezőkkel rendelkezik („alattribútumok"), amelyek nem oszlopként, hanem adatként vannak modellezve. Az eszközök személyek és raktárak közötti mozgása csak hozzáfűzhető (append-only) eseménynaplóként van rögzítve.

A rendszert három szerepkör használja — rendes felhasználók, egy központi raktáros, és az IT-csapat —, ezeket az `APPLICATION_LOGIC.md` írja le.

---

## 2. Konvenciók

Az elsődleges kulcsok az adatbázis által generált UUID-k (`gen_random_uuid()`), `uuid` típusként megjelenítve.

Az időbélyegek `timestamptz` típusúak (UTC, időzóna-érzékeny). Az idő nélküli dátumok `date` típusúak.

A táblák vagy **ehhez az adatbázishoz tartoznak**, vagy **külsők** (a meglévő klinikai webalkalmazáshoz tartoznak). A külső táblákra idegen kulccsal hivatkozunk, de ez a rendszer soha nem hozza létre és nem írja őket. Ezek: `locations`, `clinic_users`.

Egy eszköz *típusspecifikus* attribútumait az `attribute_definitions` + `device_attribute_values` metaadat-pár tárolja (lásd §6). Egy eszköz *aktuális birtokosa és helye* nem az eszköz során van tárolva; ezeket a `device_custody_events` legújabb sorából vezetjük le, és a `device_current_state` nézet teszi elérhetővé (lásd §5 és §7).

---

## 3. Integráció a klinikai webalkalmazással

A külső táblák és az oszlopok, amelyekre ez a rendszer támaszkodik:

| Tábla               | Használt kulcsoszlopok                          | Jelentés                                                                          
|---------------------|-------------------------------------------------|    
| `locations`             | `id`, `address`      | Egy céghez tartozó fizikai klinikai telephelyek.                                   |
| `clinic_users`      | `id`, `username`, `auth`           | Minden rendszerfelhasználó (adminisztrátorok, recepciósok, orvosok, raktáros, IT). |             |

Ha a nyilvántartási táblák **ugyanabban** az adatbázisban élnek, mint a webalkalmazás, az alábbi idegen kulcsok közvetlenül feloldódnak ezekre a táblákra. Ha **külön** adatbázisban élnek, tárold az azonosítókat FK-megszorítások nélkül, és az alkalmazásrétegben ellenőrizd a hivatkozásokat.

---

## 4. Felsorolt típusok (enum)

```
device_status       : 'Ready to deploy' | 'Deployed' | 'Reserved' | 'Retired' | 'Lost' | 'In repair' | 'Pending return'
custody_event_type  : 'check_out' | 'check_in' | 'transfer' | 'stock_transfer' | 'send_to_repair' | 'mark_lost'
confirmation_status : 'pending' | 'confirmed' | 'rejected'
attr_data_type      : 'text' | 'integer' | 'decimal' | 'date' | 'boolean' | 'enum'
auth                : 'user' | 'storekeeper' | 'it_admin'
```

A `'Pending return'` állapot a checkpoint-folyamathoz tartozik (lásd §5.6 és §9): az eszköz akkor van benne, amikor egy felhasználó leadta a visszavételt, de a raktáros még nem erősítette meg fizikailag.

---

## 5. Táblák (saját)

**„Kötelező" oszlop:** Igen = értéket kell tartalmaznia (NOT NULL); Nem = lehet üres (nullable); Feltételes = szabálytól függ (lásd a megjegyzést).

**„Kitölti" oszlop — ki adja az értéket (szerepkör szerint)** A szerepkörök bővülő jogosultságúak: `it_admin` ⊇ `storekeeper` (Raktáros) ⊇ `user` (Felhasználó) — vagyis a feljebb állók megtehetik az alattuk lévők műveleteit is.

- **Rendszer** — automatikusan generált/levezetett: azonosítók (PK), időbélyegek, naplóoszlopok, a `from_*` oldal, a bejelentkezett aktor.
- **IT-admin** — csak az IT-csapat (`it_admin`): a séma/katalógus (eszköztípusok, attribútum-definíciók) és a szerepkörök.
- **Raktáros** — a raktáros (`storekeeper`): eszközök regisztrálása és szerkesztése, helyek kezelése. (`it_admin` is megteheti.)
- **Felhasználó** — bármely rendes felhasználó (`user`): **kizárólag birtoklási eseményeket** rögzít (eszköz mozgatása), a jogosultsága keretein belül. (Raktáros/`it_admin` mások nevében is.)
- **Webalk.** — a webalkalmazás tölti ki (külső tábla, itt nem visszük be).

> **Fontos:** az eszköz „kemény" adatait (gyári szám, modell, típus, attribútumok, vizsgálattípusok) az eszközpéldányt **létrehozó Raktáros/IT-admin** viszi be regisztrációkor (`register_device`, lásd `APPLICATION_LOGIC.md` §4.1) — **nem** a `user` szerepkör. A `user` szerepkör csak a `device_custody_events` táblát írja.

Rövidítések a megjegyzésekben: **PK** elsődleges kulcs, **FK** idegen kulcs, **UQ** egyedi, **alapért.** kihagyáskor alkalmazott alapérték.

### 5.1 `departments` — szobák, raktárak, osztályok

Fizikai vagy szervezeti hely egy telephelyen belül. Kiváltja a külön osztálytáblát: egy helyiség egyszerűen egy `location`, amelynek `type = 'department'`.

| Oszlop       | Típus   | Kötelező | Kitölti             | Megjegyzés                                               |
|--------------|---------|----------|---------------------|----------------------------------------------------------|
| `id`         | uuid    | Igen     | Rendszer            | **PK**. Generált.                                        |
|`locations_id`| uuid    | Igen     | Raktáros / IT-admin | **FK** → `locations.id`.                                     |
| `name`       | text    | Igen     | Raktáros / IT-admin | pl. „Központi raktár", „Recepció", „Kardiológia".        |
| `type`       | text    | Igen     | Raktáros / IT-admin | CHECK in (`stockroom`, `room`, `department`, `location`, `reception`).            |

### 5.2 `device_types` — eszköztípusok katalógusa

| Oszlop        | Típus | Kötelező | Kitölti  | Megjegyzés                         |
|---------------|-------|----------|----------|------------------------------------|
| `id`          | uuid  | Igen     | Rendszer | **PK**. Generált.                  |
| `name`        | text  | Igen     | IT-admin | **UQ**. pl. „Ultrahang", „Router". |
| `description` | text  | Nem      | IT-admin | Opcionális.                        |

### 5.3 `attribute_definitions` — a típusonkénti mezősablonok

Egy sor egy adott eszköztípus egy típusspecifikus mezőjét definiálja. Ez *konfigurációs adat*, amelyet az IT hoz létre a típus beállításakor, és minden ilyen típusú eszközhöz újrahasznosul. Lásd §6.

| Oszlop           | Típus          | Kötelező | Kitölti  | Megjegyzés                                                                             |
|------------------|----------------|----------|----------|----------------------------------------------------------------------------------------|
| `id`             | uuid           | Igen     | Rendszer | **PK**. Hivatkozza `device_attribute_values.attribute_definition_id`.                  |
| `device_type_id` | uuid           | Igen     | IT-admin | **FK** → `device_types.id`. Melyik típushoz tartozik a mező.                           |
| `attribute_key`  | text           | Igen     | IT-admin | Eszközökhöz tartozó egyedi attribútumok nevei, pl. `calibration_due_date`. Típuson belül egyedi.                            |
| `label`          | text           | Nem      | IT-admin | Az alkalmazásban megjelenő értelmezési segédszöveg, emberi olvasásra szánt.                                    |
| `data_type`      | attr_data_type | Igen     | IT-admin | A beviteli mezőt és az ellenőrzést vezérli. Alapért. `'text'`.                         |
| `is_required`    | boolean        | Igen     | IT-admin | Kötelező-e a mező eszköz regisztrálásakor. Alapért. `false`.                           |
| `options`        | text           | Nem      | IT-admin | `enum` mezők megengedett értékei (pl. `Paediatric \| Adult \| Large`); egyébként null. |
| `sort_order`     | integer        | Nem      | IT-admin | A mező sorrendje az űrlapon.                                                           |

Megszorítás: **UNIQUE (`device_type_id`, `attribute_key`)** — egy típus nem definiálhatja kétszer ugyanazt a mezőt.

### 5.4 `devices` — az eszköznyilvántartás (csak közös attribútumok)

A minden eszközre közös tényeket tartalmazza. Szándékosan **nem** tárolja az aktuális birtokost vagy helyet — azok a birtoklási naplóból származnak (§7). Az itteni adatokat az eszközpéldányt regisztráló **Raktáros/IT-admin** viszi be (`register_device` / `edit_device`).

| Oszlop            | Típus         | Kötelező | Kitölti             | Megjegyzés                                                                                                                                           |
|-------------------|---------------|----------|---------------------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| `id`              | uuid          | Igen     | Rendszer            | **PK** → Megváltoztathatatlan belső azonosító.                                                                                                         
| `device_type_id`  | uuid          | Igen     | Raktáros / IT-admin | **FK** → `device_types.id`. A fő attribútum; vezérli a típusspecifikus mezőket.                                                                      |
| `manufacturer`    | text          | Nem      | Raktáros / IT-admin | pl. GE, HP, Cisco.                                                                                                                                   |
| `model`           | text          | Nem      | Raktáros / IT-admin | Modellnév/-szám.                                                                                                                                     |
| `serial_number`   | text          | Nem      | Raktáros / IT-admin | Gyári sorozatszám.                                                                                                                      
| `status`          | device_status | Igen     | Rendszer / Raktáros | Életciklus-állapot (§4). A rendszer állítja az alapértéket és a birtoklásból karbantartja; a regisztráló felülírhatja. Alapért. `'Ready to deploy'`. |
| `expiry_date`     | date          | Nem      | Raktáros / IT-admin Garancia-emlékeztető.                                                                                                            
| `condition`       | text          | Nem      | Raktáros / IT-admin | állapotjelző, pl. új / jó / megfelelő / gyenge.                                                                                                                    |
| `photo`           | text          | Nem      | Raktáros / IT-admin | Fénykép URL-je vagy útvonala.                                                                                                                        |
| `notes`           | text          | Nem      | Raktáros / IT-admin | Szabad szöveg (a selejtezés okára is használt).                                                                                                      |
| `created_at`      | timestamptz   | Igen     | Rendszer            | Napló. Alapért. `now()`.                                                                                                                             |
| `updated_at`      | timestamptz   | Igen     | Rendszer            | Napló. Alapért. `now()`.                                                                                                                             |
| `created_by`      | uuid          | Nem      | Rendszer            | **FK** → `clinic_users.id`. A bejelentkezett regisztráló, automatikusan.                                                                             |
| `updated_by`      | uuid          | Nem      | Rendszer            | **FK** → `clinic_users.id`. A bejelentkezett szerkesztő, automatikusan.                                                                              |
| `retired_date`    | date          | Nem      | Raktáros / IT-admin | Az eszköz selejtezésekor beállítva (lágy törlés; az előzmény megmarad).                                                                              |

Indexek: `idx_devices_type` a `device_type_id`-n, `idx_devices_site` a `site_id`-n.

### 5.5 `device_attribute_values` — egy eszköz típusspecifikus értékei

Egy sor egy eszköz egy kitöltött típusspecifikus mezőjéhez. A tényleges érték `text`-ként van tárolva; valódi típusát a hozzá tartozó definíció `data_type`-ja rögzíti. A két FK-t a rendszer állítja a kontextusból; a regisztráló csak a `value`-t írja.

| Oszlop                    | Típus | Kötelező   | Kitölti             | Megjegyzés                                                                             |
|---------------------------|-------|------------|---------------------|----------------------------------------------------------------------------------------|
| `id`                      | uuid  | Igen       | Rendszer            | **PK**. Generált.                                                                      |
| `device_id`               | uuid  | Igen       | Rendszer            | **FK** → `devices.id` (ON DELETE CASCADE). A mentett eszköz, kontextusból.    |
| `attribute_definition_id` | uuid  | Igen       | Rendszer            | **FK** → `attribute_definitions.id`. Melyik mezőre válaszol; az űrlap már tudja.       |
| `value`                   | text  | Feltételes | Raktáros / IT-admin | A bevitt érték szövegként. Kötelezősége mezőnként az `is_required` szerint érvényesül. |

Megszorítás: **UNIQUE (`device_id`, `attribute_definition_id`)** — eszközönként és mezőnként egy érték.

### 5.6 `device_custody_events` — a birtoklási napló (csak hozzáfűzhető)

Az egyetlen igazságforrás arra, hogy hol van és kinél van az egyes eszközök. Egy sor egy mozgáshoz: felvétel, visszavétel, átadás, raktár-raktár mozgatás, javításra küldés, elveszettnek jelölés. A sorok soha nem módosulnak és nem törlődnek; az aktuális állapot az eszközönkénti legújabb sor. **Ezt a táblát írja a `user` szerepkör is** — a felhasználó adja a mozgás típusát és célját, a `from_*` oldalt és az aktort a rendszer tölti ki.

| Oszlop                 | Típus              | Kötelező   | Kitölti                | Megjegyzés                                                                                                                           |
|------------------------|--------------------|------------|------------------------|--------------------------------------------------------------------------------------------------------------------------------------|
| `event_id`             | uuid               | Igen       | Rendszer               | **PK**. Generált.                                                                                                                    |
| `device_id`            | uuid               | Igen       | Felhasználó / Raktáros | **FK** → `devices.id`. Melyik eszköz.                                                                                       |
| `event_type`           | custody_event_type | Igen       | Felhasználó / Raktáros | Lásd §4 (felvétel / visszavétel / átadás…).                                                                                          |
| `actor_user_id`        | uuid               | Igen       | Rendszer               | **FK** → `clinic_users.id`. Ki végezte/naplózta a mozgást (a bejelentkezett aktor). **Mindig kitöltve**, a raktár→raktár esetben is. |
| `from_user_id`         | uuid               | Nem        | Rendszer               | **FK** → `clinic_users.id`. Korábbi birtokos, ha személy. Levezetett.                                                                |
| `from_departments_id`  | uuid               | Nem        | Rendszer               | **FK** → `locations.id`. Forrás szoba/raktár, ha értelmezhető. Levezetett.                                                           |
| `to_user_id`           | uuid               | Feltételes | Felhasználó / Raktáros | **FK** → `clinic_users.id`. Új birtokos, ha személy.                                                                                 |
| `to_departments_id`    | uuid               | Feltételes | Felhasználó / Raktáros | **FK** → `locations.id`. Cél szoba/raktár — felhasználói cél esetén is beállítva (lásd §7).                                          |
| `event_timestamp`      | timestamptz        | Igen       | Rendszer               | Mikor történt. Alapért. `now()`.                                                                                                     |
| `expected_return_date` | date               | Nem        | Felhasználó / Raktáros | Opcionális határidő → lejárt tételek riportja.                                                                                       |
| `condition_at_event`   | text               | Nem        | Felhasználó / Raktáros | Átadáskor megállapított állapot.                                                                                                     |
| `notes`                | text               | Nem        | Felhasználó / Raktáros | Szabad szöveg.                                                                                                                       |
| `confirmation_status`  | confirmation_status| Igen       | Rendszer / Raktáros    | `pending` \| `confirmed` \| `rejected`. **Alapért. `confirmed`.** Csak a felhasználói `check_in` indul `pending`-ként; a raktáros állítja `confirmed`/`rejected`-re. Lásd §9. |
| `confirmed_by`         | uuid               | Feltételes | Rendszer / Raktáros    | **FK** → `clinic_users.id`. Aki megerősítette/elutasította (a bejelentkezett raktáros). `pending` alatt NULL; automatikus `confirmed` esetén NULL; ellenőrzés utáni `confirmed`/`rejected` esetén kötelező. |
| `confirmed_at`         | timestamptz        | Feltételes | Rendszer               | A megerősítés/elutasítás időbélyege. A `confirmed_by`-jal együtt mozog (mindkettő NULL vagy mindkettő kitöltött).                     |

Megszorítások:

- **`chk_to_holder` CHECK (`to_user_id IS NOT NULL OR to_departments_id IS NOT NULL`)** — minden eseménynek van célja (felhasználó, hely, vagy mindkettő). Ez teszi a `to_user_id`/`to_departments_id` mezőket **feltételesen** kötelezővé: külön-külön nullázhatók, de legalább az egyiknek lennie kell.
- **`chk_confirmation_status` CHECK** — a `confirmation_status` csak `pending` / `confirmed` / `rejected` lehet.
- **`chk_confirmation_resolution` CHECK** — koherencia: `pending` → `confirmed_by`/`confirmed_at` NULL; `rejected` → mindkettő kötelező; `confirmed` → vagy mindkettő NULL (automatikus esemény), vagy mindkettő kitöltött (raktáros ellenőrzése után). A `confirmed_by` és `confirmed_at` mindig együtt mozog.
- **`chk_pending_only_checkin` CHECK** — csak `event_type = 'check_in'` lehet `pending`/`rejected`; minden más eseménytípus mindig `confirmed`.

Indexek:

- `idx_custody_device` a (`device_id`, `event_timestamp DESC`) oszlopokon — gyors „legújabb esemény ehhez az eszközhöz".
- `idx_custody_confirmed` részleges index (`device_id`, `event_timestamp DESC`) WHERE `confirmation_status = 'confirmed'` — a `device_current_state` nézet gyorsítása.
- `idx_custody_pending` részleges index (`event_timestamp`) WHERE `confirmation_status = 'pending'` — a raktáros ellenőrzési listája.
- **`uq_one_pending_checkin_per_device` részleges UNIQUE index (`device_id`) WHERE `confirmation_status = 'pending'`** — eszközönként legfeljebb egy nyitott (pending) visszavétel lehet egyszerre. DB-szintű garancia a §9 checkpoint-folyamathoz.

### 5.7 `device_reservations` - aktív foglalások

A foglalás **nem** birtoklási mozgás (az eszköz nem mozdul), és lejár — ezért külön táblában él, nem a `device_custody_events`-ben. A sor a foglalás megszűnésekor (elvitel / lemondás / lejárat) **törlődik**, így a tábla mindig csak az **éppen aktív** foglalásokat tartalmazza.

| Oszlop           | Típus       | Kötelező | Kitölti             | Megjegyzés                                                                                       |
|------------------|-------------|----------|---------------------|--------------------------------------------------------------------------------------------------|
| `id` | uuid        | Igen     | Rendszer            | **PK**. Generált.                                                                                |
| `device_id`      | uuid        | Igen     | Felhasználó         | **FK** → `devices.id`. **UQ** — eszközönként legfeljebb egy aktív foglalás (exkluzivitás).       |
| `reserved_by`    | uuid        | Igen     | Rendszer            | **FK** → `clinic_users.id`. A foglaló (a bejelentkezett aktor).                                  |
| `reserved_at`    | timestamptz | Igen     | Rendszer            | A foglalás időpontja. Alapért. `now()`.                                                          |
| `expires_at`     | timestamptz | Igen     | Rendszer            | Lejárat. Alapért. `reserved_at + 3 nap`. Lejárat után a sor törlendő (takarító job).             |
| `notes`          | text        | Nem      | Felhasználó         | Szabad szöveg.                                                                                   |

Megszorítások:

- **`uq_resv_one_per_device` UNIQUE (`device_id`)** — exkluzivitás: amíg van sor egy eszközre, más nem foglalhatja le (a beszúrás UNIQUE-sértést dob).
- **`chk_resv_expiry` CHECK (`expires_at > reserved_at`)**.

Indexek: `idx_resv_expires` (`expires_at`) a takarításhoz; `idx_resv_user` (`reserved_by`).

---

## 6. A metaadat-attribútumrendszer

A típusspecifikus mezők adatként, nem oszlopként vannak tárolva, így új eszköztípusok és mezők nem igényelnek sémamódosítást.

- Az `attribute_definitions` típusonként (`device_type_id`) deklarálja, hogy az adott típusnak mely mezői vannak (kulcs, címke, adattípus, kötelezőség, lehetőségek, sorrend). Egyszer, a típus beállításakor jön létre.
- A `device_attribute_values` tárolja az egyes eszközök tényleges válaszait, minden sor az `attribute_definition_id`-n keresztül mutat a kitöltött definícióra.

Egy eszköztípus űrlapjának megjelenítéséhez az alkalmazás beolvassa az `attribute_definitions WHERE device_type_id = <típus>` sorokat. Egy eszköz teljes rekordjának olvasásához összekapcsolja: `devices` → `device_attribute_values` → `attribute_definitions`. Új típus hozzáadása (pl. defibrillátor) tiszta adatbevitel a `device_types` + `attribute_definitions` táblákba.

---

## 7. A birtoklási modell és a `device_current_state` nézet

A birtokos és a hely soha nincs a `devices` során tárolva; ezek a legújabb birtoklási esemény célját jelentik. A nézet úgy teszi elérhetővé őket, mintha oszlopok lennének:

```sql
CREATE VIEW device_current_state AS
SELECT DISTINCT ON (device_id)
       device_id,
       to_user_id      AS current_holder_user_id,
       to_departments_id  AS current_location_id,
       event_timestamp AS since
FROM device_custody_events
WHERE confirmation_status = 'confirmed'   -- checkpoint: csak megerősített esemény számít
ORDER BY device_id, event_timestamp DESC;
```

> **Checkpoint:** a `WHERE confirmation_status = 'confirmed'` szűrő miatt egy függőben lévő (`pending`) felhasználói visszavétel nem változtatja meg az aktuális állapotot — a nézet a check_in *előtti* birtokost/helyet mutatja, amíg a raktáros meg nem erősíti. Az elutasított (`rejected`) esemény sosem érvényesül. Lásd §9.

| Nézet oszlop             | Forrás                                 | Jelentés                                             |
|--------------------------|----------------------------------------|------------------------------------------------------|
| `device_id`              | esemény                                | Az eszköz.                                           |
| `current_holder_user_id` | a legújabb esemény `to_user_id`-ja     | Kinél van most (null, ha helyen ül, személy nélkül). |
| `current_location_id`    | a legújabb esemény `to_departments_id`-ja | Hol van most fizikailag.                             |
| `since`                  | `event_timestamp`                      | Mikor érte el ezt az állapotot.                      |

Mivel a „to" oldal **egyszerre** hordozhat felhasználót és helyet is, egy személyhez kiadott eszköz továbbra is jelenthet helyet: amikor egy orvos ultrahangot visz a Kardiológiára, az esemény `to_user_id = orvos` és `to_departments_id = Kardiológia` értéket rögzít, így a nézet azt mutatja, hogy az orvosnál van *és* a Kardiológián található. A napló által támogatott négy mozgásforma:

| Folyamat                  | from_*             | to_*                                         | event_type      actor                          |
|---------------------------|--------------------|----------------------------------------------|------------------|--------------------------------|
| Raktár → felhasználó      | `from_departments_id` | `to_user_id` (+ opc. `to_departments_id` = cél) | `check_out`      | felhasználó/raktáros  |
| Felhasználó → raktár      | `from_user_id`     | `to_departments_id`                             | `check_in`       | felhasználó/raktáros  |
| Felhasználó → felhasználó | `from_user_id`     | `to_user_id` (+ opc. cél)                    | `transfer`       | felhasználó/raktáros|
| Raktár → raktár           | `from_departments_id` | `to_departments_id`                             | `stock_transfer` | **raktáros (mindig naplózva)** |

A `send_to_repair` és a `mark_lost` állapotbefolyásoló események; a `send_to_repair` jellemzően egy javító/tároló helyre állítja a `to_departments_id`-t, a `mark_lost` az utolsó ismert birtokost/helyet hagyhatja célként, `notes` megjegyzéssel.

---

## 8. Integritás-összefoglaló

- Minden eszköztípusnak egyedi `device_type_id`-ja van.
- Minden típusspecifikus mező egyedi típusonként (`device_type_id` + `attribute_key`); minden eszköz legfeljebb egyszer válaszol egy mezőre.
- Minden eszköz egy adott vizsgálattípust legfeljebb egyszer szolgál ki.
- Minden birtoklási esemény megnevez egy célt (CHECK) és mindig rögzít egy aktort.
- A selejtezés lágy törlés (`retired_date`/`status = Retired`); a sorok és az előzmény megmaradnak.
- A `from_user_id`/`from_departments_id`/`to_user_id`/`to_departments_id` mind valódi idegen kulcs (nincs polimorf oszlop), így az adatbázis validálja őket.
- A `device_current_state` csak a `confirmed` eseményeket olvassa; a `pending`/`rejected` check_in nem érvényesül (checkpoint, §9). A `confirmation_status` koherenciáját és „csak check_in lehet pending" szabályát CHECK-ek kényszerítik ki.
- Eszközönként legfeljebb egy nyitott (pending) visszavétel (`uq_one_pending_checkin_per_device`) és legfeljebb egy aktív foglalás (`uq_resv_one_per_device`) lehet — mindkettő DB-szintű UNIQUE garancia (§9, §10).

---

## 9. Checkpoint — raktáros-megerősítés a visszavételeknél

Cél: a fizikai valóság és a nyilvántartás összhangban tartása. Amikor egy felhasználó visszavesz egy eszközt a raktárba, ez nem jelenti automatikusan, hogy az eszköz tényleg ott van. A check_in ezért egy kétlépéses (kétfázisú) folyamat.

**1. fázis — a felhasználó leadja a visszavételt (`pending`).**
A felhasználói `check_in` esemény `confirmation_status = 'pending'` értékkel keletkezik. Mivel a `device_current_state` nézet csak a `confirmed` sorokat olvassa, ez az esemény **még nem** mozdítja el az eszközt: a nézet továbbra is a felhasználónál mutatja. A `devices.status` `'Pending return'`-re vált, jelezve, hogy ellenőrzésre vár.

**2. fázis — a raktáros ellenőriz, majd dönt.**
A raktáros a `device_pending_checkins` nézetből látja az elvégzendő ellenőrzéseket, és fizikailag megnézi, ott van-e az eszköz:

- **Megerősítés** (`confirmed`): az eszköz tényleg ott van. A `confirmation_status` `confirmed`, a `confirmed_by`/`confirmed_at` kitöltődik. Ettől a pillanattól a nézet a raktárban / célhelyen mutatja az eszközt, a `devices.status` pedig `'Ready to deploy'`.
- **Elutasítás** (`rejected`): az eszköz nincs ott. A `confirmation_status` `rejected`, a `confirmed_by`/`confirmed_at` kitöltődik. Az esemény sosem érvényesül, így az eszköz a check_in **előtti** birtoklásnál marad (jellemzően a felhasználónál); a `devices.status` visszaáll `'Deployed'`-ra. A diszkrepancia a `notes`-ban rögzíthető.

Hatókör: **csak a felhasználói `check_in`** megy át ezen a folyamaton. A raktáros/it_admin saját mozgatásai (és minden más eseménytípus) azonnal `confirmed` — ezt az alapérték és a `chk_pending_only_checkin` CHECK biztosítja. A pontos kezelőlogikát (`confirm_check_in` / `reject_check_in`) az `APPLICATION_LOGIC.md` §9 írja le.

**Egy nyitott ellenőrzés eszközönként:** a `uq_one_pending_checkin_per_device` részleges UNIQUE index (`device_id` WHERE `confirmation_status = 'pending'`) DB-szinten kizárja, hogy egy eszközre egyszerre két, megerősítésre váró visszavétel keletkezzen. Egy függő visszavételt előbb le kell zárni (megerősítés vagy elutasítás), mielőtt új keletkezhet ugyanarra az eszközre.

```sql
CREATE OR REPLACE VIEW device_current_state AS
SELECT DISTINCT ON (device_id)
       device_id,
       to_user_id      AS current_holder_user_id,
       to_location_id  AS current_location_id,
       event_timestamp AS since
FROM device_custody_events
WHERE confirmation_status = 'confirmed'
ORDER BY device_id, event_timestamp DESC;
 
-- Megjegyzés a teljesítményhez: a DISTINCT ON (device_id) ... WHERE
-- confirmation_status = 'confirmed' ... ORDER BY device_id,
-- event_timestamp DESC a részleges idx_custody_confirmed indexet
-- használja (device_id, event_timestamp DESC) WHERE confirmed —
-- gyors „legújabb megerősített esemény eszközönként".
 
 
-- ------------------------------------------------------------
-- Nézet — device_pending_checkins (a raktáros ellenőrzési listája)
--
-- Minden függőben lévő (pending) felhasználói visszavétel, amelyet a
-- raktárosnak fizikailag ellenőriznie és megerősítenie/elutasítania
-- kell. A „cél" oldal (to_location_id) az a hely, ahova a felhasználó
-- állítása szerint visszavitte az eszközt.
-- ------------------------------------------------------------
 
CREATE OR REPLACE VIEW device_pending_checkins AS
SELECT event_id,
       device_id,
       actor_user_id   AS submitted_by,   -- ki adta le a visszavételt
       from_user_id    AS returning_user, -- kinél volt (visszavétel előtt)
       to_location_id  AS claimed_location_id,
       event_timestamp AS submitted_at,
       condition_at_event,
       notes
FROM device_custody_events
WHERE confirmation_status = 'pending'
ORDER BY event_timestamp;
 
 
-- ------------------------------------------------------------
-- OPCIONÁLIS — feloldó nézet (kényelmi réteg)
--
-- Ha a megjelenített helynevet az adatbázisban szeretné feloldani
-- (a §10 location_label SQL-megfelelője), használja az alábbi
-- kibővített nézetet. A device_current_state változatlan marad;
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
-- FROM device_current_state s
-- LEFT JOIN locations l ON l.id = s.current_location_id
-- LEFT JOIN sites     si ON si.id = l.site_id;
-- ------------------------------------------------------------
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

1. **Foglalás** — a felhasználó lefoglal egy szabad eszközt (raktárban, `Ready to deploy`, nincs aktív foglalása). Egy sor jön létre, `expires_at = now() + 3 nap`; `devices.status = 'Reserved'`.
2. **Exkluzivitás** — amíg a sor él, más felhasználó nem foglalhatja le (UNIQUE), és nem is veheti ki — lefoglalt eszközt **csak a foglaló vagy a raktáros/it_admin** vihet el.
3. **Elvitel** — a foglaló (vagy a raktáros) `check_out`-ja teljesíti a foglalást: a sor **törlődik**, `devices.status = 'Deployed'`.
4. **Lemondás** — a foglaló vagy a raktáros lemondhatja: a sor **törlődik**, `devices.status = 'Ready to deploy'`.
5. **Lejárat (3 nap)** — ha 3 napon belül nincs elvitel, a foglalás lejár: a sor **törlődik** (ütemezett takarító job: `DELETE FROM device_reservations WHERE expires_at <= now()`), `devices.status = 'Ready to deploy'`.

A kezelőlogikát (`reserve_device` / `cancel_reservation` / `expire_reservations`, és a `check_out` általi teljesítés) az `APPLICATION_LOGIC.md` §10 írja le.