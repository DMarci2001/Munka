# Eszköznyilvántartás — 2026-07-22 feature-csomag részletes terv

Ez a dokumentum a 2026-07-22-i megbeszélésen felmerült 8 pontot dolgozza ki
végrehajtható lépésekre. A pontok egymásra épülő sorrendben szerepelnek —
lásd az "Ajánlott végrehajtási sorrend" c. részt a végén.

Feltárt architektúra (a terv ez alapján készült):

- Backend: `library/eszkoznyilvantartas/backend/lib/{Ops,Auth,Roles,Repo,Repo}.php`,
  router: `library/eszkoznyilvantartas/backend/index.php`.
- Szerepkörök: `Roles::fromUserRow()` — `users.permissions.jog_eszkoznyilvantartas_admin`
  flag + `users.jogosultsag` (0/1/2) kombinációjából derivált `user|storekeeper|it_admin`,
  session-be írva (`Auth::role()`).
- Állapotgép: 7 státusz (`Kivehető,Kiadva,Lefoglalva,Visszavétel folyamatban,
  Szerviz alatt,Elveszett,Selejtezve`), 8 esemény-típus a
  `eszkoznyilvantartas_device_custody_events` táblában, `confirmation_status`
  oszloppal (`pending|confirmed|rejected`) — **ma kizárólag `check_in`
  eseménynél engedett a `pending`/`rejected` állapot** (DB CHECK constraint
  `chk_custody_pending_only_checkin`).
- Frontend: `public/js/eszkoznyilvantartas-src/src/` (Vite build →
  `public/js/eszkoznyilvantartas/`), állapot: `state/store.js`, dialógusok:
  `ui/actions.js`, nézetek: `views/{inventory,myDevices,pending}.js`.
- Fő oldal sidebar (a bejelentkezős admin felület menüje, NEM az
  eszköznyilvántartás SPA-ja): DB-vezérelt `adminmenu` tábla,
  `library/AdminPage.php::_menuColumn()` renderel, kattintás-logika
  `public/admin/js/ajax.js::toggleSubMenu()`.

---

## 1. Sidebar — azonnali almenü-megjelenítés

**Jelenlegi állapot:** a fő admin sidebar `adminmenu` tábla `id=55` sora (az
"Eszköznyilvántartás" szülő-menüpont) `pageid`-ja korábban `'eszkoz'` volt —
ez azt jelentette, hogy rákattintva a böngésző **navigált** (nem toggle-olt),
tehát az almenü sosem nyílt ki kattintásra, csak ha valaki korábban már
külön kinyitotta és a `$_SESSION["opensubmenu"]` ezt megjegyezte.

Már létezik egy **le nem futtatott migráció**, ami pontosan ezt javítja:
`library/eszkoznyilvantartas/database/migration_2026-07-21_admin_menu.sql`
— ez `pageid='#'`-re állítja az id=55 sort, aminek hatására
`AdminPage.php::_menuColumn()` a `toggleSubMenu({$menu["id"]})` JS hívást
köti a kattintáshoz (`AdminPage.php:224-227`), ami `public/admin/js/ajax.js`
`toggleSubMenu()` függvényén keresztül **azonnal** (`slideToggle("fast")`,
kliens-oldalon, AJAX-perzisztálás csak utólag) kinyitja az almenüt.

### Teendők
1. **Ellenőrizni**, hogy a migráció le lett-e már futtatva az élő
   adatbázison (phpMyAdmin: `SELECT pageid FROM adminmenu WHERE id=55`).
   Ha nem `'#'`, futtatni kell (minden telephely saját admin DB-jén külön —
   lásd a migrációs fájl fejléce).
2. Ha a migráció után is marad olyan eset, hogy **első kattintásra** nem
   nyílik ki (pl. mert a submenu HTML csak akkor rendelődik, ha
   `!empty($menu["submenu"])` — ez rendben van, 4 gyerek sora már megvan),
   nincs több backend teendő — a jelenlegi `toggleSubMenu()` már az első
   kattintásra azonnal `slideToggle`-öl, tehát ez a pont a migráció
   lefuttatásával lezárható.
