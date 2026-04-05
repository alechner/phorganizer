/* app.js – phorganizer */

(function () {
    'use strict';

    /* ── Leaflet map initialisation ────────────────────────── */
    function initMap() {
        // Full GPS map on location list page
        var mapEl = document.getElementById('map');
        if (mapEl && typeof window.gpsPhotos !== 'undefined' && window.gpsPhotos.length > 0) {
            var map = L.map('map');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            var markers = L.markerClusterGroup ? L.markerClusterGroup() : L.layerGroup();

            window.gpsPhotos.forEach(function (p) {
                var popupHtml =
                    '<div class="leaflet-popup-photo">' +
                    '<img src="' + escHtml(p.thumb) + '" alt="' + escHtml(p.filename) + '" ' +
                    'onerror="this.style.display=\'none\'">' +
                    '<a href="' + escHtml(p.url) + '">' + escHtml(p.filename) + '</a>' +
                    (p.date ? '<br><small>' + escHtml(p.date) + '</small>' : '') +
                    '</div>';

                L.marker([p.lat, p.lng])
                    .bindPopup(popupHtml)
                    .addTo(markers);
            });

            markers.addTo(map);

            // Fit bounds to all markers
            if (window.gpsPhotos.length === 1) {
                map.setView([window.gpsPhotos[0].lat, window.gpsPhotos[0].lng], 13);
            } else {
                var lats = window.gpsPhotos.map(function (p) { return p.lat; });
                var lngs = window.gpsPhotos.map(function (p) { return p.lng; });
                map.fitBounds([
                    [Math.min.apply(null, lats), Math.min.apply(null, lngs)],
                    [Math.max.apply(null, lats), Math.max.apply(null, lngs)]
                ], { padding: [30, 30] });
            }
        }

        // Small photo-view map
        var photoMapEl = document.getElementById('photoMap');
        if (photoMapEl && typeof window.photoGps !== 'undefined') {
            var pmap = L.map('photoMap').setView([window.photoGps.lat, window.photoGps.lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(pmap);
            L.marker([window.photoGps.lat, window.photoGps.lng])
                .bindPopup(window.photoGps.title)
                .addTo(pmap);
        }
    }

    /* ── Utilities ──────────────────────────────────────────── */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Load Leaflet dynamically only when needed ──────────── */
    function loadLeaflet(callback) {
        if (typeof L !== 'undefined') {
            callback();
            return;
        }

        var needMap = document.getElementById('map') || document.getElementById('photoMap');
        if (!needMap) return;

        var css = document.createElement('link');
        css.rel  = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);

        var script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    /* ── Init ───────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        loadLeaflet(initMap);
    });
})();
