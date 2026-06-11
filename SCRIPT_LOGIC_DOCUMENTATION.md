# Hordozható Eszköznyilvántartás — Script-/kezelőlogika dokumentáció

Hogyan viselkedik az adatbázisra épülő alkalmazásréteg: milyen szerepköröket érvényesít, milyen műveleteket tesz elérhetővé, és pontosan mely sorokat írja az egyes műveletek. A `DATABASE_DOCUMENTATION.md`-vel együtt olvasandó (a tábladefiníciók ott az §5-ben, a birtoklási nézet a §7-ben).

> Ez a dokumentáció a tényleges, megvalósított logikát írja le (a `webapp/src/state/store.js` és a kapcsolódó kezelők alapján). A demó memóriában, localStorage-perzisztenciával fut; a leírt műveletnevek a kezelőfüggvényeknek felelnek meg.

---

## 1. Felelősségi körök

Az alkalmazásréteg felel mindenért, amit az adatbázis önmagában nem tud kikényszeríteni:

- **Engedélyezés** — a hívó szerepkörének (`clinic_users.auth`) ellenőrzése minden írás előtt.
- **Űrlapgenerálás és -ellenőrzés** — típusonkénti űrlapok építése az `attribute_definitions`-ból, és az értékek ellenőrzése a `data_type` / `is_required` / `options` alapján.
- **Birtoklási logika** — a felhasználói művelet helyes `device_custody_events` sorrá alakítása, a *korábbi* birtokos/hely levezetésével és az aktor rögzítésével.
- **Állapot-karbantartás** — a `devices.status` szinkronban tartása a birtoklási műveletekkel.

A személyazonosság/bejelentkezés **nem** a feladata — az a klinikai webalkalmazásé, amely átadja a hitelesített felhasználó-azonosítót (`actor`) és annak `auth` szerepkörét.

---

## 2. Hitelesítés vs. engedélyezés

- **Hitelesítés** (ki vagy): a webalkalmazás végzi.
- **Engedélyezés** (mit tehetsz itt): a modul végzi a `clinic_users.auth` alapján. Hiányzó/`NULL` érték esetén az alapértelmezés `user`.

```text
role_of(user_id):
    return SELECT auth FROM clinic_users WHERE id = :user_id
           ELSE 'user'        # hiányzó/NULL auth → 'user'
```

A szerepkörök rangsora: `user` (1) < `storekeeper` (2) < `it_admin` (3). A `role_at_least(role, min)` ezt a rangsort hasonlítja össze. A `clinic_users` **külső** tábla; ez a rendszer csak **olvassa** az `auth`-ot, sosem írja — nincs `grant_role` művelete.

---

## 3. Szerepkörök és jogosultsági mátrix

- **`user`** (Felhasználó) — rendes munkatársak (orvosok, recepciósok). Eszközöket mozgatnak: kivesznek egyet maguknak, átadják egy kollégának, visszahozzák, foglalnak. Csak a **náluk lévő** (vagy szabad, kivehető) eszközön hajthatnak végre műveletet.
- **`storekeeper`** (Raktáros) — a központi raktáros. Mindent, amit a felhasználó, **plusz** eszközök regisztrálása/szerkesztése/selejtezése, helyiségek (`departments`) kezelése, bármilyen birtoklási mozgatás bárki nevében, szerviz- és elveszett-kezelés, valamint a visszavételek megerősítése/elutasítása.
- **`it_admin`** (IT-admin) — az IT-csapat. Mindent, **plusz** eszköztípusok és `attribute_definitions` kezelése (a metaadat-séma), valamint telephelyek (`locations`) létrehozása.

