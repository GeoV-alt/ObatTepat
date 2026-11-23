<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$slug = query_param('slug', '');
$user = current_user();
$isAdmin = $user !== null && $user['role'] === 'admin';
$article = $slug !== '' ? EducationService::findArticleBySlug($slug, !$isAdmin) : null;

if ($article === null) {
    abort(404, 'Artikel edukasi yang Anda cari tidak ditemukan atau belum diterbitkan.');
}

$paragraphs = preg_split("/\r?\n\r?\n/", trim((string) $article['content'])) ?: [];

view_start($article['title'], 'education', ['guest' => $user === null]);
?>
<div class="page-header">
    <div>
        <span class="chip chip-upcoming" style="margin-bottom:0.5rem;"><?= e(label('article_category', $article['category'])) ?></span>
        <h1><?= e($article['title']) ?></h1>
        <p class="page-subtitle">Ditulis oleh <?= e($article['author_name']) ?><?= $article['published_at'] ? ', diterbitkan ' . e(format_date_id($article['published_at'], false)) : '' ?>.</p>
    </div>
    <div class="page-actions">
        <a class="button button-ghost" href="<?= e(url('education/index.php')) ?>">Kembali ke katalog</a>
    </div>
</div>

<article class="panel panel-ledger article-content">
    <?php if (!empty($article['warning_highlight'])): ?>
    <div class="notice-box notice-warning">
        <p class="notice-title">Penting diingat</p>
        <p><?= e($article['warning_highlight']) ?></p>
    </div>
    <?php endif; ?>

    <p><strong><?= e($article['summary']) ?></strong></p>

    <?php foreach ($paragraphs as $paragraph): ?>
        <?php if (trim($paragraph) !== ''): ?>
        <p><?= nl2br(e(trim($paragraph))) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!empty($article['tags'])): ?>
    <p class="muted" style="margin-top:1.25rem;font-size:0.86rem;">Kata kunci: <?= e($article['tags']) ?></p>
    <?php endif; ?>
</article>
<?php view_end(); ?>
