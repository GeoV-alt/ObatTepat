<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$category = query_param('category', '');
$search = query_param('q', '');
$user = current_user();

$articles = EducationService::listArticles($category, $search, true);
$interactions = EducationService::listInteractions($search, true);

view_start('Katalog edukasi obat dan interaksi dasar', 'education', ['guest' => $user === null]);
?>
<div class="page-header">
    <div>
        <h1>Katalog edukasi obat dan interaksi dasar</h1>
        <p class="page-subtitle">Panduan praktis aturan pakai obat, kewajiban menghabiskan antibiotik, dan daftar interaksi obat dengan makanan atau minuman sehari-hari.</p>
    </div>
    <?php if ($user !== null && $user['role'] === 'admin'): ?>
    <div class="page-actions">
        <a class="button button-primary" href="<?= e(url('education/admin/articles.php')) ?>">Kelola artikel</a>
        <a class="button button-secondary" href="<?= e(url('education/admin/interactions.php')) ?>">Kelola interaksi</a>
    </div>
    <?php endif; ?>
</div>

<form method="get" action="<?= e(url('education/index.php')) ?>" class="panel filter-bar">
    <div class="field">
        <label for="q">Cari topik, nama obat, atau minuman</label>
        <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Contoh: antibiotik, susu, kopi, paracetamol">
    </div>
    <div class="field">
        <label for="category">Kategori artikel</label>
        <select id="category" name="category">
            <?= select_options(labels('article_category'), $category, 'Semua kategori') ?>
        </select>
    </div>
    <button type="submit" class="button button-primary">Cari</button>
    <?php if ($category !== '' || $search !== ''): ?>
    <a class="button button-ghost" href="<?= e(url('education/index.php')) ?>">Reset</a>
    <?php endif; ?>
</form>

<section class="panel panel-ledger">
    <h2>Artikel panduan obat (<?= count($articles) ?>)</h2>
    <?php if ($articles === []): ?>
        <?= empty_state('Tidak ada artikel yang cocok', 'Coba gunakan kata kunci lebih umum atau pilih semua kategori.') ?>
    <?php else: ?>
    <div class="two-col">
        <?php foreach ($articles as $article): ?>
        <article class="stack-item">
            <span class="chip chip-upcoming"><?= e(label('article_category', $article['category'])) ?></span>
            <h3 style="margin-top:0.5rem;"><a href="<?= e(url('education/detail.php', ['slug' => $article['slug']])) ?>"><?= e($article['title']) ?></a></h3>
            <p class="muted" style="font-size:0.9rem;"><?= e($article['summary']) ?></p>
            <?php if (!empty($article['warning_highlight'])): ?>
            <p style="font-size:0.86rem;"><strong>Penting:</strong> <?= e($article['warning_highlight']) ?></p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Referensi interaksi dasar obat (<?= count($interactions) ?>)</h2>
    <p class="muted">Periksa kombinasi obat dengan makanan, minuman, atau obat bebas sebelum mengonsumsi secara bersamaan.</p>
    <?php if ($interactions === []): ?>
        <?= empty_state('Tidak ada data interaksi untuk kata kunci ini', 'Coba cari kata kunci lain seperti susu, kopi, alkohol, atau antasida.') ?>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Obat / Golongan</th>
                    <th>Pemicu interaksi</th>
                    <th>Risiko</th>
                    <th>Dampak</th>
                    <th>Saran tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interactions as $item): ?>
                <tr>
                    <td><strong><?= e($item['substance_a']) ?></strong></td>
                    <td><?= e($item['substance_b']) ?></td>
                    <td><?= status_chip($item['severity_level'], label('interaction_severity', $item['severity_level'])) ?></td>
                    <td><?= e($item['interaction_effect']) ?></td>
                    <td><?= e($item['recommendation']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php view_end(); ?>
