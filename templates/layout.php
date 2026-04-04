<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> &mdash; <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <?php if (!empty($extraHead)): ?>
        <?= $extraHead ?>
    <?php endif; ?>
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="navbar-brand"><?= APP_NAME ?></a>
    <ul class="navbar-nav">
        <li><a href="index.php?view=list&group=directory" <?= (($view ?? '') === 'list' && ($group ?? '') === 'directory') ? 'class="active"' : '' ?>>By Directory</a></li>
        <li><a href="index.php?view=list&group=extension" <?= (($view ?? '') === 'list' && ($group ?? '') === 'extension') ? 'class="active"' : '' ?>>By Extension</a></li>
        <li><a href="index.php?view=list&group=date" <?= (($view ?? '') === 'list' && ($group ?? '') === 'date') ? 'class="active"' : '' ?>>By Date</a></li>
        <li><a href="index.php?view=list&group=location" <?= (($view ?? '') === 'list' && ($group ?? '') === 'location') ? 'class="active"' : '' ?>>By Location</a></li>
        <li><a href="index.php?view=import" <?= ($view ?? '') === 'import' ? 'class="active"' : '' ?>>Import</a></li>
    </ul>
    <?php if ($totalPhotos > 0): ?>
        <span class="navbar-count"><?= number_format($totalPhotos) ?> photo<?= $totalPhotos !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
</nav>

<main class="main-content">
    <?php if (!empty($flashMessage)): ?>
        <div class="flash flash-<?= htmlspecialchars($flashType ?? 'info') ?>">
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<script src="assets/app.js"></script>
<?php if (!empty($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
