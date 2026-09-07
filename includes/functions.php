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
