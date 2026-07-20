import { getDevice } from '../state/store.js';
import { openModal } from './components.js';
import { esc } from '../lib/format.js';
import logoUrl from '../assets/hmm_logo.png';

function deepLinkFor(assetTag) {
  const base = location.origin + location.pathname.replace(/[^/]*$/, '');
  return `${base}#/scan/${encodeURIComponent(assetTag)}`;
}

function waitForImages(container) {
  return Promise.all([...container.querySelectorAll('img')].map((img) =>
    img.decode ? img.decode().catch(() => {}) : new Promise((res) => { img.onload = res; img.onerror = res; })));
}

export async function dlgQrLabel(deviceId) {
  const dev = getDevice(deviceId);
  if (!dev) return;

  const url = deepLinkFor(dev.asset_tag);
  const { default: QRCode } = await import('qrcode');
  const dataUrl = await QRCode.toDataURL(url, { width: 240, margin: 1 });

  openModal({
    title: `QR Címke · <span class="tag-mono" style="margin-left:8px">${esc(dev.asset_tag)}</span>`,
    bodyHTML: `
      <div id="qr-label-area" style="text-align:center;padding:12px 0">
        <img src="${dataUrl}" alt="QR kód" style="width:240px;height:240px;display:block;margin:0 auto 12px" />
        <div class="tag-mono" style="font-size:1.1rem;font-weight:600">${esc(dev.asset_tag)}</div>
        <div style="font-size:.9rem;color:#666;margin-top:4px">${esc([dev.manufacturer, dev.model].filter(Boolean).join(' '))}</div>
      </div>`,
    confirmText: 'Nyomtatás',
    onConfirm: () => {
      const style = document.createElement('style');
      style.textContent = `@media print{@page{size:auto;margin:10mm}body>*{display:none!important}#qr-print-clone{display:block!important;text-align:center;padding:24px}#qr-print-clone img{width:240px!important;height:240px!important}}`;
      document.head.appendChild(style);
      const clone = document.getElementById('qr-label-area').cloneNode(true);
      clone.id = 'qr-print-clone';
      document.body.appendChild(clone);
      window.print();
      clone.remove();
      style.remove();
      return false;
    },
  });
}

// ---- Közvetlen nyomtatás (előnézeti modal nélkül), a fizikai Zebra ZD421
// címkeanyaghoz igazítva: 104mm x 76.2mm (a nyomtató-illesztőprogram
// alapértelmezett készlet-mérete, "Nyomtatási beállítások" > Méret). ----
export async function printQrLabel(deviceId) {
  const dev = getDevice(deviceId);
  if (!dev) return;

  const url = deepLinkFor(dev.asset_tag);
  const { default: QRCode } = await import('qrcode');
  const dataUrl = await QRCode.toDataURL(url, { width: 480, margin: 1 });

  const style = document.createElement('style');
  style.textContent = `@media print{@page{size:auto;margin:0}body>*{display:none!important}#qr-print-label{position:static!important;left:auto!important;display:flex!important}}`;
  document.head.appendChild(style);

  const label = document.createElement('div');
  label.id = 'qr-print-label';
  label.style.cssText = 'position:fixed;left:-9999px;top:0;width:104mm;height:76.2mm;padding:4mm;box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:4mm;font-family:Arial,sans-serif;background:#fff';
  label.innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;flex:0 0 auto">
      <img src="${dataUrl}" alt="QR kód" style="width:60mm;height:60mm;display:block" />
      <div style="font-weight:700;font-size:6mm;margin-top:2mm;white-space:nowrap">${esc(dev.asset_tag)}</div>
    </div>
    <img src="${logoUrl}" alt="Hungária Med-M" style="width:32mm;height:auto;flex:0 0 auto" />`;
  document.body.appendChild(label);

  await waitForImages(label);
  window.print();
  label.remove();
  style.remove();
}
