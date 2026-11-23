CREATE DATABASE IF NOT EXISTS obattepat_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE obattepat_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS v_patient_adherence_daily;
DROP TABLE IF EXISTS drug_interactions;
DROP TABLE IF EXISTS education_articles;
DROP TABLE IF EXISTS symptom_logs;
DROP TABLE IF EXISTS caregiver_reminders;
DROP TABLE IF EXISTS caregiver_links;
DROP TABLE IF EXISTS caregiver_invitations;
DROP TABLE IF EXISTS refill_alerts;
DROP TABLE IF EXISTS stock_logs;
DROP TABLE IF EXISTS medication_schedules;
DROP TABLE IF EXISTS medications;
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS patient_profiles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(25) NULL,
    role ENUM('patient', 'caregiver', 'admin') NOT NULL DEFAULT 'patient',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patient_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    date_of_birth DATE NULL,
    gender ENUM('L', 'P') NULL,
    blood_type ENUM('A', 'B', 'AB', 'O', 'unknown') NOT NULL DEFAULT 'unknown',
    drug_allergies TEXT NULL,
    chronic_conditions TEXT NULL,
    special_notes TEXT NULL,
    emergency_contact_name VARCHAR(120) NULL,
    emergency_contact_phone VARCHAR(25) NULL,
    default_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_patient_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_tolerance_range
        CHECK (default_tolerance_minutes BETWEEN 15 AND 360)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    doctor_name VARCHAR(120) NULL,
    facility_name VARCHAR(150) NULL,
    prescribed_date DATE NOT NULL,
    notes TEXT NULL,
    status ENUM('active', 'completed', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_prescriptions_patient_status (patient_id, status),
    CONSTRAINT fk_prescriptions_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    prescription_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    dosage_form ENUM('tablet', 'kapsul', 'sirup', 'tetes', 'salep', 'inhaler', 'lainnya') NOT NULL DEFAULT 'tablet',
    dose_amount DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    dose_unit VARCHAR(30) NOT NULL DEFAULT 'tablet',
    meal_rule ENUM('sebelum_makan', 'sesudah_makan', 'bersama_makan', 'bebas') NOT NULL DEFAULT 'sesudah_makan',
    is_antibiotic TINYINT(1) NOT NULL DEFAULT 0,
    frequency_per_day TINYINT UNSIGNED NOT NULL,
    interval_hours TINYINT UNSIGNED NOT NULL,
    duration_days SMALLINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    first_dose_time TIME NOT NULL DEFAULT '07:00:00',
    initial_stock DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    current_stock DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    refill_threshold_days TINYINT UNSIGNED NOT NULL DEFAULT 2,
    instructions TEXT NULL,
    status ENUM('active', 'paused', 'completed', 'discontinued') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_medications_patient_status (patient_id, status),
    KEY idx_medications_prescription (prescription_id),
    CONSTRAINT fk_medications_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_medications_prescription
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_med_dose_positive CHECK (dose_amount > 0),
    CONSTRAINT chk_med_freq_valid CHECK (frequency_per_day BETWEEN 1 AND 12),
    CONSTRAINT chk_med_interval_valid CHECK (interval_hours BETWEEN 1 AND 24),
    CONSTRAINT chk_med_duration_positive CHECK (duration_days >= 1),
    CONSTRAINT chk_med_stock_non_negative CHECK (initial_stock >= 0 AND current_stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medication_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medication_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    scheduled_at DATETIME NOT NULL,
    planned_dose DECIMAL(6,2) NOT NULL,
    status ENUM('pending', 'taken', 'missed', 'skipped') NOT NULL DEFAULT 'pending',
    taken_at DATETIME NULL,
    actual_dose DECIMAL(6,2) NULL,
    delay_minutes INT NULL,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    skip_reason VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_med_schedule_slot (medication_id, scheduled_at),
    KEY idx_schedules_patient_time (patient_id, scheduled_at),
    KEY idx_schedules_patient_status_time (patient_id, status, scheduled_at),
    CONSTRAINT fk_schedules_medication
        FOREIGN KEY (medication_id) REFERENCES medications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedules_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medication_id BIGINT UNSIGNED NOT NULL,
    schedule_id BIGINT UNSIGNED NULL,
    change_type ENUM('initial', 'consumption', 'undo_consumption', 'refill', 'adjustment') NOT NULL,
    quantity_change DECIMAL(8,2) NOT NULL,
    stock_after DECIMAL(8,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stock_logs_med_time (medication_id, created_at),
    KEY idx_stock_logs_schedule (schedule_id),
    CONSTRAINT fk_stock_logs_medication
        FOREIGN KEY (medication_id) REFERENCES medications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stock_logs_schedule
        FOREIGN KEY (schedule_id) REFERENCES medication_schedules(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_logs_actor
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refill_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medication_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    estimated_days_left DECIMAL(5,1) NOT NULL,
    stock_snapshot DECIMAL(8,2) NOT NULL,
    severity ENUM('warning', 'critical') NOT NULL DEFAULT 'warning',
    status ENUM('active', 'acknowledged', 'resolved') NOT NULL DEFAULT 'active',
    triggered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_refill_patient_status (patient_id, status),
    KEY idx_refill_med_status (medication_id, status),
    CONSTRAINT fk_refill_alerts_medication
        FOREIGN KEY (medication_id) REFERENCES medications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_refill_alerts_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caregiver_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    invite_code VARCHAR(16) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_by_caregiver_id BIGINT UNSIGNED NULL,
    used_at DATETIME NULL,
    status ENUM('pending', 'used', 'expired', 'revoked') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invite_code (invite_code),
    KEY idx_invitations_patient_status (patient_id, status),
    CONSTRAINT fk_invitations_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_invitations_caregiver
        FOREIGN KEY (used_by_caregiver_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caregiver_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    caregiver_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NULL,
    relationship_label VARCHAR(50) NOT NULL DEFAULT 'Keluarga',
    status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_patient_caregiver (patient_id, caregiver_id),
    KEY idx_caregiver_links_caregiver_status (caregiver_id, status),
    CONSTRAINT fk_caregiver_links_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_caregiver_links_caregiver
        FOREIGN KEY (caregiver_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_caregiver_links_invitation
        FOREIGN KEY (invitation_id) REFERENCES caregiver_invitations(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caregiver_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caregiver_link_id BIGINT UNSIGNED NOT NULL,
    schedule_id BIGINT UNSIGNED NOT NULL,
    caregiver_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    KEY idx_reminders_patient_unread (patient_id, read_at),
    KEY idx_reminders_schedule_time (schedule_id, sent_at),
    CONSTRAINT fk_reminders_link
        FOREIGN KEY (caregiver_link_id) REFERENCES caregiver_links(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reminders_schedule
        FOREIGN KEY (schedule_id) REFERENCES medication_schedules(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reminders_caregiver
        FOREIGN KEY (caregiver_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reminders_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE symptom_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    medication_id BIGINT UNSIGNED NULL,
    schedule_id BIGINT UNSIGNED NULL,
    log_date DATE NOT NULL,
    logged_time TIME NULL,
    daily_condition ENUM('membaik', 'stabil', 'kurang_nyaman', 'memburuk') NOT NULL DEFAULT 'stabil',
    symptom_name VARCHAR(120) NOT NULL,
    severity ENUM('ringan', 'sedang', 'berat') NOT NULL DEFAULT 'ringan',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_symptoms_patient_date (patient_id, log_date),
    KEY idx_symptoms_medication (medication_id),
    KEY idx_symptoms_schedule (schedule_id),
    CONSTRAINT fk_symptoms_patient
        FOREIGN KEY (patient_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_symptoms_medication
        FOREIGN KEY (medication_id) REFERENCES medications(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_symptoms_schedule
        FOREIGN KEY (schedule_id) REFERENCES medication_schedules(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE education_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    category ENUM('aturan_pakai', 'antibiotik', 'interaksi_obat', 'penyimpanan', 'efek_samping') NOT NULL,
    summary VARCHAR(300) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    warning_highlight VARCHAR(255) NULL,
    tags VARCHAR(255) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_articles_slug (slug),
    KEY idx_articles_cat_pub (category, is_published),
    CONSTRAINT fk_articles_author
        FOREIGN KEY (author_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drug_interactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NOT NULL,
    substance_a VARCHAR(120) NOT NULL,
    substance_b VARCHAR(120) NOT NULL,
    severity_level ENUM('rendah', 'sedang', 'tinggi') NOT NULL DEFAULT 'sedang',
    interaction_effect TEXT NOT NULL,
    recommendation TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_interactions_pub (is_published),
    CONSTRAINT fk_interactions_author
        FOREIGN KEY (author_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_patient_adherence_daily AS
SELECT
    patient_id,
    DATE(scheduled_at) AS schedule_date,
    COUNT(*) AS total_due_schedules,
    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) AS taken_count,
    SUM(CASE WHEN status = 'taken' AND is_late = 0 THEN 1 ELSE 0 END) AS on_time_count,
    SUM(CASE WHEN status = 'taken' AND is_late = 1 THEN 1 ELSE 0 END) AS late_taken_count,
    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) AS missed_count,
    SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped_count,
    ROUND((SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) AS adherence_rate_pct
FROM medication_schedules
WHERE scheduled_at <= NOW()
  AND status IN ('taken', 'missed', 'skipped')
GROUP BY patient_id, DATE(scheduled_at);
