ALTER TABLE teachers
    ADD COLUMN sss_no VARCHAR(30) NULL AFTER employee_no,
    ADD COLUMN pagibig_no VARCHAR(30) NULL AFTER sss_no,
    ADD COLUMN philhealth_no VARCHAR(30) NULL AFTER pagibig_no;
