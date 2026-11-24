-- MySQL 8.4 schema for the contact form, admin accounts, and rate limiting.
CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    totp_secret_encrypted VARBINARY(128) NOT NULL,
    last_totp_step BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS contact_submissions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name_encrypted VARBINARY(512) NOT NULL,
    email_encrypted VARBINARY(512) NOT NULL,
    message_encrypted VARBINARY(20512) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_contact_submissions_recent (created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS rate_limit_counters (
    counter_key BINARY(32) NOT NULL,
    window_started_at BIGINT UNSIGNED NOT NULL,
    hits INT UNSIGNED NOT NULL,
    PRIMARY KEY (counter_key),
    KEY idx_rate_limit_counters_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
