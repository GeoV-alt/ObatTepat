<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$admin = require_role('admin');
$errors = [];
$form = ['full_name' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    require_post();
    $action = input('action', 'create');

    if ($action === 'toggle') {
        $targetId = (int) input('user_id', '0');
        $active = input('is_active', '0') === '1';
        try {
            AuthService::setActive($targetId, $active, (int) $admin['id']);
            flash('success', $active ? 'Akun apoteker diaktifkan kembali.' : 'Akun apoteker dinonaktifkan.');
        } catch (DomainException $error) {
            flash('error', $error->getMessage());
        }
        redirect('auth/staff.php');
    }

    $form = [
        'full_name' => input('full_name'),
        'email' => input('email'),
        'phone' => input('phone'),
        'password' => (string) ($_POST['password'] ?? ''),
        'role' => 'admin',
    ];

    $errors = validate($form, [
        'full_name' => 'required|min:2|max:120',
        'email' => 'required|email|max:150',
        'phone' => 'max:25',
        'password' => 'required|min:8|max:100',
    ], [
        'full_name' => 'Nama lengkap',
        'email' => 'Email',
        'phone' => 'Nomor telepon',
        'password' => 'Kata sandi awal',
    ]);

    if (!isset($errors['email']) && AuthService::emailExists($form['email'])) {
        $errors['email'] = 'Email sudah digunakan oleh akun lain.';
    }

    if ($errors === []) {
        AuthService::register($form);
        flash('success', 'Akun Admin/Apoteker baru berhasil ditambahkan.');
        redirect('auth/staff.php');
    }
}

$staffList = AuthService::staffAccounts();
view_start('Kelola akun apoteker', 'staff');
?>
<div class="page-header">
    <div>
        <h1>Akun Admin dan Apoteker</h1>
        <p class="page-subtitle">Akun admin tidak dibuka di halaman daftar publik dan hanya dapat ditambahkan oleh admin aktif.</p>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Daftar akun terdaftar</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $staff): ?>
                    <tr>
                        <td><strong><?= e($staff['full_name']) ?></strong></td>
                        <td><?= e($staff['email']) ?></td>
                        <td><?= status_chip($staff['is_active'] ? 'active' : 'revoked', $staff['is_active'] ? 'Aktif' : 'Nonaktif') ?></td>
                        <td>
                            <?php if ((int) $staff['id'] === (int) $admin['id']): ?>
                            <span class="muted">Akun Anda</span>
                            <?php else: ?>
                            <form method="post" action="<?= e(url('auth/staff.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="user_id" value="<?= (int) $staff['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $staff['is_active'] ? '0' : '1' ?>">
                                <button type="submit" class="button button-ghost button-sm"><?= $staff['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Tambah akun apoteker</h2>
        <form method="post" action="<?= e(url('auth/staff.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="field">
                <label for="full_name">Nama lengkap</label>
                <input id="full_name" name="full_name" type="text" value="<?= e($form['full_name']) ?>" placeholder="Nama apoteker" required<?= field_attrs($errors, 'full_name') ?>>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" placeholder="apoteker@email.com" required<?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="phone">Telepon (opsional)</label>
                <input id="phone" name="phone" type="tel" value="<?= e($form['phone']) ?>" placeholder="0812xxxxxxxx">
            </div>
            <div class="field">
                <label for="password">Kata sandi awal</label>
                <input id="password" name="password" type="password" required<?= field_attrs($errors, 'password') ?>>
                <?= field_error($errors, 'password') ?>
            </div>
            <button type="submit" class="button button-primary">Tambah akun</button>
        </form>
    </section>
</div>
<?php view_end(); ?>
