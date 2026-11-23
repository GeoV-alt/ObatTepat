<?php

class MedicationService
{
    private const SCHEDULE_FIELDS = ['dose_amount', 'frequency_per_day', 'interval_hours', 'duration_days', 'start_date', 'first_dose_time'];

    public static function rules(bool $creating): array
    {
        $rules = [
            'name' => 'required|max:150',
            'dosage_form' => 'required|in:' . implode(',', array_keys(labels('dosage_form'))),
            'dose_amount' => 'required|numeric|gt:0|max_value:1000',
            'dose_unit' => 'required|max:30',
            'meal_rule' => 'required|in:' . implode(',', array_keys(labels('meal_rule'))),
            'frequency_per_day' => 'required|integer|min_value:1|max_value:12',
            'interval_hours' => 'required|integer|min_value:1|max_value:24',
            'duration_days' => 'required|integer|min_value:1|max_value:365',
            'start_date' => 'required|date',
            'first_dose_time' => 'required|time',
            'refill_threshold_days' => 'required|integer|min_value:1|max_value:7',
            'instructions' => 'max:1000',
        ];

        if ($creating) {
            $rules['initial_stock'] = 'required|numeric|min_value:0|max_value:10000';
        }

        return $rules;
    }

    public static function labels(): array
    {
        return [
            'name' => 'Nama obat',
            'dosage_form' => 'Bentuk obat',
            'dose_amount' => 'Dosis sekali minum',
            'dose_unit' => 'Satuan dosis',
            'meal_rule' => 'Aturan makan',
            'frequency_per_day' => 'Frekuensi per hari',
            'interval_hours' => 'Jarak antar dosis',
            'duration_days' => 'Lama pengobatan',
            'start_date' => 'Tanggal mulai',
            'first_dose_time' => 'Jam dosis pertama',
            'initial_stock' => 'Jumlah obat yang dimiliki',
            'refill_threshold_days' => 'Batas peringatan stok',
            'instructions' => 'Catatan aturan pakai',
        ];
    }

    public static function validate(array $data, int $patientId, bool $creating): array
    {
        $errors = validate($data, self::rules($creating), self::labels());

        if (!isset($errors['interval_hours'], $errors['frequency_per_day'])) {
            $span = (int) $data['interval_hours'] * ((int) $data['frequency_per_day'] - 1);
            if ($span >= 24) {
                $errors['interval_hours'] = 'Jarak antar dosis terlalu panjang. Untuk ' . (int) $data['frequency_per_day'] . ' x sehari, jarak maksimal ' . max(1, intdiv(23, max(1, (int) $data['frequency_per_day'] - 1))) . ' jam.';
            }
        }

        $prescriptionId = (int) ($data['prescription_id'] ?? 0);
        if ($prescriptionId > 0 && self::findPrescription($prescriptionId, $patientId) === null) {
            $errors['prescription_id'] = 'Resep yang dipilih tidak ditemukan.';
        }

        return $errors;
    }

    public static function listForPatient(int $patientId, string $group = 'active'): array
    {
        $statuses = $group === 'finished' ? ['completed', 'discontinued'] : ['active', 'paused'];

        return db_all(
            "SELECT m.*, p.title AS prescription_title,
                (SELECT MIN(s.scheduled_at) FROM medication_schedules s WHERE s.medication_id = m.id AND s.status = 'pending') AS next_dose_at,
                (SELECT COUNT(*) FROM medication_schedules s WHERE s.medication_id = m.id AND s.status = 'taken') AS taken_count,
                (SELECT COUNT(*) FROM medication_schedules s WHERE s.medication_id = m.id) AS schedule_count
             FROM medications m
             LEFT JOIN prescriptions p ON p.id = m.prescription_id
             WHERE m.patient_id = ? AND m.status IN (?, ?)
             ORDER BY m.status = 'active' DESC, m.name",
            [$patientId, $statuses[0], $statuses[1]]
        );
    }

    public static function activeForPatient(int $patientId): array
    {
        return db_all("SELECT * FROM medications WHERE patient_id = ? AND status = 'active' ORDER BY name", [$patientId]);
    }

    public static function optionsForPatient(int $patientId): array
    {
        $options = [];
        foreach (db_all('SELECT id, name, status FROM medications WHERE patient_id = ? ORDER BY status = ? DESC, name', [$patientId, 'active']) as $row) {
            $options[$row['id']] = $row['name'] . ($row['status'] === 'active' ? '' : ' (' . strtolower(label('medication_status', $row['status'])) . ')');
        }

        return $options;
    }

