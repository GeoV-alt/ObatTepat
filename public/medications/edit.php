<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];
$medId = (int) query_param('id', '0');
$medication = MedicationService::findOrFail($medId, $patientId);
$prescriptions = MedicationService::prescriptionOptions($patientId);
$errors = [];

$form = [
    'prescription_id' => (string) ($medication['prescription_id'] ?? ''),
    'name' => $medication['name'],
    'dosage_form' => $medication['dosage_form'],
    'dose_amount' => (string) $medication['dose_amount'],
    'dose_unit' => $medication['dose_unit'],
    'meal_rule' => $medication['meal_rule'],
    'is_antibiotic' => (string) $medication['is_antibiotic'],
    'frequency_per_day' => (string) $medication['frequency_per_day'],
    'interval_hours' => (string) $medication['interval_hours'],
    'duration_days' => (string) $medication['duration_days'],
    'start_date' => $medication['start_date'],
    'first_dose_time' => substr((string) $medication['first_dose_time'], 0, 5),
    'refill_threshold_days' => (string) $medication['refill_threshold_days'],
    'instructions' => (string) ($medication['instructions'] ?? ''),
];

if (is_post()) {
    require_post();
    $form = [
        'prescription_id' => input('prescription_id'),
        'name' => input('name'),
        'dosage_form' => input('dosage_form', 'tablet'),
        'dose_amount' => input('dose_amount', '1'),
        'dose_unit' => input('dose_unit', 'tablet'),
        'meal_rule' => input('meal_rule', 'sesudah_makan'),
        'is_antibiotic' => isset($_POST['is_antibiotic']) ? '1' : '0',
        'frequency_per_day' => input('frequency_per_day', '1'),
        'interval_hours' => input('interval_hours', '24'),
        'duration_days' => input('duration_days', '1'),
        'start_date' => input('start_date', today()),
        'first_dose_time' => input('first_dose_time', '07:00'),
        'refill_threshold_days' => input('refill_threshold_days', '2'),
        'instructions' => input('instructions'),
    ];

    $errors = MedicationService::validate($form, $patientId, false);

    if ($errors === []) {
        $regenerated = MedicationService::update($medId, $patientId, $form);
        flash(
            'success',
            $regenerated
                ? 'Data obat diperbarui dan jadwal mendatang yang belum diminum telah dibuat ulang.'
                : 'Data obat berhasil diperbarui.'
        );
        redirect('medications/detail.php', ['id' => $medId]);
    }
}

view_start('Ubah obat: ' . $medication['name'], 'medications');
?>
<div class="page-header">
    <div>
        <h1>Ubah data obat</h1>
        <p class="page-subtitle">Jika frekuensi, jarak jam, atau lama pengobatan diubah, hanya jadwal mendatang yang masih pending yang dibuat ulang. Riwayat konsumsi sebelumnya tetap terjaga.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('medications/detail.php', ['id' => $medId])) ?>">Kembali ke detail</a>
    </div>
</div>

