import { getDevice } from '../state/store.js';
import { openModal } from './components.js';
import { esc } from '../lib/format.js';

// A QR mindig a /admin eszköznyilvántartás oldalra mutat (nem a nyers /js
// build útvonalra) — az admin oldal a "tag" paramétert továbbadja a beágyazott
// alkalmazásnak, ami közvetlenül az adott eszköz lapjára ugrik.
function deepLinkFor(assetTag) {
  return `${location.origin}/admin/index.php?page=eszkoz&tag=${encodeURIComponent(assetTag)}`;
}

function waitForImages(container) {
  return Promise.all([...container.querySelectorAll('img')].map((img) =>
    img.decode ? img.decode().catch(() => {}) : new Promise((res) => { img.onload = res; img.onerror = res; })));
}

async function buildQr(QRCode, url, sizePx) {
  const canvas = document.createElement('canvas');
  await QRCode.toCanvas(canvas, url, { width: sizePx, margin: 1, errorCorrectionLevel: 'M' });
  return canvas.toDataURL('image/png');
}

export async function dlgQrLabel(deviceId) {
  const dev = getDevice(deviceId);
  if (!dev) return;

  const url = deepLinkFor(dev.asset_tag);
  const { default: QRCode } = await import('qrcode');
  const dataUrl = await buildQr(QRCode, url, 480);

  openModal({
    title: `QR Címke · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    bodyHTML: `
      <div id="qr-label-area" style="display:flex;align-items:center;justify-content:center;gap:16px;padding:12px 0">
        <img src="${dataUrl}" alt="QR kód" style="width:240px;height:240px;display:block;flex:0 0 auto" />
        <div style="text-align:left">
          <div class="tag-mono" style="font-size:1.3rem;font-weight:600">${esc(dev.asset_tag)}</div>
          <div style="font-size:.9rem;color:#666;margin-top:4px">${esc([dev.manufacturer, dev.model].filter(Boolean).join(' '))}</div>
        </div>
      </div>`,
    confirmText: 'Nyomtatás',
    onConfirm: () => {
      printQrLabel(deviceId);
      return false;
    },
  });
}

// ---- Nyomtatás (mind az előnézeti modálból, mind a lista gyorsgombjából).
// A fizikai címke 50mm×25mm, fekvő (horizontális) tájolású. A QR a TELJES
// 25mm magasságot kitölti (nulla margó/padding — semmi nem vész el feleslegesen),
// jobbra pedig az eszközazonosító, amilyen kicsi méretben csak lehet, de
// TELJES egészében, csonkolás nélkül. A címke-elem SZÁNDÉKOSAN nem kap fix
// width/height-et (fit-content, mint egy inline-flex) — a TÉNYLEGESEN
// renderelt méretet mérjük meg és PONT azt írjuk elő @page size-ként, hogy a
// lap sose legyen nagyobb vagy kisebb a tartalomnál (ez zárja ki, hogy a QR
// a címke fizikai szélén túl nyomtatódjon). ----
export async function printQrLabel(deviceId) {
  const dev = getDevice(deviceId);
  if (!dev) return;

  const url = deepLinkFor(dev.asset_tag);
  const { default: QRCode } = await import('qrcode');
  const dataUrl = await buildQr(QRCode, url, 850);

  const label = document.createElement('div');
  label.id = 'qr-print-label';
  label.style.cssText = 'position:fixed;left:-9999px;top:0;display:inline-flex;align-items:center;gap:0.5mm;padding:0;font-family:Arial,sans-serif;background:#fff';
  label.innerHTML = `
    <img src="${dataUrl}" alt="QR kód" style="height:25mm;width:25mm;display:block;flex:0 0 auto" />
    <div style="flex:0 0 auto;font-weight:700;font-size:1.6mm;line-height:1;white-space:nowrap">${esc(dev.asset_tag)}</div>`;
  document.body.appendChild(label);

  await waitForImages(label);

  // Renderelt méret (px) → mm, hogy a @page pontosan a tartalom köré simuljon.
  const rect = label.getBoundingClientRect();
  const pxToMm = (px) => (px * 25.4) / 96;
  const wMm = pxToMm(rect.width).toFixed(1);
  const hMm = pxToMm(rect.height).toFixed(1);

  const style = document.createElement('style');
  // html/body margin/padding NULLÁZÁSA kötelező: a böngésző alap margója
  // (pl. 8px) a tartalmat a @page-hez képest eltolja, így a lapon kívülre
  // csúszik a bal/felső rész (a QR) — csak a jobb oldali szöveg maradna
  // látható. Enélkül a QR levágva jelenik meg nyomtatáskor.
  style.textContent = `@media print{html,body{margin:0!important;padding:0!important}@page{size:${wMm}mm ${hMm}mm;margin:0}body>*{display:none!important}#qr-print-label{position:static!important;left:auto!important;display:inline-flex!important}}`;
  document.head.appendChild(style);

  window.print();
  label.remove();
  style.remove();
}
