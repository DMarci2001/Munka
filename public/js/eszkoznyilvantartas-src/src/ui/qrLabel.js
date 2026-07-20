import { getDevice } from '../state/store.js';
import { openModal } from './components.js';
import { esc } from '../lib/format.js';
import logoUrl from '../assets/hmm_logo.png';

// Saját, egyszerű "URL-rövidítő" — nincs külső szolgáltatás (pl. TinyURL) és
// nincs átirányítás: a rövid link MAGA a végcél, csak egy sokkal rövidebb
// alakban. A kód az eszköz numerikus azonosítójának base36 kódolása; a
// /a.php (site gyökér) ezt egyetlen adatbázis-lekérdezéssel visszafejti
// eszközazonosítóra, és rögtön le is rendereli az admin eszközoldalt (lásd
// public/a.php). Ez a rövidség kell ahhoz, hogy a QR alacsonyabb verziójú
// (kevesebb modulos) legyen, és a modulok elég nagyok maradjanak ahhoz, hogy
// a teljes HMM-logó a közepébe férjen anélkül, hogy a QR olvashatatlanná válna.
function shortCodeFor(deviceId) {
  return deviceId.toString(36);
}
function deepLinkFor(deviceId) {
  return `${location.origin}/a.php?${shortCodeFor(deviceId)}`;
}

function waitForImages(container) {
  return Promise.all([...container.querySelectorAll('img')].map((img) =>
    img.decode ? img.decode().catch(() => {}) : new Promise((res) => { img.onload = res; img.onerror = res; })));
}

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
}

// KÍSÉRLET: a teljes HMM-logót sütjük a QR közepébe, hogy lássuk, a telefonos
// olvasók így is felismerik-e. 'H' szintű hibajavítás kell (a legmagasabb,
// ~30%-os tartalék), mert a logó a QR jelentős részét eltakarja.
async function buildQr(QRCode, url, sizePx) {
  const canvas = document.createElement('canvas');
  await QRCode.toCanvas(canvas, url, { width: sizePx, margin: 1, errorCorrectionLevel: 'H' });
  const ctx = canvas.getContext('2d');
  const logoImg = await loadImage(logoUrl);

  const logoW = sizePx * 0.32;
  const logoH = logoW * (logoImg.height / logoImg.width);
  const pad = sizePx * 0.02;
  const x = (sizePx - logoW) / 2;
  const y = (sizePx - logoH) / 2;

  const bx = x - pad, by = y - pad, bw = logoW + pad * 2, bh = logoH + pad * 2, r = Math.min(bw, bh) * 0.1;
  ctx.fillStyle = '#fff';
  ctx.beginPath();
  ctx.moveTo(bx + r, by);
  ctx.arcTo(bx + bw, by, bx + bw, by + bh, r);
  ctx.arcTo(bx + bw, by + bh, bx, by + bh, r);
  ctx.arcTo(bx, by + bh, bx, by, r);
  ctx.arcTo(bx, by, bx + bw, by, r);
  ctx.closePath();
  ctx.fill();

  ctx.drawImage(logoImg, x, y, logoW, logoH);

  return canvas.toDataURL('image/png');
}

export async function dlgQrLabel(deviceId) {
  const dev = getDevice(deviceId);
  if (!dev) return;

  const url = deepLinkFor(dev.device_id);
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

  const url = deepLinkFor(dev.device_id);
  const { default: QRCode } = await import('qrcode');
  const dataUrl = await buildQr(QRCode, url, 850);

  const label = document.createElement('div');
  label.id = 'qr-print-label';
  label.style.cssText = 'position:fixed;left:-9999px;top:0;display:inline-flex;align-items:center;gap:0.5mm;padding:0;font-family:Arial,sans-serif;background:#fff';
  label.innerHTML = `
    <img src="${dataUrl}" alt="QR kód" style="height:25mm;width:25mm;display:block;flex:0 0 auto" />
    <div style="flex:0 0 auto;font-weight:700;font-size:2.4mm;line-height:1;white-space:nowrap">${esc(dev.asset_tag)}</div>`;
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
