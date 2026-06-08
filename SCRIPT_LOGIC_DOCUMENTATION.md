# Hordozható Eszköznyilvántartás — Script-/kezelőlogika dokumentáció

Hogyan viselkedik az adatbázisra épülő script: milyen szerepköröket érvényesít, milyen műveleteket tesz elérhetővé, és pontosan mely sorokat írja az egyes műveletek. A `DATABASE_DOCUMENTATION.md`-vel együtt olvasandó (a tábladefiníciók ott az §5-ben, a birtoklási nézet a §7-ben találhatók).

---

## 1. Felelősségi körök

Az alkalmazásréteg felel mindenért, amit az adatbázis önmagában nem tud kikényszeríteni:

- **Engedélyezés** — a hívó nyilvántartási szerepkörének (`clinic_users.auth`) ellenőrzése minden írás előtt.
- **Űrlapgenerálás és -ellenőrzés** — típusonkénti űrlapok építése az `attribute_definitions`-ból, és az értékek ellenőrzése a `data_type` / `is_required` / `options` alapján.
- **Birtoklási logika** — a felhasználói művelet („check-out", „check-in") helyes `device_custody_events` sorrá alakítása, beleértve a *korábbi* birtokos levezetését az aktuális állapotból, és az aktor mindenkori rögzítését.
- **Állapot-karbantartás** — a `devices.status` szinkronban tartása a birtoklási műveletekkel.

A személyazonosság/bejelentkezés **nem** a feladata — az a klinikai webalkalmazásé, amely átadja a hitelesített felhasználó-azonosítót (`actor`). A szerepkörök (`auth`) hozzárendelése szintén a webalkalmazásé (lásd §2).

---

## 2. Hitelesítés vs. engedélyezés

- **Hitelesítés** (ki vagy): a webalkalmazás végzi. Az app átadja a nyilvántartási modulnak az aktuális munkamenet ellenőrzött `clinic_users.id`-ját.
- **Engedélyezés** (mit tehetsz itt): ezt a modul végzi a `clinic_users.auth` oszlop alapján. A kezelő kikeresi a hívó szerepkörét; hiányzó vagy `NULL` érték esetén az alapértelmezés `user`.

```
def role_of(user_id):
    return SELECT auth FROM clinic_users WHERE id = :user_id
           ELSE 'user'        # hiányzó/NULL auth → 'user'
```

A `clinic_users` **külső** tábla (a webalkalmazás tulajdona); ez a rendszer csak **olvassa** az `auth`-ot, sosem írja. A szerepkörök kiosztása/visszavonása a webalkalmazásban történik — ennek a modulnak **nincs** `grant_role` művelete.

---

## 3. Szerepkörök és jogosultsági mátrix

Három szint, a legalacsonyabbtól a legmagasabbig (`auth` enum):

- **`user`** (Felhasználó) — rendes munkatársak (adminisztrátorok, recepciósok, orvosok). Eszközöket mozgatnak: kivesznek egyet használatra, átadják egy kollégának, visszahozzák. Látják, hol van mi, és mi van náluk éppen.
- **`storekeeper`** (Raktáros) — a központi raktáros. Mindent, amit a felhasználó, **plusz** eszközök regisztrálása és szerkesztése, osztályok/helyek (`departments`) kezelése, és bármilyen birtoklási mozgatás bárki nevében (kiadás, kényszerített visszavétel, raktárak közti mozgatás, rekordok javítása). A rendszer mindennapi operátora. Nem módosíthatja a típus-/attribútumsémát.
- **`it_admin`** (IT-admin) — az IT-csapat (szuperadmin). Mindent, **plusz** eszköztípusok és `attribute_definitions` kezelése (a metaadat-séma), valamint konfiguráció/integráció.

