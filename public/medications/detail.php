<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];
$medId = (int) query_param('id', '0');
$medication = MedicationService::findOrFail($medId, $patientId);

ScheduleService::syncOverdueSchedules($patientId);
RefillService::evaluateMedicationStock($medId);
$medication = MedicationService::findOrFail($medId, $patientId);

$tolerance = AuthService::toleranceMinutes($patientId);
$schedules = ScheduleService::upcomingForMedication($medId, 20);
$stockLogs = MedicationService::stockLogs($medId, 15);
$relatedArticles = EducationService::getArticlesForMedication($medication, 2);
$daily = MedicationService::dailyConsumption($medication);
$daysLeft = $daily > 0 ? round((float) $medication['current_stock'] / $daily, 1) : 0;

view_start($medication['name'], 'medications');
?>
<div class="page-header">
    <div>
        <div class="schedule-head">
            <h1><?= e($medication['name']) ?></h1>
            <?= status_chip($medication['status'], label('medication_status', $medication['status'])) ?>
            <?php if (!empty($medication['is_antibiotic'])): ?>
            <span class="chip chip-antibiotic">Antibiotik wajib habis</span>
            <?php endif; ?>
        </div>
        <p class="page-subtitle"><?= e(label('dosage_form', $medication['dosage_form'])) ?>, <?= e(signa($medication)) ?> setiap <?= (int) $medication['interval_hours'] ?> jam, <?= e(strtolower(label('meal_rule', $medication['meal_rule']))) ?>.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('medications/index.php')) ?>">Semua obat</a>
        <a class="button button-secondary" href="<?= e(url('medications/edit.php', ['id' => $medId])) ?>">Ubah obat</a>
        <a class="button button-primary" href="<?= e(url('schedules/index.php', ['medication_id' => $medId])) ?>">Lihat di jadwal</a>
    </div>
</div>

