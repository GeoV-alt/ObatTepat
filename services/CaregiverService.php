<?php

class CaregiverService
{
    public static function createInvitation(int $patientId): array
    {
        self::expireStaleInvitations($patientId);

        db_run(
            "UPDATE caregiver_invitations SET status = 'revoked' WHERE patient_id = ? AND status = 'pending'",
            [$patientId]
        );

        $code = self::generateUniqueCode();
        $hours = (int) config('invite_valid_hours', 24);
        $expiresAt = date('Y-m-d H:i:s', time() + ($hours * 3600));

        $id = db_insert(
            "INSERT INTO caregiver_invitations (patient_id, invite_code, expires_at, status) VALUES (?, ?, ?, 'pending')",
            [$patientId, $code, $expiresAt]
        );

        return ['id' => $id, 'invite_code' => $code, 'expires_at' => $expiresAt];
    }

    public static function activeInvitationForPatient(int $patientId): ?array
    {
        self::expireStaleInvitations($patientId);

        return db_one(
            "SELECT * FROM caregiver_invitations WHERE patient_id = ? AND status = 'pending' AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            [$patientId]
        );
    }

    public static function revokeInvitation(int $invitationId, int $patientId): void
    {
        db_run(
            "UPDATE caregiver_invitations SET status = 'revoked' WHERE id = ? AND patient_id = ? AND status = 'pending'",
            [$invitationId, $patientId]
        );
    }

    public static function redeemInvitation(int $caregiverId, string $code, string $relationship): array
    {
        $code = strtoupper(trim($code));
        $relationship = trim($relationship) !== '' ? mb_substr(trim($relationship), 0, 50) : 'Keluarga';

        if ($code === '') {
            throw new DomainException('Masukkan kode undangan dari pasien.');
        }

        return db_transaction(function () use ($caregiverId, $code, $relationship): array {
            $invitation = db_one(
                'SELECT i.*, u.full_name AS patient_name
                 FROM caregiver_invitations i
                 JOIN users u ON u.id = i.patient_id
                 WHERE i.invite_code = ? FOR UPDATE',
                [$code]
            );

            if ($invitation === null) {
                throw new DomainException('Kode undangan tidak ditemukan. Periksa kembali huruf dan angkanya.');
            }

            if ((int) $invitation['patient_id'] === $caregiverId) {
                throw new DomainException('Anda tidak bisa menautkan akun sendiri.');
            }

            if ($invitation['status'] === 'used') {
                throw new DomainException('Kode undangan ini sudah pernah dipakai.');
            }

            if ($invitation['status'] === 'revoked') {
                throw new DomainException('Kode undangan ini sudah dibatalkan oleh pasien.');
            }

            if ($invitation['status'] === 'expired' || strtotime($invitation['expires_at']) < time()) {
                db_run("UPDATE caregiver_invitations SET status = 'expired' WHERE id = ?", [$invitation['id']]);
                throw new DomainException('Kode undangan sudah melewati batas waktu 24 jam. Minta pasien membuat kode baru.');
            }

            $existing = db_one(
                'SELECT * FROM caregiver_links WHERE patient_id = ? AND caregiver_id = ?',
                [$invitation['patient_id'], $caregiverId]
            );

            if ($existing !== null && $existing['status'] === 'active') {
                throw new DomainException('Akun Anda sudah tertaut dengan pasien ' . $invitation['patient_name'] . '.');
            }

            if ($existing !== null) {
                db_run(
                    "UPDATE caregiver_links
                     SET status = 'active', invitation_id = ?, relationship_label = ?, linked_at = NOW(), revoked_at = NULL
                     WHERE id = ?",
                    [$invitation['id'], $relationship, $existing['id']]
                );
            } else {
                db_insert(
                    "INSERT INTO caregiver_links (patient_id, caregiver_id, invitation_id, relationship_label, status)
                     VALUES (?, ?, ?, ?, 'active')",
                    [$invitation['patient_id'], $caregiverId, $invitation['id'], $relationship]
                );
            }

            db_run(
                "UPDATE caregiver_invitations SET status = 'used', used_by_caregiver_id = ?, used_at = NOW() WHERE id = ?",
                [$caregiverId, $invitation['id']]
            );

            return ['patient_id' => (int) $invitation['patient_id'], 'patient_name' => $invitation['patient_name']];
        });
    }

    public static function linksForPatient(int $patientId): array
    {
        return db_all(
            'SELECT l.*, u.full_name AS caregiver_name, u.email AS caregiver_email, u.phone AS caregiver_phone
             FROM caregiver_links l
             JOIN users u ON u.id = l.caregiver_id
             WHERE l.patient_id = ?
             ORDER BY l.status = ? DESC, l.linked_at DESC',
            [$patientId, 'active']
        );
    }

    public static function revokeLink(int $linkId, int $patientId): void
    {
        $updated = db_run(
            "UPDATE caregiver_links SET status = 'revoked', revoked_at = NOW() WHERE id = ? AND patient_id = ? AND status = 'active'",
            [$linkId, $patientId]
        );

        if ($updated === 0) {
            throw new DomainException('Tautan pendamping tidak ditemukan.');
        }
    }

    public static function linkedPatientsForCaregiver(int $caregiverId): array
    {
        return db_all(
            "SELECT l.id AS link_id, l.relationship_label, l.linked_at, u.id AS patient_id, u.full_name, u.email, u.phone,
                    p.blood_type, p.drug_allergies, p.chronic_conditions, p.special_notes, p.default_tolerance_minutes
             FROM caregiver_links l
             JOIN users u ON u.id = l.patient_id
             LEFT JOIN patient_profiles p ON p.user_id = u.id
             WHERE l.caregiver_id = ? AND l.status = 'active' AND u.is_active = 1
             ORDER BY u.full_name",
            [$caregiverId]
        );
    }

