import { createMap, createPin, L } from './map-core.js';

const OSRM_URL = 'https://router.project-osrm.org/route/v1/driving';
const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

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
    createPin(map, lat, lng, 'property', `<strong>${escapeHtml(title)}</strong>`);

    wireDirections(map, lat, lng);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
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
                style: { color: '#2AA7A1', weight: 4, opacity: 0.85 },
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