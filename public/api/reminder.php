<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_auth();
require_post();

$action = input('action', 'send');

try {
    if ($action === 'send') {
        if ($user['role'] !== 'caregiver') {
            abort(403, 'Hanya pendamping yang dapat mengirim pengingat.');
        }

        $scheduleId = (int) input('schedule_id', '0');
        $message = input('message');
        $sent = CaregiverService::sendReminder((int) $user['id'], $scheduleId, $message);
        respond_action(true, 'Pengingat terkirim ke akun pasien.', ['reminder' => $sent], 'caregivers/monitor.php');
    }

    if ($action === 'dismiss') {
        if ($user['role'] !== 'patient') {
            abort(403, 'Hanya pasien yang dapat menutup notifikasi ini.');
        }

        CaregiverService::markReminderRead((int) input('reminder_id', '0'), (int) $user['id']);
        respond_action(true, 'Notifikasi pengingat ditandai sudah dibaca.', [], 'reports/dashboard.php');
    }

    respond_action(false, 'Aksi pengingat tidak dikenali.', [], home_path_for($user['role']));
} catch (DomainException $error) {
    respond_action(false, $error->getMessage(), [], home_path_for($user['role']));
}
