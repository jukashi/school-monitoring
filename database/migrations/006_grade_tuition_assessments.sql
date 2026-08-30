USE school_monitoring;

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

ALTER TABLE tuition_assessments
    ADD COLUMN fee_schedule_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD CONSTRAINT fk_tuition_assessment_schedule FOREIGN KEY (fee_schedule_id) REFERENCES tuition_fee_schedules(id) ON DELETE RESTRICT,
    ADD UNIQUE KEY uq_tuition_student_schedule (student_id, fee_schedule_id);
