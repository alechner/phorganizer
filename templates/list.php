<?php
/**
 * $group         - grouping mode: directory | extension | date | location | custom
 * $groups        - array of group rows
 * $selected      - selected group value (from query string)
 * $photos        - photos in selected group
 * $directories   - all known directories (for move target)
 * $db            - Database instance
 */

$groupLabels = [
    'directory' => 'Directory',
    'extension' => 'Extension',
    'date'      => 'Date',
    'location'  => 'Location',
    'custom'    => 'Custom filter',
];
$videoExts = SUPPORTED_VIDEO_EXTENSIONS;

$baseQuery = $listBaseParams ?? ['view' => 'list', 'group' => $group];
$customFields = $customFields ?? [];
$emptyGroupToken = $emptyGroupToken ?? '__EMPTY__';

$operatorLabels = [
    'contains' => 'contains',
    'starts_with' => 'starts with',
    'ends_with' => 'ends with',
    'eq' => 'equals',
    'neq' => 'not equal',
    'gt' => 'greater than',
    'gte' => 'greater or equal',
    'lt' => 'less than',
    'lte' => 'less or equal',
    'is_empty' => 'is empty',
    'is_not_empty' => 'is not empty',
];

$customFilter = $customFilter ?? [
    'field' => 'directory',
    'operator' => 'contains',
    'value' => '',
];

$directoryTreeNodes = [];
$directoryTreeChildren = [];

if ($group === 'directory') {
    $directoryTreeChildren['__root__'] = [];
    foreach ($groups as $row) {
        $path = (string) ($row['directory'] ?? '');
        if ($path === '') {
            continue;
        }
        $parts = preg_split('~[\\\\/]+~', trim($path, "\\/"));
        if (empty($parts)) {
            continue;
        }

        $isAbsolute = str_starts_with($path, '/');
        $parent = '__root__';
        $current = $isAbsolute ? '' : '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($isAbsolute) {
                $current = ($current === '') ? '/' . $part : $current . '/' . $part;
            } else {
                $current = ($current === '') ? $part : $current . '/' . $part;
            }

            if (!isset($directoryTreeNodes[$current])) {
                $directoryTreeNodes[$current] = [
                    'path' => $current,
                    'name' => $part,
                    'direct_count' => 0,
                    'total_count' => 0,
                ];
            }
            if (!isset($directoryTreeChildren[$parent])) {
                $directoryTreeChildren[$parent] = [];
            }
            $directoryTreeChildren[$parent][$current] = true;
            $parent = $current;
        }

        if (isset($directoryTreeNodes[$path])) {
            $directoryTreeNodes[$path]['direct_count'] = (int) ($row['count'] ?? 0);
        }
    }

    $computeTotals = function (string $path) use (&$computeTotals, &$directoryTreeNodes, &$directoryTreeChildren): int {
        $total = (int) ($directoryTreeNodes[$path]['direct_count'] ?? 0);
        foreach (array_keys($directoryTreeChildren[$path] ?? []) as $child) {
            $total += $computeTotals($child);
        }
        $directoryTreeNodes[$path]['total_count'] = $total;
        return $total;
    };

    foreach (array_keys($directoryTreeChildren['__root__'] ?? []) as $rootPath) {
        $computeTotals($rootPath);
    }
}
?>

