-- ============================================================
-- Migration: add task categories to an EXISTING TaskFlow database.
-- Run ONCE (fresh installs using database.sql already have this):
--   mysql -u root -p < migrate_add_categories.sql
-- ============================================================

USE task_manager;

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

ALTER TABLE tasks
    ADD COLUMN category_id INT UNSIGNED NULL AFTER user_id,
    ADD INDEX idx_tasks_category_id (category_id),
    ADD CONSTRAINT fk_tasks_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Give every existing user the default categories.
-- Existing tasks stay "Uncategorized" until the user assigns one.
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
