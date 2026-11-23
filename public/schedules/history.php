<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$fromDate = query_param('from_date', date('Y-m-d', strtotime('-6 days')));
$toDate = query_param('to_date', today());
if (!is_valid_date($fromDate)) {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
}
if (!is_valid_date($toDate)) {
    $toDate = today();
}
$medicationId = (int) query_param('medication_id', '0');
$status = query_param('status', '');

$rows = ScheduleService::history($patientId, $fromDate, $toDate, $medicationId, $status);
$medOptions = MedicationService::optionsForPatient($patientId);

view_start('Riwayat konsumsi obat', 'history');
?>
<div class="page-header">
    <div>
        <h1>Riwayat konsumsi obat</h1>
        <p class="page-subtitle">Catatan jadwal minum obat dibandingkan dengan waktu konsumsi sebenarnya.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('reports/medical_report.php', ['preset' => 'custom', 'from_date' => $fromDate, 'to_date' => $toDate])) ?>">Buka laporan kontrol</a>
        <a class="button button-secondary" href="<?= e(url('reports/export_csv.php', ['from_date' => $fromDate, 'to_date' => $toDate])) ?>">Unduh CSV</a>
    </div>
</div>

<form method="get" action="<?= e(url('schedules/history.php')) ?>" class="panel filter-bar">
    <div class="field">
        <label for="from_date">Dari tanggal</label>
        <input id="from_date" name="from_date" type="date" value="<?= e($fromDate) ?>">
    </div>
    <div class="field">
        <label for="to_date">Sampai tanggal</label>
        <input id="to_date" name="to_date" type="date" value="<?= e($toDate) ?>">
    </div>
    <div class="field">
        <label for="medication_id">Obat</label>
        <select id="medication_id" name="medication_id">
            <?= select_options($medOptions, $medicationId, 'Semua obat') ?>
        </select>
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?= select_options(['taken' => 'Sudah diminum', 'missed' => 'Terlewat', 'skipped' => 'Dilewati', 'pending' => 'Belum diminum'], $status, 'Semua status') ?>
        </select>
    </div>
    <button type="submit" class="button button-primary">Terapkan filter</button>
</form>

<section class="panel panel-ledger">
    <?php if ($rows === []): ?>
        <?= empty_state('Tidak ada riwayat pada rentang ini', 'Ubah rentang tanggal atau filter status untuk melihat catatan konsumsi lainnya.') ?>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Jadwal</th>
                    <th>Nama obat</th>
                    <th>Dosis</th>
                    <th>Status</th>
                    <th>Waktu diminum</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="tabular"><?= e(format_datetime($row['scheduled_at'])) ?></td>
                    <td>
                        <a href="<?= e(url('medications/detail.php', ['id' => $row['medication_id']])) ?>"><strong><?= e($row['medication_name']) ?></strong></a>
                    </td>
                    <td class="tabular"><?= e(format_qty($row['actual_dose'] ?? $row['planned_dose']) . ' ' . $row['dose_unit']) ?></td>
                    <td><?= status_chip($row['display_status']) ?></td>
                    <td class="tabular"><?= $row['taken_at'] ? e(format_datetime($row['taken_at'])) : '-' ?></td>
                    <td>
                        <?php if ($row['status'] === 'taken'): ?>
                            <?= !empty($row['is_late']) ? 'Terlambat ' . e(format_minutes((int) $row['delay_minutes'])) : 'Tepat waktu' ?>
                        <?php elseif ($row['status'] === 'skipped'): ?>
                            <?= e($row['skip_reason'] ?: 'Dilewati') ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php view_end(); ?>
