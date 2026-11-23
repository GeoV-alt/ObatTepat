<?php

class AuthService
{
    public static function register(array $data): int
    {
        return db_transaction(function () use ($data): int {
            $userId = db_insert(
                'INSERT INTO users (full_name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)',
                [
                    $data['full_name'],
                    strtolower($data['email']),
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['phone'] !== '' ? $data['phone'] : null,
                    $data['role'],
                ]
            );

            if ($data['role'] === 'patient') {
                db_run('INSERT INTO patient_profiles (user_id) VALUES (?)', [$userId]);
            }

            return $userId;
        });
    }

    public static function attempt(string $email, string $password): ?array
    {
        $user = db_one('SELECT * FROM users WHERE email = ? AND is_active = 1', [strtolower($email)]);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    public static function emailExists(string $email, int $exceptUserId = 0): bool
    {
        return (bool) db_value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [strtolower($email), $exceptUserId]);
    }

    public static function findUser(int $userId): ?array
    {
        return db_one('SELECT id, full_name, email, phone, role, is_active, created_at FROM users WHERE id = ?', [$userId]);
    }

    public static function getProfile(int $patientId): array
    {
        $profile = db_one(
            'SELECT u.id, u.full_name, u.email, u.phone, p.*
             FROM users u
             LEFT JOIN patient_profiles p ON p.user_id = u.id
             WHERE u.id = ? AND u.role = ?',
            [$patientId, 'patient']
        );

        if ($profile === null) {
            throw new DomainException('Profil pasien tidak ditemukan.');
        }

        if ($profile['user_id'] === null) {
            db_run('INSERT INTO patient_profiles (user_id) VALUES (?)', [$patientId]);
            return self::getProfile($patientId);
        }

        return $profile;
    }

    public static function updateAccount(int $userId, string $fullName, string $phone): void
    {
        db_run('UPDATE users SET full_name = ?, phone = ? WHERE id = ?', [$fullName, $phone !== '' ? $phone : null, $userId]);
    }

    public static function updateProfile(int $patientId, array $data): void
    {
        db_run(
            'UPDATE patient_profiles
             SET date_of_birth = ?, gender = ?, blood_type = ?, drug_allergies = ?, chronic_conditions = ?,
                 special_notes = ?, emergency_contact_name = ?, emergency_contact_phone = ?, default_tolerance_minutes = ?
             WHERE user_id = ?',
            [
                $data['date_of_birth'] ?: null,
                $data['gender'] ?: null,
                $data['blood_type'],
                $data['drug_allergies'] ?: null,
                $data['chronic_conditions'] ?: null,
                $data['special_notes'] ?: null,
                $data['emergency_contact_name'] ?: null,
                $data['emergency_contact_phone'] ?: null,
                (int) $data['default_tolerance_minutes'],
                $patientId,
            ]
        );
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $hash = db_value('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        if (!$hash || !password_verify($currentPassword, $hash)) {
            return false;
        }

        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        return true;
    }

    public static function toleranceMinutes(int $patientId): int
    {
        $value = db_value('SELECT default_tolerance_minutes FROM patient_profiles WHERE user_id = ?', [$patientId]);

        return $value === null ? 60 : (int) $value;
    }

    public static function staffAccounts(): array
    {
        return db_all('SELECT id, full_name, email, phone, is_active, created_at FROM users WHERE role = ? ORDER BY full_name', ['admin']);
    }

    public static function setActive(int $userId, bool $active, int $actorId): void
    {
        if ($userId === $actorId) {
            throw new DomainException('Anda tidak bisa menonaktifkan akun yang sedang dipakai.');
        }

        db_run('UPDATE users SET is_active = ? WHERE id = ? AND role = ?', [$active ? 1 : 0, $userId, 'admin']);
    }
}
