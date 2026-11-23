<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_auth();
$isPatient = $user['role'] === 'patient';
$profile = $isPatient ? AuthService::getProfile((int) $user['id']) : $user;
$profileErrors = [];
$passwordErrors = [];

if (is_post()) {
    require_post();
    $action = input('action', 'profile');

    if ($action === 'password') {
        $pwForm = [
            'current_password' => (string) ($_POST['current_password'] ?? ''),
            'new_password' => (string) ($_POST['new_password'] ?? ''),
            'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
        ];
        $passwordErrors = validate($pwForm, [
            'current_password' => 'required',
            'new_password' => 'required|min:8|max:100',
            'confirm_password' => 'required|same:new_password',
        ], [
            'current_password' => 'Kata sandi saat ini',
            'new_password' => 'Kata sandi baru',
            'confirm_password' => 'Konfirmasi kata sandi baru',
        ]);

        if ($passwordErrors === []) {
            if (!AuthService::changePassword((int) $user['id'], $pwForm['current_password'], $pwForm['new_password'])) {
                $passwordErrors['current_password'] = 'Kata sandi saat ini tidak cocok.';
            } else {
                flash('success', 'Kata sandi berhasil diperbarui.');
                redirect('auth/profile.php');
            }
        }
    } else {
        $form = [
            'full_name' => input('full_name'),
            'phone' => input('phone'),
            'date_of_birth' => input('date_of_birth'),
            'gender' => input('gender'),
            'blood_type' => input('blood_type', 'unknown'),
            'drug_allergies' => input('drug_allergies'),
            'chronic_conditions' => input('chronic_conditions'),
            'special_notes' => input('special_notes'),
            'emergency_contact_name' => input('emergency_contact_name'),
            'emergency_contact_phone' => input('emergency_contact_phone'),
            'default_tolerance_minutes' => input('default_tolerance_minutes', '60'),
        ];

        $rules = [
            'full_name' => 'required|min:2|max:120',
            'phone' => 'max:25',
        ];
        if ($isPatient) {
            $rules += [
                'date_of_birth' => 'date',
                'gender' => 'in:L,P',
                'blood_type' => 'required|in:A,B,AB,O,unknown',
                'drug_allergies' => 'max:1000',
                'chronic_conditions' => 'max:1000',
                'special_notes' => 'max:1000',
                'emergency_contact_name' => 'max:120',
                'emergency_contact_phone' => 'max:25',
                'default_tolerance_minutes' => 'required|integer|min_value:15|max_value:360',
            ];
        }

        $profileErrors = validate($form, $rules, [
            'full_name' => 'Nama lengkap',
            'phone' => 'Nomor telepon',
            'date_of_birth' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'blood_type' => 'Golongan darah',
            'default_tolerance_minutes' => 'Batas toleransi keterlambatan',
        ]);

        if ($profileErrors === []) {
            AuthService::updateAccount((int) $user['id'], $form['full_name'], $form['phone']);
            if ($isPatient) {
                AuthService::updateProfile((int) $user['id'], $form);
            }
            flash('success', 'Profil berhasil disimpan.');
            redirect('auth/profile.php');
        }

        $profile = array_merge($profile, $form);
    }
}

