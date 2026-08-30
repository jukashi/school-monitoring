USE school_monitoring;

CREATE TABLE IF NOT EXISTS inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(60) NOT NULL UNIQUE,
    item_type ENUM('uniform','id_card','other') NOT NULL,
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
    INDEX idx_inventory_type_active (item_type,is_active),
    INDEX idx_inventory_stock (quantity_on_hand,reorder_level)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    recipient_type ENUM('student','teacher','employee') NOT NULL,
    student_id BIGINT UNSIGNED NULL,
    teacher_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL,
    returned_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    issued_on DATE NOT NULL,
    status ENUM('issued','partially_returned','returned') NOT NULL DEFAULT 'issued',
    notes VARCHAR(500) NULL,
    issued_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_issue_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_issue_user FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_inventory_issue_recipient (recipient_type,student_id,teacher_id,employee_id),
    INDEX idx_inventory_issue_date (issued_on,status),
    CHECK (quantity > 0),
    CHECK (returned_quantity <= quantity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    inventory_issue_id BIGINT UNSIGNED NULL,
    movement_type ENUM('opening','stock_in','adjustment_in','adjustment_out','issue','return') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    balance_after INT UNSIGNED NOT NULL,
    occurred_on DATE NOT NULL,
    notes VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_movement_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_issue FOREIGN KEY (inventory_issue_id) REFERENCES inventory_issues(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movement_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_inventory_movement_date (occurred_on,movement_type),
    CHECK (quantity > 0)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (code,module,action,description) VALUES
('inventory.view','inventory','view','View uniform and ID inventory'),
('inventory.manage','inventory','manage','Create items and adjust stock'),
('inventory.issue','inventory','issue','Issue and return uniforms and IDs'),
('inventory.export','inventory','export','Export inventory records');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='Super Administrator' AND p.module='inventory';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.name='Administrator / Registrar' AND p.code IN ('inventory.view','inventory.manage','inventory.issue','inventory.export');
