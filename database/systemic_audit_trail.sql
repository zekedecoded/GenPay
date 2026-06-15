CREATE TABLE IF NOT EXISTS systemic_audit_trail (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('GJC Admin', 'Student', 'Merchant', 'Vendor/Staff') NOT NULL,
    action_type ENUM('LOGIN', 'LOGOUT', 'PASSWORD_CHANGE', 'TRANSACTION', 'MENU_MUTATION', 'STALL_UPDATE') NOT NULL,
    stall_id VARCHAR(20) NULL,
    affected_table VARCHAR(50) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_timestamp (timestamp),
    INDEX idx_audit_role_action (user_role, action_type),
    CONSTRAINT fk_systemic_audit_user
        FOREIGN KEY (user_id) REFERENCES users(userID) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE USER IF NOT EXISTS 'gjc_audit_writer'@'localhost' IDENTIFIED BY 'replace_with_strong_password';
GRANT INSERT, SELECT ON ewallet.systemic_audit_trail TO 'gjc_audit_writer'@'localhost';
FLUSH PRIVILEGES;
