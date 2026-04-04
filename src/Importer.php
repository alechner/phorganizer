<?php

class Importer
{
    private array $extensions;
    private int $imported = 0;
    private int $updated  = 0;
    private int $skipped  = 0;
    private array $errors = [];

    public function __construct(array $supportedExtensions)
    {
        $this->extensions = array_map('strtolower', $supportedExtensions);
    }

    public function import(string $rootPath, Database $db): array
    {
        $this->imported = 0;
        $this->updated  = 0;
        $this->skipped  = 0;
        $this->errors   = [];

        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            $this->errors[] = "Path does not exist or is not a directory: {$rootPath}";
            return $this->summary();
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $this->extensions, true)) {
                continue;
            }

            try {
                $this->processFile($file->getPathname(), $db);
            } catch (Throwable $e) {
                $this->errors[] = $file->getPathname() . ': ' . $e->getMessage();
            }
        }

        return $this->summary();
    }

    private function processFile(string $filepath, Database $db): void
    {
        $metadata = $this->extractMetadata($filepath);
        $db->upsertPhoto($metadata);
        $this->imported++;
    }

    private function extractMetadata(string $filepath): array
    {
        $filename  = basename($filepath);
        $directory = dirname($filepath);
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $filesize  = filesize($filepath);
        $dateMod   = date('Y-m-d H:i:s', filemtime($filepath));

        $width         = null;
        $height        = null;
        $dateTaken     = null;
        $cameraMake    = null;
        $cameraModel   = null;
        $gpsLat        = null;
        $gpsLon        = null;
        $gpsAlt        = null;
        $orientation   = null;
        $rawExif       = null;

        // Try to get image dimensions for common formats
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'])) {
            $size = @getimagesize($filepath);
            if ($size !== false) {
                $width  = $size[0];
                $height = $size[1];
            }
        }

        // Try EXIF data (mainly for JPEG/TIFF)
        if (in_array($extension, ['jpg', 'jpeg', 'tiff', 'tif', 'heic', 'heif'])) {
            $exif = @exif_read_data($filepath, null, true, false);
            if (is_array($exif)) {
                $rawExif = json_encode($exif, JSON_PARTIAL_OUTPUT_ON_ERROR);

                // Date taken
                $dateTaken = $this->extractDateTaken($exif);

                // Dimensions from EXIF if not from getimagesize
                if ($width === null && isset($exif['COMPUTED']['Width'])) {
                    $width  = (int) $exif['COMPUTED']['Width'];
                    $height = (int) ($exif['COMPUTED']['Height'] ?? 0);
                }

                // Camera info
                if (isset($exif['IFD0']['Make'])) {
                    $cameraMake = trim($exif['IFD0']['Make']);
                }
                if (isset($exif['IFD0']['Model'])) {
                    $cameraModel = trim($exif['IFD0']['Model']);
                }

                // Orientation
                if (isset($exif['IFD0']['Orientation'])) {
                    $orientation = (int) $exif['IFD0']['Orientation'];
                }

                // GPS
                if (isset($exif['GPS'])) {
                    [$gpsLat, $gpsLon, $gpsAlt] = $this->extractGps($exif['GPS']);
                }
            }
        }

        // For PNG/WebP: try to get date from file modification time
        if ($dateTaken === null) {
            // Use file modification date as fallback only — leave null to distinguish
        }

        return [
            ':filepath'     => $filepath,
            ':filename'     => $filename,
            ':directory'    => $directory,
            ':extension'    => $extension,
            ':filesize'     => $filesize !== false ? (int) $filesize : null,
            ':date_taken'   => $dateTaken,
            ':date_modified'=> $dateMod,
            ':width'        => $width,
            ':height'       => $height,
            ':camera_make'  => $cameraMake,
            ':camera_model' => $cameraModel,
            ':gps_latitude' => $gpsLat,
            ':gps_longitude'=> $gpsLon,
            ':gps_altitude' => $gpsAlt,
            ':orientation'  => $orientation,
            ':raw_exif'     => $rawExif,
        ];
    }

    private function extractDateTaken(array $exif): ?string
    {
        $fields = [
            ['EXIF', 'DateTimeOriginal'],
            ['EXIF', 'DateTimeDigitized'],
            ['IFD0', 'DateTime'],
        ];

        foreach ($fields as [$section, $field]) {
            if (!empty($exif[$section][$field])) {
                $raw = $exif[$section][$field];
                // EXIF format: "YYYY:MM:DD HH:MM:SS"
                $normalized = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $raw);
                if ($normalized && strtotime($normalized) !== false) {
                    return date('Y-m-d H:i:s', strtotime($normalized));
                }
            }
        }

        return null;
    }

    private function extractGps(array $gps): array
    {
        $lat = null;
        $lon = null;
        $alt = null;

        if (isset($gps['GPSLatitude'], $gps['GPSLatitudeRef'])) {
            $lat = $this->gpsToDecimal($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
        }

        if (isset($gps['GPSLongitude'], $gps['GPSLongitudeRef'])) {
            $lon = $this->gpsToDecimal($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
        }

        if (isset($gps['GPSAltitude'])) {
            $alt = $this->rationalToFloat($gps['GPSAltitude']);
            if (isset($gps['GPSAltitudeRef']) && $gps['GPSAltitudeRef'] === "\x01") {
                $alt = -$alt;
            }
        }

        return [$lat, $lon, $alt];
    }

    private function gpsToDecimal(array $components, string $ref): float
    {
        $degrees = $this->rationalToFloat($components[0] ?? '0/1');
        $minutes = $this->rationalToFloat($components[1] ?? '0/1');
        $seconds = $this->rationalToFloat($components[2] ?? '0/1');

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if (in_array(strtoupper($ref), ['S', 'W'], true)) {
            $decimal = -$decimal;
        }

        return round($decimal, 7);
    }

    private function rationalToFloat(string $rational): float
    {
        if (str_contains($rational, '/')) {
            [$num, $den] = explode('/', $rational, 2);
            return $den != 0.0 ? (float) $num / (float) $den : 0.0;
        }
        return (float) $rational;
    }

    private function summary(): array
    {
        return [
            'imported' => $this->imported,
            'errors'   => $this->errors,
        ];
    }
}