3. *(Opcionális, nem blokkoló)* — a `ADMIN_SIDEBAR_SCROLL_PLAN.md`-ben már
   leírt "aktív almenü automatikus kinyitása lapbetöltéskor" logika
   (`_menuColumn()`-ban a `$hasActiveChild` ellenőrzés) még nincs
   implementálva — ha az a UX-cél is, hogy közvetlen linkről érkezve is
   nyitva legyen az almenü, ezt is érdemes egyben megcsinálni, de ez nem
   volt kifejezett kérés most.

**Érintett fájlok:** `library/eszkoznyilvantartas/database/migration_2026-07-21_admin_menu.sql`
(futtatás), esetleg `library/AdminPage.php` (ha az opcionális pontot is kéred).

---

## 2. Szerepkör-alapú 'Kivétel' jogosultság

**Cél:** a `check_out` (Kivétel) funkció ne legyen alapból elérhető sima
usernek — jogosultsághoz kötött legyen, hogy mely szerepkörnél jelenik meg.
Alapból egy sima user csak **leadni (check_in), átadni (transfer) és
foglalni (reserve)** tudjon.

**Jelenlegi állapot:** `Ops::moveAsset()` (`Ops.php:98-189`) ma **mindenkinek**
engedi a `check_out`-ot (a role-ellenőrzés csak azt korlátozza, hogy sima
user csak magának vehet ki szabad készletről — l. inline logika, nincs
`requireRole()` hívás check_out-ra). A frontend oldalon a `dlgCheckOut`
gomb (`ui/actions.js:62`) minden device-listán megjelenik, nincs
`roleAtLeast` kapu rá (`views/inventory.js` csak a "Raktárkezelés" csoportot
és az "Új eszköz" gombot rejti storekeeper alá, a Kivétel gombot nem).