| Művelet                                                | user | storekeeper | it_admin |
|--------------------------------------------------------|------|-------------|----------|
| Eszközök / osztályok / aktuális állapot megtekintése   | ✓    | ✓           | ✓       |
| Eszköz kivétele **magának**                            | ✓    | ✓           | ✓       |
| Nála lévő eszköz visszahozása                          | ✓    | ✓           | ✓       |
| Nála lévő eszköz átadása másik felhasználónak          | ✓    | ✓           | ✓       |
| Kivétel / kiadás **mások nevében**                     | —    | ✓           | ✓        |
| Raktár → raktár mozgatás (`stock_transfer`)            | —    | ✓           | ✓        |
| Kényszerített visszavétel / birtoklási rekord javítása | —    | ✓           | ✓        |
| Új eszköz regisztrálása                                | —    | ✓           | ✓        |
| Eszköz közös mezőinek szerkesztése / selejtezése       | —    | ✓           | ✓        |
| Helyszínek (`departments`) kezelése                    | —    | ✓           | ✓        |
| Eszköztípusok és `attribute_definitions` kezelése      | —    | —           | ✓        |

> **Szerepkör-hozzárendelés** (`auth` írása) nem szerepel a mátrixban: az a webalkalmazás feladata a külső `clinic_users` táblán. Ez a modul az `auth`-ot csak olvassa.

Ökölszabály, amelyet a kezelő alkalmaz: a `user` csak olyan eszközön hajthat végre műveletet, **amely éppen nála van** (vagy egy raktárból szabadon elérhetőt vehet ki); más eszközén végzett művelethez, vagy bármilyen raktár-raktár mozgatáshoz `storekeeper` vagy magasabb szerepkör kell.

---

## 4. Alapműveletek

Minden művelet alább megadja: a szükséges szerepkört, a bemeneteket, az ellenőrzést és a beírt sorokat.

### 4.1 `register_device(actor, payload)` — storekeeper / it_admin
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Egy `devices` sor beszúrása (közös mezők; `created_by = updated_by = actor`).
3. Az `attribute_definitions` betöltése a `payload.device_type_id`-hez; minden megadott érték ellenőrzése (§6); a megválaszoltak beszúrása a `device_attribute_values`-be.
4. A **kezdeti elhelyezés** rögzítése a birtoklási kezelő hívásával: `event_type = check_in` és `to_departments_id = payload.initial_department` (jellemzően a központi raktár). Ez garantálja, hogy az eszköznek a létrehozástól van ismert helye.

### 4.2 `edit_device(actor, device_id, changes)` — storekeeper / it_admin
Frissíti a közös mezőket és/vagy a `device_attribute_values`-t; beállítja: `updated_by = actor`, `updated_at = now()`. A birtoklást nem érinti.

### 4.3 `retire_device(actor, device_id, reason)` — storekeeper / it_admin
Beállítja: `status = 'Retired'`, `retired_date = ma`, az okot hozzáfűzi a `notes`-hoz. Lágy törlés: sort nem távolít el.

### 4.4 `define_type` / `manage_attribute_definitions(actor, ...)` — csak it_admin
Létrehozza/szerkeszti a `device_types` és `attribute_definitions` sorokat. Csak így keletkezhetnek új típusspecifikus mezők (lásd DB doc §6).

### 4.5 Birtoklási műveletek — `move_asset(...)` (lásd §5)
`check_out`, `check_in`, `transfer`, `stock_transfer`, `send_to_repair`, `mark_lost`. Mind pontosan egy `device_custody_events` sort fűz hozzá. A felhasználói `check_in` `pending` állapotban keletkezik, és raktáros-megerősítésre vár (checkpoint, §9); ezt a `confirm_check_in` / `reject_check_in` zárja le.

---

## 5. A birtoklási kezelő (`move_asset`)

Ez a rendszer szíve. Minden mozgás egyetlen függvényen megy keresztül, így a szabályok egy helyen élnek.

### 5.1 Bemenetek
```
move_asset(
    actor,                   # hitelesített clinic_users.id (mindig ez lesz az actor_user_id)
    device_id,
    event_type,              # check_out | check_in | transfer | stock_transfer | send_to_repair | mark_lost
    to_user_id       = None, # cél személy (ha van)
    to_departments_id = None, # cél osztály/szoba/raktár (departments) (ha van)
    expected_return_date = None,
    condition_at_event = None,
    notes = None
)
```

