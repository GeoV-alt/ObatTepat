# ObatTepat

**Sistem Manajemen Jadwal Minum Obat dan Pendampingan Kepatuhan Terapi**  
*Tugas Akhir Praktikum Pemrograman Web 1 | Mendukung SDG 3: Good Health and Well-Being*

ObatTepat adalah aplikasi web untuk membantu pasien rawat jalan menjadwalkan konsumsi obat secara otomatis, mencatat waktu minum obat sebenarnya, memantau sisa stok, melibatkan keluarga atau wali sebagai pendamping jarak jauh, serta menyiapkan ringkasan riwayat terapi siap cetak untuk jadwal kontrol dokter.

---

## Latar Belakang, Permasalahan, dan Tujuan

### Latar Belakang dan Permasalahan
Pasien rawat jalan, khususnya mahasiswa yang tinggal jauh dari orang tua maupun penderita kondisi yang memerlukan obat rutin, sering melewatkan dosis atau berhenti minum obat sebelum waktunya. Resep 2 atau 3 kali sehari kerap hanya diminum sekali karena lupa, jadwal padat, atau tidak tahu pembagian jam yang tepat. Dampaknya antara lain:
- Proses pemulihan melambat dan obat terbuang.
- Risiko resistensi antibiotik meningkat ketika terapi antibiotik tidak dihabiskan sesuai durasi resep.
- Keluarga atau wali kesulitan memantau apakah obat benar-benar sudah diminum tepat waktu.
- Saat kontrol ulang, pasien sering lupa obat apa yang sempat terlewat atau efek samping apa yang muncul pada tanggal tertentu.

### Tujuan Proyek
1. Menyediakan penjadwalan minum obat otomatis berdasarkan frekuensi, jarak jam, dan lama pengobatan.
2. Mencatat kepatuhan konsumsi harian sekaligus mengurangi sisa stok obat secara otomatis dan akurat.
3. Menghubungkan pasien dengan pendamping (keluarga, wali, atau teman kos) untuk pemantauan jarak jauh dan pengiriman pengingat saat dosis terlewat.
4. Menyediakan katalog edukasi obat, jurnal efek samping, serta laporan riwayat terapi siap cetak (A4/PDF) dan ekspor CSV untuk evaluasi dokter.

---

## Keterkaitan dengan SDGs

Proyek ini mengangkat **SDG 3: Good Health and Well-Being (Kehidupan Sehat dan Sejahtera)**, khususnya pada aspek penggunaan obat yang aman, efektif, dan rasional bagi pasien rawat jalan:
- **Pencegahan resistensi antibiotik:** Obat antibiotik ditandai khusus (`Antibiotik wajib habis`), dijadwalkan merata dalam 24 jam (misalnya setiap 8 jam untuk 3 kali sehari), dan terhubung langsung dengan panduan edukasi kewajiban menghabiskan antibiotik.
- **Keselamatan penggunaan obat:** Pasien mencatat riwayat alergi obat, memantau interaksi dasar obat dengan makanan atau minuman (seperti susu, teh, kopi, dan antasida), serta menerima peringatan medis ketika mencatat keluhan berat atau gejala reaksi alergi.
- **Dukungan kepatuhan jangka panjang:** Pemantauan pendamping, peringatan stok menipis (refill alert), dan laporan kontrol medis membantu pasien menjalani pengobatan secara tuntas.

---

## Daftar 9 Modul dan Fitur yang Diimplementasikan

Seluruh 9 modul saling terhubung dalam satu alur data terintegrasi dan dibagi rata untuk 3 anggota tim:

### Anggota 1 (Fondasi & Master Data)
1. **Modul Autentikasi Multi-Role & Profil Kesehatan Dasar** (`public/auth/`)
   - Registrasi akun untuk peran **Pasien** dan **Pendamping**; akun **Admin/Apoteker** dikelola secara internal oleh admin aktif.
   - Login dengan pengalihan otomatis sesuai peran, tombol lihat/sembunyikan kata sandi, ubah kata sandi, proteksi CSRF, session regeneration, dan kontrol akses berbasis peran (RBAC + anti-IDOR).
   - Profil kesehatan dasar pasien: tanggal lahir, jenis kelamin, golongan darah, riwayat alergi obat, kondisi penyerta, kontak darurat, catatan khusus, dan batas toleransi keterlambatan (default 60 menit).
2. **Modul Manajemen Resep & Master Obat Pribadi** (`public/medications/`)
   - Pengelompokan obat berdasarkan kunjungan dokter (**Resep dokter** berstatus aktif/selesai) maupun obat rutin tanpa resep.
   - Input dan ubah data obat lengkap: nama obat (dilengkapi saran nama obat umum), bentuk sediaan (tablet, kapsul, sirup, tetes, salep, inhaler, lainnya), dosis pecahan (`DECIMAL`), aturan makan, penanda antibiotik, frekuensi, interval jam, durasi hari, stok awal, dan ambang peringatan refill.
   - Perubahan status terapi (`aktif`, `dijeda`, `selesai`, `dihentikan`) tanpa menghapus riwayat konsumsi, serta log mutasi stok awal dan riwayat mutasi stok pada halaman detail obat.
3. **Modul Katalog Edukasi Obat & Interaksi Dasar** (`public/education/`)
   - Direktori artikel edukasi 5 kategori (aturan pakai, antibiotik, interaksi obat, penyimpanan, efek samping) dengan pencarian kata kunci dan filter kategori.
   - Tabel pencari interaksi dasar obat dengan makanan, minuman, atau obat lain lengkap dengan tingkat risiko, dampak, dan saran tindakan.
   - Panel CRUD khusus **Admin/Apoteker** untuk mengelola artikel dan data interaksi, serta rekomendasi bacaan otomatis sesuai obat aktif pasien (misalnya panduan antibiotik).

### Anggota 2 (Logika Jadwal & Transaksi Inti)
4. **Modul Generator Jadwal Minum Obat Otomatis** (`public/schedules/`)
   - Membentuk slot jadwal otomatis sebanyak `frekuensi x durasi hari` berbasis `DateTimeImmutable` yang akurat termasuk jadwal lintas tengah malam.
   - Sinkronisasi otomatis jadwal terlambat (`syncOverdueSchedules`) tanpa bergantung pada cron job server lokal.
   - Tampilan timeline harian terkelompok (Pagi, Siang, Sore, Malam) dengan 4 indikator status (**Akan datang**, **Waktunya minum**, **Sudah diminum**, **Terlewat**), navigasi tanggal, filter obat, dan halaman riwayat konsumsi.
5. **Modul Check-In Konsumsi Obat & Pengurangan Stok Otomatis** (`public/api/checkin.php`, `public/assets/js/checkin.js`)
   - Aksi **Sudah minum** / **Minum sekarang** dalam transaksi ACID (`BEGIN ... COMMIT` dengan `SELECT ... FOR UPDATE`) untuk mencatat waktu konsumsi sebenarnya, keterlambatan, memotong `current_stock`, mencatat `stock_logs`, dan mencegah double check-in.
   - Aksi **Lewati** dengan pilihan alasan (tidak mengurangi stok) dan **Batalkan check-in (Undo)** dalam jendela 15 menit yang mengembalikan stok secara transaksional.
6. **Modul Peringatan Stok Menipis (Refill Alert)** (`public/refills/`, `public/api/refill.php`)
   - Menghitung estimasi hari habis berdasarkan konsumsi harian dan sisa kebutuhan jadwal mendatang (mencegah false alarm jika stok memang cukup hingga akhir terapi).
   - Menampilkan peringatan tingkat **Stok menipis** (`warning`) dan **Stok kritis** (`critical`) di dashboard, sidebar, halaman inventaris, serta tampilan pendamping.
   - Aksi **Catat tebus ulang (Refill)** yang menambah stok, mencatat log mutasi, dan otomatis menyelesaikan peringatan aktif.

