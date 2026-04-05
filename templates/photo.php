<?php
/**
 * Single photo view
 * $photo - photo row from DB
 */

$exif = [];
if (!empty($photo['raw_exif'])) {
    $decoded = json_decode($photo['raw_exif'], true);
    if (is_array($decoded)) {
        $exif = $decoded;
    }
}

$videoExts = SUPPORTED_VIDEO_EXTENSIONS;
$videoMimeMap = [
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'mkv'  => 'video/x-matroska',
    'webm' => 'video/webm',
    'm4v'  => 'video/x-m4v',
    'wmv'  => 'video/x-ms-wmv',
    'flv'  => 'video/x-flv',
    '3gp'  => 'video/3gpp',
    'mpeg' => 'video/mpeg',
    'mpg'  => 'video/mpeg',
];
$isVideo = in_array(strtolower($photo['extension']), $videoExts);

function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>

<div class="photo-view">
    <div class="photo-view-back">
        <a href="javascript:history.back()" class="btn btn-sm">&larr; Back</a>
        <form method="post" action="index.php" class="photo-delete-form"
              onsubmit="return confirm('Permanently delete this file from disk?')">
            <input type="hidden" name="bulk_action" value="delete">
            <input type="hidden" name="ids[]" value="<?= (int) $photo['id'] ?>">
            <input type="hidden" name="return_group" value="directory">
            <input type="hidden" name="return_selected" value="<?= htmlspecialchars($photo['directory']) ?>">
            <button type="submit" class="btn btn-sm btn-danger">&#x1F5D1; Delete</button>
        </form>
    </div>

    <div class="photo-view-layout">
        <!-- Image / Video panel -->
        <div class="photo-view-image-panel">
            <div class="photo-full-wrap">
                <?php if ($isVideo): ?>
                    <video controls class="photo-full video-player">
                        <source src="serve.php?id=<?= (int) $photo['id'] ?>"
                                type="<?= htmlspecialchars($videoMimeMap[strtolower($photo['extension'])] ?? 'video/mp4') ?>">
                        Your browser does not support the video tag.
                    </video>
                <?php elseif (in_array($photo['extension'], ['jpg','jpeg','png','gif','webp','bmp'])): ?>
                    <img
                        src="serve.php?id=<?= (int) $photo['id'] ?>"
                        alt="<?= htmlspecialchars($photo['filename']) ?>"
                        class="photo-full"
                        id="fullImage"
                    >
                <?php else: ?>
                    <div class="thumb-placeholder large">
                        .<?= htmlspecialchars(strtoupper($photo['extension'])) ?>
                        <br><small>Preview not available</small>
                    </div>
                <?php endif; ?>
            </div>

            <div class="photo-view-filename">
                <?= htmlspecialchars($photo['filename']) ?>
            </div>
        </div>

        <!-- Info panel -->
        <div class="photo-view-info-panel">
            <h1 class="photo-view-title"><?= htmlspecialchars($photo['filename']) ?></h1>

            <table class="meta-table">
                <tbody>
                    <tr>
                        <th>Path</th>
                        <td class="mono small"><?= htmlspecialchars($photo['filepath']) ?></td>
                    </tr>
                    <tr>
                        <th>Directory</th>
                        <td><?= htmlspecialchars($photo['directory']) ?></td>
                    </tr>
                    <tr>
                        <th>Extension</th>
                        <td>.<?= htmlspecialchars(strtoupper($photo['extension'])) ?></td>
                    </tr>
                    <?php if ($photo['filesize']): ?>
                    <tr>
                        <th>File Size</th>
                        <td><?= formatBytes((int) $photo['filesize']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($photo['width'] && $photo['height']): ?>
                    <tr>
                        <th>Dimensions</th>
                        <td><?= (int) $photo['width'] ?> &times; <?= (int) $photo['height'] ?> px</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($photo['date_taken']): ?>
                    <tr>
                        <th>Date Taken</th>
                        <td><?= htmlspecialchars($photo['date_taken']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Modified</th>
                        <td><?= htmlspecialchars($photo['date_modified'] ?? '') ?></td>
                    </tr>
                    <?php if ($photo['camera_make'] || $photo['camera_model']): ?>
                    <tr>
                        <th>Camera</th>
                        <td><?= htmlspecialchars(trim(($photo['camera_make'] ?? '') . ' ' . ($photo['camera_model'] ?? ''))) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($photo['gps_latitude'] !== null && $photo['gps_longitude'] !== null): ?>
                    <tr>
                        <th>GPS</th>
                        <td>
                            <?= round((float) $photo['gps_latitude'], 6) ?>,
                            <?= round((float) $photo['gps_longitude'], 6) ?>
                            <?php if ($photo['gps_altitude'] !== null): ?>
                                <br><small>Altitude: <?= round((float) $photo['gps_altitude'], 1) ?> m</small>
                            <?php endif; ?>
                            <br>
                            <a href="https://www.openstreetmap.org/?mlat=<?= (float) $photo['gps_latitude'] ?>&mlon=<?= (float) $photo['gps_longitude'] ?>#map=14/<?= (float) $photo['gps_latitude'] ?>/<?= (float) $photo['gps_longitude'] ?>"
                               target="_blank" rel="noopener" class="map-link">
                                View on OpenStreetMap &#x2197;
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Imported</th>
                        <td><?= htmlspecialchars($photo['imported_at'] ?? '') ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ($photo['gps_latitude'] !== null && $photo['gps_longitude'] !== null): ?>
                <div id="photoMap" class="leaflet-map small-map"></div>
                <script>
                    window.photoGps = {
                        lat: <?= (float) $photo['gps_latitude'] ?>,
                        lng: <?= (float) $photo['gps_longitude'] ?>,
                        title: <?= json_encode($photo['filename']) ?>
                    };
                </script>
            <?php endif; ?>

            <?php if (!empty($exif)): ?>
                <details class="exif-details">
                    <summary>Raw EXIF Data</summary>
                    <div class="exif-sections">
                        <?php foreach ($exif as $section => $fields): ?>
                            <?php if (!is_array($fields)) continue; ?>
                            <div class="exif-section">
                                <h4><?= htmlspecialchars($section) ?></h4>
                                <table class="exif-table">
                                    <?php foreach ($fields as $key => $val): ?>
                                        <tr>
                                            <th><?= htmlspecialchars($key) ?></th>
                                            <td>
                                                <?php if (is_array($val)): ?>
                                                    <code><?= htmlspecialchars(json_encode($val)) ?></code>
                                                <?php else: ?>
                                                    <?= htmlspecialchars((string) $val) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($photo['gps_latitude'] !== null && $photo['gps_longitude'] !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof L !== 'undefined' && window.photoGps) {
        var map = L.map('photoMap').setView([window.photoGps.lat, window.photoGps.lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        L.marker([window.photoGps.lat, window.photoGps.lng])
            .bindPopup(window.photoGps.title)
            .addTo(map);
    }
});
</script>
<?php endif; ?>
