<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// The URL contains a secret token: don't leak it to other sites via Referer.
header('Referrer-Policy: no-referrer');

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

const MIN_PASSWORD_LENGTH = 8;

$pdo    = getDB();
$errors = [];

$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
$token = is_string($token) ? trim($token) : '';

$reset = findValidPasswordReset($pdo, $token);

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    } elseif (mb_strlen($newPassword) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    }

    if ($confirmPassword !== $newPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Atomically consume the token: if two requests race, only one
            // gets rowCount() === 1, so a link can never be used twice.
            $use = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
            $use->execute(['id' => $reset['id']]);

            if ($use->rowCount() !== 1) {
                $pdo->rollBack();
                setFlash('error', 'That reset link has already been used. Please request a new one.');
                header('Location: forgot_password.php');
                exit;
            }

            $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id')
                ->execute(['password' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $reset['user_id']]);

            // Invalidate any other outstanding links for this account.
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id')
                ->execute(['user_id' => $reset['user_id']]);

            $pdo->commit();

            setFlash('success', 'Your password has been reset. Please log in.');
            header('Location: login.php');
            exit;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Password reset failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card__title-row">
            <h1 class="auth-card__title">Choose a new password</h1>
            <button type="button" id="themeToggle" class="theme-toggle theme-toggle--small" aria-label="Toggle dark mode" title="Toggle dark mode">
                <span id="themeToggleIcon" aria-hidden="true">🌙</span>
            </button>
        </div>

        <?php if (!$reset): ?>
            <div class="alert alert--error">
                This reset link is invalid or has expired.
            </div>
            <p class="auth-card__subtitle">Reset links expire after <?= PASSWORD_RESET_TTL_MINUTES ?> minutes and only work once.</p>
            <a href="forgot_password.php" class="btn btn--primary btn--block">Request a new link</a>
            <p class="auth-card__footer"><a href="login.php">Back to log in</a></p>
        <?php else: ?>
            <p class="auth-card__subtitle">Enter a new password for your account.</p>

            <?php if ($errors): ?>
                <div class="alert alert--error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="reset_password.php" method="post" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group">
                    <label for="new_password">New password</label>
                    <div class="password-field">
                        <input type="password" id="new_password" name="new_password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required autofocus>
                        <button type="button" class="password-toggle" data-target="new_password" aria-label="Show password">&#128065;</button>
                    </div>
                    <small class="form-hint">At least <?= MIN_PASSWORD_LENGTH ?> characters.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password">&#128065;</button>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Reset password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
