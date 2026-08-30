CREATE DATABASE IF NOT EXISTS school_monitoring
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE school_monitoring;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(191) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    status ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    password_changed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_module_action (module, action)
) ENGINE=InnoDB;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_tokens_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE school_years (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (ends_on >= starts_on)
) ENGINE=InnoDB;

CREATE TABLE terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_year_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    sequence_no SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_terms_year_name (school_year_id, name),
    UNIQUE KEY uq_terms_year_sequence (school_year_id, sequence_no),
    CONSTRAINT fk_terms_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    CHECK (ends_on >= starts_on)
) ENGINE=InnoDB;

CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE grade_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL UNIQUE,
    sequence_no SMALLINT UNSIGNED NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE teachers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    employee_no VARCHAR(50) NOT NULL UNIQUE,
    department_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(20) NULL,
    sex ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
    birth_date DATE NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(191) NULL,
    address TEXT NULL,
    hire_date DATE NULL,
    employment_status ENUM('active', 'on_leave', 'inactive', 'separated') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_teachers_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_teachers_name (last_name, first_name),
    INDEX idx_teachers_status (employment_status)
) ENGINE=InnoDB;

CREATE TABLE students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    student_no VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(20) NULL,
    sex ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
    birth_date DATE NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(191) NULL,
    address TEXT NULL,
    admission_date DATE NULL,
    student_status ENUM('active', 'inactive', 'graduated', 'transferred', 'withdrawn') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_students_name (last_name, first_name),
    INDEX idx_students_status (student_status)
) ENGINE=InnoDB;

CREATE TABLE guardians (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(191) NULL,
    address TEXT NULL,
    occupation VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_guardians_name (last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE student_guardians (
    student_id BIGINT UNSIGNED NOT NULL,
    guardian_id BIGINT UNSIGNED NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    can_pick_up BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (student_id, guardian_id),
    CONSTRAINT fk_student_guardians_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_guardians_guardian FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_year_id BIGINT UNSIGNED NOT NULL,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    adviser_teacher_id BIGINT UNSIGNED NULL,
    name VARCHAR(80) NOT NULL,
    capacity SMALLINT UNSIGNED NULL,
    room VARCHAR(80) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sections_year_grade_name (school_year_id, grade_level_id, name),
    CONSTRAINT fk_sections_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sections_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sections_adviser FOREIGN KEY (adviser_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    school_year_id BIGINT UNSIGNED NOT NULL,
    section_id BIGINT UNSIGNED NOT NULL,
    enrolled_on DATE NOT NULL,
    status ENUM('enrolled', 'completed', 'transferred', 'withdrawn') NOT NULL DEFAULT 'enrolled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollments_student_year (student_id, school_year_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT,
    INDEX idx_enrollments_section_status (section_id, status)
) ENGINE=InnoDB;

CREATE TABLE section_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    term_id BIGINT UNSIGNED NULL,
    schedule_text VARCHAR(255) NULL,
    room VARCHAR(80) NULL,
    UNIQUE KEY uq_section_subject_term (section_id, subject_id, term_id),
    CONSTRAINT fk_section_subjects_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_section_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_section_subjects_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE teacher_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT UNSIGNED NOT NULL,
    section_subject_id BIGINT UNSIGNED NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT TRUE,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_section_subject (teacher_id, section_subject_id),
    CONSTRAINT fk_teacher_assignments_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_teacher_assignments_section_subject FOREIGN KEY (section_subject_id) REFERENCES section_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attendance_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id BIGINT UNSIGNED NOT NULL,
    section_subject_id BIGINT UNSIGNED NULL,
    attendance_date DATE NOT NULL,
    session_type ENUM('daily', 'class') NOT NULL DEFAULT 'daily',
    starts_at TIME NULL,
    ends_at TIME NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_session (section_id, section_subject_id, attendance_date, session_type, starts_at),
    CONSTRAINT fk_attendance_sessions_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_sessions_section_subject FOREIGN KEY (section_subject_id) REFERENCES section_subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_sessions_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_attendance_sessions_date (attendance_date)
) ENGINE=InnoDB;

CREATE TABLE student_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_session_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused', 'unrecorded') NOT NULL DEFAULT 'unrecorded',
    arrival_time TIME NULL,
    remarks VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_attendance (attendance_session_id, student_id),
    CONSTRAINT fk_student_attendance_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_student_attendance_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_student_attendance_status (status)
) ENGINE=InnoDB;

CREATE TABLE teacher_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused', 'on_leave', 'unrecorded') NOT NULL DEFAULT 'unrecorded',
    time_in TIME NULL,
    time_out TIME NULL,
    remarks VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_attendance (teacher_id, attendance_date),
    CONSTRAINT fk_teacher_attendance_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_teacher_attendance_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_teacher_attendance_date_status (attendance_date, status)
) ENGINE=InnoDB;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    venue VARCHAR(180) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    organizer_user_id BIGINT UNSIGNED NOT NULL,
    visibility ENUM('all', 'students', 'teachers', 'staff', 'invited') NOT NULL DEFAULT 'all',
    status ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    capacity INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_events_schedule (starts_at, ends_at),
    INDEX idx_events_status (status),
    CHECK (ends_at >= starts_at)
) ENGINE=InnoDB;