### Anggota 3 (Interaksi Pengguna & Laporan)
7. **Modul Tautan Pendamping (Caregiver Monitoring) & Konfirmasi** (`public/caregivers/`, `public/api/reminder.php`)
   - Pembuatan kode undangan unik 8 karakter (`OBT-XXXX`) berlaku 24 jam dan sekali pakai, serta pencabutan akses kapan saja oleh pasien.
   - Halaman pemantauan pendamping yang menampilkan profil darurat, jadwal harian, skor kepatuhan 7 hari, peringatan stok, dan keluhan terbaru secara read-only.
   - Tombol **Ingatkan sekarang** (aktif saat jadwal terlewat atau mendekati akhir toleransi, dengan jeda anti-spam 30 menit), dilengkapi tautan opsional WhatsApp (`wa.me`), serta tombol **Konfirmasi sudah minum** di akun pasien yang terhubung langsung ke Modul 4.
8. **Modul Jurnal Efek Samping & Kondisi Harian** (`public/symptoms/`, `public/api/patient_meds_by_date.php`)
   - Pencatatan keluhan dengan pilihan cepat gejala umum, tingkat keparahan (`ringan`, `sedang`, `berat`), dan kondisi harian.
   - Memuat otomatis daftar obat dan jadwal pada tanggal terpilih lewat endpoint AJAX agar keluhan tertaut langsung ke konsumsi obat hari itu.
   - Menampilkan kotak peringatan medis untuk keluhan berat atau indikasi alergi (seperti ruam, bengkak, atau sesak napas).
9. **Modul Dashboard Kepatuhan (Adherence Score) & Laporan Kontrol Medis** (`public/reports/`, `public/api/adherence_stats.php`)
   - Menghitung **Adherence Score** dan persentase ketepatan waktu dari SQL View `v_patient_adherence_daily` untuk periode 7, 14, 30 hari, atau rentang kustom (hanya menghitung jadwal jatuh tempo).
   - Grafik kepatuhan harian berbasis SVG lokal (bekerja offline, interaktif lewat hover, klik, dan keyboard) serta rincian kepatuhan per obat.
   - Halaman **Laporan kontrol medis** siap cetak ke PDF ukuran A4 (`@media print`) yang menyatukan profil alergi, skor kepatuhan, daftar obat, efek samping, dan riwayat check-in, ditambah fitur **Unduh CSV**.

---

## Teknologi yang Digunakan

| Komponen | Teknologi |
| :--- | :--- |
| **Backend** | PHP 8.1+ (Native Modular + Service Layer, PDO Prepared Statements) |
| **Database** | MySQL 8.0+ / MariaDB 10.4+ (Engine InnoDB, 13 tabel ternormalisasi 3NF + 1 SQL View) |
| **Frontend** | HTML5 Semantik, CSS3 (Tema Terang & Gelap, Responsif 3 Breakpoint, `@media print` A4) |
| **Interaktivitas** | Vanilla JavaScript (ES6+ Fetch API, Grafik SVG lokal tanpa ketergantungan CDN) |
| **Server Lokal** | XAMPP (Apache / PHP Built-in Server + MariaDB) |

---

## Struktur Direktori

```text
obattepat/
├── config/          # Konfigurasi aplikasi (timezone Asia/Jakarta) & koneksi database
├── core/            # Bootstrap, helper PDO transaksi, auth/RBAC, CSRF, flash, validator, view
├── database/        # schema.sql (13 tabel + 1 view) dan seed.sql (data demo lengkap)
├── services/        # 9 class logika bisnis (Auth, Medication, Education, Schedule, CheckIn, Refill, Caregiver, Symptom, Adherence)
├── templates/       # Layout header, sidebar, footer, halaman error, dan partial komponen
├── public/          # Document root web server (halaman 9 modul, endpoint api/, aset CSS/JS)
└── README.md
```