### 5.2 Mit csinál a kezelő
1. **Aktuális állapot feloldása** a nézetből, hogy automatikusan kitöltse a `from_*` oldalt — a hívó soha nem adja meg:
   ```
   cur = SELECT current_holder_user_id, current_department_id
         FROM device_current_state WHERE device_id = :device_id
   from_user_id       = cur.current_holder_user_id
   from_departments_id = cur.current_department_id
   ```
2. **Engedélyezés** szerepkör és aktuális birtokos alapján:
   - `user`: csak akkor engedélyezett, ha (a) `event_type = check_out` és az eszköz éppen szabad, és magához veszi (`to_user_id = actor`); vagy (b) az eszköz éppen az aktornál van (`from_user_id = actor`) `check_in`/`transfer` esetén.
   - `storekeeper`/`it_admin`: minden eseménytípusnál, bármely eszközre engedélyezett, beleértve a `stock_transfer`-t.
3. **A cél ellenőrzése** (a DB CHECK tükrözése, barátságosabb hibákkal): a `to_user_id` / `to_departments_id` közül legalább az egyik legyen kitöltve. `stock_transfer`-nél a `to_departments_id` kötelező, a `to_user_id` pedig null. `check_in`-nél a `to_departments_id` kötelező.
4. **A `confirmation_status` meghatározása** (checkpoint, §9): ha `event_type = 'check_in'` **és** az aktor szerepköre `user` (nem `storekeeper`/`it_admin`), akkor `pending`; minden más esetben `confirmed`. Pending esetén a `confirmed_by`/`confirmed_at` NULL.
5. **Pontosan egy eseménysor beszúrása**, mindig `actor_user_id = actor` és `event_timestamp = now()` bélyegzéssel, a 4. pont szerinti `confirmation_status`-szal.
6. **A `devices.status` frissítése** a megfelelőre (§7): pending check_in → `Pending return`; egyébként a szokásos leképezés.

A `from_*` oldal levezetett, sosem a klienstől származik — ez tartja a birtoklási láncot belsőleg konzisztensnek (az új esemény „from"-ja mindig az előző esemény „to"-jával egyezik).

### 5.3 Pszeudokód
```php
function move_asset($actor, $device_id, $event_type, $to_user_id = null, $to_department_id = null,
                    $expected_return_date = null, $condition_at_event = null, $notes = null) {
    $role = role_of($actor);                       // clinic_users.auth
    $cur  = current_state($device_id);             // a device_current_state nézetből
    $from_user_id       = $cur->holder;
    $from_department_id  = $cur->department;

    // --- engedélyezés ---
    if ($role === 'user') {
        $free_in_stock = ($cur->holder === null && $cur->department !== null);
        $held_by_actor = ($cur->holder === $actor);
        if ($event_type === 'check_out') {
            require_that($free_in_stock && $to_user_id === $actor,
                "a felhasználó csak szabad eszközt vehet ki, és csak magának");
        } elseif (in_array($event_type, ['check_in', 'transfer'], true)) {
            require_that($held_by_actor, "a felhasználó csak a nála lévő eszközt mozgathatja");
        } else {
            deny("stock_transfer / javítás / elveszett: storekeeper vagy it_admin kell");
        }
    }
    // storekeeper és it_admin: nincs eszközönkénti korlátozás

    // --- cél ellenőrzése (a chk_to_holder DB CHECK-et tükrözi) ---
    require_that($to_user_id !== null || $to_department_id !== null, "cél kötelező");
    if ($event_type === 'stock_transfer') {
        require_that($to_department_id !== null && $to_user_id === null, "raktármozgatáshoz cél-osztály kell");
    }
    if ($event_type === 'check_in') {
        require_that($to_department_id !== null, "visszavételhez cél-osztály kell");
    }

    // --- checkpoint: a felhasználói visszavétel megerősítésre vár ---
    $confirmation = ($event_type === 'check_in' && $role === 'user')
                  ? 'pending'      // raktáros fizikai ellenőrzéséig nem érvényesül
                  : 'confirmed';   // minden más azonnal él

    // --- pontosan egy esemény írása; az aktor mindig naplózva ---
    insert_device_custody_event([
        'device_id'            => $device_id,
        'event_type'           => $event_type,
        'actor_user_id'        => $actor,
        'from_user_id'         => $from_user_id,
        'from_department_id'   => $from_department_id,
        'to_user_id'           => $to_user_id,
        'to_department_id'     => $to_department_id,
        'event_timestamp'      => now(),
        'expected_return_date' => $expected_return_date,
        'condition_at_event'   => $condition_at_event,
        'notes'                => $notes,
        'confirmation_status'  => $confirmation,   // pending | confirmed
        'confirmed_by'         => null,
        'confirmed_at'         => null,
    ]);

    if ($confirmation === 'pending') {
        set_status($device_id, 'Pending return');        // lásd §7
    } else {
        update_status_from_event($device_id, $event_type);  // lásd §7
    }
}
```
### 5.4 A négy folyamat a gyakorlatban

