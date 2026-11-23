<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$preset = query_param('preset', '14');
$customFrom = query_param('from_date', '');
$customTo = query_param('to_date', '');
[$fromDate, $toDate, $activePreset] = AdherenceService::resolveRange($preset, $customFrom, $customTo);

$report = AdherenceService::reportPayload($patientId, $fromDate, $toDate);
$profile = $report['profile'];
$summary = $report['summary'];

view_start('Laporan kontrol medis', 'report', ['stylesheets' => ['css/print.css']]);
?>

<div class="page-header no-print">
    <div>
        <h1>Laporan kontrol medis</h1>
        <p class="page-subtitle">Ringkasan profil kesehatan, skor kepatuhan, daftar obat, riwayat konsumsi, dan catatan efek samping siap cetak ke PDF A4 untuk dokter.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('reports/export_csv.php', ['from_date' => $fromDate, 'to_date' => $toDate])) ?>">Unduh CSV riwayat</a>
        <button type="button" class="button button-primary" data-print-report>Cetak atau simpan PDF</button>
    </div>
</div>

<form method="get" action="<?= e(url('reports/medical_report.php')) ?>" class="panel filter-bar no-print">
    <input type="hidden" name="preset" value="custom">
    <div class="field">
        <label for="from_date">Dari tanggal</label>
        <input id="from_date" name="from_date" type="date" value="<?= e($fromDate) ?>" required>
    </div>
    <div class="field">
        <label for="to_date">Sampai tanggal</label>
        <input id="to_date" name="to_date" type="date" value="<?= e($toDate) ?>" required>
    </div>
    <button type="submit" class="button button-primary">Tampilkan periode</button>
    <a class="button button-ghost" href="<?= e(url('reports/medical_report.php', ['preset' => '7'])) ?>">7 hari</a>
    <a class="button button-ghost" href="<?= e(url('reports/medical_report.php', ['preset' => '30'])) ?>">30 hari</a>
</form>

