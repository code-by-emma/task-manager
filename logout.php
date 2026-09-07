<?php
require_once __DIR__ . '/includes/auth.php';

// Logging out is a state-changing action, so it must be a POST request
// protected by a CSRF token, not a plain link.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    logoutUser();
}

header('Location: login.php');
exit;
