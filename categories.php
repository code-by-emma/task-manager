<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo    = getDB();
$userId = currentUserId();
$colors = categoryColors();

$errors = [];
$old    = ['name' => '', 'color' => 'blue'];

/**
 * Validates a submitted name + color. Returns [name, color, errors].
 */
function validateCategoryInput(array $colors): array
{
    $name  = trim($_POST['name'] ?? '');
    $color = $_POST['color'] ?? 'gray';
    $errs  = [];

    if ($name === '') {
        $errs[] = 'Category name is required.';
    } elseif (mb_strlen($name) > CATEGORY_NAME_MAX_LENGTH) {
        $errs[] = 'Category name must be ' . CATEGORY_NAME_MAX_LENGTH . ' characters or fewer.';
    }

    if (!is_string($color) || !array_key_exists($color, $colors)) {
        $errs[] = 'Please choose a valid color.';
        $color = 'gray';
    }

    return [$name, $color, $errs];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            [$name, $color, $errors] = validateCategoryInput($colors);
            $old = ['name' => $name, 'color' => $color];

            if (empty($errors)) {
                $count = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = :user_id');
                $count->execute(['user_id' => $userId]);
                if ((int) $count->fetchColumn() >= CATEGORY_MAX_PER_USER) {
                    $errors[] = 'You can have up to ' . CATEGORY_MAX_PER_USER . ' categories.';
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare('INSERT INTO categories (user_id, name, color) VALUES (:user_id, :name, :color)');
                try {
                    $stmt->execute(['user_id' => $userId, 'name' => $name, 'color' => $color]);
                    setFlash('success', 'Category created.');
                    header('Location: categories.php');
                    exit;
                } catch (PDOException $ex) {
                    if ($ex->getCode() === '23000') { // unique (user_id, name) violated
                        $errors[] = 'You already have a category with that name.';
                    } else {
                        throw $ex;
                    }
                }
            }
        } elseif ($action === 'update') {
            $categoryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $category   = $categoryId ? getOwnedCategory($pdo, $categoryId, $userId) : null;

            if (!$category) {
                setFlash('error', 'Category not found.');
                header('Location: categories.php');
                exit;
            }

            [$name, $color, $errors] = validateCategoryInput($colors);

            if (empty($errors)) {
                $stmt = $pdo->prepare(
                    'UPDATE categories SET name = :name, color = :color WHERE id = :id AND user_id = :user_id'
                );
                try {
                    $stmt->execute(['name' => $name, 'color' => $color, 'id' => $categoryId, 'user_id' => $userId]);
                    setFlash('success', 'Category updated.');
                    header('Location: categories.php');
                    exit;
                } catch (PDOException $ex) {
                    if ($ex->getCode() === '23000') {
                        $errors[] = 'You already have a category with that name.';
                    } else {
                        throw $ex;
                    }
                }
            }
        } elseif ($action === 'delete') {
            $categoryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $category   = $categoryId ? getOwnedCategory($pdo, $categoryId, $userId) : null;

            if (!$category) {
                setFlash('error', 'Category not found.');
            } else {
                // Tasks in this category are kept; the FK sets their category_id to NULL.
                $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);
                setFlash('success', 'Category deleted. Its tasks are now uncategorized.');
            }
            header('Location: categories.php');
            exit;
        }
    } catch (PDOException $ex) {
        error_log('Category action failed: ' . $ex->getMessage());
        $errors[] = 'Something went wrong. Please try again.';
    }
}

// Categories with how many tasks use each.
$stmt = $pdo->prepare(
    'SELECT c.id, c.name, c.color, COUNT(t.id) AS task_count
     FROM categories c
     LEFT JOIN tasks t ON t.category_id = c.id
     WHERE c.user_id = :user_id
     GROUP BY c.id, c.name, c.color
     ORDER BY c.name ASC'
);
$stmt->execute(['user_id' => $userId]);
$categories = $stmt->fetchAll();

$pageTitle = 'Categories';
require_once __DIR__ . '/includes/header.php';
?>
<div class="form-page form-page--wide">
    <h1 class="form-page__title">Categories</h1>
    <p class="dashboard__subtitle">Group your tasks (Work, Personal, Shopping...) so they're easy to tell apart.</p>

    <?php if ($errors): ?>
        <div class="alert alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="categories.php" method="post" class="card-form category-add" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div class="form-group">
            <label for="new_name">New category</label>
            <input type="text" id="new_name" name="name" maxlength="<?= CATEGORY_NAME_MAX_LENGTH ?>"
                   placeholder="e.g. Side project" value="<?= e($old['name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="new_color">Color</label>
            <select id="new_color" name="color">
                <?php foreach ($colors as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $old['color'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn--primary">Add</button>
    </form>

    <section class="category-list" aria-label="Your categories">
        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <h2>No categories yet</h2>
                <p>Add one above to start organizing your tasks.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($categories as $cat): ?>
            <div class="category-row">
                <form action="categories.php" method="post" class="category-row__edit" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">

                    <span class="badge badge-category <?= e(categoryClass($cat['color'])) ?>"><?= e($cat['name']) ?></span>

                    <input type="text" name="name" maxlength="<?= CATEGORY_NAME_MAX_LENGTH ?>"
                           value="<?= e($cat['name']) ?>" aria-label="Category name" required>

                    <select name="color" aria-label="Category color">
                        <?php foreach ($colors as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $cat['color'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn--small btn--secondary">Save</button>
                </form>

                <span class="category-row__count">
                    <a href="dashboard.php?category=<?= (int) $cat['id'] ?>"><?= (int) $cat['task_count'] ?> task<?= (int) $cat['task_count'] === 1 ? '' : 's' ?></a>
                </span>

                <form action="categories.php" method="post" class="inline-form"
                      data-confirm="Delete this category? Its tasks will be kept but become uncategorized.">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                    <button type="submit" class="btn btn--small btn--danger">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="form-actions">
        <a href="dashboard.php" class="btn btn--ghost">Back to tasks</a>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
