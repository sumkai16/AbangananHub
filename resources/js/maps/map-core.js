import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
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

export function createMap(elementId, centerLat, centerLng, zoom = 15) {
    const map = L.map(elementId, {
        scrollWheelZoom: false, // prevents page-scroll from hijacking the map
    }).setView([centerLat, centerLng], zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

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

export function fitToMarkers(map, markers) {
    if (markers.length === 0) return;
    const group = L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.2));
}