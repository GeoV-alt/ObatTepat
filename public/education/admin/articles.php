<?php

require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

$admin = require_role('admin');
$editId = (int) query_param('edit', '0');
$editing = $editId > 0 ? EducationService::findArticle($editId) : null;
$errors = [];

$form = [
    'title' => $editing['title'] ?? '',
    'category' => $editing['category'] ?? 'aturan_pakai',
    'summary' => $editing['summary'] ?? '',
    'content' => $editing['content'] ?? '',
    'warning_highlight' => $editing['warning_highlight'] ?? '',
    'tags' => $editing['tags'] ?? '',
    'is_published' => $editing ? (string) $editing['is_published'] : '1',
];

if (is_post()) {
    require_post();
    $action = input('action', 'save');

    if ($action === 'delete') {
        EducationService::deleteArticle((int) input('article_id', '0'));
        flash('success', 'Artikel edukasi dihapus.');
        redirect('education/admin/articles.php');
    }

    $articleId = (int) input('article_id', '0');
    $form = [
        'title' => input('title'),
        'category' => input('category', 'aturan_pakai'),
        'summary' => input('summary'),
        'content' => input('content'),
        'warning_highlight' => input('warning_highlight'),
        'tags' => input('tags'),
        'is_published' => isset($_POST['is_published']) ? '1' : '0',
    ];

    $errors = validate($form, EducationService::articleRules(), EducationService::articleLabels());

    if ($errors === []) {
        EducationService::saveArticle((int) $admin['id'], $form, $articleId);
        flash('success', $articleId > 0 ? 'Artikel edukasi diperbarui.' : 'Artikel edukasi baru berhasil disimpan.');
        redirect('education/admin/articles.php');
    }

    $editId = $articleId;
}

$articles = EducationService::listArticles('', '', false);
view_start('Kelola artikel edukasi', 'admin-articles');
?>
<div class="page-header">
    <div>
        <h1>Kelola artikel edukasi obat</h1>
        <p class="page-subtitle">Panel Admin dan Apoteker untuk menambah, memperbarui, atau mengatur status terbit artikel referensi pasien.</p>
    </div>
    <div class="page-actions">
        <a class="button button-secondary" href="<?= e(url('education/admin/interactions.php')) ?>">Kelola interaksi obat</a>
        <a class="button button-ghost" href="<?= e(url('education/index.php')) ?>">Lihat tampilan pasien</a>
    </div>
</div>

<div class="split-grid">
    <section class="panel panel-ledger">
        <h2>Daftar artikel (<?= count($articles) ?>)</h2>
        <?php if ($articles === []): ?>
            <?= empty_state('Belum ada artikel edukasi', 'Tulis artikel panduan obat pertama di formulir samping.') ?>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Judul & kategori</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('education/detail.php', ['slug' => $article['slug']])) ?>"><strong><?= e($article['title']) ?></strong></a>
                            <div class="muted" style="font-size:0.84rem;"><?= e(label('article_category', $article['category'])) ?></div>
                        </td>
                        <td><?= status_chip($article['is_published'] ? 'active' : 'paused', $article['is_published'] ? 'Terbit' : 'Draf') ?></td>
                        <td>
                            <div class="action-row">
                                <a class="button button-ghost button-sm" href="<?= e(url('education/admin/articles.php', ['edit' => $article['id']])) ?>">Ubah</a>
                                <form method="post" action="<?= e(url('education/admin/articles.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
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
        <h2><?= $editId > 0 ? 'Ubah artikel' : 'Tulis artikel baru' ?></h2>
        <form method="post" action="<?= e(url('education/admin/articles.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="article_id" value="<?= $editId ?>">
            <div class="field">
                <label for="title">Judul artikel</label>
                <input id="title" name="title" type="text" value="<?= e($form['title']) ?>" required<?= field_attrs($errors, 'title') ?>>
                <?= field_error($errors, 'title') ?>
            </div>
            <div class="field">
                <label for="category">Kategori</label>
                <select id="category" name="category" required<?= field_attrs($errors, 'category') ?>>
                    <?= select_options(labels('article_category'), $form['category']) ?>
                </select>
                <?= field_error($errors, 'category') ?>
            </div>
            <div class="field">
                <label for="summary">Ringkasan singkat</label>
                <textarea id="summary" name="summary" rows="2" required<?= field_attrs($errors, 'summary') ?>><?= e($form['summary']) ?></textarea>
                <?= field_error($errors, 'summary') ?>
            </div>
            <div class="field">
                <label for="warning_highlight">Poin peringatan penting (opsional)</label>
                <input id="warning_highlight" name="warning_highlight" type="text" value="<?= e($form['warning_highlight']) ?>" placeholder="Contoh: Jangan berhenti minum antibiotik tanpa persetujuan dokter.">
            </div>
            <div class="field">
                <label for="content">Isi artikel</label>
                <textarea id="content" name="content" rows="7" required<?= field_attrs($errors, 'content') ?>><?= e($form['content']) ?></textarea>
                <p class="field-hint">Pisahkan paragraf dengan satu baris kosong.</p>
                <?= field_error($errors, 'content') ?>
            </div>
            <div class="field">
                <label for="tags">Kata kunci (pisah dengan koma)</label>
                <input id="tags" name="tags" type="text" value="<?= e($form['tags']) ?>" placeholder="antibiotik,susu,kopi">
            </div>
            <div class="field">
                <label class="checkbox-row" for="is_published">
                    <input id="is_published" name="is_published" type="checkbox" value="1"<?= checked($form['is_published'] === '1') ?>>
                    <span>Terbitkan artikel agar tampil di katalog pasien</span>
                </label>
            </div>
            <div class="action-row">
                <button type="submit" class="button button-primary"><?= $editId > 0 ? 'Simpan perubahan' : 'Simpan artikel' ?></button>
                <?php if ($editId > 0): ?>
                <a class="button button-ghost" href="<?= e(url('education/admin/articles.php')) ?>">Batal ubah</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>
<?php view_end(); ?>