<article class="report-sheet">
    <header class="report-header">
        <div>
            <h2 style="font-size:1.35rem;margin-bottom:0.2rem;">Ringkasan Riwayat Konsumsi Obat (<?= e(config('name')) ?>)</h2>
            <p class="muted" style="margin:0;">Periode evaluasi: <?= e(format_date_id($fromDate, false)) ?> s.d. <?= e(format_date_id($toDate, false)) ?></p>
        </div>
        <div style="text-align:right;font-size:0.86rem;" class="muted">
            <div>Dicetak: <?= e(format_datetime($report['generated_at'])) ?></div>
            <div>Pasien: <strong><?= e($profile['full_name']) ?></strong></div>
        </div>
    </header>

    <section class="report-section">
        <h2>1. Profil kesehatan dasar pasien</h2>
        <dl class="dl-grid">
            <div>
                <dt>Nama lengkap</dt>
                <dd><?= e($profile['full_name']) ?></dd>
            </div>
            <div>
                <dt>Tanggal lahir / Jenis kelamin</dt>
                <dd><?= e($profile['date_of_birth'] ? format_date_id($profile['date_of_birth'], false) : '-') ?> / <?= e($profile['gender'] ? label('gender', $profile['gender']) : '-') ?></dd>
            </div>
            <div>
                <dt>Golongan darah</dt>
                <dd><?= e(label('blood_type', $profile['blood_type'] ?? 'unknown')) ?></dd>
            </div>
            <div>
                <dt>Riwayat alergi obat</dt>
                <dd><?= e($profile['drug_allergies'] ?: 'Tidak ada tercatat') ?></dd>
            </div>
            <div>
                <dt>Kondisi penyerta</dt>
                <dd><?= e($profile['chronic_conditions'] ?: '-') ?></dd>
            </div>
            <div>
                <dt>Catatan khusus</dt>
                <dd><?= e($profile['special_notes'] ?: '-') ?></dd>
            </div>
        </dl>
    </section>

    <section class="report-section">
        <h2>2. Ringkasan kepatuhan terapi</h2>
        <?php if ($summary['total_due'] === 0): ?>
        <p class="muted">Belum ada jadwal jatuh tempo dalam rentang tanggal ini.</p>
        <?php else: ?>
        <dl class="dl-grid">
            <div>
                <dt>Tingkat kepatuhan (Adherence Score)</dt>
                <dd class="tabular"><?= e(format_qty($summary['adherence_rate'])) ?>% (<?= $summary['taken'] ?> dari <?= $summary['total_due'] ?> jadwal)</dd>
            </div>
            <div>
                <dt>Ketepatan waktu minum</dt>
                <dd class="tabular"><?= e(format_qty($summary['on_time_rate'])) ?>% (tepat waktu: <?= $summary['on_time'] ?>, terlambat: <?= $summary['late_taken'] ?>)</dd>
            </div>
            <div>
                <dt>Dosis terlewat (Missed)</dt>
                <dd class="tabular"><?= $summary['missed'] ?> jadwal</dd>
            </div>
            <div>
                <dt>Dosis dilewati (Skipped)</dt>
                <dd class="tabular"><?= $summary['skipped'] ?> jadwal</dd>
            </div>
        </dl>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>3. Daftar obat dan sisa stok</h2>
        <?php if ($report['medications'] === []): ?>
        <p class="muted">Tidak ada obat yang tercatat pada periode ini.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama obat</th>
                        <th>Aturan pakai</th>
                        <th>Periode terapi</th>
                        <th>Sisa stok</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['medications'] as $med): ?>
                    <tr>
                        <td>
                            <strong><?= e($med['name']) ?></strong>
                            <?= !empty($med['is_antibiotic']) ? ' (Antibiotik)' : '' ?>
                        </td>
                        <td><?= e(signa($med)) ?>, <?= e(strtolower(label('meal_rule', $med['meal_rule']))) ?></td>
                        <td class="tabular"><?= e(format_date_id($med['start_date'], false)) ?> s.d. <?= e(format_date_id(MedicationService::endDate($med), false)) ?></td>
                        <td class="tabular"><?= e(format_qty($med['current_stock']) . ' ' . $med['dose_unit']) ?></td>
                        <td><?= e(label('medication_status', $med['status'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>4. Catatan efek samping dan kondisi harian</h2>
        <?php if ($report['symptoms'] === []): ?>
        <p class="muted">Tidak ada keluhan atau efek samping yang dicatat pada periode ini.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keluhan</th>
                        <th>Keparahan</th>
                        <th>Obat terkait</th>
                        <th>Kondisi harian</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['symptoms'] as $sym): ?>
                    <tr>
                        <td class="tabular"><?= e(format_date_id($sym['log_date'], false)) ?><?= $sym['logged_time'] ? ' ' . e(format_time($sym['logged_time'])) : '' ?></td>
                        <td><strong><?= e($sym['symptom_name']) ?></strong></td>
                        <td><?= e(label('symptom_severity', $sym['severity'])) ?></td>
                        <td><?= e($sym['medication_name'] ?: '-') ?></td>
                        <td><?= e(label('daily_condition', $sym['daily_condition'])) ?></td>
                        <td><?= e($sym['notes'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>5. Riwayat check-in konsumsi obat</h2>
        <?php if ($report['history'] === []): ?>
        <p class="muted">Belum ada catatan konsumsi obat pada periode ini.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Jadwal</th>
                        <th>Obat</th>
                        <th>Status</th>
                        <th>Waktu diminum</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['history'] as $row): ?>
                    <tr>
                        <td class="tabular"><?= e(format_datetime($row['scheduled_at'])) ?></td>
                        <td><?= e($row['medication_name']) ?></td>
                        <td><?= e(label('schedule_status', $row['display_status'])) ?></td>
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

    <footer class="report-footer">
        <p>Dokumen ini dihasilkan dari pencatatan mandiri pasien (self-reported) pada aplikasi <?= e(config('name')) ?> untuk membantu evaluasi klinis saat jadwal kontrol ulang.</p>
    </footer>
</article>

<script>
document.addEventListener('click', function (event) {
    if (event.target.closest('[data-print-report]')) {
        window.print();
    }
});
</script>
<?php view_end(); ?>
