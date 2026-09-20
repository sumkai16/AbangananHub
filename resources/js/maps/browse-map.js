// Entry point for the browse map. Tiny on purpose: Leaflet + markercluster
// (~190 KB) and the tile requests only start once the visitor actually opens
// the map — the desktop "Show map" toggle or the mobile "Map" tab. Most
// visits never do, so most visits never pay for it.
let started = false;

function start() {
    if (started) return;
    started = true;

    import('./browse-map-init.js').then(({ init }) => {
        init();
        // The container was hidden when the toggle fired; remeasure now that
        // the map exists and is visible.
        window.browseMapRefit?.();
    });
}

window.addEventListener('browse-map-state', (e) => {
    if (e.detail) start();
});

document.addEventListener('click', (e) => {
    if (e.target.closest('[data-browse-map-tab]')) start();
});
