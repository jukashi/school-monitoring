ALTER TABLE section_subjects
    ADD COLUMN term_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(term_id, 0)) PERSISTENT AFTER term_id,
    DROP INDEX uq_section_subject_term,
    ADD UNIQUE KEY uq_section_subject_term (section_id, subject_id, term_id_key);

ALTER TABLE attendance_sessions
    ADD COLUMN section_subject_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(section_subject_id, 0)) PERSISTENT AFTER ends_at,
    ADD COLUMN starts_at_key TIME GENERATED ALWAYS AS (IFNULL(starts_at, CAST('00:00:00' AS TIME))) PERSISTENT AFTER section_subject_id_key,
    DROP INDEX uq_attendance_session,
    ADD UNIQUE KEY uq_attendance_session (section_id, section_subject_id_key, attendance_date, session_type, starts_at_key);

ALTER TABLE teacher_attendance
    ADD CONSTRAINT chk_teacher_attendance_times CHECK (time_out IS NULL OR time_in IS NULL OR time_out >= time_in);

ALTER TABLE events
    DROP INDEX idx_events_status,
    ADD INDEX idx_events_status_schedule (status, starts_at);

ALTER TABLE event_participants
    ADD CONSTRAINT chk_event_participant_target CHECK (
        (participant_type = 'student' AND student_id IS NOT NULL AND teacher_id IS NULL AND user_id IS NULL) OR
        (participant_type = 'teacher' AND student_id IS NULL AND teacher_id IS NOT NULL AND user_id IS NULL) OR
        (participant_type = 'user' AND student_id IS NULL AND teacher_id IS NULL AND user_id IS NOT NULL)
    );

ALTER TABLE event_attendance
    ADD CONSTRAINT chk_event_attendance_times CHECK (checked_out_at IS NULL OR checked_in_at IS NULL OR checked_out_at >= checked_in_at);

ALTER TABLE tuition_fee_schedules
    DROP CONSTRAINT `CONSTRAINT_1`,
    ADD CONSTRAINT chk_tuition_schedule_components CHECK (
        tuition_fee_amount >= 0 AND entrance_fee_amount >= 0 AND paces_fee_amount >= 0 AND
        closing_fee_amount >= 0 AND shuttle_fee_amount >= 0 AND
        amount = tuition_fee_amount + entrance_fee_amount + paces_fee_amount + closing_fee_amount + shuttle_fee_amount
    );

ALTER TABLE tuition_assessments
    DROP CONSTRAINT `CONSTRAINT_1`,
    ADD INDEX idx_tuition_assessment_created (created_at),
    ADD CONSTRAINT chk_tuition_assessment_components CHECK (
        tuition_fee_amount >= 0 AND entrance_fee_amount >= 0 AND paces_fee_amount >= 0 AND
        closing_fee_amount >= 0 AND shuttle_fee_amount >= 0 AND
        amount = tuition_fee_amount + entrance_fee_amount + paces_fee_amount + closing_fee_amount + shuttle_fee_amount
    );

ALTER TABLE insurance_policies
    DROP CONSTRAINT `CONSTRAINT_1`,
    ADD INDEX idx_insurance_status_expiry (holder_type, status, coverage_end, created_at),
    ADD CONSTRAINT chk_insurance_dates CHECK (coverage_end >= coverage_start),
    ADD CONSTRAINT chk_insurance_amounts CHECK ((premium_amount IS NULL OR premium_amount >= 0) AND (coverage_amount IS NULL OR coverage_amount >= 0)),
    ADD CONSTRAINT chk_insurance_holder_target CHECK (
        (holder_type = 'student' AND student_id IS NOT NULL AND teacher_id IS NULL AND employee_id IS NULL) OR
        (holder_type = 'teacher' AND student_id IS NULL AND teacher_id IS NOT NULL AND employee_id IS NULL) OR
        (holder_type = 'employee' AND student_id IS NULL AND teacher_id IS NULL AND employee_id IS NOT NULL)
    );

ALTER TABLE insurance_claims
    DROP CONSTRAINT `CONSTRAINT_1`,
    ADD CONSTRAINT chk_insurance_claim_amounts CHECK (claim_amount > 0 AND (approved_amount IS NULL OR (approved_amount >= 0 AND approved_amount <= claim_amount)));

ALTER TABLE inventory_issues
    DROP CONSTRAINT `CONSTRAINT_1`,
    DROP CONSTRAINT `CONSTRAINT_2`,
    ADD CONSTRAINT chk_inventory_issue_quantities CHECK (quantity > 0 AND returned_quantity <= quantity),
    ADD CONSTRAINT chk_inventory_issue_recipient CHECK (
        (recipient_type = 'student' AND student_id IS NOT NULL AND teacher_id IS NULL AND employee_id IS NULL) OR
        (recipient_type = 'teacher' AND student_id IS NULL AND teacher_id IS NOT NULL AND employee_id IS NULL) OR
        (recipient_type = 'employee' AND student_id IS NULL AND teacher_id IS NULL AND employee_id IS NOT NULL)
    );

ALTER TABLE teachers
    DROP INDEX idx_teachers_status,
    ADD INDEX idx_teachers_status_name (employment_status, last_name, first_name);

ALTER TABLE students
    DROP INDEX idx_students_status,
    ADD INDEX idx_students_status_name (student_status, last_name, first_name);
