import { createMap, createClusterGroup } from './map-core.js';
import { isAiPayload, aiUpdate, aiFit, aiSelect } from './ai-map-layer.js';

// Cebu, roughly — only used until the first fit, and when a search has no results at all.
const DEFAULT_CENTER = [10.3157, 123.8854];
const DEFAULT_ZOOM = 11;

let map = null;
let clusterGroup = null;

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
        aiFit();
    };
    // Called by browse-live.js when the filters change in place, so the pins follow the
    // list without the map being torn down (and without the visitor leaving the map view).
    window.browseMapUpdate = update;
    // The search-results page (ai-map-layer.js) lets other code select a pin, e.g. when a card is chosen.
    window.browseMapSelect = (id, options = {}) => aiSelect(id, { ...options, source: 'external' });

    const initial = readInitialData();
    update(initial.pins, initial.opts);
}

// The page's data element holds either the pins array (browse page) or {pins, origin} (search results).
function readInitialData() {
    const dataEl = document.getElementById('browse-map-data');
    if (!dataEl) return { pins: [], opts: undefined };
    try {
        const data = JSON.parse(dataEl.textContent);
        if (Array.isArray(data)) return { pins: data, opts: undefined };
        return { pins: data?.pins || [], opts: { origin: data?.origin ?? null } };
    } catch (err) {
        console.error('Browse map: could not parse property data', err);
        return { pins: [], opts: undefined };
    }
}

/**
 * Replace every pin with the given properties and re-fit the view. Every results map (the browse page, with or
 * without filters, and AI search results) is drawn by ai-map-layer.js: search results carry a `fit` per pin and
 * an `origin`; browse pins are plain price pills ('browse' mode) with the same selection, preview card and controls.
 */
function update(properties, opts) {
    if (!map) return;

    aiUpdate(map, clusterGroup, properties, { ...(opts || {}), mode: isAiPayload(properties, opts) ? 'search' : 'browse' });
}
