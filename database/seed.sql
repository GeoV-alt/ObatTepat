USE obattepat_db;

SET @password = '$2y$10$J3UDuyd6IdVXjntiU4Pi8ePQZG3qxScR6MnYZAJBoamAm40xCxws.';

INSERT INTO users (full_name, email, password_hash, phone, role) VALUES
('Apoteker Demo', 'apoteker@obattepat.test', @password, NULL, 'admin'),
('Pasien Demo', 'pasien@obattepat.test', @password, '6281200000001', 'patient'),
('Pasien Demo Dua', 'pasien2@obattepat.test', @password, NULL, 'patient'),
('Pendamping Demo', 'pendamping@obattepat.test', @password, '6281200000002', 'caregiver');

SET @admin = (SELECT id FROM users WHERE email = 'apoteker@obattepat.test');
SET @patient = (SELECT id FROM users WHERE email = 'pasien@obattepat.test');
SET @patient_two = (SELECT id FROM users WHERE email = 'pasien2@obattepat.test');
SET @caregiver = (SELECT id FROM users WHERE email = 'pendamping@obattepat.test');

INSERT INTO patient_profiles (user_id, date_of_birth, gender, blood_type, drug_allergies, chronic_conditions, special_notes, emergency_contact_name, emergency_contact_phone, default_tolerance_minutes) VALUES
(@patient, '2004-05-12', 'P', 'O', 'Penisilin G (ruam kulit)', 'Rinitis alergi', 'Data contoh untuk demo. Punya riwayat maag.', 'Pendamping Demo', '6281200000002', 60),
(@patient_two, NULL, NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, 60);

INSERT INTO prescriptions (patient_id, title, doctor_name, facility_name, prescribed_date, notes, status) VALUES
(@patient, 'Resep radang tenggorokan', NULL, NULL, CURDATE() - INTERVAL 4 DAY, 'Data contoh untuk demo.', 'active'),
(@patient, 'Resep demam minggu lalu', NULL, NULL, CURDATE() - INTERVAL 12 DAY, 'Data contoh untuk demo.', 'completed');

SET @rx_throat = (SELECT id FROM prescriptions WHERE patient_id = @patient AND title = 'Resep radang tenggorokan');
SET @rx_fever = (SELECT id FROM prescriptions WHERE patient_id = @patient AND title = 'Resep demam minggu lalu');

INSERT INTO medications (patient_id, prescription_id, name, dosage_form, dose_amount, dose_unit, meal_rule, is_antibiotic, frequency_per_day, interval_hours, duration_days, start_date, first_dose_time, initial_stock, current_stock, refill_threshold_days, instructions, status) VALUES
(@patient, @rx_throat, 'Amoxicillin 500 mg', 'kapsul', 1, 'kapsul', 'sesudah_makan', 1, 3, 8, 7, CURDATE() - INTERVAL 4 DAY, '07:00:00', 15, 15, 2, 'Habiskan sesuai resep.', 'active'),
(@patient, @rx_throat, 'Omeprazole 20 mg', 'kapsul', 1, 'kapsul', 'sebelum_makan', 0, 1, 24, 14, CURDATE() - INTERVAL 6 DAY, '06:30:00', 14, 14, 2, 'Diminum pagi saat perut kosong.', 'active'),
(@patient, NULL, 'Cetirizine 10 mg', 'tablet', 1, 'tablet', 'bebas', 0, 1, 24, 10, CURDATE() - INTERVAL 5 DAY, '21:00:00', 10, 10, 2, 'Diminum malam hari.', 'active'),
(@patient, @rx_fever, 'Paracetamol 500 mg', 'tablet', 1, 'tablet', 'sesudah_makan', 0, 3, 8, 3, CURDATE() - INTERVAL 12 DAY, '07:00:00', 10, 10, 1, 'Diminum jika demam.', 'completed');

