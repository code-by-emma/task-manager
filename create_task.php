<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

const TITLE_MAX_LENGTH       = 150;
const DESCRIPTION_MAX_LENGTH = 2000;
$allowedPriorities = ['low', 'medium', 'high'];

$errors = [];
$old = [
    'title'       => '',
    'description' => '',
    'priority'    => 'medium',
    'due_date'    => '',
    'category_id' => '',
];

$pdo        = getDB();
$categories = getUserCategories($pdo, currentUserId());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $dueDate     = trim($_POST['due_date'] ?? '');

    $categoryRaw = $_POST['category_id'] ?? '';

    $old = [
        'title'       => $title,
        'description' => $description,
        'priority'    => $priority,
        'due_date'    => $dueDate,
        'category_id' => is_string($categoryRaw) ? $categoryRaw : '',
    ];

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

    // The category (if any) must belong to the logged-in user.
    [$categoryId, $categoryError] = validateTaskCategory($pdo, $categoryRaw, currentUserId());
    if ($categoryError) {
        $errors[] = $categoryError;
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
        // user_id ALWAYS comes from the session, never from the form.
        $stmt = $pdo->prepare(
            'INSERT INTO tasks (user_id, category_id, title, description, status, priority, due_date, created_at, updated_at)
             VALUES (:user_id, :category_id, :title, :description, :status, :priority, :due_date, NOW(), NOW())'
        );

        try {
            $stmt->execute([
                'user_id'     => currentUserId(),
                'category_id' => $categoryId,
                'title'       => $title,
                'description' => $description !== '' ? $description : null,
                'status'      => 'pending',
                'priority'    => $priority,
                'due_date'    => $normalizedDueDate,
            ]);

            setFlash('success', 'Task created successfully.');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $ex) {
            error_log('Create task failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Create Task';
require_once __DIR__ . '/includes/header.php';
?>
<div class="form-page">
    <h1 class="form-page__title">Create a new task</h1>

    <?php if ($errors): ?>
        <div class="alert alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="create_task.php" method="post" class="card-form" novalidate>
        <?= csrfField() ?>

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

        <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">Uncategorized</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (string) $old['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="form-hint"><a href="categories.php">Manage categories</a></small>
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
        </div>

        <div class="form-actions">
            <a href="dashboard.php" class="btn btn--ghost">Cancel</a>
            <button type="submit" class="btn btn--primary">Create Task</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
