<?php
/**
 * Authentication / session helper functions.
 *
 * This file is included at the top of every protected page. It must be
 * included AFTER session_start() has been called (or it calls it itself
 * if a session hasn't been started yet).
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie settings before starting the session.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // JS cannot read the session cookie.
        'samesite' => 'Lax',  // Basic CSRF mitigation for cookies.
    ]);
    session_start();
}

/**
 * Returns true if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Redirects to the login page if the visitor is not authenticated.
 * Call this at the top of every page that requires a logged-in user.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Safely returns the authenticated user's ID, or null if not logged in.
 * All task queries MUST use this value rather than any client-supplied
 * user id.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserName(): string
{
    return $_SESSION['user_name'] ?? '';
}

function currentUserEmail(): string
{
    return $_SESSION['user_email'] ?? '';
}

/**
 * Logs a user in: regenerates the session id (to prevent session
 * fixation) and stores identifying info in the session.
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
}

/**
 * Destroys the session completely.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/* ---------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------- */

/**
 * Returns the current CSRF token, generating one if it doesn't exist yet.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input field containing the CSRF token, ready to be
 * echoed inside a <form>.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Validates a submitted CSRF token using a timing-safe comparison.
 * Call this at the top of every POST handler.
 */
function validateCsrf(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Convenience wrapper: dies with a 403 if the CSRF token in $_POST is
 * missing or invalid.
 */
function requireValidCsrf(): void
{
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}
