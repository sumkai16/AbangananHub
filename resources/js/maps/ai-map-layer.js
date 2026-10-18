// Search-results map (AI search): numbered price pins that show how well each listing fits, the place that was
// searched with its radius, a preview card on the selected pin, and the controls around them.
// Drawn on the same Leaflet map as the browse page (browse-map-init.js hands over when the pins carry a `fit`).
//
// Pins: { property_id, title, latitude, longitude, rental_fee, url, property_type, image, rank, fit: exact|close|other,
//         tag, rating, reviews, area, distance_km, hits[], misses[] }     origin: { name, lat, lng, radius_km } | null
import { L, createPricePin } from './map-core.js';

const DESKTOP = '(min-width: 1024px)';
const GRAD_CAP = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9l10-5 10 5-10 5z"></path><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"></path></svg>';
const PIN_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B35A3D" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>';
const FIT_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FF8A66" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"></path></svg>';
const BED_ICON = '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#8C95B0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18V8M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"></path><circle cx="7" cy="11" r="1.5"></circle></svg>';

const LEGEND = {
    exact: 'Fits everything',
    close: 'Close, see the tag',
    other: 'Other type',
};

let ctx = null; // { map, cluster, zoomPosition } while the map is showing search results
let entries = new Map(); // property_id (string) -> { marker, pin }
let origin = null;
let layers = []; // origin marker, radius circle and label: removed on the next update
let controls = [];
let popup = null;
let selectedId = null;
let showOther = true;
let mode = 'search'; // 'search' (AI results) | 'browse' (the plain browse page: no fit tags, ranks or origin)
let suppressClose = false;
let bound = false;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const safeUrl = (url) => (typeof url === 'string' && /^(https?:\/\/|\/)/i.test(url) ? url : '#');
const money = (n) => `₱${Number(n || 0).toLocaleString()}`;
const isDesktop = () => window.matchMedia(DESKTOP).matches;

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

export function isAiPayload(pins, opts) {
    return (Array.isArray(pins) && pins.length > 0 && typeof pins[0] === 'object' && 'fit' in pins[0])
        || (opts != null && opts.origin !== undefined);
}

export const aiActive = () => ctx !== null;

/** Draw search results: replaces everything this layer drew before. */
export function aiUpdate(map, cluster, pins, opts = {}) {
    teardown();
    mode = opts.mode === 'browse' ? 'browse' : 'search';
    ctx = { map, cluster, zoomPosition: map.zoomControl?.getPosition?.() ?? 'topleft' };
    cluster.clearLayers();
    entries = new Map();
    selectedId = null;
    origin = opts.origin && Number.isFinite(parseFloat(opts.origin.lat)) ? opts.origin : null;
    bind(map);
    map.zoomControl?.setPosition('topright'); // top-left belongs to the summary chip

    const valid = (pins || [])
        .map((p) => ({ ...p, latitude: parseFloat(p.latitude), longitude: parseFloat(p.longitude) }))
        .filter((p) => !Number.isNaN(p.latitude) && !Number.isNaN(p.longitude));

    valid.forEach((p) => {
        const id = String(p.property_id);
        const fit = ['exact', 'close', 'other'].includes(p.fit) ? p.fit : (mode === 'browse' ? 'plain' : 'close');
        const marker = createPricePin(map, p.latitude, p.longitude, p.property_id, pinHtml(p, fit), null, false,
            `ai-pin ai-pin--${fit}${p.rank != null ? ' ai-pin--badged' : ''}`);
        marker.on('click', () => select(id, { source: 'map' }));
        if (mode === 'browse') {
            marker.on('mouseover', () => hoverCard(id, true));
            marker.on('mouseout', () => hoverCard(id, false));
        }
        marker.on('add', () => paint(id));
        entries.set(id, { marker, pin: p, fit });
        if (fit !== 'other' || showOther) cluster.addLayer(marker);
    });

    drawOrigin(map);
    drawControls(map);
    aiFit();
    wireCards();
}

/** Remove everything this layer drew and give the map back to the plain browse pins. */
export function aiClear(map) {
    if (!ctx) return;
    teardown();
    map.zoomControl?.setPosition(ctx.zoomPosition);
    ctx = null;
    entries = new Map();
}

