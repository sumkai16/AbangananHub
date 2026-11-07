import { createMap, createPin, L } from './map-core.js';

const OSRM_URL = 'https://router.project-osrm.org/route/v1/driving';
const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

/**
 * Travel modes offered under the map.
 *
 * The route is fetched ONCE, as driving, and each mode's time is derived from
 * the returned distance. That is not laziness: the public OSRM demo server
 * hosts only the car profile, and /walking, /cycling, /foot and /bike all
 * return byte-identical durations to /driving — verified against it. Routing
 * per mode would cost three requests to be told the same number three times,
 * and presenting it as a real walking route would be a fabrication.
 *
 * Every mode uses the same method — distance ÷ an average speed. An earlier
 * pass used OSRM's own duration for the car and averages for the rest, which
 * put a motorcycle at 15 min and a car at 11 min over the same 7.3 km: OSRM
 * reports free-flow speed-limit times, so mixing it with congested-traffic
 * averages ranks the modes backwards. One method for all three keeps the
 * comparison internally consistent, which is the only thing a reader is
 * actually doing with these numbers.
 *
 * Speeds are Metro Cebu door-to-door with traffic — which is why a car is
 * slower than a motorcycle here, unlike free-flow routing. Sanity-checked
 * against real routes (IT Park to SM Seaside, 12 km: 33 min by car, 24 by
 * motorcycle; OSRM's own free-flow answer of 18 min is not a drive anyone in
 * Cebu recognises).
 *
 * They are still ESTIMATES, not measurements. Nothing here knows the time of
 * day, and rush hour against midnight is a 2-3x difference on one number.
 * Say so in the UI rather than implying a precision we don't have, and see
 * `roundMinutes` for why long answers are deliberately coarse.
 *
 * `maxKm` suppresses an answer past the distance at which the mode stops being
 * a real option — a correctly computed "2 hr 39 on foot" over 12 km is not
 * information, it's arithmetic nobody asked for.
 *
 * Replacing this with real per-mode routing (OpenRouteService, GraphHopper —
 * both need an API key) means changing routeTo() and dropping `kmh`; the mode
 * buttons and the summary rendering stay as they are. Live traffic needs a
 * paid provider (Google Directions, Mapbox) — no free tier reports it.
 */
const TRAVEL_MODES = {
    motorcycle: { label: 'by motorcycle', kmh: 30, maxKm: null },
    car: { label: 'by car', kmh: 22, maxKm: null },
    walk: { label: 'on foot', kmh: 4.5, maxKm: 5 },
};

// Last successful route, kept so switching mode re-renders from the same
// distance instead of hitting the network again.
let lastRoute = null;
let activeMode = 'motorcycle';

