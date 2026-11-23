<?php

class RefillService
{
    public static function evaluateMedicationStock(int $medicationId): ?array
    {
        $medication = db_one('SELECT * FROM medications WHERE id = ?', [$medicationId]);
        if ($medication === null) {
            return null;
        }

        if ($medication['status'] !== 'active') {
            self::resolveActiveAlerts($medicationId);
            return null;
        }

        $daily = MedicationService::dailyConsumption($medication);
        if ($daily <= 0) {
            self::resolveActiveAlerts($medicationId);
            return null;
        }

        $currentStock = (float) $medication['current_stock'];
        $estimatedDays = round($currentStock / $daily, 1);

        $remainingNeeded = (float) db_value(
            "SELECT COALESCE(SUM(planned_dose), 0) FROM medication_schedules WHERE medication_id = ? AND status = 'pending'",
            [$medicationId]
        );

        $thresholdDays = (int) $medication['refill_threshold_days'];
        $needsMoreStock = $currentStock < $remainingNeeded;
        $isBelowThreshold = $estimatedDays <= $thresholdDays;

        if (!$needsMoreStock || !$isBelowThreshold) {
            self::resolveActiveAlerts($medicationId);
            return null;
        }

        $severity = ($currentStock <= 0 || $estimatedDays < 1.0) ? 'critical' : 'warning';

        $existing = db_one(
            "SELECT id FROM refill_alerts WHERE medication_id = ? AND status IN ('active', 'acknowledged') ORDER BY id DESC LIMIT 1",
            [$medicationId]
        );

        if ($existing !== null) {
            db_run(
                "UPDATE refill_alerts
                 SET estimated_days_left = ?, stock_snapshot = ?, severity = ?, status = 'active'
                 WHERE id = ?",
                [$estimatedDays, $currentStock, $severity, $existing['id']]
            );
            $alertId = (int) $existing['id'];
        } else {
            $alertId = db_insert(
                "INSERT INTO refill_alerts (medication_id, patient_id, estimated_days_left, stock_snapshot, severity, status)
                 VALUES (?, ?, ?, ?, ?, 'active')",
                [$medicationId, $medication['patient_id'], $estimatedDays, $currentStock, $severity]
            );
        }

        return [
            'id' => $alertId,
            'medication_id' => $medicationId,
            'medication_name' => $medication['name'],
            'estimated_days_left' => $estimatedDays,
            'stock_snapshot' => $currentStock,
            'severity' => $severity,
        ];
    }

    public static function evaluateAllForPatient(int $patientId): void
    {
        $ids = db_all("SELECT id FROM medications WHERE patient_id = ? AND status = 'active'", [$patientId]);
        foreach ($ids as $row) {
            self::evaluateMedicationStock((int) $row['id']);
        }
    }

    public static function activeAlertsForPatient(int $patientId): array
    {
        self::evaluateAllForPatient($patientId);

        return db_all(
            "SELECT r.*, m.name AS medication_name, m.dose_unit, m.dose_amount, m.frequency_per_day, m.current_stock, m.is_antibiotic
             FROM refill_alerts r
             JOIN medications m ON m.id = r.medication_id
             WHERE r.patient_id = ? AND r.status IN ('active', 'acknowledged') AND m.status = 'active'
             ORDER BY r.severity = 'critical' DESC, r.estimated_days_left ASC",
            [$patientId]
        );
    }

    public static function stockSummaryForPatient(int $patientId): array
    {
        self::evaluateAllForPatient($patientId);

        $rows = db_all(
            "SELECT m.*,
                (SELECT COALESCE(SUM(s.planned_dose), 0) FROM medication_schedules s WHERE s.medication_id = m.id AND s.status = 'pending') AS remaining_needed,
                (SELECT r.severity FROM refill_alerts r WHERE r.medication_id = m.id AND r.status IN ('active', 'acknowledged') ORDER BY r.id DESC LIMIT 1) AS alert_severity
             FROM medications m
             WHERE m.patient_id = ? AND m.status IN ('active', 'paused')
             ORDER BY alert_severity IS NULL ASC, FIELD(alert_severity, 'critical', 'warning'), m.current_stock ASC",
            [$patientId]
        );

        foreach ($rows as &$row) {
            $daily = MedicationService::dailyConsumption($row);
            $row['daily_consumption'] = $daily;
            $row['estimated_days_left'] = $daily > 0 ? round((float) $row['current_stock'] / $daily, 1) : 0.0;
        }
        unset($row);

        return $rows;
    }

    public static function recordRefill(int $medicationId, int $patientId, float $addedQty, string $notes = ''): array
    {
        if ($addedQty <= 0 || $addedQty > 10000) {
            throw new DomainException('Jumlah stok tambahan harus lebih dari 0.');
        }

        return db_transaction(function () use ($medicationId, $patientId, $addedQty, $notes): array {
            $medication = db_one(
                'SELECT * FROM medications WHERE id = ? AND patient_id = ? FOR UPDATE',
                [$medicationId, $patientId]
            );

            if ($medication === null) {
                throw new DomainException('Obat tidak ditemukan atau bukan milik akun Anda.');
            }

            $newStock = round((float) $medication['current_stock'] + $addedQty, 2);
            db_run('UPDATE medications SET current_stock = ? WHERE id = ?', [$newStock, $medicationId]);

            db_run(
                "INSERT INTO stock_logs (medication_id, change_type, quantity_change, stock_after, notes, created_by)
                 VALUES (?, 'refill', ?, ?, ?, ?)",
                [
                    $medicationId,
                    $addedQty,
                    $newStock,
                    $notes !== '' ? mb_substr($notes, 0, 255) : 'Tebus ulang stok obat',
                    $patientId,
                ]
            );

            $alert = self::evaluateMedicationStock($medicationId);

            return [
                'medication_id' => $medicationId,
                'medication_name' => $medication['name'],
                'added_qty' => $addedQty,
                'new_stock' => $newStock,
                'dose_unit' => $medication['dose_unit'],
                'alert_remaining' => $alert !== null,
            ];
        });
    }

    private static function resolveActiveAlerts(int $medicationId): void
    {
        db_run(
            "UPDATE refill_alerts
             SET status = 'resolved', resolved_at = NOW()
             WHERE medication_id = ? AND status IN ('active', 'acknowledged')",
            [$medicationId]
        );
    }
}
