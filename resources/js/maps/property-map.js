import { createMap, createPin, fitToMarkers, L } from './map-core.js';

const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';
const OSRM_URL = 'https://router.project-osrm.org/route/v1/driving';
const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
const LANDMARK_RADIUS_M = 1200;

const LANDMARK_QUERIES = [
    { type: 'school', tag: 'amenity=school' },
    { type: 'hospital', tag: 'amenity=hospital' },
    { type: 'hospital', tag: 'amenity=clinic' },
    { type: 'shopping', tag: 'shop=supermarket' },
    { type: 'shopping', tag: 'shop=mall' },
    { type: 'transport', tag: 'amenity=bus_station' },
];

function init() {
    const container = document.getElementById('property-map');
    if (!container) return;

    const lat = parseFloat(container.dataset.lat);
    const lng = parseFloat(container.dataset.lng);
    const title = container.dataset.title || 'This property';

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        container.innerHTML = '<p class="text-[12px] text-gray-400 p-4">Location not available for this listing yet.</p>';
        return;
    }

    const map = createMap('property-map', lat, lng, 15);
    const propertyMarker = createPin(map, lat, lng, 'property', `<strong>${escapeHtml(title)}</strong>`);

    loadLandmarks(map, lat, lng, [propertyMarker]);
    wireDirections(map, lat, lng);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

async function loadLandmarks(map, lat, lng, markersToFit) {
    const filters = LANDMARK_QUERIES
        .map(q => `node[${q.tag}](around:${LANDMARK_RADIUS_M},${lat},${lng});`)
        .join('\n');

    const query = `[out:json][timeout:10];(${filters});out 20;`;

    try {
        const res = await fetch(OVERPASS_URL, {
            method: 'POST',
            body: `data=${encodeURIComponent(query)}`,
        });
        if (!res.ok) throw new Error('Overpass request failed: ' + res.status);
        const data = await res.json();

        data.elements.slice(0, 20).forEach(el => {
            const name = el.tags?.name || 'Unnamed location';
            const matched = LANDMARK_QUERIES.find(q => {
                const [k, v] = q.tag.split('=');
                return el.tags?.[k] === v;
            });
            const type = matched ? matched.type : 'shopping';

            const marker = createPin(map, el.lat, el.lon, type, `<strong>${escapeHtml(name)}</strong>`);
            markersToFit.push(marker);
        });

        fitToMarkers(map, markersToFit);
    } catch (err) {
        console.error('Nearby landmarks failed to load:', err);
        // Non-fatal — property pin still renders fine without landmarks.
    }
}

function wireDirections(map, destLat, destLng) {
    const panel = document.getElementById('directions-panel');
    const btn = document.getElementById('get-directions-btn');
    const manualForm = document.getElementById('manual-origin-form');
    const manualInput = document.getElementById('manual-origin-input');
    if (!btn || !panel) return;

    function resetButton() {
        btn.disabled = false;
        btn.textContent = 'Get directions';
    }

    function showManualInput() {
        manualForm?.classList.remove('hidden');
    }

    async function routeTo(fromLat, fromLng) {
        try {
            const res = await fetch(`${OSRM_URL}/${fromLng},${fromLat};${destLng},${destLat}?overview=full&geometries=geojson`);
            if (!res.ok) throw new Error('OSRM request failed: ' + res.status);
            const data = await res.json();
            const route = data.routes?.[0];
            if (!route) throw new Error('No route found');

            const layer = L.geoJSON(route.geometry, {
                style: { color: '#61B2F0', weight: 4, opacity: 0.85 },
            }).addTo(map);

            createPin(map, fromLat, fromLng, 'origin', '<strong>Your starting point</strong>');
            map.fitBounds(layer.getBounds().pad(0.15));

            const km = (route.distance / 1000).toFixed(1);
            const mins = Math.round(route.duration / 60);
            panel.innerHTML = `<div class="route-summary-badge">${km} km · ~${mins} min drive</div>`;
        } catch (err) {
            console.error('Directions failed:', err);
            panel.innerHTML = '<p class="text-[12px] text-red-500">Could not calculate directions right now.</p>';
        } finally {
            resetButton();
        }
    }

    btn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showManualInput();
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Finding your location…';

        navigator.geolocation.getCurrentPosition(
            (pos) => routeTo(pos.coords.latitude, pos.coords.longitude),
            () => { showManualInput(); resetButton(); },
            { timeout: 8000 }
        );
    });

    manualForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const address = manualInput.value.trim();
        if (!address) return;

        try {
            const res = await fetch(`${NOMINATIM_URL}?format=json&limit=1&q=${encodeURIComponent(address + ', Cebu, Philippines')}`);
            const results = await res.json();
            if (!results.length) {
                alert('Could not find that address. Try adding more detail.');
                return;
            }
            routeTo(parseFloat(results[0].lat), parseFloat(results[0].lon));
        } catch (err) {
            console.error('Geocoding failed:', err);
            alert('Something went wrong looking up that address.');
        }
    });
}

document.addEventListener('DOMContentLoaded', init);