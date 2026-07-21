# Eszköznyilvántartás — függőségek

A hordozható eszköznyilvántartó két önálló résznek felel meg, saját
függőség-kezeléssel: a **frontend SPA** (npm) és a **PHP backend** (nincs
külső csomagkezelő).

## Frontend — `public/js/eszkoznyilvantartas-src/` (npm)

`package.json` alapján, telepített (`node_modules`) verziókkal.

### Direkt függőségek (`dependencies`)

| Csomag    | Deklarált | Telepített | Szerep |
|-----------|-----------|------------|--------|
| `qrcode`  | `^1.5.4`  | 1.5.4      | QR-kód generálás canvasre — a `#/scan/:tag` mélylinkes eszközcímkéhez (`src/ui/qrLabel.js`). Dinamikusan importálva (`import('qrcode')`), külön chunkba fordul. |
| `bootstrap` | `^5.3.8` | 5.3.8    | **Deklarálva, de a forráskódban sehol nincs importálva/használva.** A `store.js`-ben szereplő "bootstrap" szó a `/bootstrap` API-végpontra utal, nem a csomagra. Törölhető lenne, ha megerősítést nyer, hogy tényleg nem kell. |

### Fejlesztői függőségek (`devDependencies`)

| Csomag        | Deklarált  | Telepített | Szerep |
|---------------|------------|------------|--------|
| `vite`        | `^8.0.16`  | 8.0.16     | Dev szerver (proxy a PHP backendhez) és production build (`vite build` → `public/js/eszkoznyilvantartas/`). |
| `@types/node` | `^26.0.0`  | 26.0.0     | Node típusdefiníciók a `vite.config.js`-hez (path/fileURLToPath használat). |

### Jelentősebb tranzitív függőségek

| Csomag             | Verzió  | Kinek a függősége |
|--------------------|---------|--------------------|
| `@popperjs/core`   | 2.11.8  | `bootstrap` (ki nem használt csomag függősége) |
| `dijkstrajs`, `pngjs`, `yargs` | — | `qrcode` (kódolás + CLI-segédeszközei; a böngészőben futó kódnak csak a kódoló-logikára van szüksége) |
| `lightningcss`, `rolldown`, `esbuild`, `picomatch`, `postcss` stb. | — | `vite` belső build-eszközlánca (platformspecifikus bináris opcionális csomagokkal együtt) |

Nincs lockfile-ellenőrzés kikényszerítve a repóban — a pontos, teljes
függőségi fát a `npm ls --all` adja vissza a
`public/js/eszkoznyilvantartas-src/` mappában futtatva.

### Futásidejű, nem npm-es frontend függőség

- **Google Fonts (Inter)** — a build `index.html`-je közvetlenül a
  `fonts.googleapis.com` / `fonts.gstatic.com`-ról tölti be a betűtípust
  (`<link rel="preconnect">` + `<link rel="stylesheet">`). Éles, internet
  nélküli/izolált környezetben ez kiesés esetén csak a betűtípusra hat,
  az app működésére nem.

## Backend — `library/eszkoznyilvantartas/backend/` (PHP)

**Nincs Composer / külső PHP csomag.** A backend tisztán natív PHP (PDO,
`password_hash`, `hash_hmac`, natív session-kezelés) — nincs
`composer.json` és nincs `vendor/autoload` hivatkozás ebben a mappában.
Az egyetlen "függősége" a MariaDB/MySQL adatbázis-kiszolgáló (PDO
`mysql` driveren keresztül) és a PHP 8.2-es futtatókörnyezet (lásd
`API_README.md` — Architektúra).

Ez elkülönül a klinikai főoldal gyökerében lévő `composer.json`-tól
(pl. PHPMailer) — azt a főoldal más moduljai használják, az
eszköznyilvántartás backendje nem.

## Frissítés

Verziófrissítés után mindig futtatandó a `public/js/eszkoznyilvantartas-src/`
mappában:

```bash
npm install
npm run build   # → public/js/eszkoznyilvantartas/ (ezt kell FTP-zni éles frissítéskor)
```
