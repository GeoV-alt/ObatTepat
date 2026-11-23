<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
require_post();

$patientId = (int) $user['id'];
$action = input('action', 'take');
$scheduleId = (int) input('schedule_id', '0');

try {
    if ($action === 'take') {
        $actualDose = input('actual_dose') !== '' ? (float) input('actual_dose') : null;
        $notes = input('notes');
        $result = CheckInService::markTaken($scheduleId, $patientId, $actualDose, $notes);
        $message = $result['medication_name'] . ' tercatat diminum pukul ' . $result['taken_time'] . '. Sisa stok: ' . format_qty($result['new_stock']) . ' ' . $result['dose_unit'] . '.';
        if (!empty($result['refill_alert'])) {
            $message .= ' Perhatian: stok obat ini menipis (' . format_qty($result['refill_alert']['estimated_days_left']) . ' hari tersisa).';
        }
        respond_action(true, $message, ['result' => $result], 'schedules/index.php');
    }

    if ($action === 'skip') {
        $reason = input('reason');
        $result = CheckInService::markSkipped($scheduleId, $patientId, $reason);
        respond_action(true, 'Dosis ditandai dilewati (' . $result['skip_reason'] . '). Stok tidak dikurangi.', ['result' => $result], 'schedules/index.php');
    }

    if ($action === 'undo') {
        $result = CheckInService::undoCheckIn($scheduleId, $patientId);
        respond_action(true, 'Check-in dibatalkan. Stok dikembalikan menjadi ' . format_qty($result['new_stock']) . ' ' . $result['dose_unit'] . '.', ['result' => $result], 'schedules/index.php');
    }

    respond_action(false, 'Aksi check-in tidak dikenali.', [], 'schedules/index.php');
} catch (DomainException $error) {
    respond_action(false, $error->getMessage(), [], 'schedules/index.php');
}
