<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    ini_set('implicit_flush', '0');
    ob_implicit_flush(false);
    ob_start();
}

define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');

require_once ROOT_PATH . '/core/helpers.php';
require_once ROOT_PATH . '/core/database.php';
require_once ROOT_PATH . '/core/flash.php';
require_once ROOT_PATH . '/core/csrf.php';
require_once ROOT_PATH . '/core/validator.php';
require_once ROOT_PATH . '/core/auth.php';
require_once ROOT_PATH . '/core/view.php';

date_default_timezone_set(config('timezone'));

spl_autoload_register(function (string $class): void {
    $file = ROOT_PATH . '/services/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);

    if ($error instanceof PDOException && str_contains($error->getMessage(), 'SQLSTATE[HY000] [')) {
        abort(503, 'Database tidak bisa dihubungi. Pastikan MySQL di XAMPP sudah berjalan dan database obattepat_db sudah diimpor dari database/schema.sql.');
    }

    if ($error instanceof DomainException) {
        abort(400, $error->getMessage());
    }

    abort(500, 'Terjadi kesalahan di server. Coba muat ulang halaman, lalu periksa log PHP jika masalah berlanjut.');
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(config('session_name'));
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
