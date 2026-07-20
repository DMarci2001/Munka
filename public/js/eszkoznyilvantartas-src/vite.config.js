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
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  // Relatív alapútvonal: a build bárhol kiszolgálható (pl. aldmappából,
  // /eszkoznyilvantartas_frontend/webapp/dist/ alól), az asset-ek és a
  // hash-alapú deep-linkek (QR: #/scan/:tag) így mindig feloldódnak.
  base: './',
  build: {
    // Egyetlen build-célhely: a ténylegesen kiszolgált, testvér public/js/eszkoznyilvantartas mappa.
    // A forrás (ez a mappa) és a build-kimenet így soha nem keveredik — emptyOutDir nem törölheti a forrást.
    outDir: path.resolve(__dirname, '../eszkoznyilvantartas'),
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost/eszkoznyilvantartas/backend',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
});