    public static function activeLink(int $caregiverId, int $patientId): ?array
    {
        return db_one(
            "SELECT * FROM caregiver_links WHERE caregiver_id = ? AND patient_id = ? AND status = 'active'",
            [$caregiverId, $patientId]
        );
    }

    public static function sendReminder(int $caregiverId, int $scheduleId, string $message): array
    {
        $schedule = db_one(
            'SELECT s.*, m.name AS medication_name, m.meal_rule
             FROM medication_schedules s
             JOIN medications m ON m.id = s.medication_id
             WHERE s.id = ?',
            [$scheduleId]
        );

        if ($schedule === null) {
            throw new DomainException('Jadwal minum obat tidak ditemukan.');
        }

        $patientId = (int) $schedule['patient_id'];
        $link = self::activeLink($caregiverId, $patientId);

        if ($link === null) {
            throw new DomainException('Anda tidak memiliki akses pendampingan aktif untuk pasien ini.');
        }

        ScheduleService::syncOverdueSchedules($patientId);
        $freshSchedule = db_one('SELECT * FROM medication_schedules WHERE id = ?', [$scheduleId]);
        $tolerance = AuthService::toleranceMinutes($patientId);

        if (!ScheduleService::canRemind($freshSchedule, $tolerance)) {
            throw new DomainException('Pengingat hanya aktif untuk jadwal yang terlewat atau mendekati akhir batas toleransi.');
        }

        $cooldownMinutes = (int) config('reminder_cooldown_minutes', 30);
        $lastSent = db_one(
            'SELECT sent_at FROM caregiver_reminders WHERE schedule_id = ? AND caregiver_id = ? ORDER BY sent_at DESC LIMIT 1',
            [$scheduleId, $caregiverId]
        );

        if ($lastSent !== null) {
            $elapsed = time() - strtotime($lastSent['sent_at']);
            if ($elapsed < ($cooldownMinutes * 60)) {
                $remaining = (int) ceil((($cooldownMinutes * 60) - $elapsed) / 60);
                throw new DomainException('Pengingat untuk jadwal ini baru saja dikirim. Coba lagi dalam ' . $remaining . ' menit.');
            }
        }

        $cleanMessage = trim($message);
        if ($cleanMessage === '') {
            $cleanMessage = 'Pengingat minum ' . $schedule['medication_name'] . ' (' . strtolower(label('meal_rule', $schedule['meal_rule'])) . ') untuk jadwal ' . format_time($schedule['scheduled_at']) . '.';
        }

        $id = db_insert(
            'INSERT INTO caregiver_reminders (caregiver_link_id, schedule_id, caregiver_id, patient_id, message) VALUES (?, ?, ?, ?, ?)',
            [$link['id'], $scheduleId, $caregiverId, $patientId, mb_substr($cleanMessage, 0, 255)]
        );

        return ['id' => $id, 'sent_at' => now_string(), 'message' => $cleanMessage];
    }

    public static function pendingRemindersForPatient(int $patientId): array
    {
        return db_all(
            "SELECT r.*, u.full_name AS caregiver_name, l.relationship_label,
                    s.scheduled_at, s.status AS schedule_status, s.planned_dose,
                    m.name AS medication_name, m.dose_unit, m.meal_rule
             FROM caregiver_reminders r
             JOIN users u ON u.id = r.caregiver_id
             JOIN caregiver_links l ON l.id = r.caregiver_link_id
             JOIN medication_schedules s ON s.id = r.schedule_id
             JOIN medications m ON m.id = s.medication_id
             WHERE r.patient_id = ? AND r.acknowledged_at IS NULL AND s.status IN ('pending', 'missed')
             ORDER BY r.sent_at DESC",
            [$patientId]
        );
    }

    public static function markReminderRead(int $reminderId, int $patientId): void
    {
        db_run(
            'UPDATE caregiver_reminders SET read_at = COALESCE(read_at, NOW()), acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE id = ? AND patient_id = ?',
            [$reminderId, $patientId]
        );
    }

    public static function acknowledgeBySchedule(int $scheduleId, int $patientId): void
    {
        db_run(
            'UPDATE caregiver_reminders SET read_at = COALESCE(read_at, NOW()), acknowledged_at = COALESCE(acknowledged_at, NOW())
             WHERE schedule_id = ? AND patient_id = ? AND acknowledged_at IS NULL',
            [$scheduleId, $patientId]
        );
    }

    public static function recentRemindersForCaregiver(int $caregiverId, int $patientId, int $limit = 8): array
    {
        return db_all(
            'SELECT r.*, s.scheduled_at, s.status AS schedule_status, m.name AS medication_name
             FROM caregiver_reminders r
             JOIN medication_schedules s ON s.id = r.schedule_id
             JOIN medications m ON m.id = s.medication_id
             WHERE r.caregiver_id = ? AND r.patient_id = ?
             ORDER BY r.sent_at DESC
             LIMIT ' . (int) $limit,
            [$caregiverId, $patientId]
        );
    }

    public static function whatsappUrl(?string $phone, string $message): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    private static function expireStaleInvitations(int $patientId): void
    {
        db_run(
            "UPDATE caregiver_invitations SET status = 'expired' WHERE patient_id = ? AND status = 'pending' AND expires_at <= NOW()",
            [$patientId]
        );
    }

    private static function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) {
                $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'OBT-' . $part;
            if ((int) db_value('SELECT COUNT(*) FROM caregiver_invitations WHERE invite_code = ?', [$code]) === 0) {
                return $code;
            }
        }

        return 'OBT-' . strtoupper(bin2hex(random_bytes(2)));
    }
}
