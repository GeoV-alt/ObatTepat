<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];
$errors = [];
$form = [
    'title' => '',
    'doctor_name' => '',
    'facility_name' => '',
    'prescribed_date' => today(),
    'notes' => '',
];

if (is_post()) {
    require_post();
    $action = input('action', 'create');

    if ($action === 'status') {
        try {
            MedicationService::setPrescriptionStatus((int) input('prescription_id', '0'), $patientId, input('status', 'completed'));
            flash('success', 'Status resep berhasil diperbarui.');
        } catch (DomainException $error) {
            flash('error', $error->getMessage());
        }
        redirect('medications/prescriptions.php');
    }

    $form = [
        'title' => input('title'),
        'doctor_name' => input('doctor_name'),
        'facility_name' => input('facility_name'),
        'prescribed_date' => input('prescribed_date', today()),
        'notes' => input('notes'),
    ];

    $errors = validate($form, [
        'title' => 'required|max:150',
        'doctor_name' => 'max:120',
        'facility_name' => 'max:150',
        'prescribed_date' => 'required|date',
        'notes' => 'max:1000',
    ], [
        'title' => 'Nama resep atau keluhan',
        'doctor_name' => 'Nama dokter',
        'facility_name' => 'Klinik atau rumah sakit',
        'prescribed_date' => 'Tanggal resep',
        'notes' => 'Catatan resep',
    ]);

    if ($errors === []) {
        MedicationService::createPrescription($patientId, $form);
        flash('success', 'Resep baru berhasil ditambahkan.');
        redirect('medications/prescriptions.php');
    }
}

$prescriptions = MedicationService::listPrescriptions($patientId);
view_start('Resep dokter', 'prescriptions');
?>
<div class="page-header">
    <div>
        <h1>Manajemen resep</h1>
        <p class="page-subtitle">Kelompokkan obat berdasarkan kunjungan dokter atau keluhan agar mudah ditinjau ulang.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('medications/index.php')) ?>">Kembali ke obat saya</a>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Daftar resep</h2>
        <?php if ($prescriptions === []): ?>
            <?= empty_state('Belum ada resep tersimpan', 'Buat kelompok resep di samping jika Anda ingin mengelompokkan beberapa obat dari satu kunjungan dokter.') ?>
        <?php else: ?>
        <div class="stack-list">
            <?php foreach ($prescriptions as $rx): ?>
            <?php $rxMeds = MedicationService::medicationsForPrescription((int) $rx['id']); ?>
            <article class="stack-item">
                <div class="panel-head" style="margin-bottom:0.4rem;">
                    <div class="schedule-head">
                        <h3><?= e($rx['title']) ?></h3>
                        <?= status_chip($rx['status'], label('prescription_status', $rx['status'])) ?>
                    </div>
                    <span class="muted"><?= e(format_date_id($rx['prescribed_date'], false)) ?></span>
                </div>
                <p class="muted" style="font-size:0.88rem;">
                    <?= e($rx['doctor_name'] ? 'Dokter: ' . $rx['doctor_name'] : 'Dokter belum dicatat') ?>
                    <?= $rx['facility_name'] ? ' / ' . e($rx['facility_name']) : '' ?>
                </p>
                <?php if (!empty($rx['notes'])): ?>
                <p style="font-size:0.9rem;"><?= e($rx['notes']) ?></p>
                <?php endif; ?>
                <?php if ($rxMeds !== []): ?>
                <p class="muted" style="font-size:0.86rem;">
                    Obat terkait:
                    <?php foreach ($rxMeds as $idx => $med): ?>
                        <?= $idx > 0 ? ', ' : '' ?><a href="<?= e(url('medications/detail.php', ['id' => $med['id']])) ?>"><?= e($med['name']) ?></a>
                    <?php endforeach; ?>
                </p>
                <?php endif; ?>
                <div class="action-row" style="margin-top:0.65rem;">
                    <?php if ($rx['status'] === 'active'): ?>
                    <a class="button button-secondary button-sm" href="<?= e(url('medications/create.php', ['prescription_id' => $rx['id']])) ?>">Tambah obat ke resep ini</a>
                    <form method="post" action="<?= e(url('medications/prescriptions.php')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="prescription_id" value="<?= (int) $rx['id'] ?>">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="button button-ghost button-sm">Tandai selesai</button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="<?= e(url('medications/prescriptions.php')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="prescription_id" value="<?= (int) $rx['id'] ?>">
                        <input type="hidden" name="status" value="active">
                        <button type="submit" class="button button-ghost button-sm">Aktifkan kembali</button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Catat resep baru</h2>
        <form method="post" action="<?= e(url('medications/prescriptions.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="field">
                <label for="title">Nama resep atau keluhan</label>
                <input id="title" name="title" type="text" value="<?= e($form['title']) ?>" placeholder="Contoh: Resep radang tenggorokan" required<?= field_attrs($errors, 'title') ?>>
                <?= field_error($errors, 'title') ?>
            </div>
            <div class="field">
                <label for="prescribed_date">Tanggal resep</label>
                <input id="prescribed_date" name="prescribed_date" type="date" value="<?= e($form['prescribed_date']) ?>" required<?= field_attrs($errors, 'prescribed_date') ?>>
                <?= field_error($errors, 'prescribed_date') ?>
            </div>
            <div class="field">
                <label for="doctor_name">Nama dokter (opsional)</label>
                <input id="doctor_name" name="doctor_name" type="text" value="<?= e($form['doctor_name']) ?>" placeholder="Nama dokter pemeriksa">
            </div>
            <div class="field">
                <label for="facility_name">Klinik atau rumah sakit (opsional)</label>
                <input id="facility_name" name="facility_name" type="text" value="<?= e($form['facility_name']) ?>" placeholder="Contoh: Klinik Kampus">
            </div>
            <div class="field">
                <label for="notes">Catatan (opsional)</label>
                <textarea id="notes" name="notes" placeholder="Anjuran jadwal kontrol ulang atau pesan dokter"><?= e($form['notes']) ?></textarea>
            </div>
            <button type="submit" class="button button-primary">Simpan resep</button>
        </form>
    </section>
</div>
<?php view_end(); ?>
