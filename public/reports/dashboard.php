<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$preset = query_param('preset', '7');
[$fromDate, $toDate, $activePreset] = AdherenceService::resolveRange($preset);

$todaySchedules = ScheduleService::forDate($patientId, today());
$takenToday = count(array_filter($todaySchedules, fn (array $r) => $r['display_status'] === 'taken'));
$urgentToday = array_values(array_filter($todaySchedules, fn (array $r) => in_array($r['display_status'], ['due', 'missed'], true)));

$adherence = AdherenceService::summary($patientId, $fromDate, $toDate);
$byMedication = AdherenceService::byMedication($patientId, $fromDate, $toDate);
$refillAlerts = RefillService::activeAlertsForPatient($patientId);
$reminders = CaregiverService::pendingRemindersForPatient($patientId);
$articles = EducationService::getContextualArticlesForPatient($patientId, 2);

view_start('Beranda kepatuhan', 'dashboard');
?>
<div class="page-header">
    <div>
        <h1>Beranda kepatuhan, <?= e($user['full_name']) ?></h1>
        <p class="page-subtitle"><?= e(format_date_id(today())) ?>. Ringkasan jadwal hari ini, tingkat kepatuhan, peringatan stok, dan pesan dari pendamping.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('reports/medical_report.php', ['preset' => $activePreset])) ?>">Cetak laporan kontrol</a>
        <a class="button button-primary" href="<?= e(url('medications/create.php')) ?>">Tambah obat</a>
    </div>
</div>

<?php if ($reminders !== []): ?>
<section class="panel panel-warning" aria-label="Pengingat dari pendamping">
    <h2>Pengingat dari pendamping (<?= count($reminders) ?>)</h2>
    <div class="stack-list">
        <?php foreach ($reminders as $rem): ?>
        <article class="med-row">
            <div class="med-row-info">
                <strong><?= e($rem['caregiver_name']) ?> (<?= e($rem['relationship_label']) ?>)</strong>
                <p style="margin:0.25rem 0;"><?= e($rem['message']) ?></p>
                <p class="muted" style="font-size:0.84rem;margin:0;">
                    Obat: <?= e($rem['medication_name']) ?> (jadwal <?= e(format_time($rem['scheduled_at'])) ?>) / Dikirim <?= e(format_datetime($rem['sent_at'])) ?>
                </p>
            </div>
            <div class="action-row">
                <form method="post" action="<?= e(url('api/checkin.php')) ?>" data-checkin-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="take">
                    <input type="hidden" name="schedule_id" value="<?= (int) $rem['schedule_id'] ?>">
                    <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                    <button type="submit" class="button button-primary button-sm">Konfirmasi sudah minum</button>
                </form>
                <form method="post" action="<?= e(url('api/reminder.php')) ?>" data-reminder-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="dismiss">
                    <input type="hidden" name="reminder_id" value="<?= (int) $rem['id'] ?>">
                    <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                    <button type="submit" class="button button-ghost button-sm">Tandai dibaca</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($refillAlerts !== []): ?>
<div class="notice-box notice-danger">
    <p class="notice-title">Peringatan stok obat menipis (<?= count($refillAlerts) ?> obat)</p>
    <p>
        <?php foreach ($refillAlerts as $idx => $alert): ?>
            <?= $idx > 0 ? ' | ' : '' ?><strong><?= e($alert['medication_name']) ?></strong>: sisa <?= e(format_qty($alert['current_stock']) . ' ' . $alert['dose_unit']) ?> (<?= e(format_qty($alert['estimated_days_left'])) ?> hari)
        <?php endforeach; ?>
        . <a href="<?= e(url('refills/index.php')) ?>">Catat tebus ulang sekarang</a>.
    </p>
</div>
<?php endif; ?>

<div class="kpi-strip">
    <div class="kpi-card kpi-focal">
        <p class="kpi-label">Skor kepatuhan (<?= e($activePreset) ?> hari terakhir)</p>
        <p class="kpi-value"><?= $adherence['adherence_rate'] !== null ? e(format_qty($adherence['adherence_rate'])) . '%' : 'Belum ada data' ?></p>
        <p class="kpi-detail">
            <?= $adherence['total_due'] > 0
                ? $adherence['taken'] . ' dari ' . $adherence['total_due'] . ' jadwal jatuh tempo diminum (' . format_qty($adherence['on_time_rate']) . '% tepat waktu)'
                : 'Skor akan terhitung saat jadwal mulai jatuh tempo' ?>
        </p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Jadwal hari ini</p>
        <p class="kpi-value"><?= $takenToday ?> / <?= count($todaySchedules) ?></p>
        <p class="kpi-detail"><?= count($urgentToday) > 0 ? count($urgentToday) . ' jadwal perlu tindakan' : 'Semua jadwal saat ini terkendali' ?></p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Peringatan stok</p>
        <p class="kpi-value"><?= count($refillAlerts) ?></p>
        <p class="kpi-detail"><a href="<?= e(url('refills/index.php')) ?>">Kelola stok dan refill</a></p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Pengingat pendamping</p>
        <p class="kpi-value"><?= count($reminders) ?></p>
        <p class="kpi-detail"><a href="<?= e(url('caregivers/index.php')) ?>">Atur pendamping</a></p>
    </div>
