<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old['email'] = $email;

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Always run password_verify (even against a dummy hash) to avoid
        // leaking, via response timing, whether the email exists.
        $hashToCheck = $user['password'] ?? '$2y$10$invalidsaltinvalidsaltinvalidsaltinvalidO';

        if ($user && password_verify($password, $hashToCheck)) {
            loginUser($user);
            setFlash('success', 'Welcome back!');
            header('Location: dashboard.php');
            exit;
        }

        $errors[] = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card__title-row">
            <h1 class="auth-card__title">Welcome back</h1>
            <button type="button" id="themeToggle" class="theme-toggle theme-toggle--small" aria-label="Toggle dark mode" title="Toggle dark mode">
                <span id="themeToggleIcon" aria-hidden="true">🌙</span>
            </button>
        </div>
        <p class="auth-card__subtitle">Log in to manage your tasks.</p>

        <?php if ($errors): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password">&#128065;</button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Log in</button>
        </form>

        <p class="auth-card__footer">Don't have an account? <a href="register.php">Register</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
