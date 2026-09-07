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
-- tasks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
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

    INDEX idx_tasks_user_id (user_id),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_priority (priority),
    INDEX idx_tasks_due_date (due_date),
    INDEX idx_tasks_user_status (user_id, status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Optional sample data.
-- Sample password for both users below is: Password123
-- (hash generated with PHP's password_hash(), bcrypt algorithm)
-- ------------------------------------------------------------
INSERT INTO users (name, email, password, created_at, updated_at) VALUES
('Ada Lovelace', 'ada@example.com', '$2b$10$zy3sphDDW1I8Y.4YD2GQG.zp4yfTJQDyTTcy7KwUtq2twN3OjZp12', NOW(), NOW()),
('Grace Hopper', 'grace@example.com', '$2b$10$zy3sphDDW1I8Y.4YD2GQG.zp4yfTJQDyTTcy7KwUtq2twN3OjZp12', NOW(), NOW());

INSERT INTO tasks (user_id, title, description, status, priority, due_date, created_at, updated_at, completed_at) VALUES
(1, 'Design the analytical engine punch cards', 'Draft the layout for the new punch card system.', 'pending', 'high', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NOW(), NOW(), NULL),
(1, 'Write documentation notes', 'Summarize the algorithm for calculating Bernoulli numbers.', 'completed', 'medium', DATE_SUB(CURDATE(), INTERVAL 2 DAY), NOW(), NOW(), NOW()),
(1, 'Review correspondence', NULL, 'pending', 'low', NULL, NOW(), NOW(), NULL),
(2, 'Prepare compiler demo', 'Show the working prototype to the review board.', 'pending', 'high', DATE_ADD(CURDATE(), INTERVAL 1 DAY), NOW(), NOW(), NULL),
(2, 'Standardize terminology', 'Draft a glossary of common programming terms.', 'pending', 'medium', DATE_ADD(CURDATE(), INTERVAL 10 DAY), NOW(), NOW(), NULL);
