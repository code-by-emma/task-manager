<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo    = getDB();
$userId = currentUserId();

const TITLE_MAX_LENGTH       = 150;
const DESCRIPTION_MAX_LENGTH = 2000;
$allowedPriorities = ['low', 'medium', 'high'];
$allowedStatuses   = ['pending', 'completed'];

$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    setFlash('error', 'Task not found.');
    header('Location: dashboard.php');
    exit;
}

// Ownership check: the task must belong to the logged-in user.
$task = getOwnedTask($pdo, $taskId, $userId);

if (!$task) {
    setFlash('error', 'You are not authorized to access this task.');
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = [
    'title'       => $task['title'],
    'description' => $task['description'] ?? '',
    'priority'    => $task['priority'],
    'due_date'    => $task['due_date'] ?? '',
    'status'      => $task['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $dueDate     = trim($_POST['due_date'] ?? '');
    $status      = $_POST['status'] ?? 'pending';

    $old = compact('title', 'description', 'priority', 'due_date', 'status');

    if ($title === '') {
        $errors[] = 'Task title is required.';
    } elseif (mb_strlen($title) > TITLE_MAX_LENGTH) {
        $errors[] = 'Task title must be ' . TITLE_MAX_LENGTH . ' characters or fewer.';
    }

    if (mb_strlen($description) > DESCRIPTION_MAX_LENGTH) {
        $errors[] = 'Description must be ' . DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
    }

    if (!in_array($priority, $allowedPriorities, true)) {
        $errors[] = 'Please choose a valid priority.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Please choose a valid status.';
    }

    $normalizedDueDate = null;
    if ($dueDate !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $dueDate);
        if (!$d || $d->format('Y-m-d') !== $dueDate) {
            $errors[] = 'Please enter a valid due date.';
        } else {
            $normalizedDueDate = $dueDate;
        }
    }

    if (empty($errors)) {
        // If the status is transitioning to completed, stamp completed_at;
        // if moving back to pending, clear it.
        $completedAt = $task['completed_at'];
        if ($status === 'completed' && $task['status'] !== 'completed') {
            $completedAt = date('Y-m-d H:i:s');
        } elseif ($status === 'pending') {
            $completedAt = null;
        }

        $stmt = $pdo->prepare(
            'UPDATE tasks
             SET title = :title,
                 description = :description,
                 priority = :priority,
                 due_date = :due_date,
                 status = :status,
                 completed_at = :completed_at,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        try {
            $stmt->execute([
                'title'        => $title,
                'description'  => $description !== '' ? $description : null,
                'priority'     => $priority,
                'due_date'     => $normalizedDueDate,
                'status'       => $status,
                'completed_at' => $completedAt,
                'id'           => $taskId,
                'user_id'      => $userId, // Ownership enforced again here.
            ]);

            setFlash('success', 'Task updated successfully.');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $ex) {
            error_log('Update task failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Edit Task';
require_once __DIR__ . '/includes/header.php';
?>
<div class="form-page">
    <h1 class="form-page__title">Edit task</h1>

    <?php if ($errors): ?>
        <div class="alert alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="edit_task.php?id=<?= (int) $taskId ?>" method="post" class="card-form" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $taskId ?>">

        <div class="form-group">
            <label for="title">Task title</label>
            <input type="text" id="title" name="title" maxlength="<?= TITLE_MAX_LENGTH ?>"
                   value="<?= e($old['title']) ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5"
                      maxlength="<?= DESCRIPTION_MAX_LENGTH ?>"><?= e($old['description']) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <?php foreach ($allowedPriorities as $p): ?>
                        <option value="<?= $p ?>" <?= $old['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="due_date">Due date</label>
                <input type="date" id="due_date" name="due_date" value="<?= e($old['due_date']) ?>">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $old['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <a href="dashboard.php" class="btn btn--ghost">Cancel</a>
            <button type="submit" class="btn btn--primary">Save Changes</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