export function aiFit() {
    if (!ctx) return;
    const { map } = ctx;
    map.invalidateSize();
    const points = visibleEntries().map((e) => e.marker.getLatLng());
    const bounds = points.length ? L.latLngBounds(points) : null;
    const circle = layers.find((l) => l instanceof L.Circle);
    if (bounds && circle) bounds.extend(circle.getBounds());
    const target = bounds || (circle ? circle.getBounds() : null);
    // Leave room for the chip (top-left) and the legend / fit button (bottom-left).
    if (target) map.fitBounds(target.pad(0.1), { maxZoom: 16, paddingTopLeft: [40, 110], paddingBottomRight: [60, isDesktop() ? 200 : 80] });
}

/** Select a pin from other code (a card, the mobile carousel). Opens the preview on desktop. */
export function aiSelect(id, options = {}) {
    if (!ctx) return;
    select(String(id), { pan: true, ...options });
}

// ── Pins ─────────────────────────────────────────────────────────────────────

function pinHtml(p, fit) {
    // Rank 1 gets the coral badge, except on an exact (coral) pin where navy reads better.
    const badge = p.rank != null
        ? `<span class="ai-pin__badge${p.rank === 1 && fit !== 'exact' ? ' ai-pin__badge--top' : ''}">${esc(p.rank)}</span>`
        : '';
    const tag = p.tag ? `<span class="ai-pin__tag">${esc(p.tag)}</span>` : '';

    return `${badge}<span class="ai-pin__price">${esc(money(p.rental_fee))}</span>${tag}`;
}

function paint(id) {
    const entry = entries.get(id);
    entry?.marker.getElement()?.querySelector('.map-price-pin')?.classList.toggle('ai-selected', id === selectedId);
}

function visibleEntries() {
    return [...entries.values()].filter((e) => e.fit !== 'other' || showOther);
}

function applyOtherVisibility() {
    entries.forEach((e) => {
        if (e.fit !== 'other') return;
        if (showOther) ctx.cluster.addLayer(e.marker);
        else ctx.cluster.removeLayer(e.marker);
    });
    if (!showOther && selectedId && entries.get(selectedId)?.fit === 'other') deselect();
}

// ── Selection ────────────────────────────────────────────────────────────────

function select(id, { source = 'map', pan = false } = {}) {
    const entry = entries.get(id);
    if (!entry) return;
    const previous = selectedId;
    selectedId = id;
    entries.get(previous)?.marker.setZIndexOffset(0);
    entry.marker.setZIndexOffset(1000);
    if (previous) paint(previous);
    paint(id);
    highlightCard(id);

    closePopup();
    const open = () => {
        // Search results use the carousel under the map on phones; the browse page has none, so it keeps the popup.
        if (isDesktop() || mode === 'browse') openPreview(entry);
        else if (pan) ctx.map.panTo(entry.marker.getLatLng());
    };
    if (pan && isDesktop()) ctx.map.panTo(entry.marker.getLatLng(), { animate: true });
    const parent = ctx.cluster.getVisibleParent(entry.marker);
    if (parent && parent !== entry.marker) ctx.cluster.zoomToShowLayer(entry.marker, () => { paint(id); open(); });
    else if (parent) open();

    window.dispatchEvent(new CustomEvent('browse-map-selected', { detail: { id: Number.isNaN(Number(id)) ? id : Number(id), source } }));
}

function deselect() {
    const previous = selectedId;
    selectedId = null;
    if (previous) {
        entries.get(previous)?.marker.setZIndexOffset(0);
        paint(previous);
    }
    closePopup();
    highlightCard(null);
    window.dispatchEvent(new CustomEvent('browse-map-selected', { detail: { id: null, source: 'map' } }));
}

function closePopup() {
    if (!popup || !ctx) return;
    suppressClose = true;
    ctx.map.closePopup(popup);
    suppressClose = false;
    popup = null;
}