| Művelet                                                           | Hívás                                                                                                 | Beírt sor (kulcsmezők)                                                          |
|-------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------|
| Orvos kivesz egy ultrahangot a központi raktárból a Kardiológiára | `move_asset(actor=orvos, …, event_type='check_out', to_user_id=orvos, to_departments_id=Kardiológia)`  | from_department=Központi, to_user=orvos, to_department=Kardiológia, actor=orvos |
| Orvos visszaviszi a központi raktárba                             | `move_asset(actor=orvos, …, event_type='check_in', to_departments_id=Központi)`                        | from_user=orvos, to_department=Központi, actor=orvos                            |
| Recepciós átad egy laptopot egy kollégának                        | `move_asset(actor=recepció, …, event_type='transfer', to_user_id=kolléga, to_departments_id=Recepció)` | from_user=recepció, to_user=kolléga, to_department=Recepció, actor=recepció     |
| Raktáros készletet mozgat két raktár között                           | `move_asset(actor=Raktáros, …, event_type='stock_transfer', to_departments_id=Fiókraktár)`                 | from_department=Központi, to_department=Fiókraktár, **actor=Raktáros**              |

Minden esetben rögzül az `actor_user_id` — a raktár-raktár mozgatásnál is, ahol mindkét oldali oszlop osztály, de a végrehajtó raktáros így is naplózva van.

---

## 6. Űrlapgenerálás és értékellenőrzés

Amikor a felhasználó kiválaszt egy eszköztípust, a kezelő az `attribute_definitions WHERE device_type_id = <típus>` sorokból építi az űrlapot, `sort_order` szerint rendezve, és mentéskor minden értéket ellenőriz:

- `data_type = date` → érvényes dátum legyen; `integer`/`decimal` → numerikus; `boolean` → igaz/hamis.
- `data_type = enum` → az érték az `options` egyike legyen.
- `is_required = true` → kell érték, különben elutasít.
- Az érvényes értékek a `device_attribute_values`-be kerülnek (soronként egy); a megválaszolatlan opcionális mezők nem írnak sort.

Mivel a `value` `text`-ként tárolódik, ez az alkalmazásoldali ellenőrzés garantálja az adatminőséget — az adatbázis nem típusellenőriz.

---

## 7. Állapot-karbantartás

A `devices.status` szinkronban marad a birtoklással, így a felhasználók ritkán állítják kézzel:

| Esemény                                              | Eredő állapot     |
|------------------------------------------------------|-------------------|
| `check_out` egy felhasználóhoz                       | `Deployed`        |
| `check_in` **felhasználótól, `pending`** (leadva)    | `Pending return`  |
| `check_in` **megerősítve** (`confirm_check_in`)      | `Ready to deploy` |
| `check_in` **elutasítva** (`reject_check_in`)        | `Deployed` (visszaáll) |
| `stock_transfer` (most raktárban)                    | `Ready to deploy` |
| `send_to_repair`                                     | `In repair`       |
| `mark_lost`                                          | `Lost`            |
| `retire_device`                                      | `Retired`         |

A `Reserved` állapotot a **felhasználói foglalás** állítja be automatikusan (`reserve_device`, §10): foglaláskor `Reserved`, elviteltől `Deployed`, lemondás/lejárat után vissza `Ready to deploy`. (A raktáros továbbra is állíthatja kézzel is.)

