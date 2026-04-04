<?php
/**
 * $group     - grouping mode: directory | extension | date | location
 * $groups    - array of group rows
 * $selected  - selected group value (from query string)
 * $photos    - photos in selected group
 * $db        - Database instance
 */

$groupLabels = [
    'directory' => 'Directory',
    'extension' => 'Extension',
    'date'      => 'Date',
    'location'  => 'Location',
];
?>

<div class="list-page">
    <div class="list-sidebar">
        <h2 class="sidebar-title">Grouped by <?= htmlspecialchars($groupLabels[$group] ?? $group) ?></h2>

        <?php if (empty($groups)): ?>
            <p class="empty-msg">No photos imported yet. <a href="index.php?view=import">Import some!</a></p>
        <?php else: ?>
            <ul class="group-list">
                <?php foreach ($groups as $row): ?>
                    <?php
                    $val   = $row[$groupField] ?? '';
                    $count = (int) ($row['count'] ?? 0);
                    $label = $val === '' ? '(unknown)' : htmlspecialchars($val);
                    $title = '';
                    if ($group === 'directory') {
                        $label = htmlspecialchars(basename($val) ?: $val);
                        $title = htmlspecialchars($val);
                    } elseif ($group === 'extension') {
                        $label = '.' . htmlspecialchars(strtoupper($val));
                    } elseif ($group === 'location') {
                        if ($val === '__map__') {
                            $label = '&#x1F4CD; Map view';
                        } elseif ($val === '__nogps__') {
                            $label = 'No GPS data';
                        } else {
                            $label = htmlspecialchars($val);
                        }
                    }
                    $isActive = ($selected === $val);
                    $href = 'index.php?view=list&group=' . urlencode($group) . '&selected=' . urlencode($val);
                    ?>
                    <li class="group-item <?= $isActive ? 'active' : '' ?>">
                        <a href="<?= $href ?>" <?= !empty($title) ? 'title="' . $title . '"' : '' ?>>
                            <span class="group-label"><?= $label ?></span>
                            <span class="group-count"><?= number_format($count) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="list-main">
        <?php if ($group === 'location' && $selected === '__map__'): ?>
            <?php include __DIR__ . '/map.php'; ?>
        <?php elseif (!empty($photos)): ?>
            <div class="photos-header">
                <h2 class="photos-title">
                    <?php if ($group === 'directory'): ?>
                        <?= htmlspecialchars($selected) ?>
                    <?php elseif ($group === 'extension'): ?>
                        .<?= htmlspecialchars(strtoupper($selected)) ?> files
                    <?php elseif ($group === 'date'): ?>
                        <?php
                        $ts = ($selected !== 'Unknown') ? strtotime($selected . '-01') : false;
                        echo htmlspecialchars($ts !== false ? date('F Y', $ts) : 'Unknown date');
                        ?>
                    <?php else: ?>
                        <?= htmlspecialchars($selected) ?>
                    <?php endif; ?>
                    <span class="photos-count">(<?= number_format(count($photos)) ?>)</span>
                </h2>
            </div>

            <div class="photo-grid">
                <?php foreach ($photos as $photo): ?>
                    <a href="index.php?view=photo&id=<?= (int) $photo['id'] ?>" class="photo-card">
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
                            <?php if (!empty($photo['date_taken'])): ?>
                                <span class="photo-date"><?= htmlspecialchars(substr($photo['date_taken'], 0, 10)) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php elseif (!empty($selected) && $group !== 'location'): ?>
            <p class="empty-msg">No photos found for this selection.</p>
        <?php elseif ($group === 'location'): ?>
            <?php include __DIR__ . '/map.php'; ?>
        <?php else: ?>
            <div class="welcome-message">
                <h2>Welcome to <?= APP_NAME ?></h2>
                <p>Select a group from the sidebar to browse photos, or <a href="index.php?view=import">import photos</a> to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
