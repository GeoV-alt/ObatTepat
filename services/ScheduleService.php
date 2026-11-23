<?php

class ScheduleService
{
    public static function generateForMedication(int $medicationId, bool $regenerateFutureOnly = false): int
    {
        $medication = db_one('SELECT * FROM medications WHERE id = ?', [$medicationId]);
        if ($medication === null || $medication['status'] !== 'active') {
            return 0;
        }

        $now = now_string();

        if ($regenerateFutureOnly) {
            db_run(
                "DELETE FROM medication_schedules WHERE medication_id = ? AND status = 'pending' AND scheduled_at >= ?",
                [$medicationId, $now]
            );
        }

        $firstDose = new DateTimeImmutable($medication['start_date'] . ' ' . $medication['first_dose_time']);
        $frequency = (int) $medication['frequency_per_day'];
        $intervalHours = (int) $medication['interval_hours'];
        $durationDays = (int) $medication['duration_days'];
        $plannedDose = (float) $medication['dose_amount'];
        $patientId = (int) $medication['patient_id'];

        $totalSlots = $frequency * $durationDays;
        $created = 0;

        $statement = db()->prepare(
            'INSERT IGNORE INTO medication_schedules (medication_id, patient_id, scheduled_at, planned_dose, status)
             VALUES (?, ?, ?, ?, ?)'
        );

        for ($slot = 0; $slot < $totalSlots; $slot++) {
            $dayIndex = intdiv($slot, $frequency);
            $doseIndex = $slot % $frequency;
            $hoursOffset = ($dayIndex * 24) + ($doseIndex * $intervalHours);
            $scheduledAt = $firstDose->modify('+' . $hoursOffset . ' hours')->format('Y-m-d H:i:s');

            if ($regenerateFutureOnly && $scheduledAt < $now) {
                continue;
            }

            $statement->execute([$medicationId, $patientId, $scheduledAt, $plannedDose, 'pending']);
            if ($statement->rowCount() > 0) {
                $created++;
            }
        }

        self::syncOverdueSchedules($patientId);

        return $created;
    }

    public static function syncOverdueSchedules(int $patientId): int
    {
        $tolerance = AuthService::toleranceMinutes($patientId);
        $cutoff = date('Y-m-d H:i:s', time() - ($tolerance * 60));

        return db_run(
            "UPDATE medication_schedules
             SET status = 'missed'
             WHERE patient_id = ? AND status = 'pending' AND scheduled_at < ?",
            [$patientId, $cutoff]
        );
    }

    public static function displayStatus(array $schedule, int $toleranceMinutes): string
    {
        if ($schedule['status'] !== 'pending') {
            return $schedule['status'];
        }

        $scheduledTime = strtotime($schedule['scheduled_at']);
        $now = time();
        $dueStart = $scheduledTime - ((int) config('due_window_minutes', 30) * 60);
        $dueEnd = $scheduledTime + ($toleranceMinutes * 60);

        if ($now < $dueStart) {
            return 'upcoming';
        }

        if ($now <= $dueEnd) {
            return 'due';
        }

        return 'missed';
    }

    public static function canUndo(array $schedule): bool
    {
        if ($schedule['status'] !== 'taken' || empty($schedule['taken_at'])) {
            return false;
        }

        $elapsed = time() - strtotime($schedule['taken_at']);

        return $elapsed <= ((int) config('undo_window_minutes', 15) * 60);
    }

    public static function canRemind(array $schedule, int $toleranceMinutes): bool
    {
        $status = self::displayStatus($schedule, $toleranceMinutes);
        if ($status === 'missed') {
            return true;
        }

        if ($status !== 'due') {
            return false;
        }

        $dueEnd = strtotime($schedule['scheduled_at']) + ($toleranceMinutes * 60);
        $leadSeconds = (int) config('reminder_lead_minutes', 15) * 60;

        return time() >= ($dueEnd - $leadSeconds);
    }

