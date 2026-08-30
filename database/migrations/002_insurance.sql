USE school_monitoring;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_no VARCHAR(50) NOT NULL UNIQUE,
    department_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    job_title VARCHAR(120) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    employment_status ENUM('active','on_leave','inactive','separated') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_employees_name (last_name,first_name),
    INDEX idx_employees_status (employment_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS insurance_policies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holder_type ENUM('student','teacher','employee') NOT NULL,
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
    status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'active',
    remarks TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_insurance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_insurance_holder (holder_type,student_id,teacher_id,employee_id),
    INDEX idx_insurance_expiry (coverage_end,status),
    CHECK (coverage_end >= coverage_start)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS insurance_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    insurance_policy_id BIGINT UNSIGNED NOT NULL,
    claim_no VARCHAR(80) NOT NULL UNIQUE,
    filed_on DATE NOT NULL,
    incident_date DATE NULL,
    claim_amount DECIMAL(12,2) NOT NULL,
    approved_amount DECIMAL(12,2) NULL,
    status ENUM('submitted','under_review','approved','rejected','paid') NOT NULL DEFAULT 'submitted',
    remarks TEXT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_insurance_claim_policy FOREIGN KEY (insurance_policy_id) REFERENCES insurance_policies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insurance_claim_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_insurance_claim_status (status,filed_on),
    CHECK (claim_amount > 0)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (code,module,action,description) VALUES
('insurance.view','insurance','view','View insurance policies and coverage'),
('insurance.manage','insurance','manage','Manage employees and insurance policies'),
('insurance.claims','insurance','claims','Record and update insurance claims'),
('insurance.export','insurance','export','Export insurance records');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='Super Administrator' AND p.module='insurance';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.name='Administrator / Registrar' AND p.code IN ('insurance.view','insurance.manage','insurance.claims','insurance.export');

