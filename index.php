<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Importer.php';

$db          = new Database(DB_PATH);
$view        = $_GET['view']   ?? 'list';
$group       = $_GET['group']  ?? 'directory';
$selected    = $_GET['selected'] ?? '';
$action      = $_GET['action'] ?? '';
$includeSubdirs = ($_GET['include_subdirs'] ?? '0') === '1';
$customGroupBy  = $db->sanitizeCustomField($_GET['custom_group_by'] ?? 'directory', 'directory');
$customFilterField = $db->sanitizeCustomField($_GET['filter_field'] ?? 'directory', 'directory');
$customFilterOperator = $db->sanitizeFilterOperator($_GET['filter_operator'] ?? 'contains');
$customFilterValue = trim((string) ($_GET['filter_value'] ?? ''));
$totalPhotos = $db->countPhotos();

$buildListParams = static function (array $overrides = []) use (
    $group,
    $selected,
    $includeSubdirs,
    $customGroupBy,
    $customFilterField,
    $customFilterOperator,
    $customFilterValue
): array {
    $params = [
        'view' => 'list',
        'group' => $group,
    ];

    if ($selected !== '') {
        $params['selected'] = $selected;
    }
    if ($group === 'directory' && $includeSubdirs) {
        $params['include_subdirs'] = '1';
    }
    if ($group === 'custom') {
        $params['custom_group_by'] = $customGroupBy;
        $params['filter_field'] = $customFilterField;
        $params['filter_operator'] = $customFilterOperator;
        if ($customFilterValue !== '') {
            $params['filter_value'] = $customFilterValue;
        }
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return $params;
};

$returnQueryString = http_build_query($buildListParams());

// ── Handle POST actions ───────────────────────────────────────────────────────
$flashMessage = '';
$flashType    = 'info';
$importResult = null;

if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $importPath = trim($_POST['import_path'] ?? '');
    if ($importPath === '') {
        $flashMessage = 'Please enter a directory path.';
        $flashType    = 'error';
        $view         = 'import';
    } else {
        $allExtensions = array_merge(SUPPORTED_EXTENSIONS, SUPPORTED_VIDEO_EXTENSIONS);
        $importer     = new Importer($allExtensions);
        $importResult = $importer->import($importPath, $db);
        $totalPhotos  = $db->countPhotos();
        $view         = 'import';
        if (empty($importResult['errors'])) {
            $flashMessage = 'Import complete: ' . number_format($importResult['imported']) . ' file(s) processed.';
            $flashType    = 'success';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction     = $_POST['bulk_action'];
    $ids            = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $returnGroup    = $_POST['return_group']    ?? 'directory';
    $returnSelected = $_POST['return_selected'] ?? '';
    $returnQueryRaw = (string) ($_POST['return_query'] ?? '');
    parse_str($returnQueryRaw, $returnQuery);
    if (!is_array($returnQuery)) {
        $returnQuery = [];
    }
    $returnQuery['view'] = 'list';
    $returnQuery['group'] = (string) ($returnQuery['group'] ?? $returnGroup);
    if (!isset($returnQuery['selected']) && $returnSelected !== '') {
        $returnQuery['selected'] = $returnSelected;
    }

    if ($bulkAction === 'delete' && !empty($ids)) {
        $deleted = 0;
        $bulkErrors = [];
        foreach ($ids as $fid) {
            $photo = $db->getById($fid);
            if (!$photo) {
                continue;
            }
            if (is_file($photo['filepath'])) {
                if (@unlink($photo['filepath'])) {
                    $db->deleteById($fid);
                    $deleted++;
                } else {
                    $bulkErrors[] = 'Could not delete file: ' . $photo['filename'];
                }
            } else {
                // File already gone from disk – remove from DB
                $db->deleteById($fid);
                $deleted++;
            }
        }
        $flashMessage = 'Deleted ' . $deleted . ' file(s).';
        if (!empty($bulkErrors)) {
            $flashMessage .= ' Errors: ' . implode('; ', $bulkErrors);
            $flashType = 'error';
        } else {
            $flashType = 'success';
        }
        $totalPhotos = $db->countPhotos();
        header('Location: index.php?' . http_build_query(array_merge($returnQuery, [
            'flash' => $flashMessage,
            'flash_type' => $flashType,
        ])));
        exit;
    }

    if ($bulkAction === 'move' && !empty($ids)) {
        $targetDir = rtrim(trim($_POST['target_directory'] ?? ''), '/\\');
        if ($targetDir === '') {
            $flashMessage = 'Please specify a target directory.';
            $flashType    = 'error';
        } elseif (!is_dir($targetDir)) {
            $flashMessage = 'Target directory does not exist: ' . $targetDir;
            $flashType    = 'error';
        } else {
            $moved = 0;
            $bulkErrors = [];
            foreach ($ids as $fid) {
                $photo = $db->getById($fid);
                if (!$photo) {
                    continue;
                }
                $newPath = $targetDir . DIRECTORY_SEPARATOR . $photo['filename'];
                if (file_exists($newPath)) {
                    $bulkErrors[] = 'File already exists in target: ' . $photo['filename'];
                    continue;
                }
                if (!is_file($photo['filepath'])) {
                    $bulkErrors[] = 'Source file not found: ' . $photo['filename'];
                    continue;
                }
                if (@rename($photo['filepath'], $newPath)) {
                    $db->updateFilepath($fid, $newPath, $targetDir);
                    $moved++;
                } else {
                    $bulkErrors[] = 'Could not move: ' . $photo['filename'];
                }
            }
            $flashMessage = 'Moved ' . $moved . ' file(s).';
            if (!empty($bulkErrors)) {
                $flashMessage .= ' Errors: ' . implode('; ', $bulkErrors);
                $flashType = 'error';
            } else {
                $flashType = 'success';
            }
        }
        header('Location: index.php?' . http_build_query(array_merge($returnQuery, [
            'flash' => $flashMessage,
            'flash_type' => $flashType,
        ])));
        exit;
    }
}

// Pick up flash messages passed via redirect
if (empty($flashMessage) && !empty($_GET['flash'])) {
    $flashMessage = $_GET['flash'];
    $flashType    = $_GET['flash_type'] ?? 'info';
}

// ── Render view ───────────────────────────────────────────────────────────────
$content     = '';
$pageTitle   = '';
$extraHead   = '';
$extraScripts = '';

ob_start();

switch ($view) {
    // ── Import page ───────────────────────────────────────────
    case 'import':
        $pageTitle = 'Import Photos';
        include __DIR__ . '/templates/import.php';
        break;

    // ── Single photo view ─────────────────────────────────────
    case 'photo':
        $id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $photo = $id > 0 ? $db->getById($id) : null;
        if (!$photo) {
            http_response_code(404);
            echo '<p class="empty-msg">Photo not found.</p>';
        } else {
            $pageTitle = $photo['filename'];
            if ($photo['gps_latitude'] !== null) {
                $extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
            }
            include __DIR__ . '/templates/photo.php';
        }
        break;

    // ── List / grouped view ───────────────────────────────────
    case 'list':
    default:
        $view = 'list';
        $groups      = [];
        $photos      = [];
        $groupField  = '';
        $photosWithGps = [];
        $photosNoGps   = [];
        $directories   = $db->getDirectories();
        $customFields  = $db->getCustomFields();
        $emptyGroupToken = $db->getEmptyGroupToken();
        $customFilter = [
            'field' => $customFilterField,
            'operator' => $customFilterOperator,
            'value' => $customFilterValue,
        ];
        $listBaseParams = $buildListParams(['selected' => null]);

        switch ($group) {
            case 'directory':
                $pageTitle  = 'Photos by Directory';
                $groupField = 'directory';
                $groups     = $db->groupByDirectory();
                if ($selected !== '') {
                    $photos = $db->getByDirectory($selected, $includeSubdirs);
                } elseif (!empty($groups)) {
                    $selected = $groups[0]['directory'];
                    $photos   = $db->getByDirectory($selected, $includeSubdirs);
                }
                $listBaseParams = $buildListParams([
                    'selected' => null,
                    'include_subdirs' => $includeSubdirs ? '1' : null,
                ]);
                break;

            case 'extension':
                $pageTitle  = 'Photos by Extension';
                $groupField = 'extension';
                $groups     = $db->groupByExtension();
                if ($selected !== '') {
                    $photos = $db->getByExtension($selected);
                } elseif (!empty($groups)) {
                    $selected = $groups[0]['extension'];
                    $photos   = $db->getByExtension($selected);
                }
                break;

            case 'date':
                $pageTitle  = 'Photos by Date';
                $groupField = 'year_month';
                $groups     = $db->groupByDate();
                if ($selected !== '') {
                    $photos = $db->getByYearMonth($selected);
                } elseif (!empty($groups)) {
                    $selected = $groups[0]['year_month'];
                    $photos   = $db->getByYearMonth($selected);
                }
                break;

            case 'location':
                $pageTitle     = 'Photos by Location';
                $groupField    = 'location';
                $photosWithGps = $db->getWithGps();
                $photosNoGps   = $db->getWithoutGps();

                // Build synthetic group list: "With GPS" + individual entries would be too many
                // Show a single "Map" entry + "No GPS" count
                $groups = [];
                if (!empty($photosWithGps)) {
                    $groups[] = ['location' => '__map__', 'count' => count($photosWithGps)];
                }
                if (!empty($photosNoGps)) {
                    $groups[] = ['location' => '__nogps__', 'count' => count($photosNoGps)];
                }

                // Default: show map
                if ($selected === '' && !empty($photosWithGps)) {
                    $selected = '__map__';
                } elseif ($selected === '' && !empty($photosNoGps)) {
                    $selected = '__nogps__';
                }

                if ($selected === '__nogps__') {
                    $photos = $photosNoGps;
                }

                $extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
                break;

            case 'custom':
                $pageTitle  = 'Custom Filter Grouping';
                $groupField = 'group_value';
                $groups     = $db->groupByCustomFilter($customGroupBy, $customFilter);

                if ($selected !== '') {
                    $photos = $db->getByCustomSelection($customGroupBy, $selected, $customFilter);
                } elseif (!empty($groups)) {
                    $selected = $groups[0]['group_value'];
                    $photos   = $db->getByCustomSelection($customGroupBy, $selected, $customFilter);
                }

                $listBaseParams = $buildListParams([
                    'selected' => null,
                    'custom_group_by' => $customGroupBy,
                    'filter_field' => $customFilterField,
                    'filter_operator' => $customFilterOperator,
                    'filter_value' => $customFilterValue !== '' ? $customFilterValue : null,
                ]);
                break;

            default:
                $group = 'directory';
                header('Location: index.php?view=list&group=directory');
                exit;
        }
        $returnQueryString = http_build_query(array_merge($listBaseParams, [
            'selected' => $selected !== '' ? $selected : null,
        ]));
        include __DIR__ . '/templates/list.php';
        break;
}

$content = ob_get_clean();

// ── Output layout ─────────────────────────────────────────────────────────────
include __DIR__ . '/templates/layout.php';
