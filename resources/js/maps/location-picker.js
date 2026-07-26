import { createMap, L } from './map-core.js';

const NOMINATIM_REVERSE_URL = 'https://nominatim.openstreetmap.org/reverse';
const DEFAULT_CENTER = { lat: 10.3157, lng: 123.8854 }; // Cebu City

// Tap-to-pin location picker for the property create form. Reverse-geocodes
// the pinned point via Nominatim (same OSM provider property-map.js already
// uses for forward geocoding) and fills the address field from it, so a
// landlord no longer types raw lat/lng by hand — they just aren't fields a
// non-technical person can reason about.
function init() {
    const container = document.getElementById('location-picker-map');
    if (!container) return;

    const hint = document.getElementById('location-picker-hint');
    const addressLine = document.getElementById('location-picker-address-line');
    const latLngLine = document.getElementById('location-picker-latlng');
    const addressInput = document.getElementById('address');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const oldLat = parseFloat(latInput?.value);
    const oldLng = parseFloat(lngInput?.value);
    const hasOldPin = !Number.isNaN(oldLat) && !Number.isNaN(oldLng);

    const startLat = hasOldPin ? oldLat : DEFAULT_CENTER.lat;
    const startLng = hasOldPin ? oldLng : DEFAULT_CENTER.lng;

    // Satellite imagery, not the Voyager style every other map on the site
    // uses — a landlord pinning their own property needs to recognize the
    // actual building/rooftop, which a stylized basemap doesn't draw.
    const map = createMap('location-picker-map', startLat, startLng, hasOldPin ? 16 : 13, 'satellite');

    let marker = null;

    function pinIcon() {
        return L.divIcon({
            className: 'map-pin map-pin--property',
            html: '<span class="map-pin__dot" title="Property location"></span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
    }

    function movePin(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true, icon: pinIcon() }).addTo(map);
            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                reverseGeocode(pos.lat, pos.lng);
            });
        }
        hint?.classList.add('hidden');
    }

    function updateCoordFields(lat, lng) {
        if (latInput) latInput.value = lat.toFixed(7);
        if (lngInput) lngInput.value = lng.toFixed(7);
        if (latLngLine) latLngLine.textContent = `Lat ${lat.toFixed(5)} · Lng ${lng.toFixed(5)}`;
    }

    async function reverseGeocode(lat, lng) {
        movePin(lat, lng);
        updateCoordFields(lat, lng);

        if (addressLine) addressLine.textContent = 'Looking up address…';

        try {
            const res = await fetch(`${NOMINATIM_REVERSE_URL}?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
            if (!res.ok) throw new Error('Reverse geocoding failed: ' + res.status);
            const data = await res.json();

            if (data.display_name) {
                if (addressLine) addressLine.textContent = data.display_name;
                if (addressInput) addressInput.value = data.display_name.slice(0, 255);
            } else {
                if (addressLine) addressLine.textContent = 'Address not found — you can type it in below.';
            }
        } catch (err) {
            console.error('Reverse geocoding failed:', err);
            if (addressLine) addressLine.textContent = 'Could not look up the address — you can type it in below.';
        }
    }

    map.on('click', (e) => reverseGeocode(e.latlng.lat, e.latlng.lng));

    // A validation failure re-renders the form with old() lat/lng/address
    // already set — restore the pin without re-hitting Nominatim, since the
    // address the landlord had is still sitting in the field.
    if (hasOldPin) {
        movePin(startLat, startLng);
        updateCoordFields(startLat, startLng);
        if (addressLine) addressLine.textContent = addressInput?.value || 'Pinned location';
    } else if (navigator.geolocation) {
        // Fresh form, no address typed yet — start the pin at the landlord's
        // current position instead of a bare Cebu City default. They can
        // still drag it or tap elsewhere; this only saves the first click
        // for the common case of listing the place you're standing in.
        if (addressLine) addressLine.textContent = 'Finding your current location…';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                reverseGeocode(pos.coords.latitude, pos.coords.longitude);
            },
            () => {
                // Denied or unavailable — fall back to the tap-to-pin hint,
                // same as before geolocation existed.
                if (addressLine) addressLine.textContent = 'Address will appear here after pinning.';
            },
            { timeout: 8000, enableHighAccuracy: true }
        );
    }

    initExpandModal(map, container);
}

// "Expand map" moves the live map's own DOM container into a full-screen
// modal, rather than standing up a second Leaflet instance to keep in sync.
// Leaflet doesn't care where its container sits in the document — only that
// invalidateSize() runs after the container's on-screen dimensions change —
// so re-parenting the same map is simpler and can't drift out of sync with
// itself. Deliberately vanilla JS, not the shared `<x-modal>` component: that
// component's max width tops out at 2xl (672px), too narrow to be worth
// expanding into, and its open/close only exists as internal Alpine state
// with no external "it just closed" signal — this modal's close paths
// (button, backdrop, Escape) all have to run the same DOM-move-back step, so
// one plain function all three call is simpler than reaching into Alpine's
// state from outside it.
function initExpandModal(map, mapEl) {
    const expandBtn = document.getElementById('location-picker-expand');
    const wrapper = document.getElementById('location-picker-map-wrapper');
    const placeholder = document.getElementById('location-picker-placeholder');
    const modal = document.getElementById('location-picker-modal');
    const backdrop = document.getElementById('location-picker-modal-backdrop');
    const panel = document.getElementById('location-picker-modal-panel');
    const slot = document.getElementById('location-picker-modal-slot');
    const closeBtn = document.getElementById('location-picker-modal-close');
    const doneBtn = document.getElementById('location-picker-modal-done');
    if (!expandBtn || !wrapper || !modal || !slot) return;

    const originalParent = wrapper.parentNode;
    const originalNextSibling = wrapper.nextSibling;
    let isOpen = false;

    function openModal() {
        if (isOpen) return;
        isOpen = true;

        slot.appendChild(wrapper);
        wrapper.classList.add('h-full');
        mapEl.classList.remove('h-[240px]');
        mapEl.classList.add('h-full');
        placeholder.classList.remove('hidden');
        placeholder.classList.add('flex');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        // rAF-after-rAF so the enter transition runs from its "-start"
        // state rather than skipping straight to "-end" — the display:none
        // to flex flip and the class removal can't land in the same paint.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'translate-y-4', 'scale-95');
            });
        });

        // Leaflet sized itself against a 240px-tall container; it needs to
        // recompute after the modal's own enter transition finishes handing
        // it a full-height one, or tiles render into the old bounds.
        setTimeout(() => map.invalidateSize(), 320);
    }

    function closeModal() {
        if (!isOpen) return;
        isOpen = false;

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'translate-y-4', 'scale-95');

        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');

            originalParent.insertBefore(wrapper, originalNextSibling);
            wrapper.classList.remove('h-full');
            mapEl.classList.remove('h-full');
            mapEl.classList.add('h-[240px]');
            placeholder.classList.add('hidden');
            placeholder.classList.remove('flex');

            map.invalidateSize();
        }, 200);
    }

    expandBtn.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    doneBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) closeModal();
    });
}

document.addEventListener('DOMContentLoaded', init);
