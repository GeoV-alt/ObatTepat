<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];
$group = query_param('group', 'active') === 'finished' ? 'finished' : 'active';

if (is_post()) {
    require_post();
    $action = input('action');
    $medId = (int) input('medication_id', '0');

    try {
        if ($action === 'status') {
            $status = input('status');
            MedicationService::setStatus($medId, $patientId, $status);
            flash('success', 'Status obat diperbarui menjadi ' . strtolower(label('medication_status', $status)) . '.');
        } elseif ($action === 'delete') {
            MedicationService::delete($medId, $patientId);
            flash('success', 'Data obat berhasil dihapus.');
        }
    } catch (DomainException $error) {
        flash('error', $error->getMessage());
    }

    redirect('medications/index.php', ['group' => $group]);
}

ScheduleService::syncOverdueSchedules($patientId);
RefillService::evaluateAllForPatient($patientId);
$medications = MedicationService::listForPatient($patientId, $group);

view_start('Obat saya', 'medications');
?>
<div class="page-header">
    <div>
        <h1>Master obat pribadi</h1>
        <p class="page-subtitle">Daftar obat yang sedang dikonsumsi beserta aturan pakai, sisa stok, dan jadwal berikutnya.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('medications/prescriptions.php')) ?>">Kelola resep</a>
        <a class="button button-primary" href="<?= e(url('medications/create.php')) ?>">Tambah obat baru</a>
    </div>
</div>

<div class="period-tabs" style="margin-bottom:1.1rem;" role="tablist" aria-label="Filter status obat">
    <a class="period-tab<?= $group === 'active' ? ' is-active' : '' ?>" href="<?= e(url('medications/index.php', ['group' => 'active'])) ?>">Obat aktif dan dijeda</a>
    <a class="period-tab<?= $group === 'finished' ? ' is-active' : '' ?>" href="<?= e(url('medications/index.php', ['group' => 'finished'])) ?>">Riwayat selesai</a>
</div>

<?php if ($medications === []): ?>
    <?= empty_state(
        $group === 'active' ? 'Belum ada obat aktif' : 'Belum ada obat yang selesai atau dihentikan',
        $group === 'active'
            ? 'Tambahkan obat dari resep dokter atau obat rutin Anda agar jadwal minum harian terbentuk otomatis.'
            : 'Obat yang sudah selesai terapinya atau dihentikan akan tampil di sini sebagai arsip riwayat.',
        $group === 'active' ? url('medications/create.php') : null,
        $group === 'active' ? 'Tambah obat pertama' : null
    ) ?>
<?php else: ?>
<div class="med-list">
    <?php foreach ($medications as $med): ?>
    <?php
    $daily = MedicationService::dailyConsumption($med);
    $daysLeft = $daily > 0 ? round((float) $med['current_stock'] / $daily, 1) : 0;
    ?>
    <article class="med-row">
        <div class="med-row-info">
            <div class="schedule-head">
                <h3><a href="<?= e(url('medications/detail.php', ['id' => $med['id']])) ?>"><?= e($med['name']) ?></a></h3>
                <?= status_chip($med['status'], label('medication_status', $med['status'])) ?>
                <?php if (!empty($med['is_antibiotic'])): ?>
                <span class="chip chip-antibiotic">Antibiotik wajib habis</span>
                <?php endif; ?>
            </div>
            <p class="med-row-meta">
                <?= e(label('dosage_form', $med['dosage_form'])) ?>
                / <?= e(signa($med)) ?> (setiap <?= (int) $med['interval_hours'] ?> jam)
                / <?= e(label('meal_rule', $med['meal_rule'])) ?>
            </p>
            <p class="med-row-meta">
                Sisa stok: <strong class="tabular"><?= e(format_qty($med['current_stock']) . ' ' . $med['dose_unit']) ?></strong>
                (estimasi <?= e(format_qty($daysLeft)) ?> hari)
                / Terapi: <?= e(format_date_id($med['start_date'], false)) ?> s.d. <?= e(format_date_id(MedicationService::endDate($med), false)) ?>
                / Diminum: <span class="tabular"><?= (int) $med['taken_count'] ?> dari <?= (int) $med['schedule_count'] ?> jadwal</span>
            </p>
            <?php if (!empty($med['prescription_title'])): ?>
            <p class="med-row-meta">Resep: <?= e($med['prescription_title']) ?></p>
            <?php endif; ?>
            <?php if (!empty($med['next_dose_at'])): ?>
            <p class="med-row-meta">Jadwal berikutnya: <strong><?= e(format_datetime($med['next_dose_at'])) ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="action-row">
            <a class="button button-secondary button-sm" href="<?= e(url('medications/detail.php', ['id' => $med['id']])) ?>">Detail</a>
            <a class="button button-ghost button-sm" href="<?= e(url('medications/edit.php', ['id' => $med['id']])) ?>">Ubah</a>
            <?php if ($med['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('medications/index.php', ['group' => $group])) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="medication_id" value="<?= (int) $med['id'] ?>">
                <input type="hidden" name="status" value="completed">
                <button type="submit" class="button button-ghost button-sm">Selesaikan</button>
            </form>
            <?php elseif ($med['status'] === 'paused'): ?>
            <form method="post" action="<?= e(url('medications/index.php', ['group' => $group])) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="medication_id" value="<?= (int) $med['id'] ?>">
                <input type="hidden" name="status" value="active">
                <button type="submit" class="button button-primary button-sm">Aktifkan</button>
            </form>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php view_end(); ?>
