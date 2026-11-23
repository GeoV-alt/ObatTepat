<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

if (is_post()) {
    require_post();
    $action = input('action');

    try {
        if ($action === 'generate') {
            $invite = CaregiverService::createInvitation($patientId);
            flash('success', 'Kode undangan baru ' . $invite['invite_code'] . ' berlaku selama ' . (int) config('invite_valid_hours', 24) . ' jam.');
        } elseif ($action === 'revoke_invite') {
            CaregiverService::revokeInvitation((int) input('invitation_id', '0'), $patientId);
            flash('success', 'Kode undangan dibatalkan.');
        } elseif ($action === 'revoke_link') {
            CaregiverService::revokeLink((int) input('link_id', '0'), $patientId);
            flash('success', 'Akses pendamping telah dicabut.');
        }
    } catch (DomainException $error) {
        flash('error', $error->getMessage());
    }

    redirect('caregivers/index.php');
}

$activeInvite = CaregiverService::activeInvitationForPatient($patientId);
$links = CaregiverService::linksForPatient($patientId);

view_start('Pendamping saya', 'caregivers');
?>
<div class="page-header">
    <div>
        <h1>Pendamping dan keluarga</h1>
        <p class="page-subtitle">Bagikan kode undangan unik agar keluarga atau teman kos dapat memantau jadwal minum obat dan mengirim pengingat jika dosis terlewat.</p>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Kode undangan pendamping</h2>
        <p class="muted">Kode berlaku 24 jam dan hanya dapat digunakan satu kali. Anda bisa mencabut akses pendamping kapan saja.</p>

        <?php if ($activeInvite !== null): ?>
        <div class="invite-code-box" style="margin-bottom:1rem;">
            <div>
                <p class="muted" style="font-size:0.84rem;margin-bottom:0.2rem;">Kode undangan aktif</p>
                <div class="invite-code-value"><?= e($activeInvite['invite_code']) ?></div>
                <p class="muted" style="font-size:0.84rem;margin-top:0.25rem;margin-bottom:0;">Berlaku sampai <?= e(format_datetime($activeInvite['expires_at'])) ?></p>
            </div>
            <div class="action-row">
                <button type="button" class="button button-primary button-sm" data-copy-text="<?= e($activeInvite['invite_code']) ?>">Salin kode</button>
                <form method="post" action="<?= e(url('caregivers/index.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke_invite">
                    <input type="hidden" name="invitation_id" value="<?= (int) $activeInvite['id'] ?>">
                    <button type="submit" class="button button-ghost button-sm">Batalkan kode</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('caregivers/index.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate">
            <button type="submit" class="button <?= $activeInvite ? 'button-secondary' : 'button-primary' ?>">
                <?= $activeInvite ? 'Buat kode undangan baru' : 'Buat kode undangan' ?>
            </button>
        </form>
    </section>

    <section class="panel">
        <h2>Daftar pendamping tertaut</h2>
        <?php if ($links === []): ?>
            <?= empty_state('Belum ada pendamping tertaut', 'Buat kode undangan di samping lalu kirimkan kepada anggota keluarga atau wali yang sudah mendaftar sebagai Pendamping.') ?>
        <?php else: ?>
        <div class="stack-list">
            <?php foreach ($links as $link): ?>
            <article class="stack-item">
                <div class="panel-head" style="margin-bottom:0.35rem;">
                    <strong><?= e($link['caregiver_name']) ?></strong>
                    <?= status_chip($link['status'], $link['status'] === 'active' ? 'Aktif' : 'Dicabut') ?>
                </div>
                <p class="muted" style="font-size:0.86rem;">
                    Hubungan: <?= e($link['relationship_label']) ?> / <?= e($link['caregiver_email']) ?>
                </p>
                <p class="muted" style="font-size:0.84rem;">
                    Tertaut sejak <?= e(format_datetime($link['linked_at'])) ?>
                </p>
                <?php if ($link['status'] === 'active'): ?>
                <form method="post" action="<?= e(url('caregivers/index.php')) ?>" style="margin-top:0.55rem;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke_link">
                    <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                    <button type="submit" class="button button-danger button-sm">Cabut akses</button>
                </form>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php view_end(); ?>
