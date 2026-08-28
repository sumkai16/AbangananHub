import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import '../../css/maps.css';

// Default Leaflet marker icons reference image paths that break under Vite
// bundling unless reconfigured. Sidestepped entirely — every marker below
// uses an explicit custom divIcon instead, so the default icon is never touched.

const ICONS = {
    property: { className: 'map-pin map-pin--property', label: 'Property' },
    school: { className: 'map-pin map-pin--school', label: 'School' },
    hospital: { className: 'map-pin map-pin--hospital', label: 'Hospital / Clinic' },
    shopping: { className: 'map-pin map-pin--shopping', label: 'Mall / Grocery' },
    transport: { className: 'map-pin map-pin--transport', label: 'Transport' },
    origin: { className: 'map-pin map-pin--origin', label: 'Your location' },
};

export { L };

// 'voyager' (default, every browse/detail map) is a stylized basemap — clean
// for scanning a city, but it draws no rooftops, so it can't tell a landlord
// which building is theirs. 'satellite' is for the one page where that
// matters: pinning your own property. Esri's free (no API key) World Imagery
// tile service, same family the location picker uses.
export function createMap(elementId, centerLat, centerLng, zoom = 15, style = 'voyager') {
    const map = L.map(elementId, {
        scrollWheelZoom: true, // Allow zoom with scroll
        zoomSnap: 0.25,        // Fractional zooming for smooth scrolling
        zoomDelta: 0.5,        // Zoom steps per wheel click
        wheelPxPerZoomLevel: 100 // How fast the scroll wheel zooms
    }).setView([centerLat, centerLng], zoom);

    if (style === 'satellite') {
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
            maxZoom: 20,
        }).addTo(map);

        // Imagery alone has no street names or barangay boundaries — this
        // reference layer overlays just labels/lines on top of it, semi
        // transparent handled by Esri's own tile styling.
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 20,
        }).addTo(map);
    } else {
        // CartoDB Voyager tiles for a clean, modern, premium look (similar to Airbnb).
        // CARTO gated this CDN behind a free API key (Aug 2026) — request one at
        // https://carto.com/basemaps/apikey (no CARTO account needed, emailed in
        // ~1 minute, free up to 5M tile requests/month) and set CARTO_API_KEY in
        // .env. A brief attempt to avoid the signup by falling back to plain OSM
        // tiles produced a visibly worse map (busier roads/labels) — see
        // ARCHITECTURE.md's decision log — so the key is worth the extra step.
        const cartoKey = import.meta.env.VITE_CARTO_API_KEY;
        if (!cartoKey) {
            console.warn('VITE_CARTO_API_KEY is not set — map tiles will show an "API key required" watermark. See resources/js/maps/map-core.js.');
        }
        L.tileLayer(`https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png${cartoKey ? `?key=${cartoKey}` : ''}`, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 20,
            subdomains: 'abcd',
        }).addTo(map);
    }

    // Re-enable scroll zoom once the user clicks into the map — standard
    // Leaflet pattern to balance page scrolling against map zooming.
    map.on('click', () => map.scrollWheelZoom.enable());

    return map;
}

export function createPin(map, lat, lng, type = 'property', popupHtml = null) {
    const config = ICONS[type] || ICONS.property;

    const icon = L.divIcon({
        className: config.className,
        html: `<span class="map-pin__dot" title="${config.label}"></span>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });

    const marker = L.marker([lat, lng], { icon }).addTo(map);

    if (popupHtml) {
        marker.bindPopup(popupHtml, { closeButton: true });
    }

    return marker;
}

// Price-pill marker for the browse page — content is dynamic text (price),
// not a fixed dot, so it can't reuse createPin's fixed iconSize approach.
// iconSize [0,0] anchors the wrapper exactly at the lat/lng point; the inner
// .map-price-pin element self-centers via CSS transform, so it auto-sizes
// to whatever price string it's given instead of clipping or overflowing.
export function createPricePin(map, lat, lng, propertyId, label, popupHtml = null, addToMap = true) {
    const icon = L.divIcon({
        className: 'map-price-pin-wrapper',
        html: `<div class="map-price-pin" data-property-id="${propertyId}">${label}</div>`,
        iconSize: [0, 0],
        iconAnchor: [0, 0]
    });

    const marker = L.marker([lat, lng], { icon });
    if (addToMap) marker.addTo(map);

    if (popupHtml) {
        marker.bindPopup(popupHtml, { closeButton: true });
    }

    return marker;
}

// Groups price-pin markers into clusters at low zoom so dense areas (e.g.
// downtown Cebu) don't collapse into an unreadable stack of overlapping pins.
export function createClusterGroup() {
    return L.markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        iconCreateFunction(cluster) {
            return L.divIcon({
                html: `<div class="map-cluster-pin">${cluster.getChildCount()}</div>`,
                className: 'map-cluster-pin-wrapper',
                iconSize: [36, 36],
            });
        },
    });
}

export function fitToMarkers(map, markers) {
    if (markers.length === 0) return;
    const group = L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.2));
}