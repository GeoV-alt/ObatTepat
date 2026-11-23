<?php

class AdherenceService
{
    public static function summary(int $patientId, string $fromDate, string $toDate): array
    {
        ScheduleService::syncOverdueSchedules($patientId);

        $row = db_one(
            'SELECT
                COALESCE(SUM(total_due_schedules), 0) AS total_due,
                COALESCE(SUM(taken_count), 0) AS taken,
                COALESCE(SUM(on_time_count), 0) AS on_time,
                COALESCE(SUM(late_taken_count), 0) AS late_taken,
                COALESCE(SUM(missed_count), 0) AS missed,
                COALESCE(SUM(skipped_count), 0) AS skipped
             FROM v_patient_adherence_daily
             WHERE patient_id = ? AND schedule_date BETWEEN ? AND ?',
            [$patientId, $fromDate, $toDate]
        );

        $totalDue = (int) ($row['total_due'] ?? 0);
        $taken = (int) ($row['taken'] ?? 0);
        $onTime = (int) ($row['on_time'] ?? 0);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_due' => $totalDue,
            'taken' => $taken,
            'on_time' => $onTime,
            'late_taken' => (int) ($row['late_taken'] ?? 0),
            'missed' => (int) ($row['missed'] ?? 0),
            'skipped' => (int) ($row['skipped'] ?? 0),
            'adherence_rate' => $totalDue > 0 ? round(($taken / $totalDue) * 100, 1) : null,
            'on_time_rate' => $totalDue > 0 ? round(($onTime / $totalDue) * 100, 1) : null,
        ];
    }

    public static function dailySeries(int $patientId, string $fromDate, string $toDate): array
    {
        ScheduleService::syncOverdueSchedules($patientId);

        $rows = db_all(
            'SELECT * FROM v_patient_adherence_daily WHERE patient_id = ? AND schedule_date BETWEEN ? AND ? ORDER BY schedule_date ASC',
            [$patientId, $fromDate, $toDate]
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row['schedule_date']] = $row;
        }

        $series = [];
        $cursor = new DateTimeImmutable($fromDate);
        $end = new DateTimeImmutable($toDate);

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $data = $byDate[$key] ?? null;

            $series[] = [
                'date' => $key,
                'label' => format_short_day($key),
                'total_due' => (int) ($data['total_due_schedules'] ?? 0),
                'taken' => (int) ($data['taken_count'] ?? 0),
                'on_time' => (int) ($data['on_time_count'] ?? 0),
                'missed' => (int) ($data['missed_count'] ?? 0),
                'skipped' => (int) ($data['skipped_count'] ?? 0),
                'rate' => $data !== null && (int) $data['total_due_schedules'] > 0 ? (float) $data['adherence_rate_pct'] : null,
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return $series;
    }

    public static function byMedication(int $patientId, string $fromDate, string $toDate): array
    {
        ScheduleService::syncOverdueSchedules($patientId);

        $rows = db_all(
            "SELECT m.id, m.name, m.dosage_form, m.dose_amount, m.dose_unit, m.frequency_per_day, m.meal_rule, m.is_antibiotic, m.current_stock, m.status,
                COUNT(s.id) AS total_due,
                SUM(CASE WHEN s.status = 'taken' THEN 1 ELSE 0 END) AS taken,
                SUM(CASE WHEN s.status = 'taken' AND s.is_late = 0 THEN 1 ELSE 0 END) AS on_time,
                SUM(CASE WHEN s.status = 'missed' THEN 1 ELSE 0 END) AS missed,
                SUM(CASE WHEN s.status = 'skipped' THEN 1 ELSE 0 END) AS skipped
             FROM medications m
             JOIN medication_schedules s ON s.medication_id = m.id
             WHERE m.patient_id = ? AND DATE(s.scheduled_at) BETWEEN ? AND ? AND s.scheduled_at <= NOW() AND s.status IN ('taken', 'missed', 'skipped')
             GROUP BY m.id
             ORDER BY (SUM(CASE WHEN s.status = 'taken' THEN 1 ELSE 0 END) / COUNT(s.id)) ASC, m.name ASC",
            [$patientId, $fromDate, $toDate]
        );

        foreach ($rows as &$row) {
            $total = (int) $row['total_due'];
            $row['adherence_rate'] = $total > 0 ? round(((int) $row['taken'] / $total) * 100, 1) : null;
        }
        unset($row);

        return $rows;
    }

    public static function reportPayload(int $patientId, string $fromDate, string $toDate): array
    {
        ScheduleService::syncOverdueSchedules($patientId);
        RefillService::evaluateAllForPatient($patientId);

        $medications = db_all(
            "SELECT m.*, p.title AS prescription_title
             FROM medications m
             LEFT JOIN prescriptions p ON p.id = m.prescription_id
             WHERE m.patient_id = ?
               AND (m.status = 'active' OR EXISTS (
                   SELECT 1 FROM medication_schedules s WHERE s.medication_id = m.id AND DATE(s.scheduled_at) BETWEEN ? AND ?
               ))
             ORDER BY m.status = 'active' DESC, m.name ASC",
            [$patientId, $fromDate, $toDate]
        );

        return [
            'profile' => AuthService::getProfile($patientId),
            'summary' => self::summary($patientId, $fromDate, $toDate),
            'by_medication' => self::byMedication($patientId, $fromDate, $toDate),
            'medications' => $medications,
            'history' => ScheduleService::history($patientId, $fromDate, $toDate),
            'symptoms' => SymptomService::listForPatient($patientId, ['from_date' => $fromDate, 'to_date' => $toDate]),
            'refill_alerts' => RefillService::activeAlertsForPatient($patientId),
            'generated_at' => now_string(),
        ];
    }

    public static function resolveRange(string $preset, string $customFrom = '', string $customTo = ''): array
    {
        if ($preset === 'custom' && is_valid_date($customFrom) && is_valid_date($customTo) && $customFrom <= $customTo) {
            return [$customFrom, $customTo, 'custom'];
        }

        $days = match ($preset) {
            '14' => 14,
            '30' => 30,
            default => 7,
        };

        $to = today();
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        return [$from, $to, (string) $days];
    }
}
