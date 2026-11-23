<?php

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        abort(419, 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman, lalu kirim ulang formulir.');
    }
}

function require_post(): void
{
    if (!is_post()) {
        abort(405, 'Aksi ini hanya bisa dikirim lewat formulir.');
    }

    verify_csrf();
}
