<?php

require_once dirname(__DIR__) . '/core/bootstrap.php';

$user = current_user();
if ($user !== null) {
    redirect(home_path_for($user['role']));
}

$sampleArticles = EducationService::listArticles('', '', true);
$sampleArticles = array_slice($sampleArticles, 0, 3);

view_start('Jadwal minum obat dan pendampingan terapi', null, ['guest' => true]);
?>
<section class="landing-hero">
    <div>
        <h1>Catat jadwal minum obat, pantau sisa stok, dan libatkan keluarga.</h1>
        <p class="landing-lead">ObatTepat menyusun jadwal minum obat harian dari aturan pakai resep Anda, mencatat waktu konsumsi sebenarnya, memperingatkan stok yang menipis, dan menyiapkan ringkasan riwayat untuk jadwal kontrol dokter.</p>
        <div class="action-row">
            <a class="button button-primary" href="<?= e(url('auth/register.php')) ?>">Buat akun pasien atau pendamping</a>
            <a class="button button-ghost" href="<?= e(url('auth/login.php')) ?>">Masuk dengan akun demo</a>
        </div>
    </div>
    <aside class="landing-sheet" aria-label="Contoh ringkasan jadwal obat">
        <h2>Alur pencatatan harian</h2>
        <p class="muted">Contoh tampilan urutan jadwal setelah obat dimasukkan ke aplikasi.</p>
        <div class="landing-sheet-row">
            <div>
                <strong>07:00</strong>
                <div class="muted">Amoxicillin 500 mg, 1 kapsul sesudah makan</div>
            </div>
            <span class="chip chip-taken">Sudah diminum</span>
        </div>
        <div class="landing-sheet-row">
            <div>
                <strong>15:00</strong>
                <div class="muted">Amoxicillin 500 mg, 1 kapsul sesudah makan</div>
            </div>
            <span class="chip chip-due">Waktunya minum</span>
        </div>
        <div class="landing-sheet-row">
            <div>
                <strong>21:00</strong>
                <div class="muted">Cetirizine 10 mg, 1 tablet</div>
            </div>
            <span class="chip chip-upcoming">Akan datang</span>
        </div>
    </aside>
</section>

<section class="landing-section">
    <h2>Tiga peran dalam satu catatan terapi</h2>
    <div class="three-col">
        <div class="panel">
            <h3>Pasien rawat jalan</h3>
            <p class="muted">Masukkan obat dan aturan pakai sekali saja. Jadwal harian terbentuk otomatis, stok berkurang setiap kali check-in, dan keluhan efek samping tercatat bersama obat yang diminum hari itu.</p>
        </div>
        <div class="panel">
            <h3>Pendamping atau keluarga</h3>
            <p class="muted">Hubungkan akun lewat kode undangan 24 jam dari pasien. Pantau jadwal hari ini dan kirim pengingat saat dosis terlewat melewati batas toleransi.</p>
        </div>
        <div class="panel">
            <h3>Admin atau apoteker</h3>
            <p class="muted">Kelola artikel aturan pakai obat, panduan menghabiskan antibiotik, dan daftar interaksi dasar obat dengan makanan atau minuman.</p>
        </div>
    </div>
</section>

<?php if ($sampleArticles !== []): ?>
<section class="landing-section">
    <div class="panel-head">
        <div>
            <h2>Bacaan edukasi obat</h2>
            <p class="muted">Referensi ringkas aturan pakai dan interaksi dasar yang dikelola apoteker.</p>
        </div>
        <a class="button button-ghost button-sm" href="<?= e(url('education/index.php')) ?>">Buka semua bacaan</a>
    </div>
    <div class="three-col">
        <?php foreach ($sampleArticles as $article): ?>
        <article class="panel">
            <span class="chip chip-upcoming"><?= e(label('article_category', $article['category'])) ?></span>
            <h3 style="margin-top:0.55rem;"><a href="<?= e(url('education/detail.php', ['slug' => $article['slug']])) ?>"><?= e($article['title']) ?></a></h3>
            <p class="muted"><?= e($article['summary']) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php view_end(); ?>
