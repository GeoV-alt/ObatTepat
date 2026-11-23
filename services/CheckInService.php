<?php

class CheckInService
{
    public static function markTaken(int $scheduleId, int $patientId, ?float $actualDose = null, ?string $notes = null): array
    {
        return db_transaction(function () use ($scheduleId, $patientId, $actualDose, $notes): array {
            $schedule = db_one(
                'SELECT * FROM medication_schedules WHERE id = ? AND patient_id = ? FOR UPDATE',
                [$scheduleId, $patientId]
            );

            if ($schedule === null) {
                throw new DomainException('Jadwal obat tidak ditemukan atau bukan milik akun Anda.');
            }

            if ($schedule['status'] === 'taken') {
                throw new DomainException('Jadwal ini sudah ditandai diminum.');
            }

            $medication = db_one(
                'SELECT * FROM medications WHERE id = ? AND patient_id = ? FOR UPDATE',
                [$schedule['medication_id'], $patientId]
            );

            if ($medication === null) {
                throw new DomainException('Data obat tidak ditemukan.');
            }

            $dose = $actualDose !== null && $actualDose > 0 ? $actualDose : (float) $schedule['planned_dose'];
            $now = now_string();
            $delayMinutes = (int) round((strtotime($now) - strtotime($schedule['scheduled_at'])) / 60);
            $tolerance = AuthService::toleranceMinutes($patientId);
            $isLate = $delayMinutes > $tolerance ? 1 : 0;

            db_run(
                "UPDATE medication_schedules
                 SET status = 'taken', taken_at = ?, actual_dose = ?, delay_minutes = ?, is_late = ?, skip_reason = NULL, notes = ?
                 WHERE id = ?",
                [$now, $dose, $delayMinutes, $isLate, $notes !== '' ? $notes : null, $scheduleId]
            );

            $currentStock = (float) $medication['current_stock'];
            $newStock = max(0.0, round($currentStock - $dose, 2));
            $actualDeduction = round($newStock - $currentStock, 2);

            db_run('UPDATE medications SET current_stock = ? WHERE id = ?', [$newStock, $medication['id']]);

            db_run(
                "INSERT INTO stock_logs (medication_id, schedule_id, change_type, quantity_change, stock_after, notes, created_by)
                 VALUES (?, ?, 'consumption', ?, ?, ?, ?)",
                [
                    $medication['id'],
                    $scheduleId,
                    $actualDeduction,
                    $newStock,
                    $isLate ? 'Check-in terlambat ' . format_minutes($delayMinutes) : 'Check-in dosis terjadwal',
                    $patientId,
                ]
            );

            CaregiverService::acknowledgeBySchedule($scheduleId, $patientId);
            $alert = RefillService::evaluateMedicationStock((int) $medication['id']);

            return [
                'schedule_id' => $scheduleId,
                'medication_id' => (int) $medication['id'],
                'medication_name' => $medication['name'],
                'status' => 'taken',
                'taken_at' => $now,
                'taken_time' => format_time($now),
                'delay_minutes' => $delayMinutes,
                'is_late' => (bool) $isLate,
                'new_stock' => $newStock,
                'dose_unit' => $medication['dose_unit'],
                'refill_alert' => $alert,
            ];
        });
    }

    public static function markSkipped(int $scheduleId, int $patientId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Pilih atau tulis alasan mengapa dosis dilewati.');
        }

        return db_transaction(function () use ($scheduleId, $patientId, $reason): array {
            $schedule = db_one(
                'SELECT * FROM medication_schedules WHERE id = ? AND patient_id = ? FOR UPDATE',
                [$scheduleId, $patientId]
            );

            if ($schedule === null) {
                throw new DomainException('Jadwal obat tidak ditemukan atau bukan milik akun Anda.');
            }

            if ($schedule['status'] === 'taken') {
                throw new DomainException('Jadwal yang sudah diminum tidak bisa diubah menjadi dilewati. Batalkan check-in terlebih dahulu jika salah pencet.');
            }

            db_run(
                "UPDATE medication_schedules
                 SET status = 'skipped', skip_reason = ?, taken_at = NULL, actual_dose = NULL, delay_minutes = NULL, is_late = 0
                 WHERE id = ?",
                [mb_substr($reason, 0, 255), $scheduleId]
            );

            CaregiverService::acknowledgeBySchedule($scheduleId, $patientId);
            RefillService::evaluateMedicationStock((int) $schedule['medication_id']);

            return [
                'schedule_id' => $scheduleId,
                'status' => 'skipped',
                'skip_reason' => $reason,
            ];
        });
    }

    public static function undoCheckIn(int $scheduleId, int $patientId): array
    {
        return db_transaction(function () use ($scheduleId, $patientId): array {
            $schedule = db_one(
                'SELECT * FROM medication_schedules WHERE id = ? AND patient_id = ? FOR UPDATE',
                [$scheduleId, $patientId]
            );

            if ($schedule === null) {
                throw new DomainException('Jadwal obat tidak ditemukan atau bukan milik akun Anda.');
            }

            if ($schedule['status'] !== 'taken' || !ScheduleService::canUndo($schedule)) {
                throw new DomainException('Check-in hanya bisa dibatalkan dalam ' . (int) config('undo_window_minutes', 15) . ' menit setelah dicatat.');
            }

            $medication = db_one(
                'SELECT * FROM medications WHERE id = ? AND patient_id = ? FOR UPDATE',
                [$schedule['medication_id'], $patientId]
            );

            if ($medication === null) {
                throw new DomainException('Data obat tidak ditemukan.');
            }

            $lastLog = db_one(
                "SELECT quantity_change FROM stock_logs
                 WHERE schedule_id = ? AND change_type = 'consumption'
                 ORDER BY id DESC LIMIT 1",
                [$scheduleId]
            );

            $restoreQty = $lastLog ? abs((float) $lastLog['quantity_change']) : (float) $schedule['actual_dose'];
            $newStock = round((float) $medication['current_stock'] + $restoreQty, 2);

            $tolerance = AuthService::toleranceMinutes($patientId);
            $isOverdue = strtotime($schedule['scheduled_at']) < (time() - ($tolerance * 60));
            $restoredStatus = $isOverdue ? 'missed' : 'pending';

            db_run(
                'UPDATE medication_schedules
                 SET status = ?, taken_at = NULL, actual_dose = NULL, delay_minutes = NULL, is_late = 0, notes = NULL
                 WHERE id = ?',
                [$restoredStatus, $scheduleId]
            );

            db_run('UPDATE medications SET current_stock = ? WHERE id = ?', [$newStock, $medication['id']]);

            db_run(
                "INSERT INTO stock_logs (medication_id, schedule_id, change_type, quantity_change, stock_after, notes, created_by)
                 VALUES (?, ?, 'undo_consumption', ?, ?, 'Pembatalan check-in dosis', ?)",
                [$medication['id'], $scheduleId, $restoreQty, $newStock, $patientId]
            );

            RefillService::evaluateMedicationStock((int) $medication['id']);

            return [
                'schedule_id' => $scheduleId,
                'status' => $restoredStatus,
                'display_status' => ScheduleService::displayStatus(['status' => $restoredStatus, 'scheduled_at' => $schedule['scheduled_at']], $tolerance),
                'new_stock' => $newStock,
                'dose_unit' => $medication['dose_unit'],
            ];
        });
    }
}
