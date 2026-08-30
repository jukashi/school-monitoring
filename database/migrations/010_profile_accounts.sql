-- Link non-teaching employee profiles to login accounts.
ALTER TABLE employees
    ADD COLUMN user_id BIGINT UNSIGNED NULL UNIQUE AFTER id,
    ADD CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
