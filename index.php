<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Importer.php';

$db          = new Database(DB_PATH);
$view        = $_GET['view']   ?? 'list';
$group       = $_GET['group']  ?? 'directory';
$selected    = $_GET['selected'] ?? '';
$action      = $_GET['action'] ?? '';
$totalPhotos = $db->countPhotos();

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
        $importer     = new Importer(SUPPORTED_EXTENSIONS);
        $importResult = $importer->import($importPath, $db);
        $totalPhotos  = $db->countPhotos();
        $view         = 'import';
        if (empty($importResult['errors'])) {
            $flashMessage = 'Import complete: ' . number_format($importResult['imported']) . ' file(s) processed.';
            $flashType    = 'success';
        }
    }
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

        switch ($group) {
            case 'directory':
                $pageTitle  = 'Photos by Directory';
                $groupField = 'directory';
                $groups     = $db->groupByDirectory();
                if ($selected !== '') {
                    $photos = $db->getByDirectory($selected);
                } elseif (!empty($groups)) {
                    $selected = $groups[0]['directory'];
                    $photos   = $db->getByDirectory($selected);
                }
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

            default:
                $group = 'directory';
                header('Location: index.php?view=list&group=directory');
                exit;
        }
        include __DIR__ . '/templates/list.php';
        break;
}

$content = ob_get_clean();

// ── Output layout ─────────────────────────────────────────────────────────────
include __DIR__ . '/templates/layout.php';
