-- sql/migrations/20260222_add_mobile_push_tokens.sql
CREATE TABLE IF NOT EXISTS mobile_push_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(16) NOT NULL DEFAULT 'fcm',
    platform VARCHAR(16) NOT NULL,
    device_id VARCHAR(191) NULL,
    token TEXT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    app_version VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NULL,
    UNIQUE KEY unique_provider_token_hash (provider, token_hash),
    KEY idx_mobile_push_user (user_id),
    KEY idx_mobile_push_active (active),
    CONSTRAINT fk_mobile_push_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
