-- Migration: add_login_attempts
-- Purpose: Tracks failed login attempts per IP address for rate limiting.
--          login.php checks this table before allowing authentication.
-- Constants used: MAX_LOGIN_ATTEMPTS (5), LOGIN_LOCKOUT_TIME (900 seconds)

CREATE TABLE IF NOT EXISTS login_attempts (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    ip_address      VARCHAR(45)      NOT NULL,               -- IPv4 or IPv6
    username_attempted VARCHAR(100)  NOT NULL DEFAULT '',
    attempted_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ip_attempted_at (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: scheduled event to purge old records (keeps the table small).
-- Enable the MySQL Event Scheduler (event_scheduler=ON in my.cnf) before using this.
-- CREATE EVENT IF NOT EXISTS purge_old_login_attempts
--     ON SCHEDULE EVERY 1 HOUR
--     DO DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
