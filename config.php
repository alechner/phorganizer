<?php

const DB_PATH = __DIR__ . '/data/phorganizer.db';

const SUPPORTED_EXTENSIONS = [
  'jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif',
  'heic', 'heif', 'webp', 'bmp',
  'raw', 'cr2', 'cr3', 'nef', 'nrw', 'orf', 'arw', 'rw2', 'dng',
];

define('SUPPORTED_VIDEO_EXTENSIONS', [
    'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'wmv', 'flv', '3gp', 'mpeg', 'mpg',
]);

const APP_NAME = 'phorganizer';
