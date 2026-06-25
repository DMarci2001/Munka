# Eszköznyilvántartás

Hordozható eszköznyilvántartó rendszer a klinika számára — PHP REST API backend,
Vite-alapú frontend webapp, és a hozzá tartozó adatbázis-séma és dokumentáció.

## Repository structure

| Directory        | Tartalom                                                                 |
| ---------------- | ------------------------------------------------------------------------ |
| `backend/`       | PHP REST API — `config/`, `helpers/`, `lib/`, `index.php` belépési pont   |
| `frontend/`      | Vite webapp — `src/`, `public/`, `package.json`, `vite.config.js`         |
| `database/`      | SQL séma, nézetek, migrációk, seed, DBML, ER-diagram generátor és képek   |
| `documentation/` | Tervek, API leírás, adatbázis- és script-dokumentáció, QR-kód kézikönyv   |

## Setup

### Backend
```bash
cd backend
cp config/config.php.example config/config.php   # töltsd ki a saját DB/SSO értékeiddel
```
A `config/config.php` git-ignorált (élő hitelesítő adatokat tartalmaz).

### Frontend
```bash
cd frontend
npm install
npm run dev      # fejlesztői szerver (Vite)
npm run build    # éles build a dist/ mappába
```

### Database
A `database/` mappában:
- `schema_dev.sql` / `schema_integration.sql` — séma (fejlesztői / integrációs)
- `view-device_current_state.sql` — nézetek
- `migration_*.sql` — migrációk
- `seed_dev.php` — fejlesztői seed adatok
- `gen_er_diagram.py` — ER-diagram generátor (Pillow)