    public static function find(int $medicationId, int $patientId): ?array
    {
        return db_one(
            'SELECT m.*, p.title AS prescription_title
             FROM medications m
             LEFT JOIN prescriptions p ON p.id = m.prescription_id
             WHERE m.id = ? AND m.patient_id = ?',
            [$medicationId, $patientId]
        );
    }

    public static function findOrFail(int $medicationId, int $patientId): array
    {
        $medication = self::find($medicationId, $patientId);

        if ($medication === null) {
            abort(404, 'Obat tidak ditemukan atau bukan milik akun Anda.');
        }

        return $medication;
    }

    public static function create(int $patientId, array $data): array
    {
        return db_transaction(function () use ($patientId, $data): array {
            $medicationId = db_insert(
                'INSERT INTO medications
                    (patient_id, prescription_id, name, dosage_form, dose_amount, dose_unit, meal_rule, is_antibiotic,
                     frequency_per_day, interval_hours, duration_days, start_date, first_dose_time,
                     initial_stock, current_stock, refill_threshold_days, instructions)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $patientId,
                    (int) ($data['prescription_id'] ?? 0) ?: null,
                    $data['name'],
                    $data['dosage_form'],
                    (float) $data['dose_amount'],
                    $data['dose_unit'],
                    $data['meal_rule'],
                    !empty($data['is_antibiotic']) ? 1 : 0,
                    (int) $data['frequency_per_day'],
                    (int) $data['interval_hours'],
                    (int) $data['duration_days'],
                    $data['start_date'],
                    $data['first_dose_time'],
                    (float) $data['initial_stock'],
                    (float) $data['initial_stock'],
                    (int) $data['refill_threshold_days'],
                    $data['instructions'] !== '' ? $data['instructions'] : null,
                ]
            );

            db_run(
                "INSERT INTO stock_logs (medication_id, change_type, quantity_change, stock_after, notes, created_by)
                 VALUES (?, 'initial', ?, ?, 'Stok awal saat obat dicatat', ?)",
                [$medicationId, (float) $data['initial_stock'], (float) $data['initial_stock'], $patientId]
            );

            $generated = ScheduleService::generateForMedication($medicationId);
            RefillService::evaluateMedicationStock($medicationId);

            return ['id' => $medicationId, 'generated' => $generated];
        });
    }

    public static function update(int $medicationId, int $patientId, array $data): bool
    {
        $current = self::findOrFail($medicationId, $patientId);

        return db_transaction(function () use ($current, $medicationId, $patientId, $data): bool {
            db_run(
                'UPDATE medications
                 SET prescription_id = ?, name = ?, dosage_form = ?, dose_amount = ?, dose_unit = ?, meal_rule = ?,
                     is_antibiotic = ?, frequency_per_day = ?, interval_hours = ?, duration_days = ?, start_date = ?,
                     first_dose_time = ?, refill_threshold_days = ?, instructions = ?
                 WHERE id = ? AND patient_id = ?',
                [
                    (int) ($data['prescription_id'] ?? 0) ?: null,
                    $data['name'],
                    $data['dosage_form'],
                    (float) $data['dose_amount'],
                    $data['dose_unit'],
                    $data['meal_rule'],
                    !empty($data['is_antibiotic']) ? 1 : 0,
                    (int) $data['frequency_per_day'],
                    (int) $data['interval_hours'],
                    (int) $data['duration_days'],
                    $data['start_date'],
                    $data['first_dose_time'],
                    (int) $data['refill_threshold_days'],
                    $data['instructions'] !== '' ? $data['instructions'] : null,
                    $medicationId,
                    $patientId,
                ]
            );

            $scheduleChanged = false;
            foreach (self::SCHEDULE_FIELDS as $field) {
                $before = in_array($field, ['dose_amount'], true) ? (float) $current[$field] : substr((string) $current[$field], 0, 5);
                $after = in_array($field, ['dose_amount'], true) ? (float) $data[$field] : substr((string) $data[$field], 0, 5);
                if ($field === 'start_date') {
                    $before = (string) $current[$field];
                    $after = (string) $data[$field];
                }
                if ((string) $before !== (string) $after) {
                    $scheduleChanged = true;
                }
            }

            if ($scheduleChanged && $current['status'] === 'active') {
                ScheduleService::generateForMedication($medicationId, true);
            }

            RefillService::evaluateMedicationStock($medicationId);

            return $scheduleChanged;
        });
    }

    public static function setStatus(int $medicationId, int $patientId, string $status): void
    {
        if (!array_key_exists($status, labels('medication_status'))) {
            throw new DomainException('Status obat tidak dikenali.');
        }

        $medication = self::findOrFail($medicationId, $patientId);

        db_transaction(function () use ($medication, $medicationId, $status): void {
            db_run('UPDATE medications SET status = ? WHERE id = ?', [$status, $medicationId]);

            if ($status === 'active' && $medication['status'] !== 'active') {
                ScheduleService::generateForMedication($medicationId, true);
            }

            if ($status !== 'active') {
                db_run("DELETE FROM medication_schedules WHERE medication_id = ? AND status = 'pending'", [$medicationId]);
            }

            RefillService::evaluateMedicationStock($medicationId);
        });
    }

    public static function delete(int $medicationId, int $patientId): void
    {
        self::findOrFail($medicationId, $patientId);

        $takenCount = (int) db_value("SELECT COUNT(*) FROM medication_schedules WHERE medication_id = ? AND status = 'taken'", [$medicationId]);
        if ($takenCount > 0) {
            throw new DomainException('Obat ini sudah punya riwayat minum. Ubah statusnya menjadi Selesai atau Dihentikan agar riwayat tetap tersimpan untuk laporan kontrol.');
        }

        db_run('DELETE FROM medications WHERE id = ? AND patient_id = ?', [$medicationId, $patientId]);
    }

    public static function stockLogs(int $medicationId, int $limit = 15): array
    {
        return db_all(
            'SELECT l.*, s.scheduled_at
             FROM stock_logs l
             LEFT JOIN medication_schedules s ON s.id = l.schedule_id
             WHERE l.medication_id = ?
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ' . (int) $limit,
            [$medicationId]
        );
    }

    public static function dailyConsumption(array $medication): float
    {
        return (float) $medication['dose_amount'] * (int) $medication['frequency_per_day'];
    }

    public static function requiredStock(array $medication): float
    {
        return self::dailyConsumption($medication) * (int) $medication['duration_days'];
    }

    public static function endDate(array $medication): string
    {
        return date('Y-m-d', strtotime($medication['start_date'] . ' +' . ((int) $medication['duration_days'] - 1) . ' days'));
    }

    public static function listPrescriptions(int $patientId): array
    {
        return db_all(
            "SELECT p.*,
                (SELECT COUNT(*) FROM medications m WHERE m.prescription_id = p.id) AS medication_count,
                (SELECT COUNT(*) FROM medications m WHERE m.prescription_id = p.id AND m.status = 'active') AS active_count
             FROM prescriptions p
             WHERE p.patient_id = ?
             ORDER BY p.status = 'active' DESC, p.prescribed_date DESC",
            [$patientId]
        );
    }

    public static function medicationsForPrescription(int $prescriptionId): array
    {
        return db_all('SELECT id, name, status, frequency_per_day, dose_amount, dose_unit FROM medications WHERE prescription_id = ? ORDER BY name', [$prescriptionId]);
    }

    public static function findPrescription(int $prescriptionId, int $patientId): ?array
    {
        return db_one('SELECT * FROM prescriptions WHERE id = ? AND patient_id = ?', [$prescriptionId, $patientId]);
    }

    public static function prescriptionOptions(int $patientId): array
    {
        $options = [];
        foreach (db_all("SELECT id, title FROM prescriptions WHERE patient_id = ? AND status = 'active' ORDER BY prescribed_date DESC", [$patientId]) as $row) {
            $options[$row['id']] = $row['title'];
        }

        return $options;
    }

    public static function createPrescription(int $patientId, array $data): int
    {
        return db_insert(
            'INSERT INTO prescriptions (patient_id, title, doctor_name, facility_name, prescribed_date, notes) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $patientId,
                $data['title'],
                $data['doctor_name'] !== '' ? $data['doctor_name'] : null,
                $data['facility_name'] !== '' ? $data['facility_name'] : null,
                $data['prescribed_date'],
                $data['notes'] !== '' ? $data['notes'] : null,
            ]
        );
    }

    public static function setPrescriptionStatus(int $prescriptionId, int $patientId, string $status): void
    {
        if (!array_key_exists($status, labels('prescription_status'))) {
            throw new DomainException('Status resep tidak dikenali.');
        }

        if (self::findPrescription($prescriptionId, $patientId) === null) {
            throw new DomainException('Resep tidak ditemukan.');
        }

        db_run('UPDATE prescriptions SET status = ? WHERE id = ? AND patient_id = ?', [$status, $prescriptionId, $patientId]);
    }
}
