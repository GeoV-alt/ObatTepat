<aside id="app-sidebar" class="sidebar" aria-label="Navigasi aplikasi" data-sidebar>
    <div class="sidebar-top">
        <a class="brand-mark" href="<?= e(url(home_path_for($user['role']))) ?>">
            <span class="brand-seal" aria-hidden="true">OT</span>
            <span class="brand-name"><?= e(config('name')) ?></span>
        </a>
        <button type="button" class="button button-ghost button-sm mobile-close-btn" data-drawer-close aria-label="Tutup menu">Tutup</button>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navGroups as $groupName => $items): ?>
        <div class="nav-group">
            <p class="nav-group-label"><?= e($groupName) ?></p>
            <ul>
                <?php foreach ($items as $key => [$path, $text]): ?>
                <?php
                $badge = 0;
                if ($key === 'schedules') {
                    $badge = $shellAlerts['due'];
                } elseif ($key === 'refills') {
                    $badge = $shellAlerts['refill'];
                } elseif ($key === 'dashboard') {
                    $badge = $shellAlerts['reminder'];
                }
                ?>
                <li>
                    <a class="nav-link<?= $activeNav === $key ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                        <span><?= e($text) ?></span>
                        <?php if ($badge > 0): ?>
                        <span class="nav-count" aria-label="<?= $badge ?> perhatian"><?= $badge ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <p class="sidebar-role-note">Masuk sebagai <?= e(label('role', $user['role'])) ?></p>
        <p class="sidebar-disclaimer">Catatan obat ini membantu kepatuhan minum obat dan tidak menggantikan saran dokter atau apoteker.</p>
    </div>
</aside>
