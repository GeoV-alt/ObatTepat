<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

require_guest();

$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'patient',
];

if (is_post()) {
    require_post();
    $form = [
        'full_name' => input('full_name'),
        'email' => input('email'),
        'phone' => input('phone'),
        'role' => input('role', 'patient'),
        'password' => (string) ($_POST['password'] ?? ''),
        'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
    ];

    $errors = validate($form, [
        'full_name' => 'required|min:2|max:120',
        'email' => 'required|email|max:150',
        'phone' => 'max:25',
        'role' => 'required|in:patient,caregiver',
        'password' => 'required|min:8|max:100',
        'password_confirmation' => 'required|same:password',
    ], [
        'full_name' => 'Nama lengkap',
        'email' => 'Email',
        'phone' => 'Nomor telepon',
        'role' => 'Peran akun',
        'password' => 'Kata sandi',
        'password_confirmation' => 'Konfirmasi kata sandi',
    ]);

    if (!isset($errors['email']) && AuthService::emailExists($form['email'])) {
        $errors['email'] = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
    }

    if ($errors === []) {
        $userId = AuthService::register($form);
        $user = AuthService::findUser($userId);
        login_user($user);
        flash('success', 'Akun berhasil dibuat. Lengkapi profil Anda kapan saja.');
        redirect(home_path_for($user['role']));
    }
}

view_start('Daftar akun', 'register', ['guest' => true]);
?>
<div class="auth-wrap">
    <section class="panel panel-ledger">
        <h1>Buat akun baru</h1>
        <p class="muted">Pilih peran Pasien untuk mengelola jadwal obat sendiri, atau Pendamping untuk memantau keluarga.</p>
        <form method="post" action="<?= e(url('auth/register.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="role">Daftar sebagai</label>
                <select id="role" name="role" required<?= field_attrs($errors, 'role') ?>>
                    <option value="patient"<?= selected($form['role'], 'patient') ?>>Pasien</option>
                    <option value="caregiver"<?= selected($form['role'], 'caregiver') ?>>Pendamping atau keluarga</option>
                </select>
                <?= field_error($errors, 'role') ?>
            </div>
            <div class="field">
                <label for="full_name">Nama lengkap</label>
                <input id="full_name" name="full_name" type="text" value="<?= e($form['full_name']) ?>" placeholder="Nama lengkap Anda" autocomplete="name" required<?= field_attrs($errors, 'full_name') ?>>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" placeholder="nama@email.com" autocomplete="email" required<?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="phone">Nomor WhatsApp atau telepon (opsional)</label>
                <input id="phone" name="phone" type="tel" value="<?= e($form['phone']) ?>" placeholder="0812xxxxxxxx" autocomplete="tel"<?= field_attrs($errors, 'phone') ?>>
                <p class="field-hint">Dipakai jika pendamping ingin mengirim tautan pengingat lewat WhatsApp.</p>
                <?= field_error($errors, 'phone') ?>
            </div>
            <div class="field">
                <label for="password">Kata sandi</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="new-password" required<?= field_attrs($errors, 'password') ?>>
                    <button type="button" class="button button-ghost button-sm password-toggle" data-password-toggle="password" aria-controls="password" aria-pressed="false">Lihat</button>
                </div>
                <p class="field-hint">Minimal 8 karakter.</p>
                <?= field_error($errors, 'password') ?>
            </div>
            <div class="field">
                <label for="password_confirmation">Ulangi kata sandi</label>
                <div class="password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required<?= field_attrs($errors, 'password_confirmation') ?>>
                    <button type="button" class="button button-ghost button-sm password-toggle" data-password-toggle="password_confirmation" aria-controls="password_confirmation" aria-pressed="false">Lihat</button>
                </div>
                <?= field_error($errors, 'password_confirmation') ?>
            </div>
            <div class="action-row">
                <button type="submit" class="button button-primary">Buat akun</button>
                <a class="button button-ghost" href="<?= e(url('auth/login.php')) ?>">Sudah punya akun? Masuk</a>
            </div>
        </form>
    </section>
</div>
<?php view_end(); ?>
