-- sql/schema.sql
CREATE DATABASE IF NOT EXISTS diet_organizer
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE diet_organizer;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reminder_logs;
DROP TABLE IF EXISTS mobile_push_tokens;
DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS diary_entries;
DROP TABLE IF EXISTS foods;
DROP TABLE IF EXISTS meal_categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    google_sub VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Kyiv',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_google_sub (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meal_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    reminder_enabled TINYINT(1) NOT NULL DEFAULT 0,
    reminder_time TIME NULL,
    reminder_days VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_category (user_id, name),
    KEY idx_meal_categories_user (user_id),
    CONSTRAINT fk_meal_categories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE mobile_push_tokens (
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

CREATE TABLE foods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    calories_per_100g DECIMAL(6,2) NOT NULL DEFAULT 0,
    proteins_per_100g DECIMAL(6,2) NOT NULL DEFAULT 0,
    fats_per_100g DECIMAL(6,2) NOT NULL DEFAULT 0,
    carbs_per_100g DECIMAL(6,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_food (user_id, name),
    KEY idx_foods_user (user_id),
    CONSTRAINT fk_foods_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diary_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    food_id INT UNSIGNED NOT NULL,
    meal_category_id INT UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    quantity_grams DECIMAL(8,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_date (user_id, entry_date),
    KEY idx_diary_food (food_id),
    KEY idx_diary_category (meal_category_id),
    CONSTRAINT fk_diary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_diary_food FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE,
    CONSTRAINT fk_diary_category FOREIGN KEY (meal_category_id) REFERENCES meal_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