Mivel a kérés **"legyen jogosultsághoz kötött, hogy melyik usernél jelenik
meg"** — tehát nem egyszerű role-szint (`user < storekeeper < it_admin`)
alapú tiltás kell, hanem egy **külön, finomabb kapcsoló**, ami akár
sima user szinten is egyénileg beállítható ("ennek a sima usernek IGEN,
annak NEM"). Ez nem fér bele a jelenlegi 3-szintű rang-modellbe.

### Terv

1. **Új jogosultsági flag** a meglévő `users.permissions` JSON-blobban,
   ugyanabban a mintában mint `jog_eszkoznyilvantartas_admin`:
   `jog_eszkoznyilvantartas_kivetel` (bool). Nincs szükség új táblára/oszlopra,
   a meglévő JSON-permission-rendszert bővítjük (konzisztens a
   `Roles::fromUserRow()` mintával).
2. **`Roles.php`**: `fromUserRow()` mellé egy különálló
   `Roles::canCheckOut(array $userRow): bool` helper — storekeeper+ mindig
   `true` (nekik amúgy is szabad minden), sima usernél a fenti flag dönt
   (alapértelmezett `false`, ha a flag hiányzik → biztonságos default).
3. **Backend (`Ops::moveAsset`)**: `check_out` eseménytípusnál, ha
   `Auth::role() === 'user'`, ellenőrizni `Roles::canCheckOut($currentUserRow)`
   — ha `false`, `OpError`-t dobni (403-szerű üzenet: "Nincs jogosultságod
   eszköz kivételéhez.").
4. **Frontend**: a session/bootstrap payload-ba (`h_bootstrap`,
   `Ops.php`/`index.php:151-163`) fel kell venni egy `can_check_out: bool`
   mezőt a currentUser objektumba; `store.js`-ben egy `canCheckOut()`
   getter; `views/inventory.js`-ben és bárhol, ahol a Kivétel gomb
   megjelenik, ezzel kapuzni (`v-if`-szerű feltétel, mint a meglévő
   `isStore` minta).
5. **Admin UI a flag beállításához**: valahol (pl. a meglévő
   `library/pages_admin/AdminEszkoz*Page.php` egyikén vagy egy új admin
   felhasználó-szerkesztő mezőn) kell egy checkbox, amivel a storekeeper/
   admin egyedi usereknél be/kikapcsolhatja ezt — anélkül nincs mód a
   flag módosítására. Ezt a meglévő user-szerkesztő admin oldalon (ahol a
   `jog_eszkoznyilvantartas_admin` is szerkeszthető, ha van ilyen felület)
   érdemes hozzáadni ugyanoda.

**Érintett fájlok:** `Roles.php`, `Ops.php` (`moveAsset`), `index.php`
(bootstrap payload), `store.js`, `views/inventory.js`, admin
user-szerkesztő oldal (pontos fájl a `jog_eszkoznyilvantartas_admin` admin
UI-jának feltérképezésével azonosítandó — ezt még nem néztem meg,
implementáció előtt meg kell keresni).

---

## 3. Átadás (transfer) megerősítési folyamat

Ez a legnagyobb, sémát is érintő pont. **Ma a `transfer` esemény azonnal,
megerősítés nélkül lezajlik** (`confirmation_status='confirmed'` rögtön) —
nincs "fogadó elfogadja/elutasítja" lépés. A meglévő pending/reject
mechanizmus ma **kizárólag** a `check_in` eseményre van a DB-sémában
engedélyezve (`chk_custody_pending_only_checkin` CHECK: `confirmation_status
= 'confirmed' OR event_type = 'check_in'`).

### 3a. Adatbázis-migráció

Új migrációs fájl (`migration_2026-07-2X_transfer_pending.sql`), a meglévő
`schema_dev.sql`-lel összhangban:

```sql
ALTER TABLE eszkoznyilvantartas_device_custody_events
  DROP CONSTRAINT chk_custody_pending_only_checkin,
  ADD CONSTRAINT chk_custody_pending_only_checkin CHECK
    (confirmation_status = 'confirmed' OR event_type IN ('check_in','transfer'));
```

A meglévő `uq_one_pending_checkin_per_device` UNIQUE (a virtuális
`pending_device_id` oszlopon) **változatlanul jó marad** — ez eseménytípustól
függetlenül csak azt garantálja, hogy egy device-nak egyszerre max. 1
pending (bármilyen típusú) eseménye lehet, ami pontosan a kívánt viselkedés
(nem lehet egyszerre pending check-in ÉS pending transfer).

Új eszközstátusz: **`'Átadás folyamatban'`** — fel kell venni a 3 helyre,
ahol a 7 státusz listázva van:
- `schema_dev.sql` CHECK constraint a `status` oszlopon (~124-126. sor)
- `Ops.php` `TERMINAL_STATUSES`-hoz **hasonló** konstans-lista (de ez nem
  terminal, hanem a `Repo::effectiveStatus()` precedencia-láncába kell
  beilleszteni — l. lent)
- `views/inventory.js:15` `STATUSES` tömb.

### 3b. Backend logika

- **`Ops::moveAsset()`**: `transfer` eseménynél, ha a küldő szerepköre
  `'user'` (storekeeper+ átadása maradjon azonnali/megerősítés nélküli,
  ahogy ma is — ez konzisztens azzal, hogy a raktáros/admin művelete
  bizalmi), állítsd `confirmation_status='pending'`-re, és NE módosítsd a
  `Repo::currentState()` által látott holder/location-t (a pending event
  `to_user_id` mezője csak "javasolt" cél, amíg nincs megerősítve — pont
  úgy, ahogy a pending check_in is működik ma: a `Repo::currentState()`
  csak a legutóbbi **confirmed** eventet nézi).
- Az eszköz `status`-a legyen `'Átadás folyamatban'`, amíg pending.
- **Új végpont/metódus**: a meglévő `confirmCheckIn`/`rejectCheckIn` mintáját
  követve, de a **fogadó user** (nem storekeeper!) hívhassa:
  `Ops::confirmTransfer(int $eventId)` / `Ops::rejectTransfer(int $eventId,
  ?string $reason)`. Jogosultság-ellenőrzés: a hívó `Auth::userId()` legyen
  egyenlő az esemény `to_user_id`-jével (VAGY storekeeper+, mert nekik a
  "felülbírálás" jogát is meg kell adni — l. lent).
  - `confirmTransfer`: `confirmation_status='confirmed'`, `confirmed_by`,
    `confirmed_at`; device status → a `transfer` cél alapján normál
    `statusFromEvent('transfer')` = `'Kiadva'`.
  - `rejectTransfer`: `confirmation_status='rejected'`, `reason` a
    notes-hoz; device státusz **visszaáll** a küldő korábbi állapotára
    (ez a `returnFromRepair`/`markFound` pending-override mintájához
    hasonlóan a `from_user_id`/`from_locations_id`/`from_departments_id`
    alapján — mivel a currentState még mindig a küldőt mutatja, elég a
    device_status-t `'Kiadva'`-ra tenni, custody nem változik, mert a
    pending event nem volt "confirmed", tehát a `currentState()` már
    eleve a küldőt látta holdernek — a művelet valójában csak a
    stray `status='Átadás folyamatban'` visszaállítása normál
    `'Kiadva'`-ra).
- **Storekeeper felülbírálás** (a kérés szerint: *"legyen lehetősége
  felülbírálni a visszautasítást(ha elfogadja a visszautasítást, akkor
  végbemegy, ha nem fogadja el, akkor pedig az átadás megy végbe)"*):
  ez egy **második döntési kör** egy már `rejected` státuszú eseményen.
  - Új metódus: `Ops::resolveRejectedTransfer(int $eventId, bool
    $acceptRejection)`, `requireRole('storekeeper')`.
    - `$acceptRejection === true` → nincs teendő az eseményen (a rejected
      állapot marad "lezárva" — l. 3c: hogyan tűnik el a listából),
      csak egy `resolved_by`/`resolved_at` pár jelöli, hogy a storekeeper
      "tudomásul vette".
    - `$acceptRejection === false` → a storekeeper **felülbírálja a
      fogadót**: az eredeti átadás mégis végbemegy — ez gyakorlatilag egy
      **új** `transfer` eseményt kell létrehozni ugyanazokkal a
      to_*-paraméterekkel, de `confirmation_status='confirmed'` (mivel a
      storekeeper döntése authoritatív, a fogadó jóváhagyása már nem
      szükséges), az eredeti `rejected` eseményt pedig meg kell jelölni
      "resolved"-ként (hogy ne maradjon örökre az Ellenőrzésre-vár listán).
  - Ehhez **két új oszlop** kell a custody-events táblába:
    `resolved_by INT NULL REFERENCES users(id)`, `resolved_at DATETIME NULL`
    — így egy `rejected` esemény lehet "függő" (resolved_at IS NULL) vagy
    "lezárt" (resolved_at IS NOT NULL), ez adja a 3c pontban leírt
    "amíg nem változik újra" feltételt.

### 3c. "Ellenőrzésre vár" — összevont lekérdezés (ld. 4. pont is)

Az `Lookups::pendingCheckins()`-hoz hasonló új/bővített lekérdezés, ami két
forrásból UNION-öl:
1. `confirmation_status='pending' AND event_type='check_in'` (ma is
   megvan — storekeeper-nek szóló, "sima user leadta, várja a raktáros
   jóváhagyását").
2. `confirmation_status='rejected' AND event_type='transfer' AND
   resolved_at IS NULL` (új — "a fogadó visszautasította az átadást, várja
   a storekeeper döntését").

Mindkettő ugyanabba a listába kerül a frontend felé, egy `kind` mezővel
megkülönböztetve (`'pending_checkin' | 'rejected_transfer'`), hogy a UI
eldönthesse, melyik akciógombokat (Elfogad/Elutasít vs.
Visszautasítás-elfogadása/Felülbírálás) mutassa soronként.

**Érintett fájlok:** új migráció (`database/migration_2026-07-2X_transfer_*.sql`),
`Ops.php` (`moveAsset`, új `confirmTransfer`/`rejectTransfer`/
`resolveRejectedTransfer`), `Repo.php` (`effectiveStatus()` — új precedencia-
ág `'Átadás folyamatban'`-hoz), `Lookups.php` (összevont lekérdezés),
`index.php` (3 új route), `store.js`, `views/pending.js`, `views/inventory.js`
és `views/myDevices.js` (`STATUSES` konstans mindenhol).

---

## 4. 'Ellenőrzésre vár' menü összevonása

Lásd 3c fent — ez technikailag a 3. pont része (az adatforrás összevonása),
a UI oldalon (`views/pending.js`) pedig:
- A táblázat ma csak pending check_in sorokat listáz storekeeper-nek szánt
  Elfogad/Elutasít gombokkal.
- Bővítendő: a `kind==='rejected_transfer'` sorokhoz külön akció-oszlop:
  **"Visszautasítás elfogadása"** (→ `resolveRejectedTransfer(id, true)`) és
  **"Felülbírálás — átadás mégis végbemegy"** (→
  `resolveRejectedTransfer(id, false)`), jól megkülönböztethető vizuálisan
  (pl. eltérő badge/szín) a sima pending check-in soroktól.

**Érintett fájl:** `views/pending.js`.

---

## 5. 'Nálam' → 'Eszközeim' átnevezés + váró elfogadások felül

**Fájl:** `views/myDevices.js` (l. a research 5. pontja).

1. **Átnevezés**: minden UI-szöveg ("Nálam" → "Eszközeim") — nav label
   (`appshell.js` `renderNav()`), oldalcím (`myDevices.js` fejléc), route
   maradhat `/my` (belső azonosító, nem UI-szöveg, nem kell átnevezni).
2. **Új szekció a lap tetején**: "Rám váró átvételek" — azok a pending
   `transfer` eventek, ahol `to_user_id === me.id`. Ehhez:
   - `store.js`: új getter, pl. `getMyPendingTransfers()` — a bootstrap/
     pending payloadból szűrve (vagy egy dedikált `GET
     transfers/pending-for-me` végpont, ha a teljes pending lista nem
     tartalmazza más userek eseményeit adatvédelmi okból — ezt érdemes
     backend oldalon szűrve visszaadni, ne a teljes listát küldjük le és
     szűrjünk kliens oldalon).
   - `myDevices.js`: harmadik tábla-blokk a jelenlegi kettő (`held`,
     `reserved`) **elé** beszúrva, soronként **Elfogad** / **Elutasít**
     gombbal (→ `confirmTransfer`/`rejectTransfer`), a device adatai
     (asset_tag, típus, küldő neve) megjelenítve.

**Érintett fájlok:** `views/myDevices.js`, `state/store.js`, `appshell.js`
(nav label), `ui/actions.js` (ha a Elfogad/Elutasít modális dialógust kap,
pl. elutasítás-indok bekérésére — a `dlgRejectCheckIn` mintáját követve).

---

## 6. Nulla sidescroll — az eszközlista mindig teljes egészében látszódjon

**Frissített cél (2026-07-23):** ne csak a duplikált scrollbar tűnjön el —
**egyáltalán ne legyen** vízszintes scroll az eszközlistánál. A táblázat
mérete (effektíve: a benne lévő szöveg/oszlopok skálája) igazodjon
automatikusan a rendelkezésre álló szélességhez (böngésző-zoom, ablakméret),
úgy hogy mind a 8 oszlop mindig egyszerre, teljes egészében látható legyen.

**Miért nem elég a puszta CSS-fix:** a korábban tervezett
`.content { min-width:0; overflow-x:hidden }` (l. az eredeti "Ok" leírást
lentebb) csak a **duplikált** scrollbart szüntetné meg — de ha a
`.table-wrap` tartalma (8 oszlop: utolsó módosítás, leltári szám,
típus+gyártó/modell, státusz-badge, birtokos, helyszín, nyíl-ikon,
"Nyomtatás" gomb — `views/inventory.js` `rowHTML()` 223-236. sor) szélesebb,
mint a rendelkezésre álló hely, `overflow-x:hidden`-nel a tartalom
egyszerűen **levágódna** — ez rosszabb, mint a scrollbar. A "sose legyen
scroll, mindig látszódjon minden" cél csak úgy érhető el, ha a táblázat
ténylegesen **összezsugorodik**, hogy beférjen.

### Javasolt megoldás: auto-fit skálázás (`transform: scale()`)

Új segédmodul: `ui/fitToWidth.js`, amit minden táblázatot renderelő nézet
(`inventory.js`, `myDevices.js`, `pending.js`) meghív a saját `.table-wrap`
elemére a táblázat (újra)festése után:

```js
const MIN_SCALE = 0.55; // e alatt inkább scroll, mint olvashatatlan szöveg

export function fitTableToWidth(wrapEl) {
  const table = wrapEl.querySelector('table');
  if (!table) return;
  // 1) mérés természetes (skálázatlan) méretben
  table.style.transform = 'none';
  wrapEl.style.height = 'auto';
  wrapEl.style.overflowX = 'hidden';
  const naturalWidth  = table.scrollWidth;
  const naturalHeight = table.scrollHeight;
  const available = wrapEl.clientWidth;
  const scale = Math.min(1, available / naturalWidth);

  if (scale < MIN_SCALE) {
    // Túl keskeny lenne a szöveg — visszaváltás a régi scroll-viselkedésre,
    // a táblázat a saját természetes méretében marad.
    table.style.transform = 'none';
    wrapEl.style.height = 'auto';
    wrapEl.style.overflowX = 'auto';
    return;
  }

  table.style.transformOrigin = 'top left';
  table.style.transform = `scale(${scale})`;
  wrapEl.style.height = (naturalHeight * scale) + 'px';
}
```

- `ResizeObserver(wrapEl)` + `window.addEventListener('resize', ...)` hívja
  újra `fitTableToWidth`-et — ez fedezi mind az ablakméret-változást, mind a
  böngésző-zoomot (zoom hatására a `clientWidth`/`scrollWidth` arány
  megváltozik, a ResizeObserver ezt észleli).
- Minden olyan helyen újra kell hívni, ahol a táblázat tartalma
  változik: szűrés, rendezés, lapozás után (`inventory.js` `paint()`
  155. sor végén, a `myDevices.js`/`pending.js` megfelelő render-
  függvényeiben).
- **`.table-wrap`-ról törölhető** a statikus `overflow-x:auto`
  (styles.css:176, 433) — ezt mostantól a `fitTableToWidth()` állítja be
  dinamikusan (`hidden` normál esetben, `auto` a küszöb alatti fallback
  esetén), l. lent.

### Döntés (2026-07-23): minimum scale-küszöb + scroll-fallback

A két felvetett opció közül a **minimum scale-küszöb** mellett döntöttünk:
`MIN_SCALE = 0.55` (finomhangolható implementáció közben). E fölött a
táblázat mindig zsugorodik, hogy beférjen — nincs scroll. E alatt (nagyon
keskeny nézet, pl. telefon álló módban) a `fitTableToWidth()` visszaváltja
a `.table-wrap`-ot a régi, természetes méretű + `overflow-x:auto`
viselkedésre, tehát ott marad a scrollbar, de a szöveg olvasható méretű
marad. Ez a logika a fenti `fitTableToWidth()` kódrészletben már szerepel.

**Érintett fájlok:** új `public/js/eszkoznyilvantartas-src/src/ui/fitToWidth.js`,
`views/inventory.js`, `views/myDevices.js`, `views/pending.js` (mindhárom
helyen a render/paint-függvény végén meghívva), `styles.css` (a
`.table-wrap` `overflow-x:auto` szabály törlése/módosítása —
pontos fájlút build közben azonosítandó, l. eredeti megjegyzés).

Ez a pont **független** a többitől, bármikor beilleszthető, akár elsőként —
de a fenti korlát-kérdésre választ igényel, mielőtt kódolás indul.

<details>
<summary>Eredeti (2026-07-22-i) elemzés a duplikált scrollbar okáról — a fenti megoldás ezt is kiváltja, de a diagnózis még releváns</summary>

**Ok** (l. research 6. pont): `.table-wrap { overflow-x:auto }`
(`styles.css:176`) a szándékos belső scroll, de a `.content` konténeren
(`styles.css:112-116`) nincs `overflow-x` szabály és nincs `min-width:0`,
így ha a táblázat szélesebb, mint a rendelkezésre álló hely, a túlcsordulás
a `.content`/`.main` fölé, végül a dokumentum-body-ra buborékol, és ott
keletkezik egy **második, külső** vízszintes scrollbar.

</details>

---

## 7. Tömeges műveletek (batch checkout / batch átadás / batch check-in)

Mivel **ma sehol nincs checkbox-os sor-kiválasztás** a kódbázisban (l.
research 6. pont — nulla találat `checkbox`-ra), ezt egy közös,
újrahasznosítható mintaként érdemes megépíteni, amit mindhárom
(kivétel/átadás/check-in) felhasznál.

### 7a. Közös "kijelölés-mód" komponens

Új segédmodul, pl. `ui/bulkSelect.js`:
- `enterBulkMode(listRootEl, devices)` — beszúr egy checkbox oszlopot a
  táblázat elejére (`thHTML`/`rowHTML` mintájára `inventory.js:26-32,
  223-236` és `myDevices.js` megfelelő helyein), és egy state-Set-et vezet
  a kijelölt `device_id`-król.
- Egy fix "akciósáv" (sticky, a táblázat felett vagy alatt), ami
  élőben mutatja a kijelölt eszközök listáját (ahogy a kérés írja:
  *"a 'Felülvizsgálandó eszközök' mező helyén megjelennek a kiválasztott
  eszközök"* — ez egy már létező UI-elem neve, meg kell találni a
  jelenlegi kódban hol/mi ez pontosan, mielőtt implementálunk rá építve;
  a research ezt nem azonosította konkrét fájlként, tisztázandó pont).
- Egy "Véglegesítés" gomb, ami a kijelölt ID-kat egy **batch API hívásba**
  csomagolja.
- **Fontos megkötés**: bulk-módban a lista maradjon végig szűrhető — a
  meglévő szűrők/keresőmező (`views/inventory.js` jelenlegi szűrő-UI-ja)
  ne tűnjön el és ne inaktiválódjon, amíg a checkbox-os kijelölés aktív.
  A kijelölt `device_id`-k halmazát **függetlenül** kell tárolni attól,
  hogy épp mely sorok látszanak a szűrt nézetben — tehát ha a user
  kijelöl 3 eszközt, utána szűr egy másik kategóriára (a kijelöltek egy
  része eltűnik a látható listából), majd visszavált az eredeti szűrőre,
  a 3 korábbi kijelölés **megmaradjon** (a Set state nem a DOM-sorokhoz,
  hanem a `device_id`-khoz kötött). Az akciósávon érdemes mindig mutatni
  az összes kijelölt eszközt (nem csak az aktuálisan látszó szűrt
  részhalmazt), hogy a user lássa, mi van "láthatatlanul" is kiválasztva.

### 7b. Batch backend végpontok

A meglévő egyesével hívható `Ops::moveAsset`/`sendToRepair`/stb. metódusok
**tranzakción belüli ciklusban** hívhatók egy új batch-wrapper metódusból,
**nem kell újraírni a mag logikát**:

- `Ops::batchCheckOut(array $deviceIds, int $toUserId, ?int $toLoc, ...)`—
  egy `$db->beginTransaction()` alatt minden device-ra meghívja a meglévő
  `moveAsset` logika device-specifikus részét (érdemes a `moveAsset` belső
  törzsét egy `moveAssetOne()`-re kiszervezni, amit mind az egyes, mind a
  batch végpont hív — elkerülve a duplikációt).
- `Ops::batchTransfer(array $deviceIds, int $toUserId, ...)`,
  `Ops::batchCheckIn(array $deviceIds, ...)` hasonlóan.
- Minden batch metódus **részleges sikert** is kezelnie kell (pl. egy
  device időközben már nem elérhető) — vagy all-or-nothing tranzakció
  (egy hiba miatt minden rollback), vagy soronkénti eredmény-lista
  (`{device_id, ok, error}[]`) — **ez eldöntendő kérdés a felhasználóval
  implementáció előtt**, mert UX-hatása van (ha egy eszköz már nem
  vehető ki, a többi is elbukjon, vagy csak azt az egyet jelezze
  hibásnak?).
- Router (`index.php`): 3 új `POST` route, pl. `devices/batch-check-out`,
  `devices/batch-transfer`, `devices/batch-check-in`.

### 7c. 'Tömeges kivétel' gomb — eszközlista teteje

`views/inventory.js`: gomb a listafejlécbe (a "Új eszköz bevitele" gomb
mintájára, `isStore` helyett minden jogosult userre — l. 2. pont
`canCheckOut` flag), ami aktiválja a 7a bulk-select módot ezen a nézeten,
`prefer` szűrő nélkül (minden `'Kivehető'` státuszú sor kijelölhető).
A meglévő lista-szűrők (kategória/keresés — `inventory.js` jelenlegi
szűrő-UI-ja) bulk-módban is **teljesen működőképesek maradnak**: a
checkbox-oszlop csak egy plusz `<td>` a már szűrt/renderelt sorokhoz,
a szűrés maga a `rowHTML`-t megelőző lista-összeállításon fut, tehát nem
kell külön logika hozzá — csak ügyelni kell rá, hogy a bulk-select
komponens (7a) a kijelöléseket ne a szűrt render-listából, hanem a
teljes device-halmazból azonosított ID-k alapján tartsa nyilván (l. 7a
fenti kiegészítés).

### 7d. 'Tömeges átadás' gomb — 'Eszközeim' teteje

`views/myDevices.js`: gomb a "Eszközök a birtokomban" tábla felett,
ugyanaz a bulk-select minta, de csak a `held` listán (a user csak azt
adhatja át tömegesen, ami nála van), a végén egy célfelhasználó-választó
és a 7b `batchTransfer` hívása — **ez a küldő oldalról a 3. pont
megerősítési workflow-ját is kiváltja**, tehát a batch-átadott eszközök is
egyenként `'Átadás folyamatban'` állapotba kerülnek, amíg a fogadó (vagy
storekeeper) el nem fogadja/utasítja mindegyiket.

### 7e. Batch check-in

A kérésben ("batch check-in, checkoutot tegyem bele, annál nincs QR-kód")
ez egy negyedik, önálló belépési pont — valószínűleg egy dedikált nézet
vagy modal, ahol a user QR-szkennelés **nélkül**, listából választva több
eszközt egyszerre ad le. Ugyanazt a 7a mintát és `batchCheckIn` (7b)
végpontot használja, csak a hozzáférési pont különbözik (nem eszközönkénti
gomb, hanem egy önálló "Tömeges leadás" akció, elhelyezése tisztázandó —
önálló nav-item legyen, vagy a 'Eszközeim' oldal `held` listájának egy
második bulk-módja "Tömeges átadás" mellett?). **Ezt a felhasználóval
pontosítani kell implementáció előtt** (a leírás nem egyértelmű, hogy ez
külön UI-belépési pont-e, vagy a 7d ugyanazon gombjának egy második
üzemmódja transfer helyett check-in céllal).

**Érintett fájlok:** új `ui/bulkSelect.js`, `Ops.php` (`moveAssetOne`
kiszervezés + 3 batch metódus), `index.php` (3 route), `store.js` (batch
wrapper hívások), `views/inventory.js`, `views/myDevices.js`.

---

## Ajánlott végrehajtási sorrend

| # | Lépés | Függ ettől |
|---|-------|-----------|
| 1 | Sidebar migráció lefuttatása | — |
| 6 | Sidescroll CSS-fix | — (bármikor beilleszthető) |
| 2 | `canCheckOut` jogosultsági flag | — |
| 3 | Átadás megerősítési workflow (séma + backend + minimál UI) | 2 (role-ellenőrzésekhez) |
| 4 | 'Ellenőrzésre vár' összevonás | 3 |
| 5 | 'Eszközeim' átnevezés + váró-elfogadások szekció | 3 |
| 7 | Batch műveletek (checkout/átadás/check-in) | 2, 3, 5 |

**Tisztázandó pontok implementáció előtt** (ezekre válasz kell, mielőtt
kódolás indul):
- 2. pont: hol legyen az admin UI a `canCheckOut` flag be/kikapcsolásához
  (melyik meglévő admin oldalra kerüljön a checkbox)?
- 3b. pont: a storekeeper felülbírálásnál elfogadjuk-e a tervezett
  "új transfer-eseményt létrehozunk" megoldást, vagy inkább az eredeti
  rejected eseményt módosítanánk helyben?
- 7a. pont: mi pontosan a "Felülvizsgálandó eszközök" mező a mai UI-ban
  (nem található ilyen néven jelenleg) — új elem, vagy létező komponens
  átnevezése/újrafelhasználása?
- 7b. pont: batch hiba-kezelés — all-or-nothing vs. soronkénti eredmény?
- 7e. pont: a batch check-in önálló nav-item legyen, vagy a 'Eszközeim'
  batch-átadás gombjának második módja?
