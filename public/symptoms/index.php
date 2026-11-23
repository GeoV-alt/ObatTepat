<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

if (is_post()) {
    require_post();
    if (input('action') === 'delete') {
        SymptomService::delete((int) input('log_id', '0'), $patientId);
        flash('success', 'Catatan efek samping dihapus.');
        redirect('symptoms/index.php');
    }
}

$filters = [
    'medication_id' => (int) query_param('medication_id', '0'),
    'severity' => query_param('severity', ''),
    'from_date' => query_param('from_date', ''),
    'to_date' => query_param('to_date', ''),
];

$logs = SymptomService::listForPatient($patientId, $filters);
$medOptions = MedicationService::optionsForPatient($patientId);

view_start('Jurnal efek samping', 'symptoms');
?>
<div class="page-header">
    <div>
        <h1>Jurnal efek samping dan kondisi harian</h1>
        <p class="page-subtitle">Catat keluhan setelah minum obat tertentu agar mudah dievaluasi dokter pada Laporan Kontrol Medis.</p>
    </div>
    <div class="page-actions">
        <a class="button button-primary" href="<?= e(url('symptoms/create.php')) ?>">Catat keluhan baru</a>
    </div>
</div>

<form method="get" action="<?= e(url('symptoms/index.php')) ?>" class="panel filter-bar">
    <div class="field">
        <label for="medication_id">Obat terkait</label>
        <select id="medication_id" name="medication_id">
            <?= select_options($medOptions, $filters['medication_id'], 'Semua obat') ?>
        </select>
    </div>
    <div class="field">
        <label for="severity">Keparahan</label>
        <select id="severity" name="severity">
            <?= select_options(labels('symptom_severity'), $filters['severity'], 'Semua tingkat') ?>
        </select>
    </div>
    <div class="field">
        <label for="from_date">Dari tanggal</label>
        <input id="from_date" name="from_date" type="date" value="<?= e($filters['from_date']) ?>">
    </div>
    <div class="field">
        <label for="to_date">Sampai tanggal</label>
        <input id="to_date" name="to_date" type="date" value="<?= e($filters['to_date']) ?>">
    </div>
    <button type="submit" class="button button-primary">Filter</button>
</form>

<?php if ($logs === []): ?>
    <?= empty_state(
        'Belum ada catatan efek samping',
        'Catat keluhan seperti mengantuk, mual, atau pusing setelah minum obat supaya riwayat Anda lengkap saat kontrol dokter.',
        url('symptoms/create.php'),
        'Catat keluhan pertama'
    ) ?>
<?php else: ?>
<div class="stack-list">
    <?php foreach ($logs as $log): ?>
    <article class="panel<?= SymptomService::requiresMedicalWarning($log['severity'], $log['symptom_name']) ? ' panel-danger' : '' ?>">
        <div class="panel-head" style="margin-bottom:0.45rem;">
            <div class="schedule-head">
                <h2><?= e($log['symptom_name']) ?></h2>
                <?= status_chip($log['severity'], label('symptom_severity', $log['severity'])) ?>
                <?= status_chip($log['daily_condition'], label('daily_condition', $log['daily_condition'])) ?>
            </div>
            <span class="muted tabular">
                <?= e(format_date_id($log['log_date'])) ?>
                <?= $log['logged_time'] ? ', pukul ' . e(format_time($log['logged_time'])) : '' ?>
            </span>
        </div>
        <p class="muted" style="font-size:0.9rem;">
            Obat terkait:
            <?php if ($log['medication_id']): ?>
                <a href="<?= e(url('medications/detail.php', ['id' => $log['medication_id']])) ?>"><strong><?= e($log['medication_name']) ?></strong></a>
                <?php if ($log['scheduled_at']): ?>
                    (jadwal <?= e(format_time($log['scheduled_at'])) ?>)
                <?php endif; ?>
            <?php else: ?>
                Catatan kondisi umum (tidak ditautkan ke obat spesifik)
            <?php endif; ?>
        </p>
        <?php if (!empty($log['notes'])): ?>
        <p><?= e($log['notes']) ?></p>
        <?php endif; ?>
        <?php if (SymptomService::requiresMedicalWarning($log['severity'], $log['symptom_name'])): ?>
        <div class="notice-box notice-danger" style="margin-top:0.75rem;margin-bottom:0.6rem;">
            <p class="notice-title">Perhatian medis</p>
            <p>Keluhan dengan keparahan berat atau gejala alergi (seperti ruam, bengkak, atau sesak napas) perlu segera dikonsultasikan ke dokter atau fasilitas kesehatan terdekat.</p>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('symptoms/index.php')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="log_id" value="<?= (int) $log['id'] ?>">
            <button type="submit" class="button button-ghost button-sm">Hapus catatan</button>
        </form>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php view_end(); ?>
