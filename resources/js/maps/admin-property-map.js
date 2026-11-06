import { createMap, createPin, L } from './map-core.js';

/**
 * The location check on the admin property page: the listing's pin on a street map, with a Satellite toggle so the
 * admin can see the rooftops and judge whether the pin sits on the right building. A listing whose landlord never
 * placed the pin gets the map without a marker, because the default point is not the property.
 */
function init() {
    const el = document.getElementById('admin-property-map');
    if (!el) return;

    const lat = parseFloat(el.dataset.lat);
    const lng = parseFloat(el.dataset.lng);
    if (Number.isNaN(lat) || Number.isNaN(lng)) return;

    const map = createMap('admin-property-map', lat, lng, el.dataset.pinned === '1' ? 16 : 13);
    if (el.dataset.pinned === '1') createPin(map, lat, lng, 'property');

    // Satellite sits on top of the street tiles and comes off again, so the street layer is never rebuilt.
    const satellite = L.layerGroup([
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Tiles &copy; Esri' }),
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 }),
    ]);

    // Drawn while its tab may be hidden, so it measures itself again whenever a tab is shown.
    window.addEventListener('admin-tab-shown', () => setTimeout(() => map.invalidateSize(), 50));

    const toggle = document.getElementById('admin-property-map-satellite');
    if (toggle) {
        toggle.addEventListener('click', () => {
            const on = toggle.getAttribute('aria-pressed') !== 'true';
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            toggle.querySelector('span').textContent = on ? 'Map' : 'Satellite';
            on ? satellite.addTo(map) : map.removeLayer(satellite);
        });
    }
}

document.addEventListener('DOMContentLoaded', init);
