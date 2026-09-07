<?php
/**
 * Shared header. Expects $pageTitle to optionally be set before include.
 */
$pageTitle = $pageTitle ?? 'Task Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | Task Manager</title>
<link rel="stylesheet" href="assets/css/style.css">
<script>
// Set the theme before the page paints, so there's no flash of the
// wrong theme. Falls back to the OS-level preference on first visit.
(function () {
    try {
        var saved = localStorage.getItem('theme');
        var theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    } catch (e) {
        // localStorage may be unavailable (private browsing, etc.) — default to light.
    }
})();
</script>
</head>
<body>
<?php if (isLoggedIn()): ?>
<header class="app-header">
    <div class="app-header__inner">
        <a href="dashboard.php" class="brand">
            <span class="brand__icon">&#10003;</span>
            <span class="brand__name">TaskFlow</span>
        </a>
        <nav class="app-nav">
            <span class="app-nav__user">Hi, <?= e(currentUserName()) ?></span>
            <a href="profile.php" class="app-nav__link">Profile</a>
            <form action="logout.php" method="post" class="app-nav__logout-form">
                <?= csrfField() ?>
                <button type="submit" class="app-nav__link app-nav__link--btn">Logout</button>
            </form>
        </nav>
        <button class="app-nav__toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>
    </div>
</header>
<?php endif; ?>
<main class="app-main">
<?php
$flashes = getFlashes();
if ($flashes): ?>
    <div class="flash-container">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
