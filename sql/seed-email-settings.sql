-- Update SMTP settings (run in phpMyAdmin). Set smtp_pass in Admin → Email Settings afterward.
INSERT INTO site_settings (setting_key, setting_value) VALUES
('admin_email', 'info@hourofgraceministries.org'),
('smtp_host', 'mail.hourofgraceministries.org'),
('smtp_port', '465'),
('smtp_user', 'smtp@hourofgraceministries.org'),
('smtp_from_email', 'smtp@hourofgraceministries.org'),
('smtp_from_name', 'Hour of Grace Ministry International'),
('notify_admin', '1'),
('notify_user', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
