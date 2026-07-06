CREATE TABLE IF NOT EXISTS giving_donations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) DEFAULT NULL,
    gift_type VARCHAR(40) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'gbp',
    payment_provider ENUM('stripe', 'paypal') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    external_id VARCHAR(255) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_giving_status (payment_status),
    INDEX idx_giving_created (created_at),
    INDEX idx_giving_external (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
