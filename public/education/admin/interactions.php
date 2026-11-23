<?php

require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

$admin = require_role('admin');
$editId = (int) query_param('edit', '0');
$editing = $editId > 0 ? EducationService::findInteraction($editId) : null;
$errors = [];

$form = [
    'substance_a' => $editing['substance_a'] ?? '',
    'substance_b' => $editing['substance_b'] ?? '',
    'severity_level' => $editing['severity_level'] ?? 'sedang',
    'interaction_effect' => $editing['interaction_effect'] ?? '',
    'recommendation' => $editing['recommendation'] ?? '',
    'is_published' => $editing ? (string) $editing['is_published'] : '1',
];

if (is_post()) {
    require_post();
    $action = input('action', 'save');

    if ($action === 'delete') {
        EducationService::deleteInteraction((int) input('interaction_id', '0'));
        flash('success', 'Data interaksi obat dihapus.');
        redirect('education/admin/interactions.php');
    }

    $interactionId = (int) input('interaction_id', '0');
    $form = [
        'substance_a' => input('substance_a'),
        'substance_b' => input('substance_b'),
        'severity_level' => input('severity_level', 'sedang'),
        'interaction_effect' => input('interaction_effect'),
        'recommendation' => input('recommendation'),
        'is_published' => isset($_POST['is_published']) ? '1' : '0',
    ];

    $errors = validate($form, EducationService::interactionRules(), EducationService::interactionLabels());

    if ($errors === []) {
        EducationService::saveInteraction((int) $admin['id'], $form, $interactionId);
        flash('success', $interactionId > 0 ? 'Data interaksi diperbarui.' : 'Data interaksi obat baru ditambahkan.');
        redirect('education/admin/interactions.php');
    }

    $editId = $interactionId;
}

$interactions = EducationService::listInteractions('', false);
view_start('Kelola interaksi obat', 'admin-interactions');
?>
<div class="page-header">
    <div>
        <h1>Kelola referensi interaksi obat</h1>
        <p class="page-subtitle">Daftar kombinasi obat dengan makanan, minuman, atau obat lain yang ditampilkan di halaman pencarian interaksi pasien.</p>
    </div>
    <div class="page-actions">
        <a class="button button-secondary" href="<?= e(url('education/admin/articles.php')) ?>">Kelola artikel</a>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Daftar interaksi (<?= count($interactions) ?>)</h2>
        <?php if ($interactions === []): ?>
            <?= empty_state('Belum ada data interaksi', 'Tambahkan pasangan obat dan pemicu interaksi pada formulir samping.') ?>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kombinasi</th>
                        <th>Risiko</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($interactions as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['substance_a']) ?></strong> + <?= e($item['substance_b']) ?>
                            <div class="muted" style="font-size:0.84rem;"><?= e($item['recommendation']) ?></div>
                        </td>
                        <td><?= status_chip($item['severity_level'], label('interaction_severity', $item['severity_level'])) ?></td>
                        <td><?= status_chip($item['is_published'] ? 'active' : 'paused', $item['is_published'] ? 'Terbit' : 'Draf') ?></td>
                        <td>
                            <div class="action-row">
                                <a class="button button-ghost button-sm" href="<?= e(url('education/admin/interactions.php', ['edit' => $item['id']])) ?>">Ubah</a>
                                <form method="post" action="<?= e(url('education/admin/interactions.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="interaction_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="button button-danger button-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2><?= $editId > 0 ? 'Ubah interaksi' : 'Tambah interaksi baru' ?></h2>
        <form method="post" action="<?= e(url('education/admin/interactions.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="interaction_id" value="<?= $editId ?>">
            <div class="field">
                <label for="substance_a">Obat atau golongan</label>
                <input id="substance_a" name="substance_a" type="text" value="<?= e($form['substance_a']) ?>" placeholder="Contoh: Tetrasiklin, doksisiklin" required<?= field_attrs($errors, 'substance_a') ?>>
                <?= field_error($errors, 'substance_a') ?>
            </div>
            <div class="field">
                <label for="substance_b">Pemicu interaksi</label>
                <input id="substance_b" name="substance_b" type="text" value="<?= e($form['substance_b']) ?>" placeholder="Contoh: Susu dan produk kalsium" required<?= field_attrs($errors, 'substance_b') ?>>
                <?= field_error($errors, 'substance_b') ?>
            </div>
            <div class="field">
                <label for="severity_level">Tingkat risiko</label>
                <select id="severity_level" name="severity_level" required<?= field_attrs($errors, 'severity_level') ?>>
                    <?= select_options(labels('interaction_severity'), $form['severity_level']) ?>
                </select>
                <?= field_error($errors, 'severity_level') ?>
            </div>
            <div class="field">
                <label for="interaction_effect">Dampak interaksi</label>
                <textarea id="interaction_effect" name="interaction_effect" rows="3" required<?= field_attrs($errors, 'interaction_effect') ?>><?= e($form['interaction_effect']) ?></textarea>
                <?= field_error($errors, 'interaction_effect') ?>
            </div>
            <div class="field">
                <label for="recommendation">Saran jeda atau tindakan</label>
                <textarea id="recommendation" name="recommendation" rows="3" required<?= field_attrs($errors, 'recommendation') ?>><?= e($form['recommendation']) ?></textarea>
                <?= field_error($errors, 'recommendation') ?>
            </div>
            <div class="field">
                <label class="checkbox-row" for="is_published">
                    <input id="is_published" name="is_published" type="checkbox" value="1"<?= checked($form['is_published'] === '1') ?>>
                    <span>Tampilkan di katalog pasien</span>
                </label>
            </div>
            <div class="action-row">
                <button type="submit" class="button button-primary"><?= $editId > 0 ? 'Simpan perubahan' : 'Tambah interaksi' ?></button>
                <?php if ($editId > 0): ?>
                <a class="button button-ghost" href="<?= e(url('education/admin/interactions.php')) ?>">Batal ubah</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>
<?php view_end(); ?>