// Route animation (Nov 2026): the line draws on while the figures count up, then a glowing marker rides it at the
// mode's pace and leaves a coral trail that fades once it arrives. Skipped for people who reduce motion, and
// cancelled the moment the map zooms (the path's pixel length changes, so a half-run dash would look broken).
const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const DRAW_MS = 1700;
let animToken = 0;
let routeLayer = null;
let routeGeometry = null;
let originMarker = null;
let trailLayer = null;
let riderMarker = null;

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
    const modes = document.getElementById('directions-modes');
    if (!btn || !panel) return;

    /**
     * Round to the precision the estimate actually has. "~2 hr 39" reads as a
     * measurement; it is distance divided by a guessed average speed, so the
     * minutes digit is noise. Coarser buckets as the answer grows keep the
     * display honest about that.
     */
    function roundMinutes(mins) {
        if (mins < 20) return mins;              // short hops: a minute matters
        if (mins < 60) return Math.round(mins / 5) * 5;
        return Math.round(mins / 10) * 10;
    }

    function formatDuration(mins) {
        const r = roundMinutes(mins);
        if (r < 60) return `${r} min`;
        const h = Math.floor(r / 60);
        const m = r % 60;
        return m ? `${h} hr ${m} min` : `${h} hr`;
    }

    // Built with textContent rather than innerHTML. Every value here is a
    // number we computed, but the panel is also where the geocoded-address
    // path lands, and one shared sink that never parses HTML cannot become an
    // injection point later.
    function renderSummary(progress = 1) {
        if (!lastRoute) return;

        const mode = TRAVEL_MODES[activeMode];
        const km = lastRoute.distance / 1000;
        const tooFar = mode.maxKm !== null && km > mode.maxKm;

        panel.replaceChildren();

        const line = document.createElement('div');
        line.className = 'flex items-baseline gap-2 flex-wrap';

        const dist = document.createElement('span');
        dist.className = 'text-[22px] font-bold tracking-tight text-[#060D26]';
        dist.textContent = `${(km * progress).toFixed(1)} km`;

        const dot = document.createElement('span');
        dot.className = tooFar ? 'text-[#5B6A8E]' : 'text-[#B35A3D]';
        dot.setAttribute('aria-hidden', 'true');
        dot.textContent = '·';

        const time = document.createElement('span');
        time.className = tooFar
            ? 'text-[14px] font-semibold text-[#5B6A8E]'
            : 'text-[14px] font-semibold text-[#060D26]';
        time.textContent = tooFar
            ? 'too far to walk'
            : `~${formatDuration(Math.round((km / mode.kmh) * 60 * progress))} ${mode.label}`;

        line.append(dist, dot, time);

        const note = document.createElement('p');
        note.className = 'mt-1 text-[11.5px] text-[#5B6A8E] transition-opacity duration-300';
        note.style.opacity = progress >= 1 ? '1' : '0';
        // Says what it is. The previous wording ("typical Metro Cebu travel
        // speeds") implied a traffic dataset stands behind these numbers.
        note.textContent = tooFar
            ? 'Try motorcycle or car for a time estimate.'
            : 'Rough estimate — actual time depends on traffic.';

        panel.append(line, note);
    }

    modes?.querySelectorAll('.directions-mode').forEach((el) => {
        el.addEventListener('click', () => {
            activeMode = el.dataset.mode;

            modes.querySelectorAll('.directions-mode').forEach((other) => {
                const on = other === el;
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
                other.classList.toggle('bg-white', on);
                other.classList.toggle('shadow-sm', on);
                other.classList.toggle('text-[#060D26]', on);
                other.classList.toggle('text-[#5B6A8E]', !on);
            });

            renderSummary();
            if (routeLayer) playRoute(false);
        });
    });

    function pathOf(group) {
        let el = null;
        group.eachLayer((l) => { if (l.getElement) el = l.getElement(); });
        return el;
    }

    function clearTransients() {
        if (trailLayer) { map.removeLayer(trailLayer); trailLayer = null; }
        if (riderMarker) { map.removeLayer(riderMarker); riderMarker = null; }
    }

    function clearRoute() {
        animToken++;
        clearTransients();
        if (routeLayer) { map.removeLayer(routeLayer); routeLayer = null; }
        if (originMarker) { map.removeLayer(originMarker); originMarker = null; }
    }

    function tween(token, ms, onFrame, ease) {
        return new Promise((resolve) => {
            const t0 = performance.now();
            const tick = (now) => {
                if (token !== animToken) return resolve(false);
                const t = Math.min(1, (now - t0) / ms);
                onFrame(ease(t));
                if (t < 1) requestAnimationFrame(tick); else resolve(true);
            };
            requestAnimationFrame(tick);
        });
    }

    /**
     * @param {boolean} redraw  true: draw the route on first, then ride. false: ride again (mode changed).
     */
    async function playRoute(redraw) {
        const token = ++animToken;
        clearTransients();

        const base = pathOf(routeLayer);
        const len = base?.getTotalLength?.() ?? 0;
        const mode = TRAVEL_MODES[activeMode];
        const tooFar = mode.maxKm !== null && lastRoute.distance / 1000 > mode.maxKm;

        // Nothing to animate against: reduced motion, a hidden map (zero length) or a canvas renderer.
        if (prefersReducedMotion() || !base || len < 1) {
            if (base) base.style.strokeDasharray = '';
            renderSummary(1);
            return;
        }

        // A zoom changes the path's pixel length, so stop and show the finished state instead.
        const stop = () => {
            animToken++;
            clearTransients();
            base.style.strokeDasharray = '';
            base.style.strokeDashoffset = '';
            renderSummary(1);
        };
        map.once('zoomstart', stop);
        const done = () => map.off('zoomstart', stop);

        if (redraw) {
            base.style.strokeDasharray = String(len);
            base.style.strokeDashoffset = String(len);
            const ok = await tween(token, DRAW_MS, (p) => {
                base.style.strokeDashoffset = String(len * (1 - p));
                renderSummary(tooFar ? 1 : p);
            }, easeInOut);
            if (!ok) return done();
            base.style.strokeDasharray = '';
            base.style.strokeDashoffset = '';
        }

        renderSummary(1);

        // Ride: coral glowing trail behind a glowing marker. Pace follows the mode (a car or a walker is slower than
        // a motorcycle); it is for show, the estimate above is unchanged.
        trailLayer = L.geoJSON(routeGeometry, {
            style: { color: '#FF8A66', weight: 4.5, opacity: 1, className: 'route-trail' },
            interactive: false,
        }).addTo(map);
        const trail = pathOf(trailLayer);
        if (!trail) { clearTransients(); return done(); }
        trail.style.strokeDasharray = String(len);
        trail.style.strokeDashoffset = String(len);

        riderMarker = L.marker(map.layerPointToLatLng(L.point(base.getPointAtLength(0))), {
            icon: L.divIcon({ className: 'route-rider', html: '<span></span>', iconSize: [22, 22], iconAnchor: [11, 11] }),
            interactive: false, keyboard: false, zIndexOffset: 1000,
        }).addTo(map);

        const rideMs = Math.min(4800, Math.max(2200, (3200 * 30) / mode.kmh));
        const arrived = await tween(token, rideMs, (p) => {
            trail.style.strokeDashoffset = String(len * (1 - p));
            const pt = base.getPointAtLength(len * p);
            riderMarker.setLatLng(map.layerPointToLatLng(L.point(pt.x, pt.y)));
        }, (t) => t);
        done();
        if (!arrived) return;

        // Arrived: drop the marker, let the trail fade out so the calm navy route is what stays.
        if (riderMarker) { map.removeLayer(riderMarker); riderMarker = null; }
        trail.style.opacity = '0';
        setTimeout(() => { if (token === animToken) clearTransients(); }, 650);
    }

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

            clearRoute();
            routeGeometry = route.geometry;
            routeLayer = L.geoJSON(routeGeometry, {
                style: { color: '#060D26', weight: 4, opacity: 0.85, className: 'route-line' },
            }).addTo(map);

            originMarker = createPin(map, fromLat, fromLng, 'origin', '<strong>Your starting point</strong>');
            // No fly animation: the path must be at its final pixel size before it is measured and drawn on.
            map.fitBounds(routeLayer.getBounds().pad(0.15), { animate: false });

            lastRoute = { distance: route.distance, duration: route.duration };
            playRoute(true);
        } catch (err) {
            console.error('Directions failed:', err);
            panel.replaceChildren();
            const msg = document.createElement('p');
            msg.className = 'text-[13px] font-medium text-[#EF4444]';
            msg.textContent = 'Could not calculate directions right now.';
            panel.append(msg);
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
                window.dispatchEvent(new CustomEvent('show-modal', {
                    detail: {
                        type: 'error',
                        title: 'Address not found',
                        message: 'Could not find that address. Try adding more detail.',
                    }
                }));
                return;
            }
            routeTo(parseFloat(results[0].lat), parseFloat(results[0].lon));
        } catch (err) {
            console.error('Geocoding failed:', err);
            window.dispatchEvent(new CustomEvent('show-modal', {
                detail: {
                    type: 'error',
                    title: 'Lookup failed',
                    message: 'Something went wrong looking up that address.',
                }
            }));
        }
    });
}

document.addEventListener('DOMContentLoaded', init);