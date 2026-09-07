<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo    = getDB();
$userId = currentUserId();

/* ---------------------------------------------------------------------
 * Statistics (always scoped to the current user)
 * ------------------------------------------------------------------- */
$statStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'completed') AS completed,
        SUM(priority = 'high' AND status = 'pending') AS high_priority_count
     FROM tasks
     WHERE user_id = :user_id"
);
$statStmt->execute(['user_id' => $userId]);
$stats = $statStmt->fetch();
$stats = [
    'total'         => (int) ($stats['total'] ?? 0),
    'pending'       => (int) ($stats['pending'] ?? 0),
    'completed'     => (int) ($stats['completed'] ?? 0),
    'high_priority' => (int) ($stats['high_priority_count'] ?? 0),
];

/* ---------------------------------------------------------------------
 * Filters / search / sort (all user input is whitelisted or bound as
 * a parameter -- never concatenated into SQL)
 * ------------------------------------------------------------------- */
$statusFilter   = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$search         = trim($_GET['q'] ?? '');
$sort           = $_GET['sort'] ?? 'newest';

$allowedStatuses   = ['all', 'pending', 'completed'];
$allowedPriorities = ['all', 'low', 'medium', 'high'];
$allowedSorts = [
    'newest'      => 'created_at DESC',
    'oldest'      => 'created_at ASC',
    'due_date'    => 'due_date IS NULL, due_date ASC',
    'priority'    => "FIELD(priority, 'high', 'medium', 'low')",
    'recently_completed' => 'completed_at IS NULL, completed_at DESC',
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
if (!in_array($priorityFilter, $allowedPriorities, true)) {
    $priorityFilter = 'all';
}
if (!array_key_exists($sort, $allowedSorts)) {
    $sort = 'newest';
}

$where  = ['user_id = :user_id'];
$params = ['user_id' => $userId];

if ($statusFilter !== 'all') {
    $where[] = 'status = :status';
    $params['status'] = $statusFilter;
}

if ($priorityFilter !== 'all') {
    $where[] = 'priority = :priority';
    $params['priority'] = $priorityFilter;
}

if ($search !== '') {
    $where[] = '(title LIKE :search1 OR description LIKE :search2)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);
$orderBySql = $allowedSorts[$sort];

$sql = "SELECT * FROM tasks WHERE $whereSql ORDER BY $orderBySql, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard__top">
        <div>
            <h1 class="dashboard__title">Your Tasks</h1>
            <p class="dashboard__subtitle">Stay on top of everything you need to do.</p>
        </div>
        <a href="create_task.php" class="btn btn--primary">+ New Task</a>
    </div>

    <section class="stats-grid" aria-label="Task statistics">
        <div class="stat-card">
            <span class="stat-card__value"><?= $stats['total'] ?></span>
            <span class="stat-card__label">Total Tasks</span>
        </div>
        <div class="stat-card stat-card--pending">
            <span class="stat-card__value"><?= $stats['pending'] ?></span>
            <span class="stat-card__label">Pending</span>
        </div>
        <div class="stat-card stat-card--completed">
            <span class="stat-card__value"><?= $stats['completed'] ?></span>
            <span class="stat-card__label">Completed</span>
        </div>
        <div class="stat-card stat-card--high">
            <span class="stat-card__value"><?= $stats['high_priority'] ?></span>
            <span class="stat-card__label">High Priority</span>
        </div>
    </section>

    <section class="filters-bar">
        <form action="dashboard.php" method="get" class="filters-form">
            <div class="filters-form__group">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" placeholder="Search title or description"
                       value="<?= e($search) ?>">
            </div>

            <div class="filters-form__group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="filters-form__group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="all" <?= $priorityFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                </select>
            </div>

            <div class="filters-form__group">
                <label for="sort">Sort by</label>
                <select id="sort" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                    <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Due date</option>
                    <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                    <option value="recently_completed" <?= $sort === 'recently_completed' ? 'selected' : '' ?>>Recently completed</option>
                </select>
            </div>

            <div class="filters-form__actions">
                <button type="submit" class="btn btn--secondary">Apply</button>
                <a href="dashboard.php" class="btn btn--ghost">Reset</a>
            </div>
        </form>
    </section>

    <section class="task-list">
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <?php if ($stats['total'] === 0): ?>
                    <h2>No tasks yet</h2>
                    <p>Create your first task to get started.</p>
                    <a href="create_task.php" class="btn btn--primary">+ Create Task</a>
                <?php else: ?>
                    <h2>No matching tasks</h2>
                    <p>Try adjusting your search or filters.</p>
                    <a href="dashboard.php" class="btn btn--ghost">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task):
                $priority = priorityBadge($task['priority']);
                $status   = statusBadge($task['status']);
                $isCompleted = $task['status'] === 'completed';
            ?>
            <article class="task-card <?= $isCompleted ? 'task-card--completed' : '' ?>">
                <div class="task-card__main">
                    <div class="task-card__header">
                        <h3 class="task-card__title"><?= e($task['title']) ?></h3>
                        <div class="task-card__badges">
                            <span class="badge <?= e($priority['class']) ?>"><?= e($priority['label']) ?></span>
                            <span class="badge <?= e($status['class']) ?>"><?= e($status['label']) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($task['description'])): ?>
                        <p class="task-card__description"><?= nl2br(e($task['description'])) ?></p>
                    <?php endif; ?>

                    <div class="task-card__meta">
                        <span>Due: <?= formatDate($task['due_date']) ?></span>
                        <span>Created: <?= formatDate($task['created_at']) ?></span>
                        <?php if ($isCompleted): ?>
                            <span>Completed: <?= formatDate($task['completed_at']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="task-card__actions">
                    <a href="edit_task.php?id=<?= (int) $task['id'] ?>" class="btn btn--small btn--edit">Edit</a>

                    <form action="toggle_task.php" method="post" class="inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                        <button type="submit" class="btn btn--small btn--secondary">
                            <?= $isCompleted ? 'Mark as Pending' : 'Complete' ?>
                        </button>
                    </form>

                    <form action="delete_task.php" method="post" class="inline-form"
                          data-confirm="Delete this task? This cannot be undone.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                        <button type="submit" class="btn btn--small btn--danger">Delete</button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