<div class="list-page">
    <div class="list-sidebar">
        <h2 class="sidebar-title">Grouped by <?= htmlspecialchars($groupLabels[$group] ?? $group) ?></h2>

        <?php if ($group === 'custom'): ?>
            <form method="get" action="index.php" class="custom-filter-form">
                <input type="hidden" name="view" value="list">
                <input type="hidden" name="group" value="custom">

                <div class="form-group">
                    <label for="customGroupBy">Group by field</label>
                    <select name="custom_group_by" id="customGroupBy" class="form-control">
                        <?php foreach ($customFields as $field => $meta): ?>
                            <option value="<?= htmlspecialchars($field) ?>" <?= ($customGroupBy ?? '') === $field ? 'selected' : '' ?>>
                                <?= htmlspecialchars($meta['label'] ?? $field) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterField">Filter field</label>
                    <select name="filter_field" id="filterField" class="form-control">
                        <?php foreach ($customFields as $field => $meta): ?>
                            <option value="<?= htmlspecialchars($field) ?>" <?= ($customFilter['field'] ?? '') === $field ? 'selected' : '' ?>>
                                <?= htmlspecialchars($meta['label'] ?? $field) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterOperator">Operator</label>
                    <select name="filter_operator" id="filterOperator" class="form-control" data-filter-operator>
                        <?php foreach ($operatorLabels as $op => $label): ?>
                            <option value="<?= htmlspecialchars($op) ?>" <?= ($customFilter['operator'] ?? '') === $op ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterValue">Value</label>
                    <input
                        type="text"
                        name="filter_value"
                        id="filterValue"
                        class="form-control"
                        data-filter-value
                        value="<?= htmlspecialchars((string) ($customFilter['value'] ?? '')) ?>"
                    >
                </div>

                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            </form>
        <?php elseif ($group === 'directory' && !empty($groups)): ?>
            <form method="get" action="index.php" class="directory-options-form">
                <input type="hidden" name="view" value="list">
                <input type="hidden" name="group" value="directory">
                <?php if ($selected !== ''): ?>
                    <input type="hidden" name="selected" value="<?= htmlspecialchars($selected) ?>">
                <?php endif; ?>
                <label class="checkbox-inline">
                    <input type="checkbox" name="include_subdirs" value="1" <?= !empty($includeSubdirs) ? 'checked' : '' ?> onchange="this.form.submit()">
                    Include subdirectories in selection
                </label>
            </form>
        <?php endif; ?>

        <?php if (empty($groups)): ?>
            <p class="empty-msg">No photos imported yet. <a href="index.php?view=import">Import some!</a></p>
        <?php elseif ($group === 'directory' && !empty($directoryTreeNodes)): ?>
            <ul class="group-list directory-tree" id="directoryTree">
                <?php
                $roots = array_keys($directoryTreeChildren['__root__'] ?? []);
                usort($roots, static fn (string $a, string $b): int => strcasecmp($a, $b));

                $renderDirectoryTree = function (array $paths, int $depth = 0) use (&$renderDirectoryTree, $directoryTreeNodes, $directoryTreeChildren, $selected, $baseQuery, $includeSubdirs): void {
                    foreach ($paths as $path) {
                        $node = $directoryTreeNodes[$path] ?? null;
                        if ($node === null) {
                            continue;
                        }
                        $children = array_keys($directoryTreeChildren[$path] ?? []);
                        usort($children, static fn (string $a, string $b): int => strcasecmp($a, $b));
                        $hasChildren = !empty($children);

                        $normalizedPath = rtrim($path, '/');
                        $isActive = ($selected === $path);
                        $isAncestorOfActive = $selected !== ''
                            && $selected !== $path
                            && str_starts_with($selected . '/', $normalizedPath . '/');
                        $isExpanded = $hasChildren && ($isActive || $isAncestorOfActive);

                        $params = array_merge($baseQuery, ['selected' => $path]);
                        $href = 'index.php?' . http_build_query($params);
                        $count = $includeSubdirs ? (int) ($node['total_count'] ?? 0) : (int) ($node['direct_count'] ?? 0);
                        ?>
                        <li class="group-item dir-node <?= $isActive ? 'active' : '' ?>" data-depth="<?= $depth ?>" data-path="<?= htmlspecialchars($path) ?>">
                            <div class="dir-row">
                                <?php if ($hasChildren): ?>
                                    <button
                                        type="button"
                                        class="dir-toggle"
                                        data-dir-toggle
                                        aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                                        aria-label="Toggle subdirectories"
                                    >
                                        <span class="dir-toggle-icon"><?= $isExpanded ? '&#9662;' : '&#9656;' ?></span>
                                    </button>
                                <?php else: ?>
                                    <span class="dir-toggle-placeholder"></span>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($href) ?>" title="<?= htmlspecialchars($path) ?>">
                                    <span class="group-label"><?= htmlspecialchars($node['name']) ?></span>
                                    <span class="group-count"><?= number_format($count) ?></span>
                                </a>
                            </div>

                            <?php if ($hasChildren): ?>
                                <ul class="group-list dir-children" <?= $isExpanded ? '' : 'hidden' ?>>
                                    <?php $renderDirectoryTree($children, $depth + 1); ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                        <?php
                    }
                };

                $renderDirectoryTree($roots, 0);
                ?>
            </ul>
        <?php else: ?>
            <ul class="group-list">
                <?php foreach ($groups as $row): ?>
                    <?php
                    $val   = (string) ($row[$groupField] ?? '');
                    $count = (int) ($row['count'] ?? 0);
                    $label = $val === '' ? '(unknown)' : htmlspecialchars($val);
                    $title = '';

                    if ($group === 'extension') {
                        $label = '.' . htmlspecialchars(strtoupper($val));
                    } elseif ($group === 'location') {
                        if ($val === '__map__') {
                            $label = '&#x1F4CD; Map view';
                        } elseif ($val === '__nogps__') {
                            $label = 'No GPS data';
                        }
                    } elseif ($group === 'custom') {
                        $label = ($val === $emptyGroupToken) ? '(empty)' : htmlspecialchars($val);
                    }

                    $isActive = ($selected === $val);
                    $href = 'index.php?' . http_build_query(array_merge($baseQuery, ['selected' => $val]));
                    ?>
                    <li class="group-item <?= $isActive ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars($href) ?>" <?= !empty($title) ? 'title="' . $title . '"' : '' ?>>
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
            <form method="post" action="index.php" id="bulkForm">
                <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                <input type="hidden" name="return_group" value="<?= htmlspecialchars($group) ?>">
                <input type="hidden" name="return_selected" value="<?= htmlspecialchars($selected) ?>">
                <input type="hidden" name="return_query" value="<?= htmlspecialchars((string) ($returnQueryString ?? '')) ?>">

                <div class="bulk-toolbar" id="bulkToolbar">
                    <div class="bulk-toolbar-left">
                        <span class="bulk-count" id="selectedCount">0 selected</span>
                        <button type="button" class="btn btn-sm btn-secondary" id="selectAllBtn">Select All</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="deselectAllBtn">Deselect All</button>
                    </div>
                    <div class="bulk-toolbar-right">
                        <div class="bulk-move-group">
                            <input
                                type="text"
                                name="target_directory"
                                id="targetDirInput"
                                list="dir-datalist"
                                placeholder="Target directory path"
                                class="form-control bulk-dir-input"
                                autocomplete="off"
                            >
                            <datalist id="dir-datalist">
                                <?php foreach ($directories as $dir): ?>
                                    <option value="<?= htmlspecialchars($dir) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <button type="button" class="btn btn-sm btn-primary" id="moveBtn">Move</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" id="deleteBtn">Delete</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="cancelSelectBtn">Cancel</button>
                    </div>
                </div>

                <div class="photos-header">
                    <h2 class="photos-title">
                        <?php if ($group === 'directory'): ?>
                            <?= htmlspecialchars($selected) ?><?= !empty($includeSubdirs) ? ' (with subdirectories)' : '' ?>
                        <?php elseif ($group === 'extension'): ?>
                            .<?= htmlspecialchars(strtoupper($selected)) ?> files
                        <?php elseif ($group === 'date'): ?>
                            <?php
                            $ts = ($selected !== 'Unknown') ? strtotime($selected . '-01') : false;
                            echo htmlspecialchars($ts !== false ? date('F Y', $ts) : 'Unknown date');
                            ?>
                        <?php elseif ($group === 'custom'): ?>
                            <?php
                            $groupFieldLabel = $customFields[$customGroupBy]['label'] ?? $customGroupBy;
                            $groupValueLabel = ($selected === $emptyGroupToken) ? '(empty)' : $selected;
                            ?>
                            <?= htmlspecialchars($groupFieldLabel . ': ' . $groupValueLabel) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($selected) ?>
                        <?php endif; ?>
                        <span class="photos-count">(<?= number_format(count($photos)) ?>)</span>
                    </h2>
                    <button type="button" class="btn btn-sm btn-secondary" id="toggleSelectMode">&#9745; Select</button>
                </div>

                <div class="photo-grid" id="photoGrid">
                    <?php foreach ($photos as $photo): ?>
                        <?php $isVideo = in_array(strtolower($photo['extension']), $videoExts, true); ?>
                        <div class="photo-card-item" data-id="<?= (int) $photo['id'] ?>">
                            <input
                                type="checkbox"
                                name="ids[]"
                                value="<?= (int) $photo['id'] ?>"
                                class="photo-checkbox"
                                aria-label="Select <?= htmlspecialchars($photo['filename']) ?>"
                            >
                            <a href="index.php?view=photo&id=<?= (int) $photo['id'] ?>" class="photo-card">
                                <div class="photo-thumb-wrap">
                                    <?php if ($isVideo): ?>
                                        <div class="thumb-placeholder video-placeholder">
                                            <span class="video-icon">&#9654;</span>
                                            <span class="video-ext">.<?= htmlspecialchars(strtoupper($photo['extension'])) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <img
                                            src="serve.php?id=<?= (int) $photo['id'] ?>&thumb=1"
                                            alt="<?= htmlspecialchars($photo['filename']) ?>"
                                            class="photo-thumb"
                                            loading="lazy"
                                            onerror="this.parentNode.innerHTML='<div class=\'thumb-placeholder\'>' + (this.alt.split('.').pop().toUpperCase() || '?') + '</div>'"
                                        >
                                    <?php endif; ?>
                                </div>
                                <div class="photo-card-info">
                                    <span class="photo-name"><?= htmlspecialchars($photo['filename']) ?></span>
                                    <?php if (!empty($photo['date_taken'])): ?>
                                        <span class="photo-date"><?= htmlspecialchars(substr($photo['date_taken'], 0, 10)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>

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
