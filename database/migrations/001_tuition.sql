USE school_monitoring;

CREATE TABLE IF NOT EXISTS tuition_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    school_year_id BIGINT UNSIGNED NOT NULL,
    term_id BIGINT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NULL,
    status ENUM('open','paid','waived','cancelled') NOT NULL DEFAULT 'open',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tuition_assessment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_assessment_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_assessment_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_tuition_assessment_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tuition_student_year (student_id, school_year_id),
    INDEX idx_tuition_due_status (due_date, status),
    CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tuition_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tuition_assessment_id BIGINT UNSIGNED NOT NULL,
    receipt_no VARCHAR(60) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('cash','bank_transfer','card','cheque','other') NOT NULL DEFAULT 'cash',
    remarks VARCHAR(500) NULL,
    received_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tuition_payment_assessment FOREIGN KEY (tuition_assessment_id) REFERENCES tuition_assessments(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tuition_payment_receiver FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tuition_payment_date (paid_on),
    CHECK (amount > 0)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (code,module,action,description) VALUES
('tuition.view','tuition','view','View tuition assessments and balances'),
('tuition.manage','tuition','manage','Create and manage tuition assessments'),
('tuition.record_payment','tuition','record_payment','Record tuition payments'),
('tuition.export','tuition','export','Export tuition records');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.name='Super Administrator' AND p.module='tuition';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.name='Administrator / Registrar' AND p.code IN ('tuition.view','tuition.manage','tuition.record_payment','tuition.export');

