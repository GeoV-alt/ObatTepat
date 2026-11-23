<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];
$prescriptions = MedicationService::prescriptionOptions($patientId);
$errors = [];

$form = [
    'prescription_id' => query_param('prescription_id', ''),
    'name' => '',
    'dosage_form' => 'tablet',
    'dose_amount' => '1',
    'dose_unit' => 'tablet',
    'meal_rule' => 'sesudah_makan',
    'is_antibiotic' => '0',
    'frequency_per_day' => '3',
    'interval_hours' => '8',
    'duration_days' => '5',
    'start_date' => today(),
    'first_dose_time' => '07:00',
    'initial_stock' => '15',
    'refill_threshold_days' => '2',
    'instructions' => '',
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
        'frequency_per_day' => input('frequency_per_day', '3'),
        'interval_hours' => input('interval_hours', '8'),
        'duration_days' => input('duration_days', '5'),
        'start_date' => input('start_date', today()),
        'first_dose_time' => input('first_dose_time', '07:00'),
        'initial_stock' => input('initial_stock', '0'),
        'refill_threshold_days' => input('refill_threshold_days', '2'),
        'instructions' => input('instructions'),
    ];

    $errors = MedicationService::validate($form, $patientId, true);

    if ($errors === []) {
        $result = MedicationService::create($patientId, $form);
        $needed = (float) $form['dose_amount'] * (int) $form['frequency_per_day'] * (int) $form['duration_days'];
        $stock = (float) $form['initial_stock'];

        flash('success', 'Obat disimpan dan ' . $result['generated'] . ' jadwal minum obat telah dibuat otomatis.');
        if ($stock < $needed) {
            flash('warning', 'Stok awal (' . format_qty($stock) . ' ' . $form['dose_unit'] . ') lebih kecil dari total kebutuhan terapi (' . format_qty($needed) . ' ' . $form['dose_unit'] . '). Peringatan tebus ulang akan aktif saat stok menipis.');
        }

        redirect('medications/detail.php', ['id' => $result['id']]);
    }
}

$selectedPrescriptionTitle = $form['prescription_id'] !== '' && isset($prescriptions[(int) $form['prescription_id']])
    ? $prescriptions[(int) $form['prescription_id']]
    : null;

$commonMedications = [
    'Amoxicillin 500 mg',
    'Paracetamol 500 mg',
    'Omeprazole 20 mg',
    'Cetirizine 10 mg',
    'Ibuprofen 400 mg',
    'Antasida Doen',
    'Ciprofloxacin 500 mg',
    'Metformin 500 mg',
    'Amlodipine 5 mg',
    'Vitamin C 500 mg',
];

view_start('Tambah obat baru', 'medications');
?>
<div class="page-header">
    <div>
        <h1>Tambah obat baru</h1>
        <p class="page-subtitle">Sistem akan otomatis membentuk daftar jadwal minum obat berdasarkan frekuensi, jarak jam, dan lama pengobatan.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('medications/index.php')) ?>">Kembali ke daftar obat</a>
    </div>
</div>

<?php if ($selectedPrescriptionTitle !== null): ?>
<div class="notice-box">
    <p class="notice-title">Menambahkan obat ke resep: <?= e($selectedPrescriptionTitle) ?></p>
    <p>Kelompok resep sudah dipilih otomatis. Masukkan nama obat dan aturan pakainya sesuai etiket apotek.</p>
</div>
<?php endif; ?>

<section class="panel panel-ledger">
    <form method="post" action="<?= e(url('medications/create.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field field-full">
                <label for="name">Nama obat</label>
                <input id="name" name="name" type="text" list="common-medications" value="<?= e($form['name']) ?>" placeholder="Pilih saran atau ketik nama obat, contoh: Amoxicillin 500 mg" required<?= field_attrs($errors, 'name') ?>>
                <datalist id="common-medications">
                    <?php foreach ($commonMedications as $medSuggestion): ?>
                    <option value="<?= e($medSuggestion) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
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
                <p class="field-hint">Boleh pecahan, misalnya 0.5 tablet atau 5 ml.</p>
                <?= field_error($errors, 'dose_amount') ?>
            </div>
            <div class="field">
                <label for="dose_unit">Satuan dosis</label>
                <input id="dose_unit" name="dose_unit" type="text" value="<?= e($form['dose_unit']) ?>" placeholder="tablet, kapsul, ml, sendok takar" required<?= field_attrs($errors, 'dose_unit') ?>>
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
                <label for="initial_stock">Total stok awal yang dimiliki</label>
                <input id="initial_stock" name="initial_stock" type="number" step="0.25" min="0" value="<?= e($form['initial_stock']) ?>" required<?= field_attrs($errors, 'initial_stock') ?>>
                <p class="field-hint">Gunakan satuan yang sama dengan satuan dosis.</p>
                <?= field_error($errors, 'initial_stock') ?>
            </div>
            <div class="field">
                <label for="frequency_per_day">Frekuensi per hari</label>
                <input id="frequency_per_day" name="frequency_per_day" type="number" min="1" max="12" value="<?= e($form['frequency_per_day']) ?>" data-freq-input required<?= field_attrs($errors, 'frequency_per_day') ?>>
                <?= field_error($errors, 'frequency_per_day') ?>
            </div>
            <div class="field">
                <label for="interval_hours">Jarak antar dosis (jam)</label>
                <input id="interval_hours" name="interval_hours" type="number" min="1" max="24" value="<?= e($form['interval_hours']) ?>" data-interval-input required<?= field_attrs($errors, 'interval_hours') ?>>
                <p class="field-hint">Misalnya 12 jam untuk 2 x sehari atau 8 jam untuk 3 x sehari.</p>
                <?= field_error($errors, 'interval_hours') ?>
            </div>
            <div class="field">
                <label for="duration_days">Lama pengobatan (hari)</label>
                <input id="duration_days" name="duration_days" type="number" min="1" max="365" value="<?= e($form['duration_days']) ?>" required<?= field_attrs($errors, 'duration_days') ?>>
                <?= field_error($errors, 'duration_days') ?>
            </div>
            <div class="field">
                <label for="refill_threshold_days">Peringatan stok menipis (sisa hari)</label>
                <input id="refill_threshold_days" name="refill_threshold_days" type="number" min="1" max="7" value="<?= e($form['refill_threshold_days']) ?>" required<?= field_attrs($errors, 'refill_threshold_days') ?>>
                <p class="field-hint">Standar 2 hari sebelum stok habis.</p>
                <?= field_error($errors, 'refill_threshold_days') ?>
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
                    <span><strong>Obat ini adalah antibiotik</strong><br><span class="muted">Menandai obat wajib dihabiskan sesuai durasi resep dan menampilkan panduan pencegahan resistensi antibiotik.</span></span>
                </label>
            </div>
            <div class="field field-full">
                <label for="instructions">Catatan aturan pakai tambahan (opsional)</label>
                <textarea id="instructions" name="instructions" placeholder="Contoh: Jangan diminum bersamaan dengan susu atau antasida"><?= e($form['instructions']) ?></textarea>
                <?= field_error($errors, 'instructions') ?>
            </div>
        </div>
        <div class="action-row" style="margin-top:1.1rem;">
            <button type="submit" class="button button-primary">Simpan obat dan buat jadwal</button>
            <a class="button button-ghost" href="<?= e(url('medications/index.php')) ?>">Batal</a>
        </div>
    </form>
</section>
<?php view_end(); ?>