> **Checkpoint:** a felhasználói `check_in` nem azonnal `Ready to deploy`. Először `Pending return` lesz (az esemény `confirmation_status = 'pending'`), és csak a raktáros megerősítése után vált `Ready to deploy`-ra. Elutasításkor az állapot visszaáll a check_in előtti `Deployed`-ra. Részletek: §9 és DATABASE_DOCUMENTATION.md §9.

A birtokost és a helyet maga a `devices` nem tárolja; ezek a legújabb `device_custody_events` sorból származnak, és a `device_current_state` nézet teszi elérhetővé őket (`current_holder_user_id`, `current_department_id`). A `current_department_id` a `departments` táblára mutat; ennek `name`-je a megjelenített hely (szükség esetén a `departments.locations_id` → külső `locations` kapcsolaton át bővíthető az épület/cím adatával).

---

## 8. Peremesetek és szabályok

- **Frissen regisztrált eszköz birtoklási esemény nélkül** — elkerülve: a `register_device` kezdeti `check_in`-t ír egy osztályba, így minden eszköznek azonnal van helye.
- **Levezetett `from_*` eltérés** — a kezelő mindig a nézetből veszi a `from_*`-ot, sosem a klienstől, így nem mondhat ellent az előzménynek.
- **Egyidejű mozgatások egy eszközön** — eszközönként sorosítva (sorzár vagy app-szintű zár), hogy két egyidejű esemény ne olvashassa ugyanazt az „aktuális" állapotot.
- **`user` más eszközén próbál műveletet** — elutasítva; csak storekeeper/it_admin mozgathat olyan eszközt, amely nem nála van.
- **Eszköz törlése** — nem megengedett; használd a `retire_device`-t. A birtoklási előzmény és az attribútumértékek megmaradnak.
- **Szerepkör-hozzárendelés** — az `auth` a külső `clinic_users` táblában él, és a webalkalmazás kezeli; ez a rendszer csak olvassa, nem írja. Nincs `grant_role` művelet ebben a modulban.

---

## 9. Checkpoint kezelők — `confirm_check_in` / `reject_check_in`

A felhasználói visszavétel kétfázisú: a `move_asset` leadja `pending` állapotban (§5), majd a raktáros itt zárja le. Mindkét kezelő `storekeeper` vagy `it_admin` szerepkört igényel, és **csak `pending`, `check_in` típusú** eseményt érinthet. Ez a megerősítés/elutasítás az egyetlen engedélyezett mutáció a `device_custody_events` táblán — a birtoklási mezők (`event_type`, `from_*`, `to_*`, `event_timestamp`) sosem módosulnak.

### 9.1 A raktáros munkalistája
A `device_pending_checkins` nézet adja az elvégzendő ellenőrzéseket (függőben lévő visszavételek: melyik eszköz, ki adta le, hova állítása szerint, mikor).

### 9.2 `confirm_check_in(actor, event_id)` — storekeeper / it_admin
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Az esemény betöltése; ellenőrzés: `confirmation_status = 'pending'` és `event_type = 'check_in'` (különben elutasít).
3. Frissítés: `confirmation_status = 'confirmed'`, `confirmed_by = actor`, `confirmed_at = now()`.
4. Mostantól a `device_current_state` nézet ezt az eseményt látja → az eszköz a raktárban/célhelyen van. `devices.status = 'Ready to deploy'`.

### 9.3 `reject_check_in(actor, event_id, reason)` — storekeeper / it_admin
1. Engedélyezés: szerepkör ≥ storekeeper.
2. Ugyanaz az ellenőrzés, mint fent (`pending` + `check_in`).
3. Frissítés: `confirmation_status = 'rejected'`, `confirmed_by = actor`, `confirmed_at = now()`; a `reason` a `notes`-hoz fűzve (diszkrepancia).
4. Az esemény sosem érvényesül: a nézet a check_in **előtti** állapotot tartja (az eszköz a felhasználónál marad). `devices.status` visszaáll `'Deployed'`-ra.