<div class="split-grid">
    <div>
        <section class="panel panel-ledger">
            <h2>Ringkasan aturan pakai dan stok</h2>
            <dl class="dl-grid">
                <div>
                    <dt>Sisa stok saat ini</dt>
                    <dd class="tabular"><?= e(format_qty($medication['current_stock']) . ' ' . $medication['dose_unit']) ?> (estimasi <?= e(format_qty($daysLeft)) ?> hari)</dd>
                </div>
                <div>
                    <dt>Stok awal / Kebutuhan terapi</dt>
                    <dd class="tabular"><?= e(format_qty($medication['initial_stock'])) ?> / <?= e(format_qty(MedicationService::requiredStock($medication)) . ' ' . $medication['dose_unit']) ?></dd>
                </div>
                <div>
                    <dt>Periode pengobatan</dt>
                    <dd><?= e(format_date_id($medication['start_date'], false)) ?> s.d. <?= e(format_date_id(MedicationService::endDate($medication), false)) ?> (<?= (int) $medication['duration_days'] ?> hari)</dd>
                </div>
                <div>
                    <dt>Jam dosis pertama</dt>
                    <dd class="tabular"><?= e(format_time($medication['first_dose_time'])) ?> (interval <?= (int) $medication['interval_hours'] ?> jam)</dd>
                </div>
                <div>
                    <dt>Resep induk</dt>
                    <dd><?= e($medication['prescription_title'] ?: 'Tanpa kelompok resep') ?></dd>
                </div>
                <div>
                    <dt>Batas peringatan stok</dt>
                    <dd><?= (int) $medication['refill_threshold_days'] ?> hari sebelum habis</dd>
                </div>
            </dl>
            <?php if (!empty($medication['instructions'])): ?>
            <div class="notice-box" style="margin-top:1rem;margin-bottom:0;">
                <p class="notice-title">Catatan aturan pakai</p>
                <p><?= e($medication['instructions']) ?></p>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2>Daftar jadwal obat ini</h2>
                <a class="button button-ghost button-sm" href="<?= e(url('schedules/history.php', ['medication_id' => $medId])) ?>">Lihat seluruh riwayat</a>
            </div>
            <?php if ($schedules === []): ?>
                <?= empty_state('Belum ada slot jadwal', 'Ubah status obat menjadi Aktif untuk membuat jadwal minum mendatang.') ?>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Jadwal</th>
                            <th>Dosis</th>
                            <th>Status</th>
                            <th>Waktu diminum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $slot): ?>
                        <?php $display = ScheduleService::displayStatus($slot, $tolerance); ?>
                        <tr>
                            <td class="tabular"><?= e(format_datetime($slot['scheduled_at'])) ?></td>
                            <td class="tabular"><?= e(format_qty($slot['planned_dose']) . ' ' . $medication['dose_unit']) ?></td>
                            <td><?= status_chip($display) ?></td>
                            <td class="tabular"><?= $slot['taken_at'] ? e(format_datetime($slot['taken_at'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Riwayat mutasi stok</h2>
            <?php if ($stockLogs === []): ?>
                <?= empty_state('Belum ada catatan mutasi stok', 'Mutasi stok tercatat otomatis saat obat dibuat, saat check-in minum obat, dan saat tebus ulang.') ?>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis</th>
                            <th>Perubahan</th>
                            <th>Stok akhir</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockLogs as $log): ?>
                        <tr>
                            <td class="tabular"><?= e(format_datetime($log['created_at'])) ?></td>
                            <td><?= e(label('stock_change', $log['change_type'])) ?></td>
                            <td class="tabular"><?= ((float) $log['quantity_change'] >= 0 ? '+' : '') . e(format_qty($log['quantity_change'])) ?></td>
                            <td class="tabular"><?= e(format_qty($log['stock_after']) . ' ' . $medication['dose_unit']) ?></td>
                            <td><?= e($log['notes'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="panel panel-warning">
            <h2>Catat tebus ulang stok</h2>
            <p class="muted">Tambahkan jumlah obat yang baru ditebus agar peringatan stok menipis terselesaikan otomatis.</p>
            <form method="post" action="<?= e(url('api/refill.php')) ?>" data-refill-form>
                <?= csrf_field() ?>
                <input type="hidden" name="medication_id" value="<?= $medId ?>">
                <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                <div class="field">
                    <label for="added_qty">Jumlah tambahan (<?= e($medication['dose_unit']) ?>)</label>
                    <input id="added_qty" name="added_qty" type="number" step="0.25" min="0.25" placeholder="Contoh: 10" required>
                </div>
                <div class="field">
                    <label for="refill_notes">Catatan (opsional)</label>
                    <input id="refill_notes" name="notes" type="text" placeholder="Contoh: Tebus ulang di apotek kampus">
                </div>
                <button type="submit" class="button button-primary">Simpan stok tambahan</button>
            </form>
        </section>

        <section class="panel">
            <h2>Ubah status terapi</h2>
            <div class="action-row">
                <?php foreach (['active' => 'Aktifkan', 'paused' => 'Jeda sementara', 'completed' => 'Tandai selesai', 'discontinued' => 'Hentikan'] as $st => $btnLabel): ?>
                <?php if ($medication['status'] !== $st): ?>
                <form method="post" action="<?= e(url('medications/index.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="medication_id" value="<?= $medId ?>">
                    <input type="hidden" name="status" value="<?= e($st) ?>">
                    <button type="submit" class="button button-ghost button-sm"><?= e($btnLabel) ?></button>
                </form>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($relatedArticles !== []): ?>
        <section class="panel">
            <h2>Bacaan edukasi terkait</h2>
            <div class="stack-list">
                <?php foreach ($relatedArticles as $article): ?>
                <article class="stack-item">
                    <span class="chip chip-upcoming"><?= e(label('article_category', $article['category'])) ?></span>
                    <h3 style="margin-top:0.45rem;"><a href="<?= e(url('education/detail.php', ['slug' => $article['slug']])) ?>"><?= e($article['title']) ?></a></h3>
                    <p class="muted" style="font-size:0.88rem;"><?= e($article['summary']) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php view_end(['checkin.js']); ?>