| Művelet                                                  | user | storekeeper | it_admin |
|----------------------------------------------------------|------|-------------|----------|
| Eszközök / helyek / aktuális állapot megtekintése        | ✓    | ✓           | ✓        |
| Eszköz kivétele **magának** (szabad eszköz)              | ✓    | ✓           | ✓        |
| Nála lévő eszköz leadása / átadása                       | ✓    | ✓           | ✓        |
| Szabad eszköz foglalása / saját foglalás lemondása       | ✓    | ✓           | ✓        |
| Kivétel / kiadás **mások nevében**                       | —    | ✓           | ✓        |
| Raktár → raktár mozgatás (`stock_transfer`)              | —    | ✓           | ✓        |
| Kényszerített visszavétel / birtoklási rekord javítása   | —    | ✓           | ✓        |
| Visszavétel megerősítése / elutasítása (checkpoint)      | —    | ✓           | ✓        |
| Szervizbe küldés / szervizből visszahelyezés             | —    | ✓           | ✓        |
| Elveszettnek jelölés / megtaláltnak jelölés              | —    | ✓           | ✓        |
| Bármely foglalás lemondása                               | —    | ✓           | ✓        |
| Új eszköz regisztrálása / szerkesztése / selejtezése     | —    | ✓           | ✓        |
| Helyiségek (`departments`) létrehozása                   | —    | ✓           | ✓        |
| Telephelyek (`locations`) létrehozása                    | —    | —           | ✓        |
| Eszköztípusok és `attribute_definitions` kezelése        | —    | —           | ✓        |

Ökölszabály: a `user` csak olyan eszközön hajthat végre műveletet, **amely éppen nála van** (vagy egy szabadon elérhetőt vehet ki/foglalhat); minden máshoz `storekeeper` vagy magasabb kell.

---

## 4. Alapműveletek

### 4.1 `register_device(actor, payload)` — storekeeper / it_admin
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Egy `devices` sor beszúrása (közös mezők; `status = 'Kivehető'`, `created_by = updated_by = actor`).
3. A típusspecifikus értékek mentése (`device_attribute_values`, a demóban `attrs`-objektum) az `attribute_definitions` szerint ellenőrizve (§6).
4. A **kezdeti elhelyezés** rögzítése: egy `check_in` esemény `to_locations_id = payload.initial_location`, `to_departments_id = payload.initial_department` (jellemzően a központi raktár), „Regisztrációs elhelyezés." megjegyzéssel, `confirmed` állapotban. Ez garantálja, hogy az eszköznek a létrehozástól van ismert helye.

### 4.2 `edit_device(actor, device_id, changes)` — storekeeper / it_admin
Frissíti a közös mezőket és/vagy a típusspecifikus értékeket; beállítja: `updated_by = actor`, `updated_at = now()`. A birtoklást nem érinti.

