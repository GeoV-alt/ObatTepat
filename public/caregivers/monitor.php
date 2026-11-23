<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('caregiver');
$caregiverId = (int) $user['id'];
$patients = CaregiverService::linkedPatientsForCaregiver($caregiverId);

$selectedId = (int) query_param('patient_id', '0');
$selectedPatient = null;
foreach ($patients as $patient) {
    if ((int) $patient['patient_id'] === $selectedId) {
        $selectedPatient = $patient;
        break;
    }
}
if ($selectedPatient === null && $patients !== []) {
    $selectedPatient = $patients[0];
    $selectedId = (int) $selectedPatient['patient_id'];
}

$date = query_param('date', today());
if (!is_valid_date($date)) {
    $date = today();
}

$todaySchedules = [];
$adherence = null;
$refillAlerts = [];
$recentSymptoms = [];
$recentReminders = [];

if ($selectedPatient !== null) {
    $todaySchedules = ScheduleService::forDate($selectedId, $date);
    [$from7, $to7] = AdherenceService::resolveRange('7');
    $adherence = AdherenceService::summary($selectedId, $from7, $to7);
    $refillAlerts = RefillService::activeAlertsForPatient($selectedId);
    $recentSymptoms = SymptomService::listForPatient($selectedId, ['limit' => 5]);
    $recentReminders = CaregiverService::recentRemindersForCaregiver($caregiverId, $selectedId, 6);
}

view_start('Pantau jadwal pasien', 'monitor');
?>
<div class="page-header">
    <div>
        <h1>Pemantauan pendamping</h1>
        <p class="page-subtitle">Pantau jadwal minum obat pasien tertaut dan kirim pengingat segera bila dosis terlewat melewati batas toleransi.</p>
    </div>
    <div class="page-actions">
        <a class="button button-primary" href="<?= e(url('caregivers/link.php')) ?>">Tautkan pasien baru</a>
    </div>
</div>

<?php if ($patients === []): ?>
    <?= empty_state(
        'Belum ada pasien yang tertaut ke akun Anda',
        'Minta anggota keluarga atau teman kos Anda membuat kode undangan pada menu Pendamping saya, lalu masukkan kode tersebut di halaman Tautkan pasien.',
        url('caregivers/link.php'),
        'Masukkan kode undangan'
    ) ?>
