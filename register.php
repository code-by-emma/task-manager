<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

const MIN_PASSWORD_LENGTH = 8;
const MAX_NAME_LENGTH = 100;
const MAX_EMAIL_LENGTH = 150;

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $name            = trim($_POST['name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $old['name']  = $name;
    $old['email'] = $email;

    // ---- Server-side validation (never trust the client) ----
    if ($name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($name) > MAX_NAME_LENGTH) {
        $errors[] = 'Full name is too long.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > MAX_EMAIL_LENGTH) {
        $errors[] = 'Email is too long.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    }

    if ($confirmPassword !== $password) {
        $errors[] = 'Passwords do not match.';
    }

    $pdo = getDB();

    if (empty($errors)) {
        // Check for duplicate email.
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, created_at, updated_at)
             VALUES (:name, :email, :password, NOW(), NOW())'
        );

        try {
            $stmt->execute([
                'name'     => $name,
                'email'    => $email,
                'password' => $hashedPassword,
            ]);

            // Give the new user a starter set of categories (Work, Personal, ...).
            seedDefaultCategories($pdo, (int) $pdo->lastInsertId());

            setFlash('success', 'Account created successfully. Please log in.');
            header('Location: login.php');
            exit;
        } catch (PDOException $ex) {
            error_log('Registration failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card__title-row">
            <h1 class="auth-card__title">Create your account</h1>
            <button type="button" id="themeToggle" class="theme-toggle theme-toggle--small" aria-label="Toggle dark mode" title="Toggle dark mode">
                <span id="themeToggleIcon" aria-hidden="true">🌙</span>
            </button>
        </div>
        <p class="auth-card__subtitle">Start organizing your tasks in minutes.</p>

        <?php if ($errors): ?>
            <div class="alert alert--error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="post" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" maxlength="<?= MAX_NAME_LENGTH ?>"
                       value="<?= e($old['name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="<?= MAX_EMAIL_LENGTH ?>"
                       value="<?= e($old['email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password">&#128065;</button>
                </div>
                <small class="form-hint">At least <?= MIN_PASSWORD_LENGTH ?> characters.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password">&#128065;</button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Create account</button>
        </form>

        <p class="auth-card__footer">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