function highlightCard(id) {
    document.querySelectorAll('[data-property-card].map-highlighted').forEach((c) => c.classList.remove('map-highlighted'));
    if (id == null) return;
    const card = document.querySelector(`[data-property-card="${String(id).replace(/["\\]/g, '')}"]`);
    if (!card) return;
    card.classList.add('map-highlighted');
    card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

// Browse page: hovering a pin lightly marks its list card (selecting is a click, as in search results).
function hoverCard(id, on) {
    if (id === selectedId) return;
    const card = document.querySelector(`[data-property-card="${String(id).replace(/["\\]/g, '')}"]`);
    if (!card) return;
    card.classList.toggle('map-highlighted', on);
    if (on) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

// Hovering a card lights its pin (the reverse of selecting a pin).
function wireCards() {
    document.querySelectorAll('[data-property-card]').forEach((card) => {
        const pin = () => entries.get(String(card.dataset.propertyCard))?.marker.getElement()?.querySelector('.map-price-pin');
        card.addEventListener('mouseenter', () => pin()?.classList.add('active'));
        card.addEventListener('mouseleave', () => pin()?.classList.remove('active'));
    });
}

function bind(map) {
    if (bound) return;
    bound = true;
    map.on('click', () => { if (ctx && selectedId) deselect(); });
    map.on('popupclose', (e) => {
        if (ctx && !suppressClose && e.popup === popup && selectedId) {
            popup = null;
            deselect();
        }
    });
}

// ── Preview card (desktop) ───────────────────────────────────────────────────

function openPreview(entry) {
    const p = entry.pin;
    const chips = [
        ...(p.misses || []).map((t) => `<span class="ai-chip-tag ai-chip-tag--miss">${esc(t)}</span>`),
        ...(p.hits || []).slice(0, 2).map((t) => `<span class="ai-chip-tag ai-chip-tag--hit">${esc(t)}</span>`),
    ].join('');
    const rating = p.rating != null
        ? `<span class="ai-card__rating">★ ${esc(Number(p.rating).toFixed(1))}${p.reviews ? ` <span>(${esc(p.reviews)})</span>` : ''}</span>`
        : '';
    const photo = p.image
        ? `<img src="${esc(safeUrl(p.image))}" alt="${esc(p.title)}" loading="lazy">`
        : BED_ICON;
    const meta = [p.area, p.property_type].filter(Boolean).map(esc).join(' · ');

    const html = `
        <div class="ai-card">
            <div class="ai-card__photo">${photo}</div>
            <div class="ai-card__body">
                <div class="ai-card__head"><span class="ai-card__title">${esc(p.title)}</span>${rating}</div>
                <span class="ai-card__meta">${meta}</span>
                ${chips ? `<div class="ai-card__chips">${chips}</div>` : ''}
                <div class="ai-card__foot">
                    <span class="ai-card__price">${esc(money(p.rental_fee))} <span>/month</span></span>
                    <a class="ai-card__cta" href="${esc(safeUrl(p.url))}">View listing</a>
                </div>
            </div>
        </div>`;

    suppressClose = true;
    popup = L.popup({
        className: 'ai-popup', offset: [0, -50], minWidth: 290, maxWidth: 290, closeButton: true,
        autoPanPaddingTopLeft: [16, 90], autoPanPaddingBottomRight: [72, 24],
    }).setLatLng(entry.marker.getLatLng()).setContent(html);
    ctx.map.openPopup(popup);
    suppressClose = false;
}

// ── The searched place ───────────────────────────────────────────────────────

function drawOrigin(map) {
    if (!origin) return;
    const lat = parseFloat(origin.lat);
    const lng = parseFloat(origin.lng);
    // radius_km 0 = no radius to show (a named business's places are listed nearest first, not cut at a distance).
    const noRadius = origin.radius_km !== undefined && Number(origin.radius_km) === 0;
    const km = Number(origin.radius_km) > 0 ? Number(origin.radius_km) : 2;
    const glyph = origin.kind === 'point' ? PIN_ICON.replace('#B35A3D', '#FFFFFF') : GRAD_CAP; // a person's own position is a pin, a school is a cap

    const dot = L.marker([lat, lng], {
        interactive: false, keyboard: false, zIndexOffset: -500,
        icon: L.divIcon({
            className: 'ai-origin-wrapper', iconSize: [0, 0], iconAnchor: [0, 0],
            html: `<div class="ai-origin__dot">${glyph}</div><div class="ai-origin__label">${esc(origin.name)}</div>`,
        }),
    }).addTo(map);

    if (noRadius) {
        layers = [dot];
        return;
    }

    const circle = L.circle([lat, lng], {
        radius: km * 1000, color: '#FF8A66', weight: 1.5, dashArray: '6 5', fillColor: '#FF8A66', fillOpacity: 0.1, interactive: false,
    }).addTo(map);

    // The radius label sits on the circle's north edge.
    const label = L.marker([lat + km / 111, lng], {
        interactive: false, keyboard: false, zIndexOffset: -600,
        icon: L.divIcon({
            className: 'ai-origin-wrapper', iconSize: [0, 0], iconAnchor: [0, 0],
            html: `<div class="ai-radius-label">${esc(Number(km.toFixed(1)))} km</div>`,
        }),
    }).addTo(map);

    layers = [circle, dot, label];
}

// ── Controls ─────────────────────────────────────────────────────────────────

function control(map, position, build) {
    const Control = L.Control.extend({
        onAdd() {
            const node = build();
            L.DomEvent.disableClickPropagation(node);
            L.DomEvent.disableScrollPropagation(node);
            return node;
        },
    });
    const instance = new Control({ position }).addTo(map);
    controls.push(instance);
}

function drawControls(map) {
    const all = [...entries.values()];
    const results = all.filter((e) => e.fit !== 'other');
    const others = all.filter((e) => e.fit === 'other');
    const distances = results.map((e) => e.pin.distance_km).filter((d) => d != null && Number.isFinite(Number(d))).map(Number);
    const nearest = distances.length ? Math.min(...distances) : null;
    const noun = (n) => (mode === 'browse' ? (n === 1 ? 'property' : 'properties') : (n === 1 ? 'result' : 'results'));
    const count = `${results.length} ${noun(results.length)}`;

    control(map, 'topleft', () => {
        const panel = el('div', 'ai-panel');
        const chip = el('div', 'ai-chip');
        const icon = el('span', 'ai-chip__icon');
        icon.innerHTML = PIN_ICON;
        const text = el('span', 'ai-chip__text');
        if (origin) {
            text.append(el('span', 'ai-chip__title', origin.kind === 'point' ? 'Near you' : `Around ${origin.name}`));
            text.append(el('span', 'ai-chip__sub', nearest != null ? `${count} · nearest is ${nearest.toFixed(1)} km away` : count));
        } else {
            text.append(el('span', 'ai-chip__title', count));
        }
        chip.append(icon, text);
        panel.append(chip);

        if (others.length) {
            const label = el('label', 'ai-switch');
            const input = el('input');
            input.type = 'checkbox';
            input.checked = showOther;
            input.setAttribute('role', 'switch');
            const track = el('span', 'ai-switch__track');
            label.append(input, track, el('span', 'ai-switch__text', 'Show other options'));
            input.addEventListener('change', () => {
                showOther = input.checked;
                applyOtherVisibility();
                aiFit();
            });
            panel.append(label);
        }

        return panel;
    });

    control(map, 'bottomleft', () => {
        const stack = el('div', 'ai-panel ai-panel--bottom');
        const present = ['exact', 'close', 'other'].filter((fit) => all.some((e) => e.fit === fit));
        if (present.length > 1) {
            const legend = el('div', 'ai-legend');
            legend.append(el('span', 'ai-legend__title', 'Pin colours'));
            present.forEach((fit) => {
                const row = el('div', 'ai-legend__row');
                row.append(el('span', `ai-legend__swatch ai-legend__swatch--${fit}`), el('span', '', LEGEND[fit]));
                legend.append(row);
            });
            stack.append(legend);
        }
        const button = el('button', 'ai-fit');
        button.type = 'button';
        button.innerHTML = FIT_ICON;
        button.append(document.createTextNode(`Fit all ${results.length} ${noun(results.length)}`));
        button.addEventListener('click', () => aiFit());
        stack.append(button);

        return stack;
    });
}

function teardown() {
    if (!ctx) return;
    closePopup();
    highlightCard(null);
    layers.forEach((l) => ctx.map.removeLayer(l));
    controls.forEach((c) => c.remove());
    layers = [];
    controls = [];
}