INSERT INTO medication_schedules (medication_id, patient_id, scheduled_at, planned_dose, status, taken_at, actual_dose, delay_minutes, is_late)
SELECT
    x.medication_id,
    x.patient_id,
    x.slot,
    x.dose,
    CASE WHEN x.slot >= CURDATE() THEN 'pending' WHEN x.is_missed = 1 THEN 'missed' ELSE 'taken' END,
    CASE WHEN x.slot >= CURDATE() OR x.is_missed = 1 THEN NULL ELSE x.slot + INTERVAL x.delay MINUTE END,
    CASE WHEN x.slot >= CURDATE() OR x.is_missed = 1 THEN NULL ELSE x.dose END,
    CASE WHEN x.slot >= CURDATE() OR x.is_missed = 1 THEN NULL ELSE x.delay END,
    CASE WHEN x.slot < CURDATE() AND x.is_missed = 0 AND x.delay > 60 THEN 1 ELSE 0 END
FROM (
    SELECT
        m.id AS medication_id,
        m.patient_id,
        m.dose_amount AS dose,
        TIMESTAMP(m.start_date, m.first_dose_time) + INTERVAL (d.n * 24 + s.k * m.interval_hours) HOUR AS slot,
        MOD(d.n * 17 + s.k * 29 + m.id * 7, 80) AS delay,
        CASE WHEN MOD(d.n * 3 + s.k + m.id, 7) = 5 THEN 1 ELSE 0 END AS is_missed
    FROM medications m
    JOIN (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
        UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13
    ) d ON d.n < m.duration_days
    JOIN (
        SELECT 0 AS k UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
    ) s ON s.k < m.frequency_per_day
    WHERE m.patient_id = @patient
) x;

UPDATE medications m
SET m.current_stock = GREATEST(0, m.initial_stock - COALESCE((
    SELECT SUM(s.actual_dose) FROM medication_schedules s WHERE s.medication_id = m.id AND s.status = 'taken'
), 0))
WHERE m.patient_id = @patient;

INSERT INTO stock_logs (medication_id, schedule_id, change_type, quantity_change, stock_after, notes, created_by, created_at)
SELECT id, NULL, 'initial', initial_stock, initial_stock, 'Stok awal saat obat dicatat', patient_id, TIMESTAMP(start_date, '06:00:00')
FROM medications
WHERE patient_id = @patient;

INSERT INTO stock_logs (medication_id, schedule_id, change_type, quantity_change, stock_after, notes, created_by, created_at)
SELECT
    s.medication_id,
    s.id,
    'consumption',
    -s.actual_dose,
    m.initial_stock - SUM(s.actual_dose) OVER (PARTITION BY s.medication_id ORDER BY s.scheduled_at),
    'Check-in dosis terjadwal',
    s.patient_id,
    s.taken_at
FROM medication_schedules s
JOIN medications m ON m.id = s.medication_id
WHERE s.patient_id = @patient AND s.status = 'taken';

SET @med_amox = (SELECT id FROM medications WHERE patient_id = @patient AND name = 'Amoxicillin 500 mg');
SET @med_cet = (SELECT id FROM medications WHERE patient_id = @patient AND name = 'Cetirizine 10 mg');

INSERT INTO caregiver_links (patient_id, caregiver_id, invitation_id, relationship_label, status) VALUES
(@patient, @caregiver, NULL, 'Orang tua', 'active'),
(@patient_two, @caregiver, NULL, 'Teman kos', 'active');

INSERT INTO symptom_logs (patient_id, medication_id, schedule_id, log_date, logged_time, daily_condition, symptom_name, severity, notes) VALUES
(@patient, @med_cet, (SELECT id FROM medication_schedules WHERE medication_id = @med_cet AND DATE(scheduled_at) = CURDATE() - INTERVAL 3 DAY LIMIT 1), CURDATE() - INTERVAL 3 DAY, '22:00:00', 'stabil', 'Mengantuk', 'ringan', 'Mengantuk sekitar satu jam setelah minum obat. Data contoh untuk demo.'),
(@patient, @med_amox, (SELECT id FROM medication_schedules WHERE medication_id = @med_amox AND DATE(scheduled_at) = CURDATE() - INTERVAL 2 DAY ORDER BY scheduled_at LIMIT 1), CURDATE() - INTERVAL 2 DAY, '08:15:00', 'membaik', 'Mual', 'ringan', 'Sedikit mual karena minum sebelum sarapan. Data contoh untuk demo.');

