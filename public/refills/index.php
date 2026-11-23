<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$alerts = RefillService::activeAlertsForPatient($patientId);
$inventory = RefillService::stockSummaryForPatient($patientId);

view_start('Stok dan peringatan tebus ulang', 'refills');
?>
<div class="page-header">
    <div>
        <h1>Stok obat dan peringatan tebus ulang</h1>
        <p class="page-subtitle">Sistem menghitung estimasi sisa hari obat berdasarkan konsumsi harian dan sisa jadwal yang masih harus dijalani.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('medications/index.php')) ?>">Kelola obat</a>
    </div>
</div>

<?php if ($alerts !== []): ?>
<section class="panel panel-danger">
    <h2>Peringatan stok menipis aktif (<?= count($alerts) ?>)</h2>
    <div class="stack-list">
        <?php foreach ($alerts as $alert): ?>
        <article class="med-row">
            <div class="med-row-info">
                <div class="schedule-head">
                    <h3><a href="<?= e(url('medications/detail.php', ['id' => $alert['medication_id']])) ?>"><?= e($alert['medication_name']) ?></a></h3>
                    <?= status_chip($alert['severity'], label('refill_severity', $alert['severity'])) ?>
                </div>
                <p class="med-row-meta">
                    Sisa stok: <strong class="tabular"><?= e(format_qty($alert['current_stock']) . ' ' . $alert['dose_unit']) ?></strong>
                    / Estimasi habis dalam <strong class="tabular"><?= e(format_qty($alert['estimated_days_left'])) ?> hari</strong>.
                </p>
            </div>
            <form method="post" action="<?= e(url('api/refill.php')) ?>" class="action-row" data-refill-form>
                <?= csrf_field() ?>
                <input type="hidden" name="medication_id" value="<?= (int) $alert['medication_id'] ?>">
                <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                <label class="sr-only" for="refill-qty-<?= (int) $alert['medication_id'] ?>">Tambah stok <?= e($alert['medication_name']) ?></label>
                <input id="refill-qty-<?= (int) $alert['medication_id'] ?>" name="added_qty" type="number" step="0.25" min="0.25" placeholder="Tambah <?= e($alert['dose_unit']) ?>" style="width:135px;" required>
                <button type="submit" class="button button-primary button-sm">Catat tebus ulang</button>
            </form>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<div class="notice-box">
    <p class="notice-title">Tidak ada peringatan stok menipis saat ini</p>
    <p>Stok semua obat aktif masih mencukupi batas hari peringatan atau mencukupi hingga jadwal terapi selesai.</p>
</div>
<?php endif; ?>

<section class="panel panel-ledger">
    <h2>Ringkasan inventaris obat aktif</h2>
    <?php if ($inventory === []): ?>
        <?= empty_state('Belum ada obat aktif untuk dipantau stoknya', 'Tambahkan obat terlebih dahulu di halaman Obat saya.', url('medications/create.php'), 'Tambah obat') ?>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>Konsumsi harian</th>
                    <th>Sisa stok</th>
                    <th>Sisa kebutuhan jadwal</th>
                    <th>Estimasi hari</th>
                    <th>Status stok</th>
                    <th>Tebus ulang cepat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('medications/detail.php', ['id' => $item['id']])) ?>"><strong><?= e($item['name']) ?></strong></a>
                    </td>
                    <td class="tabular"><?= e(format_qty($item['daily_consumption']) . ' ' . $item['dose_unit']) ?>/hari</td>
                    <td class="tabular"><strong><?= e(format_qty($item['current_stock']) . ' ' . $item['dose_unit']) ?></strong></td>
                    <td class="tabular"><?= e(format_qty($item['remaining_needed']) . ' ' . $item['dose_unit']) ?></td>
                    <td class="tabular"><?= e(format_qty($item['estimated_days_left'])) ?> hari</td>
                    <td>
                        <?php if ($item['alert_severity']): ?>
                            <?= status_chip($item['alert_severity'], label('refill_severity', $item['alert_severity'])) ?>
                        <?php else: ?>
                            <?= status_chip('taken', 'Aman') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= e(url('api/refill.php')) ?>" class="action-row" data-refill-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="medication_id" value="<?= (int) $item['id'] ?>">
                            <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                            <label class="sr-only" for="inv-qty-<?= (int) $item['id'] ?>">Jumlah tebus ulang <?= e($item['name']) ?></label>
                            <input id="inv-qty-<?= (int) $item['id'] ?>" name="added_qty" type="number" step="0.25" min="0.25" placeholder="+<?= e($item['dose_unit']) ?>" style="width:105px;" required>
                            <button type="submit" class="button button-secondary button-sm">Tambah</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php view_end(['checkin.js']); ?>
