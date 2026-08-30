INSERT INTO system_settings (setting_key, setting_value, value_type, is_public)
VALUES ('school_logo_position_y', '50', 'integer', 1)
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