INSERT INTO education_articles (author_id, title, slug, category, summary, content, warning_highlight, tags, is_published, published_at) VALUES
(@admin, 'Mengapa antibiotik harus diminum sesuai durasi resep', 'antibiotik-sesuai-durasi-resep', 'antibiotik',
 'Berhenti minum antibiotik lebih awal bisa membuat infeksi kambuh dan mendorong bakteri menjadi kebal obat.',
 'Antibiotik bekerja dengan membunuh atau menghambat pertumbuhan bakteri. Rasa badan yang membaik setelah dua atau tiga hari belum tentu berarti semua bakteri sudah hilang.\n\nJika antibiotik dihentikan sebelum waktunya, bakteri yang tersisa bisa tumbuh lagi sehingga infeksi kambuh. Bakteri yang bertahan juga berpeluang menjadi kebal terhadap antibiotik tersebut. Kondisi ini disebut resistensi antimikroba.\n\nMinum antibiotik dengan jarak waktu yang teratur. Aturan 3 x sehari berarti kira-kira setiap 8 jam, bukan hanya pagi, siang, dan sore. Jarak yang teratur menjaga kadar obat di dalam tubuh tetap cukup.\n\nJika satu dosis terlewat, minum segera saat ingat. Jika sudah dekat dengan jadwal berikutnya, lewati dosis yang terlupa dan lanjutkan sesuai jadwal. Jangan menggandakan dosis untuk mengejar yang terlewat.\n\nJangan menyimpan sisa antibiotik untuk dipakai sendiri di lain waktu dan jangan memberikannya kepada orang lain. Antibiotik juga tidak menyembuhkan flu atau batuk pilek yang disebabkan virus.',
 'Jangan berhenti minum antibiotik tanpa persetujuan dokter, meskipun badan sudah terasa lebih baik.',
 'antibiotik,amoxicillin,resistensi,dosis terlewat', 1, NOW()),
(@admin, 'Arti aturan minum sebelum, sesudah, dan bersama makan', 'arti-aturan-minum-dan-makan', 'aturan_pakai',
 'Aturan makan pada etiket obat menentukan seberapa baik obat diserap dan seberapa nyaman untuk lambung.',
 'Sebelum makan umumnya berarti obat diminum saat perut kosong, yaitu sekitar 30 sampai 60 menit sebelum makan. Contohnya beberapa obat lambung seperti omeprazole.\n\nSesudah makan berarti obat diminum segera setelah selesai makan. Aturan ini biasanya dipakai untuk obat yang bisa mengiritasi lambung.\n\nBersama makan berarti obat diminum di tengah atau tepat saat makan.\n\nAturan 2 x sehari berarti kira-kira setiap 12 jam, sedangkan 3 x sehari berarti kira-kira setiap 8 jam. ObatTepat membuat jadwal berdasarkan jarak jam ini.\n\nJika etiket hanya menulis aturan pakai tanpa keterangan makan, tanyakan kepada apoteker saat menebus obat.',
 'Aturan 3 x sehari berarti dibagi rata dalam 24 jam, kira-kira setiap 8 jam.',
 'aturan pakai,sebelum makan,sesudah makan,omeprazole', 1, NOW()),
(@admin, 'Jeda minum obat dengan susu, teh, dan kopi', 'jeda-obat-susu-teh-kopi', 'interaksi_obat',
 'Beberapa minuman sehari-hari bisa mengurangi penyerapan obat atau menambah efek sampingnya.',
 'Kalsium dalam susu dan produk olahannya dapat mengikat beberapa antibiotik, misalnya tetrasiklin, doksisiklin, dan siprofloksasin. Akibatnya obat yang diserap tubuh menjadi lebih sedikit. Beri jeda antara minum obat dan minum susu sesuai anjuran apoteker.\n\nTanin pada teh dan kopi dapat menghambat penyerapan suplemen zat besi. Minum tablet tambah darah dengan air putih dan beri jeda dari teh atau kopi.\n\nKafein pada kopi bisa menambah rasa berdebar dan sulit tidur jika diminum bersamaan dengan obat flu yang mengandung pseudoefedrin atau kafein.\n\nPilihan paling aman untuk menelan obat adalah air putih.',
 'Gunakan air putih untuk minum obat, kecuali dokter atau apoteker menyarankan lain.',
 'susu,teh,kopi,tetrasiklin,siprofloksasin,zat besi', 1, NOW()),
