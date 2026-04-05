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

    /* ── Multi-select ───────────────────────────────────────── */
    function initMultiSelect() {
        var grid         = document.getElementById('photoGrid');
        var toolbar      = document.getElementById('bulkToolbar');
        var toggleBtn    = document.getElementById('toggleSelectMode');
        var selectAllBtn = document.getElementById('selectAllBtn');
        var deselectBtn  = document.getElementById('deselectAllBtn');
        var cancelBtn    = document.getElementById('cancelSelectBtn');
        var deleteBtn    = document.getElementById('deleteBtn');
        var moveBtn      = document.getElementById('moveBtn');
        var countEl      = document.getElementById('selectedCount');
        var actionInput  = document.getElementById('bulkActionInput');
        var targetInput  = document.getElementById('targetDirInput');
        var form         = document.getElementById('bulkForm');

        if (!grid || !toolbar || !toggleBtn) return;

        var selectMode = false;

        function getCheckboxes() {
            return Array.prototype.slice.call(grid.querySelectorAll('.photo-checkbox'));
        }

        function getChecked() {
            return getCheckboxes().filter(function (cb) { return cb.checked; });
        }

        function updateUI() {
            var checked = getChecked();
            var n = checked.length;
            countEl.textContent = n + ' selected';
            if (n > 0) {
                toolbar.classList.add('visible');
            } else {
                toolbar.classList.remove('visible');
            }
            // Highlight selected items
            getCheckboxes().forEach(function (cb) {
                var item = cb.closest('.photo-card-item');
                if (item) {
                    item.classList.toggle('selected', cb.checked);
                }
            });
        }

        function enterSelectMode() {
            selectMode = true;
            grid.classList.add('select-mode');
            toggleBtn.textContent = '✕ Cancel Select';
            updateUI();
        }

        function exitSelectMode() {
            selectMode = false;
            grid.classList.remove('select-mode');
            toolbar.classList.remove('visible');
            toggleBtn.textContent = '☑ Select';
            getCheckboxes().forEach(function (cb) {
                cb.checked = false;
                var item = cb.closest('.photo-card-item');
                if (item) item.classList.remove('selected');
            });
        }

        toggleBtn.addEventListener('click', function () {
            if (selectMode) {
                exitSelectMode();
            } else {
                enterSelectMode();
            }
        });

        cancelBtn.addEventListener('click', exitSelectMode);

        // Click on photo-card-item in select mode toggles selection
        grid.addEventListener('click', function (e) {
            if (!selectMode) return;
            var item = e.target.closest('.photo-card-item');
            if (!item) return;
            var cb = item.querySelector('.photo-checkbox');
            if (!cb) return;
            if (e.target === cb) {
                // Direct checkbox click – let it handle itself, just update UI
                updateUI();
                return;
            }
            e.preventDefault();
            cb.checked = !cb.checked;
            updateUI();
        });

        // Checkbox change from keyboard
        grid.addEventListener('change', function (e) {
            if (e.target.classList.contains('photo-checkbox')) {
                updateUI();
            }
        });

        selectAllBtn.addEventListener('click', function () {
            getCheckboxes().forEach(function (cb) { cb.checked = true; });
            updateUI();
        });

        deselectBtn.addEventListener('click', function () {
            getCheckboxes().forEach(function (cb) { cb.checked = false; });
            updateUI();
        });

        deleteBtn.addEventListener('click', function () {
            var n = getChecked().length;
            if (n === 0) return;
            if (!confirm('Permanently delete ' + n + ' file(s) from disk?')) return;
            actionInput.value = 'delete';
            form.submit();
        });

        moveBtn.addEventListener('click', function () {
            var n = getChecked().length;
            if (n === 0) {
                alert('Please select at least one file.');
                return;
            }
            var target = targetInput ? targetInput.value.trim() : '';
            if (!target) {
                alert('Please enter a target directory path.');
                if (targetInput) targetInput.focus();
                return;
            }
            actionInput.value = 'move';
            form.submit();
        });
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
        initMultiSelect();
    });
})();
