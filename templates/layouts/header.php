<?php
$shellAlerts = ['due' => 0, 'refill' => 0, 'reminder' => 0];
if (!$isGuest && $user['role'] === 'patient') {
    $shellAlerts['due'] = count(ScheduleService::urgentToday((int) $user['id']));
    $shellAlerts['refill'] = count(RefillService::activeAlertsForPatient((int) $user['id']));
    $shellAlerts['reminder'] = count(CaregiverService::pendingRemindersForPatient((int) $user['id']));
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(config('name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%230e5a4c'/%3E%3Ctext x='16' y='21' text-anchor='middle' font-family='Georgia,serif' font-size='14' font-weight='700' fill='%23ffffff'%3EOT%3C/text%3E%3C/svg%3E">
    <script>
        (function () {
            var stored = localStorage.getItem('obattepat-theme');
            if (stored === 'dark' || stored === 'light') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ($extraStylesheets as $stylesheet): ?>
    <link rel="stylesheet" href="<?= e(asset($stylesheet)) ?>">
    <?php endforeach; ?>
</head>
<body class="<?= $isGuest ? 'body-guest' : 'body-app' ?>" data-base-url="<?= e(base_url()) ?>">
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<?php if ($isGuest): ?>
<header class="guest-header">
    <div class="guest-header-inner">
        <a class="brand-mark" href="<?= e(url()) ?>">
            <span class="brand-seal" aria-hidden="true">OT</span>
            <span class="brand-name"><?= e(config('name')) ?></span>
        </a>
        <nav class="guest-nav" aria-label="Navigasi utama">
            <a href="<?= e(url('education/index.php')) ?>"<?= $activeNav === 'education' ? ' aria-current="page"' : '' ?>>Edukasi obat</a>
            <a href="<?= e(url('auth/login.php')) ?>"<?= $activeNav === 'login' ? ' aria-current="page"' : '' ?>>Masuk</a>
            <a class="button button-primary button-sm" href="<?= e(url('auth/register.php')) ?>">Daftar akun</a>
            <button type="button" class="button button-ghost button-sm" data-theme-toggle aria-label="Ganti tema terang atau gelap">Tema gelap</button>
        </nav>
    </div>
</header>
<main id="main-content" class="guest-main">
    <?php partial('flash_messages', ['messages' => $flashMessages]); ?>
<?php else: ?>
<div class="app-shell" data-shell>
    <div class="drawer-backdrop" data-drawer-backdrop hidden></div>
    <?php require ROOT_PATH . '/templates/layouts/sidebar.php'; ?>
    <div class="app-workspace">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="button button-ghost button-sm mobile-menu-btn" data-drawer-open aria-controls="app-sidebar" aria-expanded="false">Menu</button>
                <span class="topbar-title"><?= e($pageTitle) ?></span>
            </div>
            <div class="topbar-actions">
                <?php if ($user['role'] === 'patient' && $shellAlerts['refill'] > 0): ?>
                <a class="topbar-alert" href="<?= e(url('refills/index.php')) ?>">Stok menipis (<?= $shellAlerts['refill'] ?>)</a>
                <?php endif; ?>
                <button type="button" class="button button-ghost button-sm" data-theme-toggle aria-label="Ganti tema terang atau gelap">Tema gelap</button>
                <a class="topbar-user" href="<?= e(url('auth/profile.php')) ?>">
                    <span class="user-name"><?= e($user['full_name']) ?></span>
                    <span class="user-role"><?= e(label('role', $user['role'])) ?></span>
                </a>
                <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-ghost button-sm">Keluar</button>
                </form>
            </div>
        </header>
        <main id="main-content" class="app-main">
            <?php partial('flash_messages', ['messages' => $flashMessages]); ?>
<?php endif; ?>