### 9.4 Pszeudokód
```php
function confirm_check_in($actor, $event_id) {
    require_that(role_of($actor) >= 'storekeeper', "raktáros vagy it_admin kell");
    $ev = load_event($event_id);
    require_that($ev->confirmation_status === 'pending' && $ev->event_type === 'check_in',
        "csak függőben lévő visszavétel erősíthető meg");
    update_event($event_id, [
        'confirmation_status' => 'confirmed',
        'confirmed_by'        => $actor,
        'confirmed_at'        => now(),
    ]);
    update_status_from_event($ev->device_id, 'check_in');   // → 'Ready to deploy'
}

function reject_check_in($actor, $event_id, $reason) {
    require_that(role_of($actor) >= 'storekeeper', "raktáros vagy it_admin kell");
    $ev = load_event($event_id);
    require_that($ev->confirmation_status === 'pending' && $ev->event_type === 'check_in',
        "csak függőben lévő visszavétel utasítható el");
    update_event($event_id, [
        'confirmation_status' => 'rejected',
        'confirmed_by'        => $actor,
        'confirmed_at'        => now(),
        'notes'               => append_note($ev->notes, "ELUTASÍTVA: {$reason}"),
    ]);
    // az esemény nem érvényesül → az aktuális állapot a check_in előtti marad
    set_status($ev->device_id, 'Deployed');
}
```

### 9.5 Peremesetek
- **Dupla leadás** — eszközönként legfeljebb egy nyitott (`pending`) check_in lehet. Ezt a `uq_one_pending_checkin_per_device` részleges UNIQUE index DB-szinten is kikényszeríti (`device_id` WHERE `confirmation_status = 'pending'`), így egy második pending visszavétel UNIQUE-sértést dob; a `move_asset` ezt előzetesen ellenőrzi és barátságos hibát ad.
- **Pending alatti egyéb mozgás** — amíg a check_in `pending`, a nézet szerint az eszköz még a felhasználónál van; egy újabb `move_asset` ennek megfelelően a felhasználótól indul. A raktáros előbb döntsön a függő tételről.
- **Raktáros saját visszavétele** — a raktáros/it_admin `check_in`-je azonnal `confirmed` (nincs külön ellenőrzési kör), mert ő maga az ellenőrző.

---

## 10. Felhasználói foglalás (reservation)

A felhasználók maguk foglalhatnak le egy szabad eszközt; a foglalás az elvitelig (`check_out`) fenntartja nekik. A `device_reservations` tábla mindig csak az aktív foglalásokat tartalmazza (a megszűnt foglalás sora törlődik). Lásd DATABASE_DOCUMENTATION.md §10.

