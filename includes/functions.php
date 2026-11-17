<?php
/**
 * General-purpose helper functions shared across pages.
 */

/**
 * Shorthand for htmlspecialchars() with sensible defaults, used to
 * escape all user-generated output before it hits the page (XSS
 * protection).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Stores a one-time flash message in the session to be displayed after
 * a redirect (e.g. "Task created successfully.").
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieves and clears any pending flash messages.
 */
function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Validates an email address.
 */
function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Formats a date/datetime string for display. Returns an em dash if the
 * value is empty/null.
 */
function formatDate(?string $value, string $format = 'M j, Y'): string
{
    if (empty($value)) {
        return '&mdash;';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '&mdash;';
}

/**
 * Returns a human readable label + CSS class for a priority value.
 */
function priorityBadge(string $priority): array
{
    $map = [
        'low'    => ['label' => 'Low',    'class' => 'badge-low'],
        'medium' => ['label' => 'Medium', 'class' => 'badge-medium'],
        'high'   => ['label' => 'High',   'class' => 'badge-high'],
    ];
    return $map[$priority] ?? ['label' => ucfirst($priority), 'class' => 'badge-medium'];
}

/**
 * Returns a human readable label + CSS class for a status value.
 */
function statusBadge(string $status): array
{
    $map = [
        'pending'   => ['label' => 'Pending',   'class' => 'badge-pending'],
        'completed' => ['label' => 'Completed', 'class' => 'badge-completed'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-pending'];
}

/**
 * Fetches a single task belonging to the given user, or null if it
 * doesn't exist / doesn't belong to that user. Centralizing this check
 * means every page that needs "does this task belong to me?" logic uses
 * the exact same, verified query.
 */
function getOwnedTask(PDO $pdo, int $taskId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['id' => $taskId, 'user_id' => $userId]);
    $task = $stmt->fetch();
    return $task ?: null;
}

/* ---------------------------------------------------------------------
 * Categories
 * ------------------------------------------------------------------- */

const CATEGORY_NAME_MAX_LENGTH = 50;
const CATEGORY_MAX_PER_USER    = 30;

/**
 * The allowed category colors (palette key => label). The key maps to a
 * CSS class (.cat-<key>) so light/dark theme variants live in the
 * stylesheet. Anything not in this list is rejected.
 */
function categoryColors(): array
{
    return [
        'blue'   => 'Blue',
        'green'  => 'Green',
        'purple' => 'Purple',
        'pink'   => 'Pink',
        'orange' => 'Orange',
        'red'    => 'Red',
        'teal'   => 'Teal',
        'gray'   => 'Gray',
    ];
}

/**
 * Categories every new user starts with: [name, color].
 */
function defaultCategories(): array
{
    return [
        ['Work',     'blue'],
        ['Personal', 'green'],
        ['Shopping', 'orange'],
        ['Health',   'red'],
        ['Study',    'purple'],
    ];
}

function seedDefaultCategories(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO categories (user_id, name, color) VALUES (:user_id, :name, :color)'
    );
    foreach (defaultCategories() as [$name, $color]) {
        $stmt->execute(['user_id' => $userId, 'name' => $name, 'color' => $color]);
    }
}

/**
 * All categories belonging to a user, alphabetically.
 */
function getUserCategories(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, name, color FROM categories WHERE user_id = :user_id ORDER BY name ASC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Fetches one category only if it belongs to the given user.
 */
function getOwnedCategory(PDO $pdo, int $categoryId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);
    $category = $stmt->fetch();
    return $category ?: null;
}

/**
 * CSS class for a category color, falling back to gray.
 */
function categoryClass(?string $color): string
{
    return 'cat-' . (array_key_exists((string) $color, categoryColors()) ? $color : 'gray');
}

/**
 * Validates the optional category_id submitted from a task form.
 * Returns [categoryId|null, errorMessage|null]. The category must belong
 * to the logged-in user, so a tampered form can't attach someone else's.
 */
function validateTaskCategory(PDO $pdo, $raw, int $userId): array
{
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') {
        return [null, null];
    }
    $id = filter_var($raw, FILTER_VALIDATE_INT);
    if ($id === false || !getOwnedCategory($pdo, $id, $userId)) {
        return [null, 'Please choose a valid category.'];
    }
    return [$id, null];
}

/* ---------------------------------------------------------------------
 * Password reset
 * ------------------------------------------------------------------- */

const PASSWORD_RESET_TTL_MINUTES     = 60;
const PASSWORD_RESET_COOLDOWN_SECONDS = 60;

/**
 * Only this hash is stored in the database. If the table ever leaks,
 * the hashes can't be turned back into working reset links.
 */
function hashResetToken(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Looks up an unused, unexpired reset by its raw token.
 * Returns ['id' => ..., 'user_id' => ...] or null. Expiry is compared
 * using the database clock (the same one that set it).
 */
function findValidPasswordReset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id FROM password_resets
         WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute(['hash' => hashResetToken($token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}
