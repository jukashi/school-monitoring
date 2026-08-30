USE school_monitoring;

ALTER TABLE tuition_assessments
    ADD COLUMN tuition_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER description,
    ADD COLUMN entrance_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tuition_fee_amount,
    ADD COLUMN paces_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER entrance_fee_amount,
    ADD COLUMN closing_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER paces_fee_amount,
    ADD COLUMN shuttle_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER closing_fee_amount;

UPDATE tuition_assessments
SET tuition_fee_amount=amount
WHERE tuition_fee_amount=0 AND entrance_fee_amount=0 AND paces_fee_amount=0
  AND closing_fee_amount=0 AND shuttle_fee_amount=0;
