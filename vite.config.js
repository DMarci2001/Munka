// ============================================================
// Vite konfiguráció — dev proxy a PHP backendhez.
//
// A böngésző azonos-origin `/api/...` hívásokat lát; a Vite ezeket a
// helyi Apache-on futó API-ra továbbítja. Így nincs CORS, és a
// munkamenet-süti magától működik fejlesztés közben.
//
// Éles, beágyazott build esetén az app a VITE_API_BASE (vagy a
// beágyazott alapértelmezett) útvonalat hívja — a proxy nem játszik.
// ============================================================

import { defineConfig } from 'vite';

export default defineConfig({
  // Relatív alapútvonal: a build bárhol kiszolgálható (pl. aldmappából,
  // /eszkoznyilvantartas_frontend/webapp/dist/ alól), az asset-ek és a
  // hash-alapú deep-linkek (QR: #/scan/:tag) így mindig feloldódnak.
  base: './',
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost/eszkoznyilvantartas_api',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
});
