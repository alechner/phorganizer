<?php
/**
 * Map view for location grouping.
 * $photosWithGps  - photos that have GPS data
 * $photosNoGps    - photos without GPS data
 */
?>
<div class="map-container">
    <h2>Photos by Location</h2>

    <?php if (empty($photosWithGps)): ?>
        <div class="empty-msg">
            <p>No photos with GPS data found.</p>
            <p>Make sure you import photos taken with a device that records location data.</p>
        </div>
    <?php else: ?>
        <p class="map-stat">
            <?= number_format(count($photosWithGps)) ?> photo<?= count($photosWithGps) !== 1 ? 's' : '' ?> with GPS coordinates
            <?php if (!empty($photosNoGps)): ?>
                &nbsp;&bull;&nbsp;
                <?= number_format(count($photosNoGps)) ?> without GPS
            <?php endif; ?>
        </p>
        <div id="map" class="leaflet-map"></div>
    <?php endif; ?>

    <?php if (!empty($photosNoGps)): ?>
        <div class="no-gps-section">
            <h3>Photos without GPS data (<?= number_format(count($photosNoGps)) ?>)</h3>
            <div class="photo-grid compact">
                <?php foreach ($photosNoGps as $photo): ?>
                    <a href="index.php?view=photo&id=<?= (int) $photo['id'] ?>" class="photo-card small">
                        <div class="photo-thumb-wrap">
                            <img
                                src="serve.php?id=<?= (int) $photo['id'] ?>&thumb=1"
                                alt="<?= htmlspecialchars($photo['filename']) ?>"
                                class="photo-thumb"
                                loading="lazy"
                                onerror="this.parentNode.innerHTML='<div class=\'thumb-placeholder\'>' + (this.alt.split('.').pop().toUpperCase() || '?') + '</div>'"
                            >
                        </div>
                        <div class="photo-card-info">
                            <span class="photo-name"><?= htmlspecialchars($photo['filename']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($photosWithGps)): ?>
<script>
    window.gpsPhotos = <?= json_encode(array_map(function($p) {
        return [
            'id'       => (int) $p['id'],
            'filename' => $p['filename'],
            'lat'      => (float) $p['gps_latitude'],
            'lng'      => (float) $p['gps_longitude'],
            'thumb'    => 'serve.php?id=' . (int) $p['id'] . '&thumb=1',
            'url'      => 'index.php?view=photo&id=' . (int) $p['id'],
            'date'     => $p['date_taken'] ? substr($p['date_taken'], 0, 10) : '',
        ];
    }, $photosWithGps)) ?>;
</script>
<?php endif; ?>
