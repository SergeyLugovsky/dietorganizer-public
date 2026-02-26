-- sql/migrations/20260109_add_meal_reminders.sql
ALTER TABLE users
    ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Kyiv';

ALTER TABLE meal_categories
    ADD COLUMN reminder_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN reminder_time TIME NULL,
    ADD COLUMN reminder_days VARCHAR(32) NULL;

CREATE TABLE push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NULL,
    UNIQUE KEY unique_user_endpoint (user_id, endpoint(255)),
    KEY idx_push_user (user_id),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reminder_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    fire_date DATE NOT NULL,
    fire_time TIME NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reminder_log (user_id, category_id, fire_date, fire_time),
    KEY idx_reminder_user (user_id),
    KEY idx_reminder_category (category_id),
    CONSTRAINT fk_reminder_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_logs_category FOREIGN KEY (category_id) REFERENCES meal_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
