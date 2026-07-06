CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(180) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO site_settings (setting_key, setting_value) VALUES
('admin_email', 'info@hourofgraceministries.org'),
('smtp_host', 'mail.hourofgraceministries.org'),
('smtp_port', '465'),
('smtp_user', 'smpt@hourofgraceministries.org'),
('smtp_pass', '#smptaction2'),
('smtp_from_email', 'smpt@hourofgraceministries.org'),
('smtp_from_name', 'Hour of Grace Ministry International'),
('notify_admin', '1'),
('notify_user', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
