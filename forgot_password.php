<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors    = [];
$submitted = false;
$old       = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $email = trim($_POST['email'] ?? '');
    $old['email'] = $email;

    if ($email === '' || !isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $pdo = getDB();

        try {
            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Cooldown: ignore repeat requests for the same account so
                // this form can't be used to flood someone's inbox.
                $recent = $pdo->prepare(
                    'SELECT COUNT(*) FROM password_resets
                     WHERE user_id = :user_id AND created_at > (NOW() - INTERVAL ' . PASSWORD_RESET_COOLDOWN_SECONDS . ' SECOND)'
                );
                $recent->execute(['user_id' => $user['id']]);

                if ((int) $recent->fetchColumn() === 0) {
                    // Housekeeping: drop this user's older links and any expired ones,
                    // so only the newest link ever works.
                    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id OR expires_at < NOW()')
                        ->execute(['user_id' => $user['id']]);

                    // 256 bits of randomness; only its hash is stored.
                    $token = bin2hex(random_bytes(32));

                    $pdo->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at)
                         VALUES (:user_id, :hash, DATE_ADD(NOW(), INTERVAL ' . PASSWORD_RESET_TTL_MINUTES . ' MINUTE))'
                    )->execute(['user_id' => $user['id'], 'hash' => hashResetToken($token)]);

                    $link = appBaseUrl() . '/reset_password.php?token=' . $token;

                    $body = "Hi {$user['name']},\n\n"
                          . "We received a request to reset your TaskFlow password. "
                          . "Use the link below to choose a new one:\n\n"
                          . "{$link}\n\n"
                          . "This link expires in " . PASSWORD_RESET_TTL_MINUTES . " minutes and can only be used once.\n\n"
                          . "If you didn't ask for this, you can safely ignore this email; your password won't change.\n";

                    sendMail($user['email'], 'Reset your TaskFlow password', $body);
                }
            }

            // Same response whether or not the email is registered, so this
            // form can't be used to discover who has an account.
            $submitted = true;
        } catch (PDOException $ex) {
            error_log('Forgot password failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card__title-row">
            <h1 class="auth-card__title">Forgot your password?</h1>
            <button type="button" id="themeToggle" class="theme-toggle theme-toggle--small" aria-label="Toggle dark mode" title="Toggle dark mode">
                <span id="themeToggleIcon" aria-hidden="true">🌙</span>
            </button>
        </div>

        <?php if ($submitted): ?>
            <div class="alert alert--success">
                If an account exists for <strong><?= e($old['email']) ?></strong>, we've sent a link to reset
                its password. It expires in <?= PASSWORD_RESET_TTL_MINUTES ?> minutes.
            </div>
            <p class="auth-card__subtitle">Nothing there? Check your spam folder, or wait a minute and try again.</p>
            <p class="auth-card__footer"><a href="login.php">Back to log in</a></p>
        <?php else: ?>
            <p class="auth-card__subtitle">Enter your email and we'll send you a link to choose a new one.</p>

            <?php if ($errors): ?>
                <div class="alert alert--error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="forgot_password.php" method="post" novalidate>
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required autofocus>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Send reset link</button>
            </form>

            <p class="auth-card__footer">Remembered it? <a href="login.php">Log in</a></p>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
