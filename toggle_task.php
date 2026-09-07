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

// Confirm ownership before toggling.
$task = getOwnedTask($pdo, $taskId, $userId);

if (!$task) {
    setFlash('error', 'You are not authorized to access this task.');
    header('Location: dashboard.php');
    exit;
}

if ($task['status'] === 'completed') {
    $newStatus    = 'pending';
    $completedAt  = null;
    $flashMessage = 'Task marked as pending.';
} else {
    $newStatus    = 'completed';
    $completedAt  = date('Y-m-d H:i:s');
    $flashMessage = 'Task completed.';
}

$stmt = $pdo->prepare(
    'UPDATE tasks
     SET status = :status, completed_at = :completed_at, updated_at = NOW()
     WHERE id = :id AND user_id = :user_id'
);

try {
    $stmt->execute([
        'status'       => $newStatus,
        'completed_at' => $completedAt,
        'id'           => $taskId,
        'user_id'      => $userId,
    ]);
    setFlash('success', $flashMessage);
} catch (PDOException $ex) {
    error_log('Toggle task failed: ' . $ex->getMessage());
    setFlash('error', 'Something went wrong. Please try again.');
}

header('Location: dashboard.php');
exit;
