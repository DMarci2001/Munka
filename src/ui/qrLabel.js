import { getDevice } from '../state/store.js';
import { openModal } from './components.js';
import { esc } from '../lib/format.js';

function deepLinkFor(assetTag) {
  const base = location.origin + location.pathname.replace(/[^/]*$/, '');
  return `${base}#/scan/${encodeURIComponent(assetTag)}`;
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
      style.textContent = `@media print{body>*{display:none!important}#qr-print-clone{display:block!important;text-align:center;padding:24px}}`;
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
