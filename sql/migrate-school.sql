CREATE TABLE IF NOT EXISTS school_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    home_address TEXT NOT NULL,
    programme VARCHAR(80) NOT NULL,
    role VARCHAR(80) NOT NULL,
    education VARCHAR(255) NOT NULL,
    church_name VARCHAR(255) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_school_read (is_read),
    INDEX idx_school_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
