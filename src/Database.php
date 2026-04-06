<?php

class Database
{
    private PDO $pdo;

    private const EMPTY_GROUP_TOKEN = '__EMPTY__';

    private const CUSTOM_FIELDS = [
        'directory'     => ['label' => 'Directory', 'type' => 'text'],
        'extension'     => ['label' => 'Extension', 'type' => 'text'],
        'filename'      => ['label' => 'Filename', 'type' => 'text'],
        'filesize'      => ['label' => 'File size', 'type' => 'number'],
        'date_taken'    => ['label' => 'Date taken', 'type' => 'date'],
        'date_modified' => ['label' => 'Date modified', 'type' => 'date'],
        'width'         => ['label' => 'Width', 'type' => 'number'],
        'height'        => ['label' => 'Height', 'type' => 'number'],
        'camera_make'   => ['label' => 'Camera make', 'type' => 'text'],
        'camera_model'  => ['label' => 'Camera model', 'type' => 'text'],
        'gps_latitude'  => ['label' => 'GPS latitude', 'type' => 'number'],
        'gps_longitude' => ['label' => 'GPS longitude', 'type' => 'number'],
        'gps_altitude'  => ['label' => 'GPS altitude', 'type' => 'number'],
        'orientation'   => ['label' => 'Orientation', 'type' => 'number'],
        'imported_at'   => ['label' => 'Imported at', 'type' => 'date'],
    ];

