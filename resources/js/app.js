import './bootstrap';
import './echo';
import './field-validation';
import { aiSearch, aiSearchBox } from './ai-search';
import propertyCard from './property-card';
import { leaseEditor } from './lease-editor-alpine';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('aiSearch', aiSearch);
Alpine.data('aiSearchBox', aiSearchBox);
Alpine.data('propertyCard', propertyCard);
Alpine.data('leaseEditor', leaseEditor);

Alpine.start();

// Payment QR helpers (landlord shares a QR in chat, tenant saves it as a picture to upload in their wallet app).
// jsQR is loaded only when a landlord actually picks a screenshot.
window.readPaymentQr = async (file) => {
    const bitmap = await createImageBitmap(file);
    // Large phone screenshots decode more reliably when scaled to a sane size.
    const scale = Math.min(1, 1400 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    const { default: jsQR } = await import('jsqr');
    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);

    return jsQR(image.data, image.width, image.height)?.data ?? null;
};

window.downloadQr = (button) => {
    const holder = button.closest('[x-data]')?.querySelector('[data-qr] svg');
    if (!holder) return;

    const svg = new XMLSerializer().serializeToString(holder);
    const img = new Image();
    img.onload = () => {
        const size = 800;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = size;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(img, 0, 0, size, size);
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = 'payment-qr.png';
        link.click();
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
};
