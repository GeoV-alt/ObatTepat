<?php if ($isGuest): ?>
</main>
<footer class="guest-footer">
    <div class="guest-footer-inner">
        <p><?= e(config('name')) ?>: pencatatan jadwal minum obat, stok, dan pendampingan terapi.</p>
        <p class="muted">Informasi di aplikasi ini bersifat edukatif dan tidak menggantikan pemeriksaan dokter atau apoteker.</p>
    </div>
</footer>
<?php else: ?>
        </main>
    </div>
</div>
<?php endif; ?>
<div class="toast-region" aria-live="polite" aria-atomic="true" data-toast-region></div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
