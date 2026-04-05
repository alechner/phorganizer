<?php

/**
 * serve.php – securely serve image and video files stored on disk.
 *
 * Usage:
 *   serve.php?id=123           → full file
 *   serve.php?id=123&thumb=1   → 200×200 thumbnail (images only)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';

$db    = new Database(DB_PATH);
$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$thumb = !empty($_GET['thumb']);

if ($id <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$photo = $db->getById($id);
if (!$photo) {
    http_response_code(404);
    exit('Not found');
}

$filepath = $photo['filepath'];

if (!is_file($filepath) || !is_readable($filepath)) {
    http_response_code(404);
    exit('File not found on disk');
}

$ext = strtolower($photo['extension']);

$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
    'tiff' => 'image/tiff',
    'tif'  => 'image/tiff',
    'heic' => 'image/heic',
    'heif' => 'image/heif',
    'svg'  => 'image/svg+xml',
    // video
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

$mime = $mimeMap[$ext] ?? 'application/octet-stream';

$thumbableImages = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'];

if ($thumb) {
    if (in_array($ext, $thumbableImages)) {
        serveThumb($filepath, $mime, (int) ($photo['orientation'] ?? 0), $ext);
    } else {
        // No thumbnail available for videos or unsupported formats
        http_response_code(404);
        exit('No thumbnail available');
    }
} else {
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: public, max-age=86400');
    readfile($filepath);
}

function serveThumb(string $filepath, string $mime, int $orientation, string $ext): void
{
    $thumbSize = 200;

    $img = null;
    try {
        $img = @imagecreatefromstring(file_get_contents($filepath));
    } catch (Throwable $e) {
        $img = false;
    }

    if ($img === false) {
        // Fall back to serving the original file
        header('Content-Type: ' . $mime);
        readfile($filepath);
        return;
    }

    $srcW = imagesx($img);
    $srcH = imagesy($img);

    // Handle EXIF orientation
    switch ($orientation) {
        case 3: $img = imagerotate($img, 180, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0);  break;
    }

    if ($orientation === 6 || $orientation === 8) {
        [$srcW, $srcH] = [$srcH, $srcW];
    }

    // Crop to square then scale
    if ($srcW > $srcH) {
        $cropX = (int) (($srcW - $srcH) / 2);
        $cropY = 0;
        $cropS = $srcH;
    } else {
        $cropX = 0;
        $cropY = (int) (($srcH - $srcW) / 2);
        $cropS = $srcW;
    }

    $thumb = imagecreatetruecolor($thumbSize, $thumbSize);

    // Preserve transparency for PNG/GIF
    if (in_array($ext, ['png', 'gif'])) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $thumbSize, $thumbSize, $transparent);
    }

    imagecopyresampled($thumb, $img, 0, 0, $cropX, $cropY, $thumbSize, $thumbSize, $cropS, $cropS);

    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    imagejpeg($thumb, null, 80);

    imagedestroy($img);
    imagedestroy($thumb);
}
