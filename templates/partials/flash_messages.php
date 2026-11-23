<?php if (!empty($messages)): ?>
<div class="flash-stack" role="status" aria-live="polite">
    <?php foreach ($messages as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>">
        <span><?= e($flash['message']) ?></span>
        <button type="button" class="flash-close" data-dismiss-flash aria-label="Tutup pesan">Tutup</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