<?php else: ?>
<form method="get" action="<?= e(url('caregivers/monitor.php')) ?>" class="panel filter-bar">
    <div class="field">
        <label for="patient_id">Pilih pasien</label>
        <select id="patient_id" name="patient_id">
            <?php foreach ($patients as $patient): ?>
            <option value="<?= (int) $patient['patient_id'] ?>"<?= selected($selectedId, $patient['patient_id']) ?>>
                <?= e($patient['full_name']) ?> (<?= e($patient['relationship_label']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="date">Tanggal jadwal</label>
        <input id="date" name="date" type="date" value="<?= e($date) ?>">
    </div>
    <button type="submit" class="button button-primary">Tampilkan</button>
</form>

<div class="kpi-strip">
    <div class="kpi-card kpi-focal">
        <p class="kpi-label">Kepatuhan 7 hari terakhir</p>
        <p class="kpi-value"><?= $adherence['adherence_rate'] !== null ? e(format_qty($adherence['adherence_rate'])) . '%' : 'Belum ada data' ?></p>
        <p class="kpi-detail"><?= $adherence['taken'] ?> dari <?= $adherence['total_due'] ?> jadwal jatuh tempo diminum</p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Jadwal tanggal ini</p>
        <p class="kpi-value"><?= count($todaySchedules) ?></p>
        <p class="kpi-detail"><?= e(format_date_id($date, false)) ?></p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Obat stok menipis</p>
        <p class="kpi-value"><?= count($refillAlerts) ?></p>
        <p class="kpi-detail"><?= count($refillAlerts) > 0 ? 'Perlu tebus ulang segera' : 'Semua stok aman' ?></p>
    </div>
    <div class="kpi-card">
        <p class="kpi-label">Golongan darah dan alergi</p>
        <p class="kpi-value" style="font-size:1.25rem;"><?= e(label('blood_type', $selectedPatient['blood_type'] ?? 'unknown')) ?></p>
        <p class="kpi-detail"><?= e($selectedPatient['drug_allergies'] ?: 'Tidak ada alergi tercatat') ?></p>
    </div>
</div>

<div class="split-grid">
    <div>
        <section class="panel panel-ledger">
            <h2>Jadwal minum obat: <?= e($selectedPatient['full_name']) ?></h2>
            <?php if ($todaySchedules === []): ?>
                <?= empty_state('Tidak ada jadwal pada tanggal ini', 'Pilih tanggal lain untuk memeriksa riwayat jadwal minum obat pasien.') ?>
            <?php else: ?>
            <div class="schedule-list">
                <?php foreach ($todaySchedules as $slot): ?>
                <?php
                $status = $slot['display_status'];
                $waMessage = 'Halo ' . $selectedPatient['full_name'] . ', pengingat minum ' . $slot['medication_name'] . ' (' . format_qty($slot['planned_dose']) . ' ' . $slot['dose_unit'] . ', ' . strtolower(label('meal_rule', $slot['meal_rule'])) . ') untuk jadwal pukul ' . format_time($slot['scheduled_at']) . '.';
                $waLink = CaregiverService::whatsappUrl($selectedPatient['phone'], $waMessage);
                ?>
                <article class="schedule-item status-<?= e($status) ?>">
                    <div class="schedule-time-col">
                        <time class="schedule-clock" datetime="<?= e($slot['scheduled_at']) ?>"><?= e(format_time($slot['scheduled_at'])) ?></time>
                        <?= status_chip($status) ?>
                    </div>
                    <div class="schedule-body">
                        <h3 class="schedule-med-name"><?= e($slot['medication_name']) ?></h3>
                        <p class="schedule-meta">
                            <?= e(format_qty($slot['planned_dose']) . ' ' . $slot['dose_unit']) ?>
                            / <?= e(label('meal_rule', $slot['meal_rule'])) ?>
                            / Sisa stok: <strong class="tabular"><?= e(format_qty($slot['current_stock']) . ' ' . $slot['dose_unit']) ?></strong>
                        </p>
                        <?php if ($status === 'taken'): ?>
                        <p class="schedule-note">Diminum pukul <?= e(format_time($slot['taken_at'])) ?></p>
                        <?php elseif ($status === 'skipped'): ?>
                        <p class="schedule-note">Dilewati: <?= e($slot['skip_reason'] ?: '-') ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="schedule-actions">
                        <?php if (!empty($slot['can_remind'])): ?>
                        <details class="skip-disclosure">
                            <summary class="button button-accent button-sm">Ingatkan sekarang</summary>
                            <form method="post" action="<?= e(url('api/reminder.php')) ?>" class="skip-form" data-reminder-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="send">
                                <input type="hidden" name="schedule_id" value="<?= (int) $slot['id'] ?>">
                                <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                                <label for="msg-<?= (int) $slot['id'] ?>">Pesan pengingat</label>
                                <textarea id="msg-<?= (int) $slot['id'] ?>" name="message" rows="3" placeholder="Jangan lupa minum obatnya ya"><?= e('Pengingat minum ' . $slot['medication_name'] . ' (' . strtolower(label('meal_rule', $slot['meal_rule'])) . ') untuk jadwal ' . format_time($slot['scheduled_at']) . '.') ?></textarea>
                                <button type="submit" class="button button-primary button-sm">Kirim notifikasi</button>
                                <?php if ($waLink !== null): ?>
                                <a class="button button-ghost button-sm" href="<?= e($waLink) ?>" target="_blank" rel="noopener">Kirim lewat WhatsApp</a>
                                <?php endif; ?>
                            </form>
                        </details>
                        <?php else: ?>
                        <span class="muted" style="font-size:0.84rem;"><?= $status === 'taken' ? 'Tercatat' : 'Belum melewati toleransi' ?></span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Riwayat pengingat terakhir</h2>
            <?php if ($recentReminders === []): ?>
                <p class="muted">Belum ada pengingat yang dikirim kepada pasien ini.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu kirim</th>
                            <th>Obat & jadwal</th>
                            <th>Pesan</th>
                            <th>Konfirmasi pasien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReminders as $rem): ?>
                        <tr>
                            <td class="tabular"><?= e(format_datetime($rem['sent_at'])) ?></td>
                            <td><?= e($rem['medication_name']) ?> (<?= e(format_time($rem['scheduled_at'])) ?>)</td>
                            <td><?= e($rem['message']) ?></td>
                            <td>
                                <?php if ($rem['acknowledged_at']): ?>
                                    <?= status_chip('taken', 'Dikonfirmasi ' . format_time($rem['acknowledged_at'])) ?>
                                <?php else: ?>
                                    <?= status_chip('due', 'Menunggu konfirmasi') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="panel<?= $refillAlerts !== [] ? ' panel-warning' : '' ?>">
            <h2>Status stok obat pasien</h2>
            <?php if ($refillAlerts === []): ?>
                <p class="muted">Tidak ada obat pasien yang stoknya menipis saat ini.</p>
            <?php else: ?>
            <div class="stack-list">
                <?php foreach ($refillAlerts as $alert): ?>
                <article class="stack-item">
                    <div class="panel-head" style="margin-bottom:0.3rem;">
                        <strong><?= e($alert['medication_name']) ?></strong>
                        <?= status_chip($alert['severity'], label('refill_severity', $alert['severity'])) ?>
                    </div>
                    <p class="muted" style="font-size:0.86rem;">Sisa <?= e(format_qty($alert['current_stock']) . ' ' . $alert['dose_unit']) ?> (estimasi <?= e(format_qty($alert['estimated_days_left'])) ?> hari).</p>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Keluhan dan efek samping terbaru</h2>
            <?php if ($recentSymptoms === []): ?>
                <p class="muted">Belum ada catatan efek samping dari pasien.</p>
            <?php else: ?>
            <div class="stack-list">
                <?php foreach ($recentSymptoms as $sym): ?>
                <article class="stack-item">
                    <div class="panel-head" style="margin-bottom:0.3rem;">
                        <strong><?= e($sym['symptom_name']) ?></strong>
                        <?= status_chip($sym['severity'], label('symptom_severity', $sym['severity'])) ?>
                    </div>
                    <p class="muted" style="font-size:0.85rem;">
                        <?= e(format_date_id($sym['log_date'], false)) ?>
                        <?= $sym['medication_name'] ? ' / ' . e($sym['medication_name']) : '' ?>
                    </p>
                    <?php if (!empty($sym['notes'])): ?>
                    <p style="font-size:0.88rem;"><?= e($sym['notes']) ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Profil darurat pasien</h2>
            <dl class="dl-grid">
                <div>
                    <dt>Alergi obat</dt>
                    <dd><?= e($selectedPatient['drug_allergies'] ?: '-') ?></dd>
                </div>
                <div>
                    <dt>Kondisi penyerta</dt>
                    <dd><?= e($selectedPatient['chronic_conditions'] ?: '-') ?></dd>
                </div>
                <div>
                    <dt>Catatan khusus</dt>
                    <dd><?= e($selectedPatient['special_notes'] ?: '-') ?></dd>
                </div>
                <div>
                    <dt>Batas toleransi</dt>
                    <dd><?= (int) ($selectedPatient['default_tolerance_minutes'] ?? 60) ?> menit</dd>
                </div>
            </dl>
        </section>
    </div>
</div>
<?php endif; ?>
<?php view_end(['caregiver.js']); ?>
