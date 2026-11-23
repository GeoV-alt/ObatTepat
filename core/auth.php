<?php

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId === 0) {
        return $user = null;
    }

    $user = db_one('SELECT id, full_name, email, phone, role, is_active FROM users WHERE id = ? AND is_active = 1', [$userId]);

    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function current_user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function home_path_for(string $role): string
{
    return match ($role) {
        'caregiver' => 'caregivers/monitor.php',
        'admin' => 'education/admin/articles.php',
        default => 'reports/dashboard.php',
    };
}

function require_auth(): array
{
    $user = current_user();

    if ($user === null) {
        if (is_api_request()) {
            json_response(['ok' => false, 'message' => 'Sesi login berakhir. Silakan masuk kembali.'], 401);
        }

        flash('error', 'Silakan masuk terlebih dahulu untuk membuka halaman tersebut.');
        redirect('auth/login.php');
    }

    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_auth();

    if (!in_array($user['role'], $roles, true)) {
        abort(403, 'Halaman ini tidak tersedia untuk peran ' . label('role', $user['role']) . '.');
    }

    return $user;
}

function require_guest(): void
{
    $user = current_user();

    if ($user !== null) {
        redirect(home_path_for($user['role']));
    }
}
