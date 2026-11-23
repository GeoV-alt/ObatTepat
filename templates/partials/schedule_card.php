<?php
$status = $item['display_status'];
$backUrl = $backUrl ?? current_url();
?>
<article class="schedule-item status-<?= e($status) ?>" data-schedule-card="<?= (int) $item['id'] ?>">
    <div class="schedule-time-col">
        <time class="schedule-clock" datetime="<?= e($item['scheduled_at']) ?>"><?= e(format_time($item['scheduled_at'])) ?></time>
        <?= status_chip($status) ?>
    </div>
    <div class="schedule-body">
        <div class="schedule-head">
            <h3 class="schedule-med-name">
                <a href="<?= e(url('medications/detail.php', ['id' => $item['medication_id']])) ?>"><?= e($item['medication_name']) ?></a>
            </h3>
            <?php if (!empty($item['is_antibiotic'])): ?>
            <span class="chip chip-antibiotic">Antibiotik wajib habis</span>
            <?php endif; ?>
        </div>
        <p class="schedule-meta">
            <?= e(format_qty($item['planned_dose']) . ' ' . $item['dose_unit']) ?>
            <span aria-hidden="true"> / </span>
            <?= e(label('meal_rule', $item['meal_rule'])) ?>
            <span aria-hidden="true"> / </span>
            Sisa stok: <strong data-stock-label="<?= (int) $item['medication_id'] ?>"><?= e(format_qty($item['current_stock']) . ' ' . $item['dose_unit']) ?></strong>
        </p>
        <?php if ($status === 'taken'): ?>
        <p class="schedule-note">
            Diminum pukul <?= e(format_time($item['taken_at'])) ?>
            <?php if (!empty($item['is_late'])): ?>
                (terlambat <?= e(format_minutes((int) $item['delay_minutes'])) ?>)
            <?php else: ?>
                (tepat waktu)
            <?php endif; ?>
        </p>
        <?php elseif ($status === 'skipped' && !empty($item['skip_reason'])): ?>
        <p class="schedule-note">Alasan dilewati: <?= e($item['skip_reason']) ?></p>
        <?php elseif (!empty($item['instructions'])): ?>
        <p class="schedule-note"><?= e($item['instructions']) ?></p>
        <?php endif; ?>
    </div>
    <div class="schedule-actions">
        <?php if (in_array($status, ['due', 'missed', 'upcoming'], true)): ?>
        <form method="post" action="<?= e(url('api/checkin.php')) ?>" data-checkin-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="take">
            <input type="hidden" name="schedule_id" value="<?= (int) $item['id'] ?>">
            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
            <button type="submit" class="button <?= in_array($status, ['due', 'missed'], true) ? 'button-primary' : 'button-secondary' ?> button-sm">
                <?= $status === 'missed' ? 'Minum sekarang' : 'Sudah minum' ?>
            </button>
        </form>
        <details class="skip-disclosure">
            <summary class="button button-ghost button-sm">Lewati</summary>
            <form method="post" action="<?= e(url('api/checkin.php')) ?>" class="skip-form" data-checkin-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="skip">
                <input type="hidden" name="schedule_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                <label class="sr-only" for="skip-reason-<?= (int) $item['id'] ?>">Alasan melewati dosis</label>
                <select id="skip-reason-<?= (int) $item['id'] ?>" name="reason" required>
                    <?= select_options(labels('skip_reason'), '', 'Pilih alasan') ?>
                </select>
                <button type="submit" class="button button-danger button-sm">Simpan lewati</button>
            </form>
        </details>
        <?php elseif ($status === 'taken' && !empty($item['can_undo'])): ?>
        <form method="post" action="<?= e(url('api/checkin.php')) ?>" data-checkin-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="undo">
            <input type="hidden" name="schedule_id" value="<?= (int) $item['id'] ?>">
            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
            <button type="submit" class="button button-ghost button-sm">Batalkan check-in</button>
        </form>
        <?php endif; ?>
    </div>
</article>
