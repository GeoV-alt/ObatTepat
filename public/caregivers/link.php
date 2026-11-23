<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('caregiver');
$caregiverId = (int) $user['id'];
$errors = [];
$form = ['invite_code' => '', 'relationship_label' => 'Orang tua'];

if (is_post()) {
    require_post();
    $form = [
        'invite_code' => strtoupper(input('invite_code')),
        'relationship_label' => input('relationship_label', 'Keluarga'),
    ];

    $errors = validate($form, [
        'invite_code' => 'required|max:16',
        'relationship_label' => 'required|max:50',
    ], [
        'invite_code' => 'Kode undangan',
        'relationship_label' => 'Hubungan dengan pasien',
    ]);

    if ($errors === []) {
        try {
            $linked = CaregiverService::redeemInvitation($caregiverId, $form['invite_code'], $form['relationship_label']);
            flash('success', 'Akun berhasil ditautkan dengan pasien ' . $linked['patient_name'] . '.');
            redirect('caregivers/monitor.php', ['patient_id' => $linked['patient_id']]);
        } catch (DomainException $error) {
            $errors['invite_code'] = $error->getMessage();
        }
    }
}

$patients = CaregiverService::linkedPatientsForCaregiver($caregiverId);
view_start('Tautkan pasien', 'link');
?>
<div class="page-header">
    <div>
        <h1>Tautkan akun pasien</h1>
        <p class="page-subtitle">Masukkan kode undangan 8 karakter yang dibuat pasien pada menu Pendamping saya.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('caregivers/monitor.php')) ?>">Kembali ke pemantauan</a>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Masukkan kode undangan</h2>
        <form method="post" action="<?= e(url('caregivers/link.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="invite_code">Kode undangan pasien</label>
                <input id="invite_code" name="invite_code" type="text" value="<?= e($form['invite_code']) ?>" placeholder="Contoh: OBT-7K4M" autocomplete="off" required<?= field_attrs($errors, 'invite_code') ?>>
                <?= field_error($errors, 'invite_code') ?>
            </div>
            <div class="field">
                <label for="relationship_label">Hubungan dengan pasien</label>
                <select id="relationship_label" name="relationship_label" required<?= field_attrs($errors, 'relationship_label') ?>>
                    <?= select_options([
                        'Orang tua' => 'Orang tua',
                        'Pasangan' => 'Pasangan',
                        'Saudara' => 'Saudara',
                        'Teman kos' => 'Teman kos',
                        'Wali' => 'Wali',
                        'Keluarga' => 'Keluarga lainnya',
                    ], $form['relationship_label']) ?>
                </select>
                <?= field_error($errors, 'relationship_label') ?>
            </div>
            <button type="submit" class="button button-primary">Hubungkan pasien</button>
        </form>
    </section>

    <section class="panel">
        <h2>Pasien yang sedang dipantau</h2>
        <?php if ($patients === []): ?>
            <?= empty_state('Belum ada pasien yang tertaut', 'Minta pasien membuka menu Pendamping saya dan membagikan kode undangan 24 jam kepada Anda.') ?>
        <?php else: ?>
        <div class="stack-list">
            <?php foreach ($patients as $patient): ?>
            <article class="stack-item">
                <div class="panel-head" style="margin-bottom:0.35rem;">
                    <strong><?= e($patient['full_name']) ?></strong>
                    <span class="chip chip-active"><?= e($patient['relationship_label']) ?></span>
                </div>
                <p class="muted" style="font-size:0.86rem;">Tertaut sejak <?= e(format_datetime($patient['linked_at'])) ?></p>
                <a class="button button-secondary button-sm" href="<?= e(url('caregivers/monitor.php', ['patient_id' => $patient['patient_id']])) ?>">Pantau jadwal</a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php view_end(); ?>
