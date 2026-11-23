<?php

function config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require ROOT_PATH . '/config/app.php';
    }

    return $config[$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $configured = rtrim((string) config('base_url', ''), '/');
    if ($configured !== '') {
        return $base = $configured;
    }

    $scriptFile = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $publicPath = str_replace('\\', '/', (string) realpath(PUBLIC_PATH));
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $relative = substr($scriptFile, strlen($publicPath));

    if ($relative !== '' && str_ends_with($scriptName, $relative)) {
        return $base = rtrim(substr($scriptName, 0, -strlen($relative)), '/');
    }

    return $base = '';
}

function url(string $path = '', array $params = []): string
{
    $url = base_url() . '/' . ltrim($path, '/');
    $params = array_filter($params, fn ($value) => $value !== null && $value !== '');

    return $params ? $url . '?' . http_build_query($params) : $url;
}

function asset(string $path): string
{
    $file = PUBLIC_PATH . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function clear_output_buffers(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function send_buffered_response(string $body, string $contentType = 'text/html; charset=utf-8'): void
{
    if (!headers_sent()) {
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($body));
        if (PHP_SAPI === 'cli-server') {
            header('Connection: close');
        }
    }

    echo $body;
}

function redirect(string $path, array $params = []): never
{
    clear_output_buffers();
    $target = preg_match('#^https?://#', $path) || str_starts_with($path, base_url() . '/') ? $path : url($path, $params);
    if (!headers_sent()) {
        header('Location: ' . $target);
        header('Content-Length: 0');
        if (PHP_SAPI === 'cli-server') {
            header('Connection: close');
        }
    }
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $value;
}

function query_param(string $key, mixed $default = ''): mixed
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $value;
}

function is_fetch_request(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function is_api_request(): bool
{
    return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') || is_fetch_request();
}

function json_response(array $data, int $status = 200): never
{
    clear_output_buffers();
    http_response_code($status);
    $payload = (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    send_buffered_response($payload, 'application/json; charset=utf-8');
    exit;
}

function abort(int $status, string $message): never
{
    if (is_api_request()) {
        json_response(['ok' => false, 'message' => $message], $status);
    }

    clear_output_buffers();
    http_response_code($status);
    render_error_page($status, $message);
    exit;
}

function safe_back(string $fallback): string
{
    $back = (string) ($_POST['back'] ?? $_GET['back'] ?? '');
    $prefix = base_url() . '/';

    if ($back !== '' && str_starts_with($back, $prefix) && !str_starts_with($back, '//') && !str_contains($back, "\n")) {
        return $back;
    }

    return url($fallback);
}

function current_url(): string
{
    return $_SERVER['REQUEST_URI'] ?? url();
}

function respond_action(bool $ok, string $message, array $data = [], string $fallback = ''): never
{
    if (is_fetch_request()) {
        json_response(['ok' => $ok, 'message' => $message] + $data, $ok ? 200 : 422);
    }

    flash($ok ? 'success' : 'error', $message);
    redirect(safe_back($fallback));
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function is_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function format_qty(mixed $value): string
{
    $number = round((float) $value, 2);

    if (floor($number) == $number) {
        return number_format($number, 0, ',', '.');
    }

    return rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
}

function format_date_id(?string $value, bool $withDay = true): string
{
    if (!$value) {
        return '-';
    }

    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $time = strtotime($value);
    $text = date('j', $time) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);

    return $withDay ? $days[(int) date('w', $time)] . ', ' . $text : $text;
}

function format_short_day(string $value): string
{
    $days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $time = strtotime($value);

    return $days[(int) date('w', $time)] . ' ' . date('j', $time);
}

function format_time(?string $value): string
{
    return $value ? date('H:i', strtotime($value)) : '-';
}

function format_datetime(?string $value): string
{
    return $value ? format_date_id($value, false) . ', ' . format_time($value) : '-';
}

function format_minutes(int $minutes): string
{
    $minutes = abs($minutes);

    if ($minutes < 60) {
        return $minutes . ' menit';
    }

    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;

    return $rest === 0 ? $hours . ' jam' : $hours . ' jam ' . $rest . ' menit';
}

function signa(array $medication): string
{
    return $medication['frequency_per_day'] . ' x sehari ' . format_qty($medication['dose_amount']) . ' ' . $medication['dose_unit'];
}

function labels(string $group): array
{
    $labels = [
        'role' => ['patient' => 'Pasien', 'caregiver' => 'Pendamping', 'admin' => 'Admin/Apoteker'],
        'gender' => ['L' => 'Laki-laki', 'P' => 'Perempuan'],
        'blood_type' => ['A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O', 'unknown' => 'Belum diketahui'],
        'dosage_form' => [
            'tablet' => 'Tablet', 'kapsul' => 'Kapsul', 'sirup' => 'Sirup', 'tetes' => 'Tetes',
            'salep' => 'Salep', 'inhaler' => 'Inhaler', 'lainnya' => 'Lainnya',
        ],
        'meal_rule' => [
            'sebelum_makan' => 'Sebelum makan', 'sesudah_makan' => 'Sesudah makan',
            'bersama_makan' => 'Bersama makan', 'bebas' => 'Kapan saja',
        ],
        'medication_status' => ['active' => 'Aktif', 'paused' => 'Dijeda', 'completed' => 'Selesai', 'discontinued' => 'Dihentikan'],
        'prescription_status' => ['active' => 'Aktif', 'completed' => 'Selesai', 'archived' => 'Diarsipkan'],
        'schedule_status' => [
            'upcoming' => 'Akan datang', 'due' => 'Waktunya minum', 'taken' => 'Sudah diminum',
            'missed' => 'Terlewat', 'skipped' => 'Dilewati', 'pending' => 'Belum diminum',
        ],
        'symptom_severity' => ['ringan' => 'Ringan', 'sedang' => 'Sedang', 'berat' => 'Berat'],
        'daily_condition' => ['membaik' => 'Membaik', 'stabil' => 'Stabil', 'kurang_nyaman' => 'Kurang nyaman', 'memburuk' => 'Memburuk'],
        'article_category' => [
            'aturan_pakai' => 'Aturan pakai', 'antibiotik' => 'Antibiotik', 'interaksi_obat' => 'Interaksi obat',
            'penyimpanan' => 'Penyimpanan', 'efek_samping' => 'Efek samping',
        ],
        'interaction_severity' => ['rendah' => 'Risiko rendah', 'sedang' => 'Risiko sedang', 'tinggi' => 'Risiko tinggi'],
        'refill_severity' => ['warning' => 'Stok menipis', 'critical' => 'Stok kritis'],
        'stock_change' => [
            'initial' => 'Stok awal', 'consumption' => 'Diminum', 'undo_consumption' => 'Batal minum',
            'refill' => 'Tebus ulang', 'adjustment' => 'Koreksi stok',
        ],
        'skip_reason' => [
            'Obat habis' => 'Obat habis', 'Lupa membawa obat' => 'Lupa membawa obat',
            'Atas saran dokter' => 'Atas saran dokter', 'Efek samping' => 'Efek samping', 'Lainnya' => 'Lainnya',
        ],
    ];

    return $labels[$group] ?? [];
}

function label(string $group, ?string $value): string
{
    return labels($group)[$value] ?? (string) $value;
}
