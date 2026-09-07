<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

requireValidCsrf();

$pdo    = getDB();
$userId = currentUserId();
$taskId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    setFlash('error', 'Task not found.');
    header('Location: dashboard.php');
    exit;
}

// Confirm ownership before deleting.
$task = getOwnedTask($pdo, $taskId, $userId);

if (!$task) {
    setFlash('error', 'You are not authorized to access this task.');
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :user_id');

try {
    $stmt->execute(['id' => $taskId, 'user_id' => $userId]);
    setFlash('success', 'Task deleted successfully.');
} catch (PDOException $ex) {
    error_log('Delete task failed: ' . $ex->getMessage());
    setFlash('error', 'Something went wrong. Please try again.');
}

header('Location: dashboard.php');
exit;
