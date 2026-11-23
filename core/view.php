<?php

function view_shell_guest(?bool $set = null): bool
{
    static $guest = true;

    if ($set !== null) {
        $guest = $set;
    }

    return $guest;
}

function view_start(string $title, ?string $activeNav = null, array $options = []): void
{
    $pageTitle = $title;
    $user = current_user();
    $isGuest = ($options['guest'] ?? false) || $user === null;
    $extraStylesheets = $options['stylesheets'] ?? [];
    view_shell_guest($isGuest);
    $flashMessages = pull_flash_messages();
    $navGroups = $isGuest ? [] : navigation_for($user['role']);

    require ROOT_PATH . '/templates/layouts/header.php';
}

function view_end(array $scripts = []): void
{
    $pageScripts = $scripts;
    $isGuest = view_shell_guest();

    require ROOT_PATH . '/templates/layouts/footer.php';

    if (PHP_SAPI !== 'cli' && ob_get_level() > 0) {
        $html = '';
        while (ob_get_level() > 0) {
            $html = (string) ob_get_clean() . $html;
        }
        send_buffered_response($html);
    }
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require ROOT_PATH . '/templates/partials/' . $name . '.php';
}

function partial_html(string $name, array $data = []): string
{
    ob_start();
    partial($name, $data);

    return (string) ob_get_clean();
}

function navigation_for(string $role): array
{
    return match ($role) {
        'caregiver' => [
            'Pendampingan' => [
                'monitor' => ['caregivers/monitor.php', 'Pantau pasien'],
                'link' => ['caregivers/link.php', 'Tautkan pasien'],
            ],
            'Referensi' => [
                'education' => ['education/index.php', 'Edukasi obat'],
                'profile' => ['auth/profile.php', 'Akun saya'],
            ],
        ],
        'admin' => [
            'Kelola edukasi' => [
                'admin-articles' => ['education/admin/articles.php', 'Artikel edukasi'],
                'admin-interactions' => ['education/admin/interactions.php', 'Interaksi obat'],
                'education' => ['education/index.php', 'Lihat katalog pasien'],
            ],
            'Akun' => [
                'staff' => ['auth/staff.php', 'Akun apoteker'],
                'profile' => ['auth/profile.php', 'Akun saya'],
            ],
        ],
        default => [
            'Hari ini' => [
                'dashboard' => ['reports/dashboard.php', 'Beranda'],
                'schedules' => ['schedules/index.php', 'Jadwal minum'],
                'history' => ['schedules/history.php', 'Riwayat minum'],
            ],
            'Obat' => [
                'medications' => ['medications/index.php', 'Obat saya'],
                'prescriptions' => ['medications/prescriptions.php', 'Resep dokter'],
                'refills' => ['refills/index.php', 'Stok & tebus ulang'],
            ],
            'Catatan' => [
                'symptoms' => ['symptoms/index.php', 'Jurnal efek samping'],
                'report' => ['reports/medical_report.php', 'Laporan kontrol'],
            ],
            'Lainnya' => [
                'caregivers' => ['caregivers/index.php', 'Pendamping saya'],
                'education' => ['education/index.php', 'Edukasi obat'],
                'profile' => ['auth/profile.php', 'Profil kesehatan'],
            ],
        ],
    };
}

function render_error_page(int $status, string $message): void
{
    $titles = [
        400 => 'Permintaan tidak bisa diproses',
        403 => 'Akses ditolak',
        404 => 'Halaman tidak ditemukan',
        405 => 'Metode tidak diizinkan',
        419 => 'Sesi formulir kedaluwarsa',
        503 => 'Layanan belum siap',
    ];
    $title = $titles[$status] ?? 'Terjadi kesalahan';
    $homeUrl = function_exists('current_user') && isset($_SESSION['user_id']) ? url() : url('auth/login.php');

    ob_start();
    require ROOT_PATH . '/templates/layouts/error.php';
    $html = (string) ob_get_clean();
    send_buffered_response($html);
}

function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
}

function field_attrs(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}

function selected(mixed $current, mixed $value): string
{
    return (string) $current === (string) $value ? ' selected' : '';
}

function checked(mixed $condition): string
{
    return $condition ? ' checked' : '';
}

function select_options(array $options, mixed $current, ?string $placeholder = null): string
{
    $html = $placeholder !== null ? '<option value="">' . e($placeholder) . '</option>' : '';

    foreach ($options as $value => $text) {
        $html .= '<option value="' . e($value) . '"' . selected($current, $value) . '>' . e($text) . '</option>';
    }

    return $html;
}

function status_chip(string $status, ?string $text = null): string
{
    return '<span class="chip chip-' . e($status) . '">' . e($text ?? label('schedule_status', $status)) . '</span>';
}

function empty_state(string $title, string $text, ?string $actionUrl = null, ?string $actionLabel = null): string
{
    $html = '<div class="empty-state"><p class="empty-title">' . e($title) . '</p><p>' . e($text) . '</p>';

    if ($actionUrl !== null && $actionLabel !== null) {
        $html .= '<a class="button" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a>';
    }

    return $html . '</div>';
}