view_start($isPatient ? 'Profil kesehatan dasar' : 'Pengaturan akun', 'profile');
?>
<div class="page-header">
    <div>
        <h1><?= $isPatient ? 'Profil kesehatan dasar' : 'Pengaturan akun' ?></h1>
        <p class="page-subtitle">
            <?= $isPatient
                ? 'Data alergi, golongan darah, dan catatan khusus akan tampil di Laporan Kontrol Medis serta dapat dilihat pendamping saat darurat.'
                : 'Perbarui nama, nomor telepon, atau kata sandi akun Anda.' ?>
        </p>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Data akun<?= $isPatient ? ' dan informasi medis dasar' : '' ?></h2>
        <form method="post" action="<?= e(url('auth/profile.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div class="form-grid">
                <div class="field">
                    <label for="full_name">Nama lengkap</label>
                    <input id="full_name" name="full_name" type="text" value="<?= e($profile['full_name'] ?? '') ?>" required<?= field_attrs($profileErrors, 'full_name') ?>>
                    <?= field_error($profileErrors, 'full_name') ?>
                </div>
                <div class="field">
                    <label for="phone">Nomor WhatsApp atau telepon</label>
                    <input id="phone" name="phone" type="tel" value="<?= e($profile['phone'] ?? '') ?>" placeholder="0812xxxxxxxx"<?= field_attrs($profileErrors, 'phone') ?>>
                    <?= field_error($profileErrors, 'phone') ?>
                </div>
                <div class="field field-full">
                    <label for="email_view">Email akun</label>
                    <input id="email_view" type="email" value="<?= e($user['email']) ?>" disabled>
                </div>

                <?php if ($isPatient): ?>
                <div class="field">
                    <label for="date_of_birth">Tanggal lahir</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="<?= e($profile['date_of_birth'] ?? '') ?>"<?= field_attrs($profileErrors, 'date_of_birth') ?>>
                    <?= field_error($profileErrors, 'date_of_birth') ?>
                </div>
                <div class="field">
                    <label for="gender">Jenis kelamin</label>
                    <select id="gender" name="gender"<?= field_attrs($profileErrors, 'gender') ?>>
                        <?= select_options(labels('gender'), $profile['gender'] ?? '', 'Pilih jenis kelamin') ?>
                    </select>
                    <?= field_error($profileErrors, 'gender') ?>
                </div>
                <div class="field">
                    <label for="blood_type">Golongan darah</label>
                    <select id="blood_type" name="blood_type"<?= field_attrs($profileErrors, 'blood_type') ?>>
                        <?= select_options(labels('blood_type'), $profile['blood_type'] ?? 'unknown') ?>
                    </select>
                    <?= field_error($profileErrors, 'blood_type') ?>
                </div>
                <div class="field">
                    <label for="default_tolerance_minutes">Batas toleransi keterlambatan (menit)</label>
                    <input id="default_tolerance_minutes" name="default_tolerance_minutes" type="number" min="15" max="360" value="<?= e((string) ($profile['default_tolerance_minutes'] ?? 60)) ?>" required<?= field_attrs($profileErrors, 'default_tolerance_minutes') ?>>
                    <p class="field-hint">Jika melewati batas ini tanpa check-in, status menjadi Terlewat dan pendamping bisa mengirim pengingat.</p>
                    <?= field_error($profileErrors, 'default_tolerance_minutes') ?>
                </div>
                <div class="field field-full">
                    <label for="drug_allergies">Riwayat alergi obat</label>
                    <textarea id="drug_allergies" name="drug_allergies" placeholder="Contoh: Penisilin (ruam), Sulfa, atau tulis Tidak ada jika belum pernah alergi"><?= e($profile['drug_allergies'] ?? '') ?></textarea>
                    <?= field_error($profileErrors, 'drug_allergies') ?>
                </div>
                <div class="field field-full">
                    <label for="chronic_conditions">Kondisi kesehatan penyerta (opsional)</label>
                    <textarea id="chronic_conditions" name="chronic_conditions" placeholder="Contoh: Maag, asma, hipertensi"><?= e($profile['chronic_conditions'] ?? '') ?></textarea>
                    <?= field_error($profileErrors, 'chronic_conditions') ?>
                </div>
                <div class="field field-full">
                    <label for="special_notes">Catatan khusus</label>
                    <textarea id="special_notes" name="special_notes" placeholder="Catatan penting untuk dokter atau pendamping"><?= e($profile['special_notes'] ?? '') ?></textarea>
                    <?= field_error($profileErrors, 'special_notes') ?>
                </div>
                <div class="field">
                    <label for="emergency_contact_name">Nama kontak darurat</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" value="<?= e($profile['emergency_contact_name'] ?? '') ?>" placeholder="Nama orang tua atau wali">
                </div>
                <div class="field">
                    <label for="emergency_contact_phone">Telepon kontak darurat</label>
                    <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="<?= e($profile['emergency_contact_phone'] ?? '') ?>" placeholder="0812xxxxxxxx">
                </div>
                <?php endif; ?>
            </div>
            <div class="action-row" style="margin-top:1rem;">
                <button type="submit" class="button button-primary">Simpan profil</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Ubah kata sandi</h2>
        <form method="post" action="<?= e(url('auth/profile.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="field">
                <label for="current_password">Kata sandi saat ini</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required<?= field_attrs($passwordErrors, 'current_password') ?>>
                <?= field_error($passwordErrors, 'current_password') ?>
            </div>
            <div class="field">
                <label for="new_password">Kata sandi baru</label>
                <input id="new_password" name="new_password" type="password" autocomplete="new-password" required<?= field_attrs($passwordErrors, 'new_password') ?>>
                <p class="field-hint">Minimal 8 karakter.</p>
                <?= field_error($passwordErrors, 'new_password') ?>
            </div>
            <div class="field">
                <label for="confirm_password">Ulangi kata sandi baru</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required<?= field_attrs($passwordErrors, 'confirm_password') ?>>
                <?= field_error($passwordErrors, 'confirm_password') ?>
            </div>
            <button type="submit" class="button button-secondary">Perbarui kata sandi</button>
        </form>
    </section>
</div>
<?php view_end(); ?>
