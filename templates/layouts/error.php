<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $status) ?> <?= e($title) ?> | <?= e(config('name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="body-guest">
<main class="error-shell">
    <section class="panel panel-ledger error-card">
        <p class="error-code">Kode <?= e((string) $status) ?></p>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <div class="action-row">
            <a class="button button-primary" href="<?= e($homeUrl) ?>">Kembali ke beranda</a>
            <a class="button button-ghost" href="<?= e(url('education/index.php')) ?>">Buka edukasi obat</a>
        </div>
    </section>
</main>
</body>
</html>
