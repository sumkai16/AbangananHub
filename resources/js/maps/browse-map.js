import { createMap, createPricePin, fitToMarkers } from './map-core.js';

function init() {
    const container = document.getElementById('browse-map');
    const dataEl = document.getElementById('browse-map-data');
    if (!container || !dataEl) return;

    let properties = [];
    try {
        properties = JSON.parse(dataEl.textContent);
    } catch (err) {
        console.error('Browse map: could not parse property data', err);
        return;
    }

    // latitude/longitude come from a decimal DB column, which Eloquent
    // serializes as strings — coerce before handing them to Leaflet.
    const valid = properties
        .map(p => ({ ...p, latitude: parseFloat(p.latitude), longitude: parseFloat(p.longitude) }))
        .filter(p => !Number.isNaN(p.latitude) && !Number.isNaN(p.longitude));

    if (valid.length === 0) {
        container.innerHTML = '<p class="text-[12px] text-[#9B9F98] p-4">No locations available for the current results.</p>';
        return;
    }

    const center = valid[0];
    const map = createMap('browse-map', center.latitude, center.longitude, 13);

    const markers = new Map(); // property_id -> marker
    const fitList = [];

    valid.forEach(p => {
        const priceLabel = `₱${Number(p.rental_fee).toLocaleString()}`;
        const popupHtml = `
            <a href="${p.url}" class="block">
                <strong>${escapeHtml(p.title)}</strong><br>
                <span>${priceLabel}/month</span>
            </a>
        `;
        const marker = createPricePin(map, p.latitude, p.longitude, p.property_id, priceLabel, popupHtml);
        markers.set(String(p.property_id), marker);
        fitList.push(marker);
    });

    fitToMarkers(map, fitList);
    wireListSync(markers);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Hovering a list card highlights its pin on the map — Airbnb's pattern.
function wireListSync(markers) {
    document.querySelectorAll('[data-property-card]').forEach(card => {
        const id = card.dataset.propertyCard;
        const marker = markers.get(String(id));
        if (!marker) return;

        card.addEventListener('mouseenter', () => {
            marker.getElement()?.querySelector('.map-price-pin')?.classList.add('active');
        });
        card.addEventListener('mouseleave', () => {
            marker.getElement()?.querySelector('.map-price-pin')?.classList.remove('active');
        });
    });
}

document.addEventListener('DOMContentLoaded', init);