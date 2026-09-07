<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo    = getDB();
$userId = currentUserId();

const MIN_PASSWORD_LENGTH = 8;
const MAX_NAME_LENGTH = 100;
const MAX_EMAIL_LENGTH = 150;

$stmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    // Session points to a user that no longer exists.
    logoutUser();
    header('Location: login.php');
    exit;
}

$profileErrors  = [];
$passwordErrors = [];

$oldProfile = ['name' => $user['name'], 'email' => $user['email']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    requireValidCsrf();

    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $oldProfile = ['name' => $name, 'email' => $email];

    if ($name === '') {
        $profileErrors[] = 'Full name is required.';
    } elseif (mb_strlen($name) > MAX_NAME_LENGTH) {
        $profileErrors[] = 'Full name is too long.';
    }

    if ($email === '') {
        $profileErrors[] = 'Email is required.';
    } elseif (!isValidEmail($email)) {
        $profileErrors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > MAX_EMAIL_LENGTH) {
        $profileErrors[] = 'Email is too long.';
    }

    if (empty($profileErrors) && strcasecmp($email, $user['email']) !== 0) {
        $dupStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $dupStmt->execute(['email' => $email, 'id' => $userId]);
        if ($dupStmt->fetch()) {
            $profileErrors[] = 'This email is already registered.';
        }
    }

    if (empty($profileErrors)) {
        $update = $pdo->prepare(
            'UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id'
        );
        try {
            $update->execute(['name' => $name, 'email' => $email, 'id' => $userId]);
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            $user['name']  = $name;
            $user['email'] = $email;
            setFlash('success', 'Profile updated successfully.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $ex) {
            error_log('Profile update failed: ' . $ex->getMessage());
            $profileErrors[] = 'Something went wrong. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    requireValidCsrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $pwStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
    $pwStmt->execute(['id' => $userId]);
    $currentHash = $pwStmt->fetchColumn();

    if ($currentPassword === '' || !password_verify($currentPassword, $currentHash)) {
        $passwordErrors[] = 'Current password is incorrect.';
    }

    if ($newPassword === '') {
        $passwordErrors[] = 'New password is required.';
    } elseif (mb_strlen($newPassword) < MIN_PASSWORD_LENGTH) {
        $passwordErrors[] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    }

    if ($confirmPassword !== $newPassword) {
        $passwordErrors[] = 'Passwords do not match.';
    }

    if (empty($passwordErrors)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
        try {
            $update->execute(['password' => $newHash, 'id' => $userId]);
            setFlash('success', 'Password updated successfully.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $ex) {
            error_log('Password update failed: ' . $ex->getMessage());
            $passwordErrors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Profile';
require_once __DIR__ . '/includes/header.php';
?>
<div class="form-page">
    <h1 class="form-page__title">Your profile</h1>
    <p class="form-page__subtitle">Member since <?= formatDate($user['created_at'], 'F j, Y') ?></p>

    <div class="profile-grid">
        <div class="card-form">
            <h2 class="card-form__heading">Account details</h2>

            <?php if ($profileErrors): ?>
                <div class="alert alert--error">
                    <ul>
                        <?php foreach ($profileErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="profile.php" method="post" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="form" value="profile">

                <div class="form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" maxlength="<?= MAX_NAME_LENGTH ?>"
                           value="<?= e($oldProfile['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" maxlength="<?= MAX_EMAIL_LENGTH ?>"
                           value="<?= e($oldProfile['email']) ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="card-form">
            <h2 class="card-form__heading">Change password</h2>

            <?php if ($passwordErrors): ?>
                <div class="alert alert--error">
                    <ul>
                        <?php foreach ($passwordErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="profile.php" method="post" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="form" value="password">

                <div class="form-group">
                    <label for="current_password">Current password</label>
                    <div class="password-field">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="password-toggle" data-target="current_password" aria-label="Show password">&#128065;</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">New password</label>
                    <div class="password-field">
                        <input type="password" id="new_password" name="new_password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
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

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