### 10.1 `reserve_device(actor, device_id, notes=null)` — user / storekeeper / it_admin
1. Engedélyezés: bármely bejelentkezett felhasználó (≥ `user`).
2. **Lejárt sor takarítása** az adott eszközre, hogy egy be nem söpört lejárt foglalás ne blokkoljon: `DELETE FROM device_reservations WHERE device_id = :d AND expires_at <= now()`.
3. Ellenőrzés: az eszköz **szabad** — a `device_current_state` szerint nincs birtokosa (raktárban van), `status = 'Ready to deploy'`, és nincs aktív foglalása (`device_active_reservations`). Ha foglalt → barátságos hiba („már lefoglalva, lejár: …").
4. Egy sor beszúrása: `reserved_by = actor`, `reserved_at = now()`, `expires_at = now() + 3 nap`. A `uq_resv_one_per_device` UNIQUE egyidejű kettős foglalásnál is garantálja az exkluzivitást (a vesztes ágat barátságos hibára fordítjuk).
5. `devices.status = 'Reserved'`.

### 10.2 `cancel_reservation(actor, device_id)` — a foglaló vagy storekeeper / it_admin
1. Engedélyezés: az aktor a `reserved_by`, vagy szerepkör ≥ storekeeper.
2. A sor törlése: `DELETE FROM device_reservations WHERE device_id = :d`.
3. `devices.status = 'Ready to deploy'`.

### 10.3 Elvitel — `check_out` interplay (a `move_asset`-ben)
A `move_asset` `check_out` eseménynél, a beszúrás előtt:

1. Ha van **aktív foglalás** az eszközre (`device_active_reservations`):
   - engedélyezett az elvitel, ha az aktor a `reserved_by` **vagy** szerepköre ≥ `storekeeper`; egyébként **elutasítva** („másnak fenntartva").
2. Sikeres `check_out` után a foglalás **teljesült**: `DELETE FROM device_reservations WHERE device_id = :d`. (Az elvitelt a custody-esemény már rögzíti.)

> A meglévő `user` szabály változatlan: szabad eszközt magának kivehet. Az új réteg csak annyit tesz hozzá, hogy egy **lefoglalt** eszköz nem szabad más számára — a foglalónak és a raktárosnak viszont igen.

### 10.4 Lejárat — `expire_reservations()` (ütemezett)
1. Egy ütemezett job rendszeresen futtatja: `DELETE FROM device_reservations WHERE expires_at <= now()`.
2. Minden érintett eszköz `status`-a `'Ready to deploy'`-ra áll (ha közben nem vitték el).
3. A `device_active_reservations` nézet a takarítás között is helyes (`expires_at > now()` szűr), így a felhasználók sosem látnak lejárt foglalást aktívként.

### 10.5 Pszeudokód (kulcsrészletek)
```php
function reserve_device($actor, $device_id, $notes = null) {
    purge_expired_for($device_id);                 // DELETE ... expires_at <= now()
    $cur = current_state($device_id);
    require_that($cur->holder === null && $cur->department !== null
                 && status_of($device_id) === 'Ready to deploy',
        "csak szabad, raktárban lévő eszköz foglalható");
    require_that(active_reservation($device_id) === null, "az eszköz már le van foglalva");
    try {
        insert_reservation($device_id, $actor, now(), now() + days(3), $notes);
    } catch (UniqueViolation $e) {
        deny("az eszköz időközben lefoglalva");    // uq_resv_one_per_device
    }
    set_status($device_id, 'Reserved');
}

// a move_asset check_out ágában, a beszúrás előtt:
$resv = active_reservation($device_id);
if ($event_type === 'check_out' && $resv !== null) {
    require_that($resv->reserved_by === $actor || $role >= 'storekeeper',
        "az eszköz másnak van fenntartva");
}
// ... esemény beszúrása ...
if ($event_type === 'check_out') {
    delete_reservation($device_id);                // teljesült
}
```

### 10.6 Peremesetek
- **Foglalás + checkpoint** — a foglalás csak szabad (raktárban lévő) eszközre köthető; egy `pending` visszavétel alatt az eszköz a nézet szerint még a felhasználónál van, tehát nem foglalható, amíg a raktáros meg nem erősíti a visszavételt.
- **Versenyhelyzet** — két egyidejű foglalás esetén az UNIQUE(`device_id`) az egyiket elutasítja; az alkalmazás ezt barátságos „már lefoglalva" hibára fordítja.
- **Lejárt, de be nem söpört sor** — a `reserve_device` előbb takarít az adott eszközre (10.1/2. lépés), így nem blokkol; az aktív nézet amúgy is kiszűri.

---

## 11. Összefoglaló

Egyetlen kezelő (`move_asset`) írja az összes birtoklási előzményt, a korábbi birtokost a `device_current_state` nézetből vezeti le, és mindig bélyegzi az aktort; egy kis szerepkör-ellenőrzés (`user` < `storekeeper` < `it_admin`, a `clinic_users.auth`-ból) kapuz minden írást; a típusspecifikus űrlapok és ellenőrzés az `attribute_definitions`-ból jönnek; a `devices.status` pedig automatikusan követi a birtoklást. A helyeket a `departments` tábla tartalmazza (a `locations` helyszínre `locations_id`-vel hivatkozva). Az eredmény: egy rendes felhasználó teljes interakciója „beolvas, kattint kivétel/visszatétel", a raktáros viszi a regisztrációt és a készletmozgatást, az IT pedig a típus-/attribútumsémát; a felhasználói szerepkörök kiosztása a webalkalmazásé.