### 4.3 `retire_device(actor, device_id, reason)` — storekeeper / it_admin
Beállítja: `status = 'Selejtezve'`, `retired_date = ma`, az okot a `notes`-hoz fűzi („Selejtezve: …"). Töröl minden aktív foglalást. Lágy törlés: sort nem távolít el.

### 4.4 Törzsadat-műveletek
- `add_location({address})` — **it_admin**: új telephely.
- `add_department({locations_id, name, type})` — **storekeeper**: új helyiség (`type` alapért. `'osztály'`).
- `add_device_type({type, description})` — **it_admin**: új eszköztípus.
- `add_attr_def({device_type_id, attribute_key, label, data_type, is_required, options, sort_order})` — **it_admin**: új típusspecifikus mező. `enum` esetén az `options` kötelező; `device_type_id = NULL` globális mezőt jelent.

### 4.5 Birtoklási és életciklus-műveletek
Felhasználó által is hívható (a `move_asset` kapun át, §5): `check_out`, `check_in`, `transfer`. Csak raktáros+ (a `move_asset_internal` belső kapun át, §11): `stock_transfer`, `send_to_repair`, `return_from_repair`, `mark_lost`, `mark_found`. A felhasználói `check_in` `pending` állapotban keletkezik, és raktáros-megerősítésre vár (checkpoint, §9).

---

## 5. A birtoklási kezelő (`move_asset`)

Ez a felhasználó-facing birtoklási műveletek (`check_out`, `check_in`, `transfer`) egyetlen kapuja, így a szabályok egy helyen élnek.

### 5.1 Bemenetek
```text
move_asset(
    device_id,
    event_type,               # check_out | check_in | transfer
    to_user_id        = None, # cél személy (ha van)
    to_locations_id   = None, # cél telephely (ha van)
    to_departments_id = None, # cél helyiség (raktár/osztály/…)
    expected_return_date = None,
    condition_at_event = None,
    notes = None
)
# az actor mindig a bejelentkezett felhasználó (state.currentUserId)
```

### 5.2 Mit csinál a kezelő
1. **Aktuális állapot feloldása** a `device_current_state`-ből, hogy automatikusan kitöltse a `from_*` oldalt — a hívó soha nem adja meg:
   ```text
   cur = current_state(device_id)        # {holder, location, department}
   from_user_id        = cur.holder
   from_locations_id   = cur.location
   from_departments_id = cur.department
   ```
2. **Engedélyezés** szerepkör és aktuális birtokos alapján (csak `user`-re korlátoz):
   - `check_out`: csak ha az eszköz szabad (nincs birtokosa, de van helye) **és** magához veszi (`to_user_id = actor`).
   - `check_in` / `transfer`: csak ha az eszköz éppen az aktornál van (`cur.holder = actor`).
   - bármi más `user`-ként → elutasítva („raktáros vagy IT-admin kell").
   - `storekeeper` / `it_admin`: nincs eszközönkénti korlátozás.
3. **A cél ellenőrzése:** a `to_user_id` / `to_departments_id` / `to_locations_id` közül legalább az egyik kitöltve. `stock_transfer`-nél (raktáros-ágon) `to_departments_id` **és** `to_locations_id` kell, `to_user_id` nélkül. `check_in`-nél a cél-helyiség kötelező.
4. **Foglalás-interplay** (`check_out` ág): ha van aktív foglalás, az elvitel csak a `reserved_by`-nak vagy raktáros+-nak engedélyezett; egyébként elutasítva.
5. **Egy nyitott visszavétel:** ha `user` `check_in`-t ad, és már van `pending` visszavétel az eszközre → elutasítva.
6. **Raktár sosem birtokos:** ha a cél-helyiség `type = 'raktár'`, a `to_user_id` NULL-ra áll (az eszköz a készletbe kerül).
7. **A `confirmation_status` meghatározása** (checkpoint, §9): `check_in` **és** `user` → `pending`; minden más → `confirmed`.
8. **Pontosan egy eseménysor beszúrása**, mindig `actor_user_id = actor` és `event_timestamp = now()` bélyegzéssel.
9. **Teljesült foglalás:** `check_out` után a foglalás sora törlődik.
10. **A `devices.status` frissítése** (§7): `pending` → `'Visszavétel folyamatban'`; raktárba (`toStorage`) → `'Kivehető'`; egyébként a `status_from_event` leképezés.

### 5.3 Pszeudokód
```php
function move_asset($device_id, $event_type, $to_user_id = null,
                    $to_locations_id = null, $to_departments_id = null,
                    $expected_return_date = null, $condition_at_event = null, $notes = null) {
    $actor = current_user_id();
    $role  = role_of($actor);
    $cur   = current_state($device_id);          // {holder, location, department}

    // --- engedélyezés (csak user-re korlátoz) ---
    if ($role === 'user') {
        $free   = ($cur->holder === null && ($cur->department !== null || $cur->location !== null));
        $mine   = ($cur->holder === $actor);
        if ($event_type === 'check_out') {
            require_that($free && $to_user_id === $actor,
                "felhasználóként csak szabad eszközt, csak magadnak");
        } elseif ($event_type === 'check_in' || $event_type === 'transfer') {
            require_that($mine, "felhasználóként csak a nálad lévő eszközt mozgathatod");
        } else {
            deny("raktáros vagy it_admin kell");
        }
    }

    // --- cél ellenőrzése ---
    require_that($to_user_id !== null || $to_departments_id !== null || $to_locations_id !== null,
        "cél kötelező");
    if ($event_type === 'check_in') require_that($to_departments_id !== null, "visszavételhez cél-helyiség kell");

    // --- foglalás-interplay ---
    if ($event_type === 'check_out') {
        $resv = active_reservation($device_id);
        require_that(!$resv || $resv->reserved_by === $actor || $role >= 'storekeeper',
            "az eszköz másnak van fenntartva");
    }

    // --- egy nyitott visszavétel eszközönként ---
    if ($event_type === 'check_in' && $role === 'user')
        require_that(pending_checkin_for($device_id) === null, "már van megerősítésre váró visszavétel");

    // --- raktár sosem birtokos ---
    $to_storage = is_storage_dept($to_departments_id);
    if ($to_storage) $to_user_id = null;

    // --- checkpoint ---
    $confirmation = ($event_type === 'check_in' && $role === 'user') ? 'pending' : 'confirmed';

    insert_device_custody_event([
        'device_id' => $device_id, 'event_type' => $event_type, 'actor_user_id' => $actor,
        'from_user_id' => $cur->holder, 'from_locations_id' => $cur->location,
        'from_departments_id' => $cur->department,
        'to_user_id' => $to_user_id, 'to_locations_id' => $to_locations_id,
        'to_departments_id' => $to_departments_id, 'event_timestamp' => now(),
        'expected_return_date' => $expected_return_date, 'condition_at_event' => $condition_at_event,
        'notes' => $notes, 'confirmation_status' => $confirmation,
    ]);

    if ($event_type === 'check_out') delete_reservation($device_id);   // teljesült

    if ($confirmation === 'pending')   set_status($device_id, 'Visszavétel folyamatban');
    elseif ($to_storage)               set_status($device_id, 'Kivehető');
    else                               set_status($device_id, status_from_event($event_type));
}
```

### 5.4 A folyamatok a gyakorlatban

| Művelet                                                | Hívás (kulcsmezők)                                                                                  | Beírt sor                                                            |
|--------------------------------------------------------|-----------------------------------------------------------------------------------------------------|---------------------------------------------------------------------|
| Orvos kivesz egy ultrahangot a raktárból a Kardiológiára | `move_asset(event_type='check_out', to_user_id=orvos, to_locations_id=Bp, to_departments_id=Kardiológia)` | from_dept=Központi raktár, to_user=orvos, to_dept=Kardiológia, actor=orvos |
| Orvos visszaviszi a központi raktárba                  | `move_asset(event_type='check_in', to_locations_id=Bp, to_departments_id=Központi raktár)`           | from_user=orvos, to_dept=Központi raktár, actor=orvos, **pending**   |
| Recepciós átad egy laptopot egy kollégának             | `move_asset(event_type='transfer', to_user_id=kolléga, to_departments_id=Recepció)`                  | from_user=recepció, to_user=kolléga, actor=recepció                  |
| Raktáros készletet mozgat két raktár között            | `move_asset(event_type='stock_transfer', to_locations_id=Db, to_departments_id=Fiókraktár)`          | from_dept=Központi raktár, to_dept=Fiókraktár, **actor=raktáros**    |

Minden esetben rögzül az `actor_user_id` — a raktár-raktár mozgatásnál is.

---

## 6. Űrlapgenerálás és értékellenőrzés

Amikor a felhasználó kiválaszt egy eszköztípust, a kezelő az `attribute_definitions WHERE device_type_id = <típus> OR device_type_id IS NULL` sorokból építi az űrlapot, `sort_order` szerint rendezve, és mentéskor minden értéket ellenőriz:

- `data_type = date` → érvényes dátum; `integer`/`decimal` → numerikus; `boolean` → igaz/hamis.
- `data_type = enum` → az érték az `options` (vesszővel tagolt lista) egyike.
- `is_required = true` → kell érték, különben elutasít.
- Az érvényes értékek a `device_attribute_values`-be kerülnek (soronként egy); a megválaszolatlan opcionális mezők nem írnak sort.

Mivel a `value` `text`-ként tárolódik, ez az alkalmazásoldali ellenőrzés garantálja az adatminőséget.

---

## 7. Állapot-karbantartás

A `devices.status` szinkronban marad a birtoklással. A `status_from_event` alap-leképezés:

| Esemény (`event_type`)              | Eredő állapot              |
|-------------------------------------|----------------------------|
| `check_out`                         | `Kiadva`                   |
| `transfer`                          | `Kiadva`                   |
| `check_in` (megerősítve)            | `Kivehető`                 |
| `stock_transfer`                    | `Kivehető`                 |
| `return_from_repair`                | `Kivehető`                 |
| `mark_found`                        | `Kivehető`                 |
| `send_to_repair`                    | `Szerviz alatt`            |
| `mark_lost`                         | `Elveszett`                |

A `move_asset` felülírásai a leképezés előtt:

| Eset                                              | Eredő állapot              |
|---------------------------------------------------|----------------------------|
| `check_in` **felhasználótól, `pending`** (leadva) | `Visszavétel folyamatban`  |
| bármely esemény **raktárba** (`toStorage`)        | `Kivehető`                 |

A checkpoint-lezárás (§9) és a foglalás (§10) külön állítja:

| Művelet                              | Eredő állapot              |
|--------------------------------------|----------------------------|
| `confirm_check_in`                   | `Kivehető`                 |
| `reject_check_in`                    | `Kiadva` (visszaáll)       |
| `reserve_device`                     | `Lefoglalva`               |
| `cancel_reservation` / lejárat       | `Kivehető`                 |
| `retire_device`                      | `Selejtezve`               |

A birtokost és a helyet maga a `devices` nem tárolja; ezek a legújabb `device_custody_events` sorból származnak, és a `device_current_state` nézet teszi elérhetővé őket (`current_holder_user_id`, `current_location_id`, `current_department_id`).

---

## 8. Peremesetek és szabályok

- **Frissen regisztrált eszköz birtoklási esemény nélkül** — elkerülve: a `register_device` kezdeti `check_in`-t ír egy helyiségbe, így minden eszköznek azonnal van helye.
- **Levezetett `from_*` eltérés** — a kezelő mindig a nézetből veszi a `from_*`-ot, sosem a klienstől, így nem mondhat ellent az előzménynek (az új esemény „from"-ja az előző „to"-ja).
- **Egyidejű mozgatások egy eszközön** — eszközönként sorosítva (sorzár vagy app-szintű zár), hogy két egyidejű esemény ne olvashassa ugyanazt az „aktuális" állapotot.
- **`user` más eszközén próbál műveletet** — elutasítva; csak storekeeper/it_admin mozgathat olyan eszközt, amely nem nála van.
- **Raktár mint cél** — ha a cél-helyiség `type = 'raktár'`, az eszköznek nincs birtokosa, így `Kivehető` marad, függetlenül a `to_user_id`-tól.
- **Eszköz törlése** — nem megengedett; használd a `retire_device`-t. A birtoklási előzmény és az attribútumértékek megmaradnak.

---

## 9. Checkpoint kezelők — `confirm_check_in` / `reject_check_in`

A felhasználói visszavétel kétfázisú: a `move_asset` leadja `pending` állapotban (§5), majd a raktáros itt zárja le. Mindkét kezelő `storekeeper`+ szerepkört igényel, és **csak `pending`, `check_in` típusú** eseményt érinthet. Ez az egyetlen engedélyezett mutáció a `device_custody_events` táblán — a birtoklási mezők (`event_type`, `from_*`, `to_*`, `event_timestamp`) sosem módosulnak.

### 9.1 A raktáros munkalistája
A `device_pending_checkins` nézet adja a függőben lévő visszavételeket (melyik eszköz, ki adta le, hova állítása szerint, mikor).

### 9.2 `confirm_check_in(actor, event_id)` — storekeeper / it_admin
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Az esemény betöltése; ellenőrzés: `confirmation_status = 'pending'` és `event_type = 'check_in'`.
3. Frissítés: `confirmation_status = 'confirmed'`, `confirmed_by = actor`, `confirmed_at = now()`.
4. Mostantól a `device_current_state` ezt látja → az eszköz a raktárban/célhelyen. `devices.status = 'Kivehető'`.

### 9.3 `reject_check_in(actor, event_id, reason)` — storekeeper / it_admin
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Ugyanaz az ellenőrzés (`pending` + `check_in`).
3. Frissítés: `confirmation_status = 'rejected'`, `confirmed_by = actor`, `confirmed_at = now()`; a `reason` a `notes`-hoz fűzve („ELUTASÍTVA: …").
4. Az esemény sosem érvényesül: a nézet a check_in **előtti** állapotot tartja (az eszköz a felhasználónál marad). `devices.status = 'Kiadva'`.

### 9.4 Pszeudokód
```php
function confirm_check_in($actor, $event_id) {
    require_role($actor, 'storekeeper');
    $ev = load_event($event_id);
    require_that($ev->confirmation_status === 'pending' && $ev->event_type === 'check_in',
        "csak függőben lévő visszavétel erősíthető meg");
    update_event($event_id, ['confirmation_status' => 'confirmed',
        'confirmed_by' => $actor, 'confirmed_at' => now()]);
    set_status($ev->device_id, 'Kivehető');
}

function reject_check_in($actor, $event_id, $reason) {
    require_role($actor, 'storekeeper');
    $ev = load_event($event_id);
    require_that($ev->confirmation_status === 'pending' && $ev->event_type === 'check_in',
        "csak függőben lévő visszavétel utasítható el");
    update_event($event_id, ['confirmation_status' => 'rejected',
        'confirmed_by' => $actor, 'confirmed_at' => now(),
        'notes' => append_note($ev->notes, "ELUTASÍTVA: {$reason}")]);
    set_status($ev->device_id, 'Kiadva');   // visszaáll a check_in előtti birtoklásra
}
```

### 9.5 Peremesetek
- **Dupla leadás** — eszközönként legfeljebb egy nyitott (`pending`) check_in; a `move_asset` előzetesen ellenőrzi, a `uq_one_pending_checkin_per_device` index DB-szinten is kikényszeríti.
- **Raktáros saját visszavétele** — a raktáros/it_admin `check_in`-je azonnal `confirmed` (ő maga az ellenőrző).

---

## 10. Felhasználói foglalás (reservation)

A felhasználók maguk foglalhatnak le egy szabad eszközt; a foglalás az elvitelig (`check_out`) fenntartja nekik. A `device_reservations` tábla mindig csak az aktív foglalásokat tartalmazza (a megszűnt sor törlődik). Lásd `DATABASE_DOCUMENTATION.md` §10.

### 10.1 `reserve_device(actor, device_id, notes=null)` — bármely bejelentkezett felhasználó
1. **Lejárt sor takarítása** az adott eszközre, hogy egy be nem söpört lejárt foglalás ne blokkoljon.
2. Ellenőrzés: az eszköz **szabad** — nincs birtokosa (van helye), és `status = 'Kivehető'`. Ha nem → barátságos hiba.
3. Ellenőrzés: nincs aktív foglalása (`active_reservation`).
4. Egy sor beszúrása: `reserved_by = actor`, `reserved_at = now()`, `expires_at = now() + 3 nap`.
5. `devices.status = 'Lefoglalva'`.

### 10.2 `cancel_reservation(actor, device_id)` — a foglaló vagy storekeeper / it_admin
1. Engedélyezés: az aktor a `reserved_by`, vagy szerepkör ≥ storekeeper.
2. A sor törlése.
3. `devices.status = 'Kivehető'`.

### 10.3 Elvitel — `check_out` interplay (a `move_asset`-ben)
1. Ha van **aktív foglalás**: az elvitel engedélyezett, ha az aktor a `reserved_by` **vagy** szerepköre ≥ `storekeeper`; egyébként elutasítva.
2. Sikeres `check_out` után a foglalás **teljesült**: a sor törlődik.

### 10.4 Lejárat — `expire_reservations()` (ütemezett)
1. Egy ütemezett job: `DELETE FROM device_reservations WHERE expires_at <= now()`.
2. Minden érintett eszköz `status`-a `'Kivehető'`-re áll (ha közben nem vitték el).
3. A `device_active_reservations` nézet a takarítás között is helyes (`expires_at > now()` szűr).

### 10.5 Peremesetek
- **Foglalás + checkpoint** — egy `pending` visszavétel alatt az eszköz a nézet szerint még a felhasználónál van, tehát nem foglalható, amíg a raktáros meg nem erősíti.
- **Versenyhelyzet** — két egyidejű foglalás esetén az UNIQUE(`device_id`) az egyiket elutasítja; az alkalmazás „már lefoglalva" hibára fordítja.

---

## 11. Szerviz- és elveszett-kezelők — raktáros+

Ezek a műveletek a **belső** birtoklási kapun (`move_asset_internal`) mennek át, amely megkerüli a felhasználói korlátozást, mindig `confirmed` eseményt ír, levezeti a `from_*` oldalt az aktuális állapotból, és frissíti a `devices.status`-t a `status_from_event` szerint.

### 11.1 `send_to_repair(actor, device_id, to_location_id=null, to_department_id=null, notes=null)`
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Ha nincs megadva cél-helyiség, a rendszer a `type = 'műhely'` helyiséget választja.
3. `move_asset_internal(event_type='send_to_repair', …)` → `devices.status = 'Szerviz alatt'`.

### 11.2 `return_from_repair(actor, device_id, to_location_id, to_department_id, notes=null)`
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Ellenőrzés: az eszköz `status = 'Szerviz alatt'`; különben hiba („Csak szerviz alatt lévő eszköz helyezhető vissza.").
3. `move_asset_internal(event_type='return_from_repair', …)` → `devices.status = 'Kivehető'`. A `from_*` a műhely lesz, a `to_*` a választott telephely + helyiség, így a napló mutatja, **honnan** érkezett vissza.

### 11.3 `mark_lost(actor, device_id, notes=null)`
1. Engedélyezés: szerepkör ≥ storekeeper.
2. `move_asset_internal(event_type='mark_lost', …)` az **utolsó ismert** birtokost/helyet megőrizve a cél oldalon → `devices.status = 'Elveszett'`.

### 11.4 `mark_found(actor, device_id, to_location_id, to_department_id, notes=null)`
1. Engedélyezés: szerepkör ≥ storekeeper.
2. `move_asset_internal(event_type='mark_found', …)` a választott helyre (forrás nélkül — nem ismert, honnan került elő) → `devices.status = 'Kivehető'`.

> A megjelenítésben a `mark_lost` és `mark_found` események **nem** mutatnak „forrás → cél" nyilat, mert ezeknél csak az (utolsó ismert, illetve a megtalálási) hely a releváns.

---

## 12. Összefoglaló

A felhasználó-facing birtoklási előzményt egyetlen kapu (`move_asset`) írja, a korábbi birtokost/helyet a `device_current_state` nézetből vezeti le, és mindig bélyegzi az aktort; a raktáros+ életciklus-műveletek a `move_asset_internal` belső kapun mennek át. Egy kis szerepkör-ellenőrzés (`user` < `storekeeper` < `it_admin`, a `clinic_users.auth`-ból) kapuz minden írást; a típusspecifikus űrlapok és ellenőrzés az `attribute_definitions`-ból jönnek; a `devices.status` pedig automatikusan követi a birtoklást. A helyet két szint adja: `locations` (telephely) + `departments` (helyiség). Az eredmény: egy rendes felhasználó interakciója „beolvas, kattint kivétel/leadás/foglalás", a raktáros viszi a regisztrációt, készletmozgatást, szervizt és a visszavétel-megerősítést, az IT pedig a típus-/attribútumsémát és a telephelyeket.