    public static function forDate(int $patientId, string $date, int $medicationId = 0): array
    {
        self::syncOverdueSchedules($patientId);
        $tolerance = AuthService::toleranceMinutes($patientId);

        $sql = 'SELECT s.*, m.name AS medication_name, m.dosage_form, m.dose_unit, m.meal_rule, m.is_antibiotic, m.current_stock, m.instructions
                FROM medication_schedules s
                JOIN medications m ON m.id = s.medication_id
                WHERE s.patient_id = ? AND DATE(s.scheduled_at) = ?';
        $params = [$patientId, $date];

        if ($medicationId > 0) {
            $sql .= ' AND s.medication_id = ?';
            $params[] = $medicationId;
        }

        $sql .= ' ORDER BY s.scheduled_at ASC, m.name ASC';
        $rows = db_all($sql, $params);

        return array_map(fn (array $row) => self::enrich($row, $tolerance), $rows);
    }

    public static function groupByPeriod(array $schedules): array
    {
        $groups = [
            'Pagi (04:00 - 10:59)' => [],
            'Siang (11:00 - 14:59)' => [],
            'Sore (15:00 - 17:59)' => [],
            'Malam (18:00 - 03:59)' => [],
        ];

        foreach ($schedules as $item) {
            $hour = (int) date('G', strtotime($item['scheduled_at']));
            if ($hour >= 4 && $hour <= 10) {
                $groups['Pagi (04:00 - 10:59)'][] = $item;
            } elseif ($hour >= 11 && $hour <= 14) {
                $groups['Siang (11:00 - 14:59)'][] = $item;
            } elseif ($hour >= 15 && $hour <= 17) {
                $groups['Sore (15:00 - 17:59)'][] = $item;
            } else {
                $groups['Malam (18:00 - 03:59)'][] = $item;
            }
        }

        return array_filter($groups, fn (array $items) => $items !== []);
    }

    public static function urgentToday(int $patientId): array
    {
        $todaySchedules = self::forDate($patientId, today());

        return array_values(array_filter(
            $todaySchedules,
            fn (array $row) => in_array($row['display_status'], ['due', 'missed'], true)
        ));
    }

    public static function history(int $patientId, string $fromDate, string $toDate, int $medicationId = 0, string $status = ''): array
    {
        self::syncOverdueSchedules($patientId);
        $tolerance = AuthService::toleranceMinutes($patientId);

        $sql = 'SELECT s.*, m.name AS medication_name, m.dose_unit, m.meal_rule, m.is_antibiotic
                FROM medication_schedules s
                JOIN medications m ON m.id = s.medication_id
                WHERE s.patient_id = ? AND DATE(s.scheduled_at) BETWEEN ? AND ?';
        $params = [$patientId, $fromDate, $toDate];

        if ($medicationId > 0) {
            $sql .= ' AND s.medication_id = ?';
            $params[] = $medicationId;
        }

        if (in_array($status, ['pending', 'taken', 'missed', 'skipped'], true)) {
            $sql .= ' AND s.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY s.scheduled_at DESC';
        $rows = db_all($sql, $params);

        return array_map(fn (array $row) => self::enrich($row, $tolerance), $rows);
    }

    public static function takenOrScheduledOnDate(int $patientId, string $date): array
    {
        self::syncOverdueSchedules($patientId);

        return db_all(
            'SELECT s.id, s.medication_id, s.scheduled_at, s.taken_at, s.status, s.planned_dose, m.name AS medication_name, m.dose_unit
             FROM medication_schedules s
             JOIN medications m ON m.id = s.medication_id
             WHERE s.patient_id = ? AND (DATE(s.scheduled_at) = ? OR DATE(s.taken_at) = ?)
             ORDER BY s.scheduled_at ASC',
            [$patientId, $date, $date]
        );
    }

    public static function upcomingForMedication(int $medicationId, int $limit = 10): array
    {
        return db_all(
            'SELECT * FROM medication_schedules WHERE medication_id = ? ORDER BY scheduled_at ASC LIMIT ' . (int) $limit,
            [$medicationId]
        );
    }

    private static function enrich(array $row, int $tolerance): array
    {
        $row['display_status'] = self::displayStatus($row, $tolerance);
        $row['can_undo'] = self::canUndo($row);
        $row['can_remind'] = self::canRemind($row, $tolerance);

        return $row;
    }
}