CREATE TABLE event_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    participant_type ENUM('student', 'teacher', 'user') NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    teacher_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    registration_status ENUM('invited', 'registered', 'declined', 'cancelled') NOT NULL DEFAULT 'invited',
    registered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_participants_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_participants_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_participants_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_event_student (event_id, student_id),
    UNIQUE KEY uq_event_teacher (event_id, teacher_id),
    UNIQUE KEY uq_event_user (event_id, user_id),
    INDEX idx_event_participants_type (event_id, participant_type)
) ENGINE=InnoDB;

CREATE TABLE event_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_participant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    status ENUM('present', 'absent', 'late', 'excused', 'unrecorded') NOT NULL DEFAULT 'unrecorded',
    checked_in_at DATETIME NULL,
    checked_out_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_attendance_participant FOREIGN KEY (event_participant_id) REFERENCES event_participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    audience ENUM('all', 'students', 'teachers', 'staff') NOT NULL DEFAULT 'all',
    published_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CONSTRAINT fk_announcements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_announcements_publish (published_at, expires_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_logs_actor_date (user_id, created_at),
    INDEX idx_audit_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    value_type ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_system_settings_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE backup_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NULL,
    checksum_sha256 CHAR(64) NULL,
    status ENUM('created', 'restored', 'failed', 'missing') NOT NULL DEFAULT 'created',
    notes VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restored_at DATETIME NULL,
    CONSTRAINT fk_backup_history_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_backup_history_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE tuition_fee_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    school_year_id BIGINT UNSIGNED NOT NULL,
    term_id BIGINT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    tuition_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    entrance_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paces_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    shuttle_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tuition_schedule_grade FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_schedule_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_schedule_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_tuition_schedule_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tuition_schedule_grade_year (grade_level_id, school_year_id, status),
    CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE tuition_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    fee_schedule_id BIGINT UNSIGNED NULL,
    school_year_id BIGINT UNSIGNED NOT NULL,
    term_id BIGINT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    tuition_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    entrance_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paces_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    shuttle_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NULL,
    status ENUM('open', 'paid', 'waived', 'cancelled') NOT NULL DEFAULT 'open',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tuition_assessment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_assessment_schedule FOREIGN KEY (fee_schedule_id) REFERENCES tuition_fee_schedules(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_assessment_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_assessment_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_tuition_assessment_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tuition_student_year (student_id, school_year_id),
    INDEX idx_tuition_due_status (due_date, status),
    UNIQUE KEY uq_tuition_student_schedule (student_id, fee_schedule_id),
    CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE tuition_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tuition_assessment_id BIGINT UNSIGNED NOT NULL,
    receipt_no VARCHAR(60) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    payment_for ENUM('tuition','entrance_fee','paces','closing_fee','shuttle','other') NOT NULL DEFAULT 'tuition',
    paid_on DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'card', 'cheque', 'other') NOT NULL DEFAULT 'cash',
    remarks VARCHAR(500) NULL,
    received_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tuition_payment_assessment FOREIGN KEY (tuition_assessment_id) REFERENCES tuition_assessments(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_payment_receiver FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tuition_payment_date (paid_on),
    INDEX idx_tuition_payment_category (tuition_assessment_id, payment_for),
    CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    employee_no VARCHAR(50) NOT NULL UNIQUE,
    department_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    job_title VARCHAR(120) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    employment_status ENUM('active', 'on_leave', 'inactive', 'separated') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_employees_name (last_name, first_name),
    INDEX idx_employees_status (employment_status)
) ENGINE=InnoDB;

CREATE TABLE insurance_policies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holder_type ENUM('student', 'teacher', 'employee') NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    teacher_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NULL,
    provider VARCHAR(180) NOT NULL,
    policy_no VARCHAR(80) NOT NULL UNIQUE,
    plan_name VARCHAR(180) NULL,
    coverage_start DATE NOT NULL,
    coverage_end DATE NOT NULL,
    premium_amount DECIMAL(12,2) NULL,
    coverage_amount DECIMAL(12,2) NULL,
    status ENUM('pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    remarks TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_insurance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_insurance_holder (holder_type, student_id, teacher_id, employee_id),
    INDEX idx_insurance_expiry (coverage_end, status),
    CHECK (coverage_end >= coverage_start)
) ENGINE=InnoDB;

CREATE TABLE insurance_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    insurance_policy_id BIGINT UNSIGNED NOT NULL,
    claim_no VARCHAR(80) NOT NULL UNIQUE,
    filed_on DATE NOT NULL,
    incident_date DATE NULL,
    claim_amount DECIMAL(12,2) NOT NULL,
    approved_amount DECIMAL(12,2) NULL,
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'submitted',
    remarks TEXT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_insurance_claim_policy FOREIGN KEY (insurance_policy_id) REFERENCES insurance_policies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_claim_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_insurance_claim_status (status, filed_on),
    CHECK (claim_amount > 0)
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(60) NOT NULL UNIQUE,
    item_type ENUM('uniform', 'id_card', 'other') NOT NULL,
    name VARCHAR(160) NOT NULL,
    variant VARCHAR(100) NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'piece',
    quantity_on_hand INT UNSIGNED NOT NULL DEFAULT 0,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_item_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_inventory_type_active (item_type, is_active),
    INDEX idx_inventory_stock (quantity_on_hand, reorder_level)
) ENGINE=InnoDB;

CREATE TABLE inventory_issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    recipient_type ENUM('student', 'teacher', 'employee') NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    teacher_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL,
    returned_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    issued_on DATE NOT NULL,
    status ENUM('issued', 'partially_returned', 'returned') NOT NULL DEFAULT 'issued',
    notes VARCHAR(500) NULL,
    issued_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_issue_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_user FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_inventory_issue_recipient (recipient_type, student_id, teacher_id, employee_id),
    INDEX idx_inventory_issue_date (issued_on, status),
    CHECK (quantity > 0), CHECK (returned_quantity <= quantity)
) ENGINE=InnoDB;

CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    inventory_issue_id BIGINT UNSIGNED NULL,
    movement_type ENUM('opening', 'stock_in', 'adjustment_in', 'adjustment_out', 'issue', 'return') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    balance_after INT UNSIGNED NOT NULL,
    occurred_on DATE NOT NULL,
    notes VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_movement_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_issue FOREIGN KEY (inventory_issue_id) REFERENCES inventory_issues(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_inventory_movement_date (occurred_on, movement_type),
    CHECK (quantity > 0)
) ENGINE=InnoDB;