<section class="panel panel-ledger">
    <form method="post" action="<?= e(url('medications/edit.php', ['id' => $medId])) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field field-full">
                <label for="name">Nama obat</label>
                <input id="name" name="name" type="text" value="<?= e($form['name']) ?>" required<?= field_attrs($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="prescription_id">Kelompok resep (opsional)</label>
                <select id="prescription_id" name="prescription_id"<?= field_attrs($errors, 'prescription_id') ?>>
                    <?= select_options($prescriptions, $form['prescription_id'], 'Tanpa resep / obat rutin') ?>
                </select>
                <?= field_error($errors, 'prescription_id') ?>
            </div>
            <div class="field">
                <label for="dosage_form">Bentuk sediaan</label>
                <select id="dosage_form" name="dosage_form" required<?= field_attrs($errors, 'dosage_form') ?>>
                    <?= select_options(labels('dosage_form'), $form['dosage_form']) ?>
                </select>
                <?= field_error($errors, 'dosage_form') ?>
            </div>
            <div class="field">
                <label for="dose_amount">Dosis sekali minum</label>
                <input id="dose_amount" name="dose_amount" type="number" step="0.25" min="0.25" value="<?= e($form['dose_amount']) ?>" required<?= field_attrs($errors, 'dose_amount') ?>>
                <?= field_error($errors, 'dose_amount') ?>
            </div>
            <div class="field">
                <label for="dose_unit">Satuan dosis</label>
                <input id="dose_unit" name="dose_unit" type="text" value="<?= e($form['dose_unit']) ?>" required<?= field_attrs($errors, 'dose_unit') ?>>
                <?= field_error($errors, 'dose_unit') ?>
            </div>
            <div class="field">
                <label for="meal_rule">Aturan makan</label>
                <select id="meal_rule" name="meal_rule" required<?= field_attrs($errors, 'meal_rule') ?>>
                    <?= select_options(labels('meal_rule'), $form['meal_rule']) ?>
                </select>
                <?= field_error($errors, 'meal_rule') ?>
            </div>
            <div class="field">
                <label for="refill_threshold_days">Peringatan stok menipis (sisa hari)</label>
                <input id="refill_threshold_days" name="refill_threshold_days" type="number" min="1" max="7" value="<?= e($form['refill_threshold_days']) ?>" required<?= field_attrs($errors, 'refill_threshold_days') ?>>
                <?= field_error($errors, 'refill_threshold_days') ?>
            </div>
            <div class="field">
                <label for="frequency_per_day">Frekuensi per hari</label>
                <input id="frequency_per_day" name="frequency_per_day" type="number" min="1" max="12" value="<?= e($form['frequency_per_day']) ?>" data-freq-input required<?= field_attrs($errors, 'frequency_per_day') ?>>
                <?= field_error($errors, 'frequency_per_day') ?>
            </div>
            <div class="field">
                <label for="interval_hours">Jarak antar dosis (jam)</label>
                <input id="interval_hours" name="interval_hours" type="number" min="1" max="24" value="<?= e($form['interval_hours']) ?>" data-interval-input required<?= field_attrs($errors, 'interval_hours') ?>>
                <?= field_error($errors, 'interval_hours') ?>
            </div>
            <div class="field">
                <label for="duration_days">Lama pengobatan (hari)</label>
                <input id="duration_days" name="duration_days" type="number" min="1" max="365" value="<?= e($form['duration_days']) ?>" required<?= field_attrs($errors, 'duration_days') ?>>
                <?= field_error($errors, 'duration_days') ?>
            </div>
            <div class="field">
                <label for="start_date">Tanggal mulai terapi</label>
                <input id="start_date" name="start_date" type="date" value="<?= e($form['start_date']) ?>" required<?= field_attrs($errors, 'start_date') ?>>
                <?= field_error($errors, 'start_date') ?>
            </div>
            <div class="field">
                <label for="first_dose_time">Jam dosis pertama</label>
                <input id="first_dose_time" name="first_dose_time" type="time" value="<?= e($form['first_dose_time']) ?>" required<?= field_attrs($errors, 'first_dose_time') ?>>
                <?= field_error($errors, 'first_dose_time') ?>
            </div>
            <div class="field field-full">
                <label class="checkbox-row" for="is_antibiotic">
                    <input id="is_antibiotic" name="is_antibiotic" type="checkbox" value="1"<?= checked($form['is_antibiotic'] === '1') ?>>
                    <span><strong>Obat ini adalah antibiotik</strong></span>
                </label>
            </div>
            <div class="field field-full">
                <label for="instructions">Catatan aturan pakai tambahan</label>
                <textarea id="instructions" name="instructions"><?= e($form['instructions']) ?></textarea>
                <?= field_error($errors, 'instructions') ?>
            </div>
        </div>
        <div class="action-row" style="margin-top:1.1rem;">
            <button type="submit" class="button button-primary">Simpan perubahan</button>
            <a class="button button-ghost" href="<?= e(url('medications/detail.php', ['id' => $medId])) ?>">Batal</a>
        </div>
    </form>
</section>
<?php view_end(); ?>
