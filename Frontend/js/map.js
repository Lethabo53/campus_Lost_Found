/* Leaflet helpers shared by the report forms, item detail, and browse map. */

// Richfield Centurion Campus, Gauteng — real coordinates (confirmed via Google Places)
const CAMPUS_CENTER = [-25.8545564, 28.1872956];

// Notable reference landmarks shown on the picker map so users can orient themselves
const CAMPUS_LANDMARKS = [
    { name: 'Richfield Centurion Campus', lat: -25.8545564, lon: 28.1872956 },
    { name: 'Centurion Mall', lat: -25.8571457, lon: 28.186789 },
];

window.pickedLatLng = null;

/** Interactive map used on the report-lost / report-found forms. Click to drop a pin. */
function initPickerMap(elId, onPick) {
    const map = L.map(elId).setView(CAMPUS_CENTER, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    // Reference landmarks (not pickable — just orientation points)
    CAMPUS_LANDMARKS.forEach(lm => {
        L.circleMarker([lm.lat, lm.lon], {
            radius: 6, color: '#A15A3E', fillColor: '#A15A3E', fillOpacity: 0.85, weight: 2,
        }).addTo(map).bindPopup(lm.name);
    });

    let marker = null;
    map.on('click', (e) => {
        const { lat, lng } = e.latlng;
        window.pickedLatLng = { lat, lon: lng };
        if (marker) marker.setLatLng(e.latlng); else marker = L.marker(e.latlng).addTo(map);
        if (typeof onPick === 'function') onPick(lat, lng);
    });
    return map;
}

/** Read-only map used on the item detail page and admin hotspot view. */
function initDisplayMap(elId, points) {
    const map = L.map(elId).setView(CAMPUS_CENTER, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    points.forEach(p => {
        L.marker([p.lat, p.lon]).addTo(map).bindPopup(p.label || '');
    });
    if (points.length) {
        const bounds = L.latLngBounds(points.map(p => [p.lat, p.lon]));
        map.fitBounds(bounds.pad(0.3));
    }
    return map;
}

/** Read-only map used on the item detail page and admin hotspot view. */
function initDisplayMap(elId, points) {
    const map = L.map(elId).setView(CAMPUS_CENTER, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    points.forEach(p => {
        L.marker([p.lat, p.lon]).addTo(map).bindPopup(p.label || '');
    });
    if (points.length) {
        const bounds = L.latLngBounds(points.map(p => [p.lat, p.lon]));
        map.fitBounds(bounds.pad(0.3));
    }
    return map;
}
