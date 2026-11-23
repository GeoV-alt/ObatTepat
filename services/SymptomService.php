<?php

class SymptomService
{
    public const COMMON_SYMPTOMS = [
        'Mengantuk',
        'Mual',
        'Pusing',
        'Nyeri lambung',
        'Diare',
        'Ruam kulit',
        'Sesak napas',
        'Bengkak wajah atau bibir',
    ];

    public const ALLERGY_KEYWORDS = ['ruam', 'sesak', 'bengkak', 'gatal hebat', 'biduran'];

    public static function rules(): array
    {
        return [
            'log_date' => 'required|date',
            'logged_time' => 'time',
            'daily_condition' => 'required|in:' . implode(',', array_keys(labels('daily_condition'))),
            'symptom_name' => 'required|max:120',
            'severity' => 'required|in:' . implode(',', array_keys(labels('symptom_severity'))),
            'notes' => 'max:1000',
        ];
    }

    public static function labels(): array
    {
        return [
            'log_date' => 'Tanggal keluhan',
            'logged_time' => 'Jam keluhan',
            'daily_condition' => 'Kondisi harian',
            'symptom_name' => 'Keluhan atau efek samping',
            'severity' => 'Tingkat keparahan',
            'notes' => 'Catatan tambahan',
        ];
    }

    public static function create(int $patientId, array $data): array
    {
        $scheduleId = (int) ($data['schedule_id'] ?? 0) ?: null;
        $medicationId = (int) ($data['medication_id'] ?? 0) ?: null;

        if ($scheduleId !== null) {
            $schedule = db_one('SELECT id, medication_id FROM medication_schedules WHERE id = ? AND patient_id = ?', [$scheduleId, $patientId]);
            if ($schedule === null) {
                throw new DomainException('Jadwal obat yang dipilih tidak ditemukan.');
            }
            $medicationId = (int) $schedule['medication_id'];
        } elseif ($medicationId !== null) {
            $medication = db_one('SELECT id FROM medications WHERE id = ? AND patient_id = ?', [$medicationId, $patientId]);
            if ($medication === null) {
                throw new DomainException('Obat yang dipilih tidak ditemukan.');
            }
        }

        $id = db_insert(
            'INSERT INTO symptom_logs (patient_id, medication_id, schedule_id, log_date, logged_time, daily_condition, symptom_name, severity, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $patientId,
                $medicationId,
                $scheduleId,
                $data['log_date'],
                $data['logged_time'] !== '' ? $data['logged_time'] : null,
                $data['daily_condition'],
                $data['symptom_name'],
                $data['severity'],
                $data['notes'] !== '' ? $data['notes'] : null,
            ]
        );

        return [
            'id' => $id,
            'urgent_warning' => self::requiresMedicalWarning($data['severity'], $data['symptom_name']),
        ];
    }

    public static function delete(int $logId, int $patientId): void
    {
        db_run('DELETE FROM symptom_logs WHERE id = ? AND patient_id = ?', [$logId, $patientId]);
    }

    public static function listForPatient(int $patientId, array $filters = []): array
    {
        $sql = 'SELECT l.*, m.name AS medication_name, s.scheduled_at, s.taken_at
                FROM symptom_logs l
                LEFT JOIN medications m ON m.id = l.medication_id
                LEFT JOIN medication_schedules s ON s.id = l.schedule_id
                WHERE l.patient_id = ?';
        $params = [$patientId];

        if (!empty($filters['medication_id'])) {
            $sql .= ' AND l.medication_id = ?';
            $params[] = (int) $filters['medication_id'];
        }

        if (!empty($filters['severity']) && array_key_exists($filters['severity'], labels('symptom_severity'))) {
            $sql .= ' AND l.severity = ?';
            $params[] = $filters['severity'];
        }

        if (!empty($filters['from_date']) && is_valid_date($filters['from_date'])) {
            $sql .= ' AND l.log_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date']) && is_valid_date($filters['to_date'])) {
            $sql .= ' AND l.log_date <= ?';
            $params[] = $filters['to_date'];
        }

        $sql .= ' ORDER BY l.log_date DESC, COALESCE(l.logged_time, "23:59:59") DESC, l.id DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . (int) $filters['limit'];
        }

        return db_all($sql, $params);
    }

    public static function requiresMedicalWarning(string $severity, string $symptomName): bool
    {
        if ($severity === 'berat') {
            return true;
        }

        $lower = strtolower($symptomName);
        foreach (self::ALLERGY_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
