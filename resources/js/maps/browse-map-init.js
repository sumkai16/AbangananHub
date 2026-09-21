import { createMap, createPricePin, createClusterGroup, fitToMarkers } from './map-core.js';

// Cebu, roughly — only used until the first fit, and when a search has no results at all.
const DEFAULT_CENTER = [10.3157, 123.8854];
const DEFAULT_ZOOM = 11;

let map = null;
let clusterGroup = null;
let markers = new Map(); // property_id -> marker
let fitList = [];

export function init() {
    const container = document.getElementById('browse-map');
    if (!container || map) return;

    map = createMap('browse-map', DEFAULT_CENTER[0], DEFAULT_CENTER[1], DEFAULT_ZOOM);
    // Exposed so the desktop "Hide/Show map" toggle (properties/index.blade.php)
    // can call invalidateSize() after un-hiding the container — Leaflet
    // measures its container at init time, and a display:none ancestor at
    // that point means tiles render into a 0x0 box and stay that way until
    // told to remeasure.
    window.browseMap = map;
    clusterGroup = createClusterGroup();
    map.addLayer(clusterGroup);

    // The map is built while its container is display:none (0x0), so the first
    // fit is meaningless. The Show-map toggle calls this after un-hiding it.
    window.browseMapRefit = () => {
        map.invalidateSize();
        fitToMarkers(map, fitList);
    };
    // Called by browse-live.js when the filters change in place, so the pins follow the
    // list without the map being torn down (and without the visitor leaving the map view).
    window.browseMapUpdate = update;

    update(readInitialData());
}

function readInitialData() {
    const dataEl = document.getElementById('browse-map-data');
    if (!dataEl) return [];
    try {
        return JSON.parse(dataEl.textContent);
    } catch (err) {
        console.error('Browse map: could not parse property data', err);
        return [];
    }
}

/** Replace every pin with the given properties and re-fit the view. */
function update(properties) {
    if (!map) return;

    clusterGroup.clearLayers();
    markers = new Map();
    fitList = [];

    // latitude/longitude come from a decimal DB column, which Eloquent
    // serializes as strings — coerce before handing them to Leaflet.
    const valid = (properties || [])
        .map(p => ({ ...p, latitude: parseFloat(p.latitude), longitude: parseFloat(p.longitude) }))
        .filter(p => !Number.isNaN(p.latitude) && !Number.isNaN(p.longitude));

    valid.forEach(p => {
        const priceLabel = `₱${Number(p.rental_fee).toLocaleString()}`;
        const popupHtml = `
            <div class="map-popup-card">
                <a href="${p.url}" class="block">
                    <div class="relative bg-[#ECEEF6] h-[180px] w-full">
                        ${p.image ? `<img src="${p.image}" class="w-full h-full object-cover rounded-t-xl" alt="${escapeHtml(p.title)}">` : `<div class="w-full h-full flex items-center justify-center text-[#060D26]">No image</div>`}
                    </div>
                    <div class="p-4 bg-white rounded-b-xl">
                        <div class="flex justify-between items-start mb-1">
                            <h3 class="text-[15px] font-semibold text-[#060D26] truncate pr-4">${escapeHtml(p.title)}</h3>
                            <span class="text-[13px] font-semibold flex items-center shrink-0">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="#060D26" class="mr-1"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                4.9
                            </span>
                        </div>
                        <p class="text-[14px] text-[#5B6A8E] truncate">${escapeHtml(p.property_type)} in Cebu</p>
                        <p class="text-[15px] font-bold text-[#060D26] mt-2">
                            ₱${Number(p.rental_fee).toLocaleString()} <span class="text-[13px] font-normal text-[#5B6A8E]">/month</span>
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

    // The container may still be hidden (map never opened on this view) — then this is a no-op
    // and the Show-map toggle's browseMapRefit() does the real fit.
    map.invalidateSize();
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
