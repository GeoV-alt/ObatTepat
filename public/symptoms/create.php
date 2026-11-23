<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];
$errors = [];

$form = [
    'log_date' => query_param('date', today()),
    'logged_time' => date('H:i'),
    'schedule_id' => '',
    'medication_id' => '',
    'daily_condition' => 'stabil',
    'symptom_name' => '',
    'severity' => 'ringan',
    'notes' => '',
];
if (!is_valid_date($form['log_date'])) {
    $form['log_date'] = today();
}

if (is_post()) {
    require_post();
    $form = [
        'log_date' => input('log_date', today()),
        'logged_time' => input('logged_time'),
        'schedule_id' => input('schedule_id'),
        'medication_id' => input('medication_id'),
        'daily_condition' => input('daily_condition', 'stabil'),
        'symptom_name' => input('symptom_name'),
        'severity' => input('severity', 'ringan'),
        'notes' => input('notes'),
    ];

    $errors = validate($form, SymptomService::rules(), SymptomService::labels());

    if ($errors === []) {
        try {
            $created = SymptomService::create($patientId, $form);
            flash('success', 'Catatan keluhan berhasil disimpan dan terhubung ke laporan kontrol.');
            if ($created['urgent_warning']) {
                flash('warning', 'Keluhan ini termasuk keparahan berat atau gejala reaksi alergi. Segera hubungi dokter atau fasilitas kesehatan terdekat.');
            }
            redirect('symptoms/index.php');
        } catch (DomainException $error) {
            flash('error', $error->getMessage());
        }
    }
}

$dateSchedules = ScheduleService::takenOrScheduledOnDate($patientId, $form['log_date']);
$medOptions = MedicationService::optionsForPatient($patientId);

view_start('Catat efek samping', 'symptoms');
?>
<div class="page-header">
    <div>
        <h1>Catat efek samping atau kondisi harian</h1>
        <p class="page-subtitle">Pilih tanggal untuk memunculkan obat yang diminum pada hari tersebut agar keluhan langsung terhubung dengan riwayat obat.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('symptoms/index.php')) ?>">Kembali ke jurnal</a>
    </div>
</div>

<section class="panel panel-ledger">
    <form method="post" action="<?= e(url('symptoms/create.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="log_date">Tanggal kejadian</label>
                <input id="log_date" name="log_date" type="date" value="<?= e($form['log_date']) ?>" data-symptom-date required<?= field_attrs($errors, 'log_date') ?>>
                <?= field_error($errors, 'log_date') ?>
            </div>
            <div class="field">
                <label for="logged_time">Jam kejadian (opsional)</label>
                <input id="logged_time" name="logged_time" type="time" value="<?= e($form['logged_time']) ?>"<?= field_attrs($errors, 'logged_time') ?>>
                <?= field_error($errors, 'logged_time') ?>
            </div>
            <div class="field field-full">
                <label for="schedule_id">Jadwal konsumsi terkait pada tanggal ini (opsional)</label>
                <select id="schedule_id" name="schedule_id" data-symptom-schedules>
                    <option value="">
                        <?= $dateSchedules !== [] ? 'Pilih jadwal konsumsi pada tanggal ini (opsional)' : 'Tidak ada jadwal obat pada tanggal ini' ?>
                    </option>
                    <?php foreach ($dateSchedules as $slot): ?>
                    <option value="<?= (int) $slot['id'] ?>" data-med-id="<?= (int) $slot['medication_id'] ?>"<?= selected($form['schedule_id'], $slot['id']) ?>>
                        <?= e(format_time($slot['scheduled_at']) . ' - ' . $slot['medication_name'] . ' (' . strtolower(label('schedule_status', $slot['status'])) . ')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <label for="medication_id">Atau pilih nama obat secara langsung (opsional)</label>
                <select id="medication_id" name="medication_id" data-symptom-medication>
                    <?= select_options($medOptions, $form['medication_id'], 'Tidak ditautkan ke obat spesifik') ?>
                </select>
            </div>
            <div class="field field-full">
                <label for="symptom_name">Keluhan atau efek samping</label>
                <div class="symptom-chips" role="group" aria-label="Pilihan cepat gejala umum">
                    <?php foreach (SymptomService::COMMON_SYMPTOMS as $preset): ?>
                    <button type="button" class="symptom-pick<?= $form['symptom_name'] === $preset ? ' is-picked' : '' ?>" data-symptom-pick="<?= e($preset) ?>"><?= e($preset) ?></button>
                    <?php endforeach; ?>
                </div>
                <input id="symptom_name" name="symptom_name" type="text" value="<?= e($form['symptom_name']) ?>" placeholder="Pilih di atas atau tulis keluhan Anda" required<?= field_attrs($errors, 'symptom_name') ?>>
                <?= field_error($errors, 'symptom_name') ?>
            </div>
            <div class="field">
                <label for="severity">Tingkat keparahan</label>
                <select id="severity" name="severity" required<?= field_attrs($errors, 'severity') ?>>
                    <?= select_options(labels('symptom_severity'), $form['severity']) ?>
                </select>
                <?= field_error($errors, 'severity') ?>
            </div>
            <div class="field">
                <label for="daily_condition">Kondisi umum harian</label>
                <select id="daily_condition" name="daily_condition" required<?= field_attrs($errors, 'daily_condition') ?>>
                    <?= select_options(labels('daily_condition'), $form['daily_condition']) ?>
                </select>
                <?= field_error($errors, 'daily_condition') ?>
            </div>
            <div class="field field-full">
                <label for="notes">Catatan detail (opsional)</label>
                <textarea id="notes" name="notes" placeholder="Contoh: Muncul sekitar 1 jam sesudah minum obat, membaik setelah makan"><?= e($form['notes']) ?></textarea>
                <?= field_error($errors, 'notes') ?>
            </div>
        </div>
        <div class="action-row" style="margin-top:1.1rem;">
            <button type="submit" class="button button-primary">Simpan catatan keluhan</button>
            <a class="button button-ghost" href="<?= e(url('symptoms/index.php')) ?>">Batal</a>
        </div>
    </form>
</section>
<?php view_end(); ?>
