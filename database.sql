-- ============================================================
-- Task Manager - Database Schema
-- Import this file into MySQL to create the database and tables.
--   mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS task_manager
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE task_manager;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL,
    password   VARCHAR(255)  NOT NULL COMMENT 'Hashed with password_hash(), never plain text',
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- categories (per-user, e.g. Work / Personal / Shopping)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(50)  NOT NULL,
    color      VARCHAR(20)  NOT NULL DEFAULT 'gray' COMMENT 'Palette key, validated in PHP',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_categories_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_categories_user_name (user_id, name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- tasks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    category_id  INT UNSIGNED NULL,
    title        VARCHAR(150) NOT NULL,
    description  TEXT NULL,
    status       ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
    priority     ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    due_date     DATE NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,

    CONSTRAINT fk_tasks_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE   -- Deleting a user removes their tasks too.
        ON UPDATE CASCADE,

    CONSTRAINT fk_tasks_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL  -- Deleting a category keeps its tasks (as "Uncategorized").
        ON UPDATE CASCADE,

    INDEX idx_tasks_user_id (user_id),
    INDEX idx_tasks_category_id (category_id),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_priority (priority),
    INDEX idx_tasks_due_date (due_date),
    INDEX idx_tasks_user_status (user_id, status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- password_resets (one-time "forgot password" tokens)
-- Only a SHA-256 hash of the token is stored, never the token itself.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_password_resets_token (token_hash),
    INDEX idx_password_resets_user (user_id),
    INDEX idx_password_resets_expires (expires_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Optional sample data.
-- Sample password for both users below is: Password123
-- (hash generated with PHP's password_hash(), bcrypt algorithm)
-- ------------------------------------------------------------
INSERT INTO users (name, email, password, created_at, updated_at) VALUES
('Ada Lovelace', 'ada@example.com', '$2b$10$zy3sphDDW1I8Y.4YD2GQG.zp4yfTJQDyTTcy7KwUtq2twN3OjZp12', NOW(), NOW()),
('Grace Hopper', 'grace@example.com', '$2b$10$zy3sphDDW1I8Y.4YD2GQG.zp4yfTJQDyTTcy7KwUtq2twN3OjZp12', NOW(), NOW());

-- Default categories for every user (same list the app seeds on registration).
INSERT IGNORE INTO categories (user_id, name, color)
SELECT u.id, d.name, d.color
FROM users u
CROSS JOIN (
    SELECT 'Work' AS name, 'blue' AS color
    UNION ALL SELECT 'Personal', 'green'
    UNION ALL SELECT 'Shopping', 'orange'
    UNION ALL SELECT 'Health',   'red'
    UNION ALL SELECT 'Study',    'purple'
) d;

INSERT INTO tasks (user_id, category_id, title, description, status, priority, due_date, created_at, updated_at, completed_at) VALUES
(1, (SELECT id FROM categories WHERE user_id = 1 AND name = 'Work'),     'Design the analytical engine punch cards', 'Draft the layout for the new punch card system.', 'pending', 'high', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NOW(), NOW(), NULL),
(1, (SELECT id FROM categories WHERE user_id = 1 AND name = 'Work'),     'Write documentation notes', 'Summarize the algorithm for calculating Bernoulli numbers.', 'completed', 'medium', DATE_SUB(CURDATE(), INTERVAL 2 DAY), NOW(), NOW(), NOW()),
(1, (SELECT id FROM categories WHERE user_id = 1 AND name = 'Personal'), 'Review correspondence', NULL, 'pending', 'low', NULL, NOW(), NOW(), NULL),
(2, (SELECT id FROM categories WHERE user_id = 2 AND name = 'Work'),     'Prepare compiler demo', 'Show the working prototype to the review board.', 'pending', 'high', DATE_ADD(CURDATE(), INTERVAL 1 DAY), NOW(), NOW(), NULL),
(2, (SELECT id FROM categories WHERE user_id = 2 AND name = 'Study'),    'Standardize terminology', 'Draft a glossary of common programming terms.', 'pending', 'medium', DATE_ADD(CURDATE(), INTERVAL 10 DAY), NOW(), NOW(), NULL);