    public function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->createSchema();
    }

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS photos (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                filepath       TEXT    NOT NULL UNIQUE,
                filename       TEXT    NOT NULL,
                directory      TEXT    NOT NULL,
                extension      TEXT    NOT NULL,
                filesize       INTEGER,
                date_taken     TEXT,
                date_modified  TEXT,
                width          INTEGER,
                height         INTEGER,
                camera_make    TEXT,
                camera_model   TEXT,
                gps_latitude   REAL,
                gps_longitude  REAL,
                gps_altitude   REAL,
                orientation    INTEGER,
                raw_exif       TEXT,
                imported_at    TEXT    DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_directory  ON photos (directory);
            CREATE INDEX IF NOT EXISTS idx_extension  ON photos (extension);
            CREATE INDEX IF NOT EXISTS idx_date_taken ON photos (date_taken);
            CREATE INDEX IF NOT EXISTS idx_gps        ON photos (gps_latitude, gps_longitude);
        ");
    }

    public function upsertPhoto(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO photos
                (filepath, filename, directory, extension, filesize, date_taken, date_modified,
                 width, height, camera_make, camera_model, gps_latitude, gps_longitude,
                 gps_altitude, orientation, raw_exif)
            VALUES
                (:filepath, :filename, :directory, :extension, :filesize, :date_taken, :date_modified,
                 :width, :height, :camera_make, :camera_model, :gps_latitude, :gps_longitude,
                 :gps_altitude, :orientation, :raw_exif)
            ON CONFLICT(filepath) DO UPDATE SET
                filename      = excluded.filename,
                directory     = excluded.directory,
                extension     = excluded.extension,
                filesize      = excluded.filesize,
                date_taken    = excluded.date_taken,
                date_modified = excluded.date_modified,
                width         = excluded.width,
                height        = excluded.height,
                camera_make   = excluded.camera_make,
                camera_model  = excluded.camera_model,
                gps_latitude  = excluded.gps_latitude,
                gps_longitude = excluded.gps_longitude,
                gps_altitude  = excluded.gps_altitude,
                orientation   = excluded.orientation,
                raw_exif      = excluded.raw_exif
        ");
        $stmt->execute($data);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM photos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private const ALLOWED_ORDER_COLUMNS = [
        'date_taken', 'date_modified', 'filename', 'directory',
        'extension', 'filesize', 'imported_at', 'id',
    ];

    public function getAll(string $column = 'date_taken', string $direction = 'DESC'): array
    {
        if (!in_array($column, self::ALLOWED_ORDER_COLUMNS, true)) {
            $column = 'date_taken';
        }
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        return $this->pdo->query(
            "SELECT * FROM photos ORDER BY {$column} {$direction}, filename ASC"
        )->fetchAll();
    }

    public function countPhotos(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn();
    }

    public function groupByDirectory(): array
    {
        return $this->pdo->query("
            SELECT directory, COUNT(*) AS count
            FROM photos
            GROUP BY directory
            ORDER BY directory ASC
        ")->fetchAll();
    }

    public function getByDirectory(string $directory, bool $includeSubdirectories = false): array
    {
        if (!$includeSubdirectories) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM photos WHERE directory = ? ORDER BY date_taken ASC, filename ASC
            ");
            $stmt->execute([$directory]);
            return $stmt->fetchAll();
        }

        $escapedPrefix = $this->escapeLike($directory) . '/%';
        $stmt = $this->pdo->prepare("
            SELECT * FROM photos
            WHERE directory = :directory
               OR directory LIKE :prefix ESCAPE '\\'
            ORDER BY date_taken ASC, filename ASC
        ");
        $stmt->execute([
            ':directory' => $directory,
            ':prefix' => $escapedPrefix,
        ]);
        return $stmt->fetchAll();
    }

    public function getCustomFields(): array
    {
        return self::CUSTOM_FIELDS;
    }

    public function sanitizeCustomField(string $field, string $fallback = 'directory'): string
    {
        if (isset(self::CUSTOM_FIELDS[$field])) {
            return $field;
        }
        return isset(self::CUSTOM_FIELDS[$fallback]) ? $fallback : 'directory';
    }

    public function sanitizeFilterOperator(string $operator): string
    {
        $allowed = [
            'eq', 'neq', 'contains', 'starts_with', 'ends_with',
            'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty',
        ];
        return in_array($operator, $allowed, true) ? $operator : 'contains';
    }

    public function groupByCustomFilter(string $groupField, array $filter): array
    {
        $groupField = $this->sanitizeCustomField($groupField);
        $params = [];
        $whereSql = $this->buildFilterWhereSql($filter, $params);
        $groupExpr = $this->groupExpression($groupField);

        $sql = "
            SELECT {$groupExpr} AS group_value, COUNT(*) AS count
            FROM photos
            {$whereSql}
            GROUP BY group_value
            ORDER BY count DESC, group_value ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getByCustomSelection(string $groupField, string $selected, array $filter): array
    {
        $groupField = $this->sanitizeCustomField($groupField);
        $params = [];
        $whereSql = $this->buildFilterWhereSql($filter, $params);
        $groupExpr = $this->groupExpression($groupField);

        $sql = "
            SELECT *
            FROM photos
            {$whereSql}
            " . ($whereSql === '' ? 'WHERE' : 'AND') . " {$groupExpr} = :selected_group
            ORDER BY date_taken ASC, filename ASC
        ";
        $params[':selected_group'] = ($selected === '') ? self::EMPTY_GROUP_TOKEN : $selected;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getEmptyGroupToken(): string
    {
        return self::EMPTY_GROUP_TOKEN;
    }

    public function groupByExtension(): array
    {
        return $this->pdo->query("
            SELECT extension, COUNT(*) AS count
            FROM photos
            GROUP BY extension
            ORDER BY count DESC
        ")->fetchAll();
    }

    public function getByExtension(string $extension): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM photos WHERE extension = ? ORDER BY date_taken ASC, filename ASC
        ");
        $stmt->execute([$extension]);
        return $stmt->fetchAll();
    }

    public function groupByDate(): array
    {
        return $this->pdo->query("
            SELECT
                COALESCE(substr(date_taken, 1, 7), 'Unknown') AS year_month,
                COUNT(*) AS count
            FROM photos
            GROUP BY year_month
            ORDER BY year_month DESC
        ")->fetchAll();
    }

    public function getByYearMonth(string $yearMonth): array
    {
        if ($yearMonth === 'Unknown') {
            $stmt = $this->pdo->prepare("
                SELECT * FROM photos WHERE date_taken IS NULL OR date_taken = ''
                ORDER BY filename ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM photos WHERE substr(date_taken, 1, 7) = ?
                ORDER BY date_taken ASC, filename ASC
            ");
            $stmt->execute([$yearMonth]);
        }
        return $stmt->fetchAll();
    }

    public function getWithGps(): array
    {
        return $this->pdo->query("
            SELECT * FROM photos
            WHERE gps_latitude IS NOT NULL AND gps_longitude IS NOT NULL
            ORDER BY date_taken ASC
        ")->fetchAll();
    }

    public function getWithoutGps(): array
    {
        return $this->pdo->query("
            SELECT * FROM photos
            WHERE gps_latitude IS NULL OR gps_longitude IS NULL
            ORDER BY date_taken ASC, filename ASC
        ")->fetchAll();
    }

    public function search(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare("
            SELECT * FROM photos
            WHERE filename LIKE :q OR directory LIKE :q OR camera_make LIKE :q OR camera_model LIKE :q
            ORDER BY date_taken DESC, filename ASC
        ");
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll();
    }

    public function deleteByFilepath(string $filepath): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM photos WHERE filepath = ?');
        $stmt->execute([$filepath]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM photos WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function updateFilepath(int $id, string $newFilepath, string $newDirectory): void
    {
        $stmt = $this->pdo->prepare('UPDATE photos SET filepath = ?, directory = ?, filename = ? WHERE id = ?');
        $stmt->execute([$newFilepath, $newDirectory, basename($newFilepath), $id]);
    }

    public function getDirectories(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT directory FROM photos ORDER BY directory ASC'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function groupExpression(string $field): string
    {
        return "COALESCE(NULLIF(TRIM(CAST({$field} AS TEXT)), ''), '" . self::EMPTY_GROUP_TOKEN . "')";
    }

    private function buildFilterWhereSql(array $filter, array &$params): string
    {
        $field = $this->sanitizeCustomField((string) ($filter['field'] ?? ''), 'directory');
        $operator = $this->sanitizeFilterOperator((string) ($filter['operator'] ?? 'contains'));
        $value = trim((string) ($filter['value'] ?? ''));

        if ($operator !== 'is_empty' && $operator !== 'is_not_empty' && $value === '') {
            return '';
        }

        if ($operator === 'is_empty') {
            return "WHERE ({$field} IS NULL OR TRIM(CAST({$field} AS TEXT)) = '')";
        }
        if ($operator === 'is_not_empty') {
            return "WHERE ({$field} IS NOT NULL AND TRIM(CAST({$field} AS TEXT)) <> '')";
        }

        if (in_array($operator, ['contains', 'starts_with', 'ends_with'], true)) {
            if ($operator === 'contains') {
                $params[':filter_value'] = '%' . $this->escapeLike($value) . '%';
            } elseif ($operator === 'starts_with') {
                $params[':filter_value'] = $this->escapeLike($value) . '%';
            } else {
                $params[':filter_value'] = '%' . $this->escapeLike($value);
            }
            return "WHERE CAST({$field} AS TEXT) LIKE :filter_value ESCAPE '\\'";
        }

        $operatorSql = match ($operator) {
            'eq' => '=',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            default => '=',
        };

        $params[':filter_value'] = $value;
        return "WHERE {$field} {$operatorSql} :filter_value";
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '%' => '\\%',
            '_' => '\\_',
        ]);
    }
}