</div>

<div class="split-grid">
    <div>
        <section class="panel panel-ledger">
            <div class="panel-head">
                <h2>Jadwal minum obat hari ini</h2>
                <a class="button button-ghost button-sm" href="<?= e(url('schedules/index.php')) ?>">Buka jadwal lengkap</a>
            </div>
            <?php if ($todaySchedules === []): ?>
                <?= empty_state('Belum ada jadwal untuk hari ini', 'Tambahkan obat aktif di menu Obat saya agar jadwal harian muncul di sini.', url('medications/create.php'), 'Tambah obat baru') ?>
            <?php else: ?>
            <div class="schedule-list">
                <?php foreach ($todaySchedules as $item): ?>
                    <?php partial('schedule_card', ['item' => $item, 'backUrl' => current_url()]); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Persentase jadwal diminum per hari (<?= e($activePreset) ?> hari terakhir)</h2>
                    <p class="muted" style="font-size:0.86rem;margin:0;" data-chart-detail>Arahkan kursor atau ketuk batang grafik untuk melihat rincian harian.</p>
                </div>
                <div class="period-tabs" aria-label="Pilih periode grafik">
                    <a class="period-tab<?= $activePreset === '7' ? ' is-active' : '' ?>" href="<?= e(url('reports/dashboard.php', ['preset' => '7'])) ?>">7 hari</a>
                    <a class="period-tab<?= $activePreset === '14' ? ' is-active' : '' ?>" href="<?= e(url('reports/dashboard.php', ['preset' => '14'])) ?>">14 hari</a>
                    <a class="period-tab<?= $activePreset === '30' ? ' is-active' : '' ?>" href="<?= e(url('reports/dashboard.php', ['preset' => '30'])) ?>">30 hari</a>
                </div>
            </div>
            <div class="chart-host" data-adherence-chart data-endpoint="<?= e(url('api/adherence_stats.php', ['preset' => $activePreset])) ?>"></div>
            <p class="chart-caption">Hijau tua: 80% ke atas. Kuning kecokelatan: 50% hingga 79%. Merah: di bawah 50%. Hanya jadwal jatuh tempo yang dihitung.</p>
        </section>
    </div>

    <div>
        <section class="panel">
            <h2>Kepatuhan per obat (<?= e($activePreset) ?> hari)</h2>
            <?php if ($byMedication === []): ?>
                <p class="muted">Belum ada jadwal jatuh tempo dalam <?= e($activePreset) ?> hari terakhir.</p>
            <?php else: ?>
            <div class="stack-list">
                <?php foreach ($byMedication as $med): ?>
                <article class="stack-item">
                    <div class="panel-head" style="margin-bottom:0.25rem;">
                        <a href="<?= e(url('medications/detail.php', ['id' => $med['id']])) ?>"><strong><?= e($med['name']) ?></strong></a>
                        <strong class="tabular"><?= e(format_qty($med['adherence_rate'])) ?>%</strong>
                    </div>
                    <p class="muted" style="font-size:0.85rem;margin:0;">
                        Diminum <?= (int) $med['taken'] ?> dari <?= (int) $med['total_due'] ?> jadwal (terlewat <?= (int) $med['missed'] ?>, dilewati <?= (int) $med['skipped'] ?>).
                    </p>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2>Tindakan cepat</h2>
            </div>
            <div class="stack-list">
                <a class="button button-secondary" href="<?= e(url('symptoms/create.php')) ?>">Catat keluhan atau efek samping</a>
                <a class="button button-ghost" href="<?= e(url('reports/medical_report.php', ['preset' => $activePreset])) ?>">Siapkan laporan kontrol dokter</a>
                <a class="button button-ghost" href="<?= e(url('auth/profile.php')) ?>">Lengkapi alergi & profil kesehatan</a>
            </div>
        </section>

        <?php if ($articles !== []): ?>
        <section class="panel">
            <div class="panel-head">
                <h2>Bacaan sesuai obat Anda</h2>
                <a href="<?= e(url('education/index.php')) ?>">Katalog</a>
            </div>
            <div class="stack-list">
                <?php foreach ($articles as $article): ?>
                <article class="stack-item">
                    <span class="chip chip-upcoming"><?= e(label('article_category', $article['category'])) ?></span>
                    <h3 style="margin-top:0.4rem;"><a href="<?= e(url('education/detail.php', ['slug' => $article['slug']])) ?>"><?= e($article['title']) ?></a></h3>
                    <p class="muted" style="font-size:0.86rem;"><?= e($article['summary']) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php view_end(['checkin.js', 'caregiver.js', 'adherence_chart.js']); ?>
