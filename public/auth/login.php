<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

require_guest();

$errors = [];
$email = '';

if (is_post()) {
    require_post();
    $email = input('email');
    $password = (string) ($_POST['password'] ?? '');

    $errors = validate(
        ['email' => $email, 'password' => $password],
        ['email' => 'required|email', 'password' => 'required'],
        ['email' => 'Email', 'password' => 'Kata sandi']
    );

    if ($errors === []) {
        $user = AuthService::attempt($email, $password);
        if ($user === null) {
            $errors['email'] = 'Email atau kata sandi tidak cocok.';
        } else {
            login_user($user);
            flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '.');
            redirect(home_path_for($user['role']));
        }
    }
}

view_start('Masuk akun', 'login', ['guest' => true]);
?>
<div class="auth-wrap">
    <section class="panel panel-ledger">
        <h1>Masuk ke ObatTepat</h1>
        <p class="muted">Gunakan akun pasien, pendamping, atau apoteker Anda.</p>
        <form method="post" action="<?= e(url('auth/login.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="nama@email.com" autocomplete="email" required<?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="password">Kata sandi</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="current-password" required<?= field_attrs($errors, 'password') ?>>
                    <button type="button" class="button button-ghost button-sm password-toggle" data-password-toggle="password" aria-controls="password" aria-pressed="false">Lihat</button>
                </div>
                <?= field_error($errors, 'password') ?>
            </div>
            <div class="action-row">
                <button type="submit" class="button button-primary">Masuk</button>
                <a class="button button-ghost" href="<?= e(url('auth/register.php')) ?>">Belum punya akun? Daftar</a>
            </div>
        </form>
        <div class="demo-box">
            <strong>Akun demo (kata sandi: <code>obattepat123</code>)</strong>
            <ul>
                <li>Pasien: <code>pasien@obattepat.test</code></li>
                <li>Pendamping: <code>pendamping@obattepat.test</code></li>
                <li>Admin/Apoteker: <code>apoteker@obattepat.test</code></li>
            </ul>
        </div>
    </section>
</div>
<?php view_end(); ?>