(@admin, 'Cara menyimpan obat di kamar kos', 'menyimpan-obat-di-kamar-kos', 'penyimpanan',
 'Panas, lembap, dan sinar matahari bisa merusak obat sebelum tanggal kedaluwarsanya.',
 'Simpan obat di tempat yang kering, sejuk, dan tidak terkena sinar matahari langsung. Hindari kamar mandi, dekat kompor, atau jok motor.\n\nSimpan obat di kemasan aslinya bersama etiket dari apotek agar aturan pakai tetap terbaca.\n\nSirup antibiotik kering yang sudah dicampur air punya batas waktu pemakaian. Ikuti batas waktu yang tertulis di etiket atau tanyakan kepada apoteker.\n\nPeriksa tanggal kedaluwarsa secara berkala. Obat yang berubah warna, bau, atau bentuk sebaiknya tidak diminum.',
 'Jangan menyimpan obat di jok motor atau tempat yang panas.',
 'penyimpanan,sirup,suhu,kedaluwarsa', 1, NOW()),
(@admin, 'Mengantuk setelah minum obat alergi', 'mengantuk-setelah-obat-alergi', 'efek_samping',
 'Obat antihistamin bisa menimbulkan kantuk. Kenali kapan perlu berhati-hati dan kapan perlu ke dokter.',
 'Antihistamin seperti CTM (klorfeniramin) sering menimbulkan kantuk. Cetirizine lebih jarang menimbulkan kantuk, tetapi tetap bisa terjadi pada sebagian orang.\n\nJika merasa mengantuk, hindari berkendara atau mengoperasikan mesin. Hindari juga minuman beralkohol karena bisa menambah rasa kantuk.\n\nCatat keluhan di Jurnal Efek Samping, termasuk jam minum obat dan jam keluhan muncul. Catatan ini akan ikut tercetak di Laporan Kontrol untuk dokter.\n\nSegera ke fasilitas kesehatan jika muncul ruam yang meluas, bengkak di wajah atau bibir, atau sesak napas setelah minum obat.',
 'Segera ke fasilitas kesehatan jika muncul bengkak di wajah atau sesak napas setelah minum obat.',
 'cetirizine,ctm,antihistamin,mengantuk,alergi', 1, NOW());

INSERT INTO drug_interactions (author_id, substance_a, substance_b, severity_level, interaction_effect, recommendation, is_published) VALUES
(@admin, 'Tetrasiklin, doksisiklin', 'Susu dan produk kalsium', 'sedang', 'Kalsium mengikat antibiotik sehingga jumlah obat yang diserap tubuh berkurang.', 'Minum antibiotik dengan air putih dan beri jeda dari susu sesuai anjuran apoteker.', 1),
(@admin, 'Siprofloksasin', 'Antasida (obat maag cair atau tablet kunyah)', 'sedang', 'Aluminium dan magnesium dalam antasida mengikat siprofloksasin sehingga penyerapannya turun.', 'Minum siprofloksasin minimal 2 jam sebelum atau 6 jam sesudah antasida.', 1),
(@admin, 'Paracetamol', 'Alkohol', 'tinggi', 'Meningkatkan risiko gangguan hati, terutama pada pemakaian rutin atau dosis tinggi.', 'Hindari minuman beralkohol selama minum paracetamol.', 1),
(@admin, 'Obat flu dengan pseudoefedrin atau kafein', 'Kopi', 'sedang', 'Efek kafein bertambah sehingga jantung terasa berdebar, gelisah, dan sulit tidur.', 'Batasi kopi selama minum obat flu dan baca komposisi obat pada kemasan.', 1),
(@admin, 'Suplemen zat besi', 'Teh dan kopi', 'rendah', 'Tanin pada teh dan kopi menghambat penyerapan zat besi.', 'Minum suplemen zat besi dengan air putih dan beri jeda dari teh atau kopi.', 1),
(@admin, 'Antihistamin (CTM, cetirizine)', 'Alkohol', 'sedang', 'Rasa kantuk dan penurunan konsentrasi bertambah berat.', 'Hindari alkohol dan jangan berkendara jika merasa mengantuk.', 1),
(@admin, 'Simvastatin', 'Jus jeruk bali (grapefruit)', 'tinggi', 'Kadar simvastatin dalam darah meningkat sehingga risiko nyeri otot bertambah.', 'Hindari jus jeruk bali selama minum simvastatin.', 1);
