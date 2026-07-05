import { createMap, createPricePin, createClusterGroup, fitToMarkers } from './map-core.js';

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
        container.innerHTML = '<p class="text-[12px] text-[#64748B] p-4">No locations available for the current results.</p>';
        return;
    }

    const center = valid[0];
    const map = createMap('browse-map', center.latitude, center.longitude, 13);
    const clusterGroup = createClusterGroup();
    map.addLayer(clusterGroup);

    const markers = new Map(); // property_id -> marker
    const fitList = [];

    valid.forEach(p => {
        const priceLabel = `₱${Number(p.rental_fee).toLocaleString()}`;
        const popupHtml = `
            <div class="map-popup-card">
                <a href="${p.url}" class="block">
<<<<<<< HEAD
                    <div class="relative bg-[#F7FCFC] h-[180px] w-full">
                        ${p.image ? `<img src="${p.image}" class="w-full h-full object-cover rounded-t-xl" alt="${escapeHtml(p.title)}">` : `<div class="w-full h-full flex items-center justify-center text-[#FF8A65]">No image</div>`}
                    </div>
                    <div class="p-4 bg-white rounded-b-xl">
                        <div class="flex justify-between items-start mb-1">
                            <h3 class="text-[15px] font-semibold text-[#156F8C] truncate pr-4">${escapeHtml(p.title)}</h3>
                            <span class="text-[13px] font-semibold flex items-center shrink-0">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#156F8C" class="mr-1"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                4.9
                            </span>
                        </div>
                        <p class="text-[14px] text-[#9B9F98] truncate">${escapeHtml(p.property_type)} in Cebu</p>
                        <p class="text-[15px] font-bold text-[#156F8C] mt-2">
                            ₱${Number(p.rental_fee).toLocaleString()} <span class="text-[13px] font-normal text-[#9B9F98]">/month</span>
=======
                    <div class="relative bg-[#EEF8F8] h-[180px] w-full">
                        ${p.image ? `<img src="${p.image}" class="w-full h-full object-cover rounded-t-xl" alt="${escapeHtml(p.title)}">` : `<div class="w-full h-full flex items-center justify-center text-[#156F8C]">No image</div>`}
                    </div>
                    <div class="p-4 bg-white rounded-b-xl">
                        <div class="flex justify-between items-start mb-1">
                            <h3 class="text-[15px] font-semibold text-[#1F2937] truncate pr-4">${escapeHtml(p.title)}</h3>
                            <span class="text-[13px] font-semibold flex items-center shrink-0">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#1F2937" class="mr-1"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                4.9
                            </span>
                        </div>
                        <p class="text-[14px] text-[#64748B] truncate">${escapeHtml(p.property_type)} in Cebu</p>
                        <p class="text-[15px] font-bold text-[#1F2937] mt-2">
                            ₱${Number(p.rental_fee).toLocaleString()} <span class="text-[13px] font-normal text-[#64748B]">/month</span>
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                        </p>
                    </div>
                </a>
            </div>
        `;
        const marker = createPricePin(map, p.latitude, p.longitude, p.property_id, priceLabel, popupHtml, false);
        clusterGroup.addLayer(marker);
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

// Hovering a list card highlights its pin on the map, and vice versa —
// Airbnb's cross-highlighting pattern, now bidirectional.
function wireListSync(markers) {
    const cards = new Map();
    document.querySelectorAll('[data-property-card]').forEach(card => {
        cards.set(String(card.dataset.propertyCard), card);
    });

    markers.forEach((marker, id) => {
        const card = cards.get(id);
        if (!card) return;

        card.addEventListener('mouseenter', () => {
            marker.getElement()?.querySelector('.map-price-pin')?.classList.add('active');
        });
        card.addEventListener('mouseleave', () => {
            marker.getElement()?.querySelector('.map-price-pin')?.classList.remove('active');
        });

        marker.on('mouseover', () => {
            card.classList.add('map-highlighted');
            card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
        marker.on('mouseout', () => {
            card.classList.remove('map-highlighted');
        });
    });
}

document.addEventListener('DOMContentLoaded', init);