---

## Panduan Instalasi dan Menjalankan Aplikasi di Lokal

### Prasyarat
- **XAMPP** (PHP 8.1 atau lebih baru dengan ekstensi `pdo_mysql` dan `mbstring`, serta MariaDB 10.4+ / MySQL 8.0+).
- Browser modern (Chrome, Edge, atau Firefox).

### Langkah 1: Unduh Repositori
```bash
git clone https://github.com/GeoV-alt/ObatTepat.git
cd ObatTepat
```
*(Atau salin folder `obattepat` langsung ke `C:\xampp\htdocs\obattepat` jika ingin menjalankan lewat Apache XAMPP.)*

### Langkah 2: Konfigurasi dan Impor Database
1. Buka **XAMPP Control Panel**, lalu jalankan **MySQL**.
2. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`), masuk ke menu **Import**, lalu jalankan berurutan:
   - `database/schema.sql` (membuat database `obattepat_db`, 13 tabel, dan view `v_patient_adherence_daily`).
   - `database/seed.sql` (mengisi akun demo, resep, obat, jadwal, tautan pendamping, jurnal efek samping, artikel edukasi, dan daftar interaksi).
3. Jika kredensial MySQL lokal Anda berbeda dari bawaan XAMPP (`root` tanpa kata sandi, host `127.0.0.1`, port `3306`), sesuaikan pada file `config/database.php` atau gunakan environment variable `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

### Langkah 3: Jalankan Aplikasi
Pilih salah satu cara berikut:

- **Opsi A (Disarankan untuk Demo - Apache XAMPP):**
  1. Pastikan folder proyek berada di `C:\xampp\htdocs\obattepat`.
  2. Nyalakan **Apache** di XAMPP Control Panel.
  3. Buka browser dan akses: `http://localhost/obattepat/public/`

- **Opsi B (Cepat - PHP Built-in Server):**
  ```bash
  php -S 127.0.0.1:8080 -t public
  ```
  Lalu buka browser dan akses: `http://127.0.0.1:8080`

---

## Akun Demo (`database/seed.sql`)

Kata sandi untuk seluruh akun demo: `obattepat123`

| Peran | Email | Kegunaan Demo |
| :--- | :--- | :--- |
| **Pasien (Utama)** | `pasien@obattepat.test` | Memiliki resep, obat aktif termasuk antibiotik, riwayat 7 hari, dan catatan efek samping |
| **Pasien (Kosong)** | `pasien2@obattepat.test` | Untuk menguji tampilan awal kosong (empty state) dan pendaftaran obat baru |
| **Pendamping** | `pendamping@obattepat.test` | Sudah tertaut ke pasien demo untuk pemantauan jadwal dan pengiriman pengingat |
| **Admin / Apoteker** | `apoteker@obattepat.test` | Mengelola artikel edukasi obat, interaksi dasar, dan akun apoteker |

---

## Tim Pengembang

| Anggota | Nama & NIM | Tanggung Jawab Modul |
| :--- | :--- | :--- |
| **Anggota 1** | **Geovanie Fahmela** (`H1D025172`) | Modul 1 (Auth & Profil), Modul 2 (Resep & Master Obat), Modul 8 (Katalog Edukasi & Interaksi) |
| **Anggota 2** | **Rizky Marseta Rachman** (`H1D025174`) | Modul 3 (Generator Jadwal), Modul 4 (Check-In & Potong Stok), Modul 5 (Refill Alert) |
| **Anggota 3** | **Hanip Mulyadi** (`H1D025175`) | Modul 6 (Tautan Pendamping), Modul 7 (Jurnal Efek Samping), Modul 9 (Dashboard & Laporan Kontrol) |

---

## Catatan

Aplikasi ini dibuat untuk mendukung pencatatan dan kepatuhan konsumsi obat secara mandiri, dan tidak menggantikan diagnosis maupun anjuran dokter atau apoteker.
