<div class="import-page">
    <h1>Import Photos</h1>

    <form method="post" action="index.php?action=import" class="import-form">
        <div class="form-group">
            <label for="import_path">Directory Path</label>
            <input
                type="text"
                id="import_path"
                name="import_path"
                class="form-control"
                placeholder="/path/to/your/photos"
                value="<?= htmlspecialchars($_POST['import_path'] ?? '') ?>"
                required
            >
            <small class="form-hint">Enter the absolute path to the directory containing your photos. The directory will be scanned recursively.</small>
        </div>

        <button type="submit" class="btn btn-primary">
            <span class="icon">&#x2B06;</span> Import Photos
        </button>
    </form>

    <?php if (!empty($importResult)): ?>
        <div class="import-result <?= count($importResult['errors']) > 0 ? 'has-errors' : 'success' ?>">
            <h2>Import Results</h2>
            <p class="import-stat">
                <strong><?= number_format($importResult['imported']) ?></strong>
                file<?= $importResult['imported'] !== 1 ? 's' : '' ?> processed
                (<?= number_format($totalPhotos) ?> total in database)
            </p>

            <?php if (!empty($importResult['errors'])): ?>
                <div class="import-errors">
                    <h3>Errors (<?= count($importResult['errors']) ?>)</h3>
                    <ul>
                        <?php foreach ($importResult['errors'] as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($importResult['imported'] > 0): ?>
                <p><a href="index.php?view=list&group=directory" class="btn btn-secondary">View Imported Photos</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="import-info">
        <h2>Supported Formats</h2>
        <p>The following file formats will be imported:</p>
        <ul class="format-list">
            <?php foreach (SUPPORTED_EXTENSIONS as $ext): ?>
                <li>.<?= htmlspecialchars(strtoupper($ext)) ?></li>
            <?php endforeach; ?>
        </ul>

        <h2>What is Collected</h2>
        <ul>
            <li>File path, name, directory, extension</li>
            <li>File size and modification date</li>
            <li>Image dimensions (width &times; height)</li>
            <li>Date taken (from EXIF data)</li>
            <li>Camera make and model</li>
            <li>GPS coordinates (latitude, longitude, altitude)</li>
            <li>All available EXIF metadata</li>
        </ul>
    </div>
</div>
