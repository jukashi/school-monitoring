USE school_monitoring;

ALTER TABLE tuition_payments
    ADD COLUMN payment_for ENUM('tuition','entrance_fee','paces','closing_fee','shuttle','other') NOT NULL DEFAULT 'tuition' AFTER amount,
    ADD INDEX idx_tuition_payment_category (tuition_assessment_id, payment_for);
