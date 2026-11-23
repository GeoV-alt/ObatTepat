<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$date = query_param('date', today());
if (!is_valid_date($date)) {
    $date = today();
}
$medicationId = (int) query_param('medication_id', '0');

$schedules = ScheduleService::forDate($patientId, $date, $medicationId);
$grouped = ScheduleService::groupByPeriod($schedules);
$medOptions = MedicationService::optionsForPatient($patientId);

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

$counts = ['due' => 0, 'missed' => 0, 'taken' => 0, 'upcoming' => 0];
foreach ($schedules as $row) {
    if (isset($counts[$row['display_status']])) {
        $counts[$row['display_status']]++;
    }
}

view_start('Jadwal minum obat', 'schedules');
?>
<div class="page-header">
    <div>
        <h1>Jadwal minum obat</h1>
        <p class="page-subtitle"><?= e(format_date_id($date)) ?>. Tekan Sudah minum untuk mencatat waktu konsumsi sebenarnya dan mengurangi sisa stok obat secara otomatis.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost button-sm" href="<?= e(url('schedules/index.php', ['date' => $prevDate, 'medication_id' => $medicationId ?: null])) ?>">Kemarin</a>
        <a class="button button-secondary button-sm" href="<?= e(url('schedules/index.php', ['date' => today(), 'medication_id' => $medicationId ?: null])) ?>">Hari ini</a>
        <a class="button button-ghost button-sm" href="<?= e(url('schedules/index.php', ['date' => $nextDate, 'medication_id' => $medicationId ?: null])) ?>">Besok</a>
    </div>
</div>

<form method="get" action="<?= e(url('schedules/index.php')) ?>" class="panel filter-bar">
    <div class="field">
        <label for="date">Pilih tanggal</label>
        <input id="date" name="date" type="date" value="<?= e($date) ?>">
    </div>
    <div class="field">
        <label for="medication_id">Filter obat</label>
        <select id="medication_id" name="medication_id">
            <?= select_options($medOptions, $medicationId, 'Semua obat') ?>
        </select>
    </div>
    <button type="submit" class="button button-primary">Tampilkan</button>
</form>

<?php if ($schedules !== []): ?>
<div class="kpi-strip">
    <div class="kpi-card kpi-focal">
        <p class="kpi-label">Sudah diminum</p>
        <p class="kpi-value"><?= $counts['taken'] ?> / <?= count($schedules) ?></p>
        <p class="kpi-detail">Total slot terjadwal hari ini</p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Waktunya minum</p>
        <p class="kpi-value"><?= $counts['due'] ?></p>
        <p class="kpi-detail">Siap check-in sekarang</p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Terlewat</p>
        <p class="kpi-value"><?= $counts['missed'] ?></p>
        <p class="kpi-detail">Melewati batas toleransi</p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Akan datang</p>
        <p class="kpi-value"><?= $counts['upcoming'] ?></p>
        <p class="kpi-detail">Jadwal sesi berikutnya</p>
    </div>
</div>
<?php endif; ?>

<?php if ($grouped === []): ?>
    <?= empty_state(
        'Tidak ada jadwal minum obat pada tanggal ini',
        $medicationId > 0
            ? 'Coba pilih obat lain atau ubah tanggal untuk melihat slot jadwal yang tersedia.'
            : 'Tambahkan obat aktif di menu Obat saya agar jadwal harian terbentuk otomatis.',
        url('medications/create.php'),
        'Tambah obat baru'
    ) ?>
<?php else: ?>
    <?php foreach ($grouped as $periodLabel => $items): ?>
    <section class="schedule-group">
        <h2 class="schedule-group-title"><?= e($periodLabel) ?></h2>
        <div class="schedule-list">
            <?php foreach ($items as $item): ?>
                <?php partial('schedule_card', ['item' => $item, 'backUrl' => current_url()]); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php view_end(['checkin.js']); ?>
