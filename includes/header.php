<?php
// ============================================================
//  RaktSetu — Header Include
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth-guard.php';

// Auto-login via cookie handled inside auth-guard.php

// Unread alerts count for logged-in user
$alertCount = 0;
if (isLoggedIn()) {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM alerts WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $alertCount = (int)$stmt->fetchColumn();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <div class="nav-inner">
        <!-- Logo -->
        <a href="<?= APP_URL ?>/index.php" class="nav-logo">
            <span class="logo-drop">🩸</span>
            <span class="logo-text">RaktSetu</span>
        </a>

        <!-- Desktop links -->
        <ul class="nav-links">
            <li><a href="<?= APP_URL ?>/index.php"
                   class="<?= $currentPage === 'index.php'    ? 'active' : '' ?>">Home</a></li>
            <li><a href="<?= APP_URL ?>/requests.php"
                   class="<?= $currentPage === 'requests.php' ? 'active' : '' ?>">Live Requests</a></li>
            <li><a href="<?= APP_URL ?>/donor-search.php"
                   class="<?= $currentPage === 'donor-search.php' ? 'active' : '' ?>">Find Donors</a></li>

            <?php if (isLoggedIn()): ?>
                <?php if (userRole() === 'hospital_staff'): ?>
                    <li><a href="<?= APP_URL ?>/hospital.php"
                           class="<?= $currentPage === 'hospital.php' ? 'active' : '' ?>">Hospital Panel</a></li>
                <?php endif; ?>
                <?php if (userRole() === 'admin'): ?>
                    <li><a href="<?= APP_URL ?>/admin.php"
                           class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>">Admin</a></li>
                <?php endif; ?>
            <?php endif; ?>

            <li><a href="<?= APP_URL ?>/about.php"
                   class="<?= $currentPage === 'about.php' ? 'active' : '' ?>">About</a></li>
            <li><a href="<?= APP_URL ?>/contact.php"
                   class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">Contact</a></li>
        </ul>

        <!-- Right actions -->
        <div class="nav-actions">
            <?php if (isLoggedIn()): ?>
                <a href="<?= APP_URL ?>/post-request.php" class="btn btn-sm btn-outline">+ Request Blood</a>

                <!-- Alerts bell -->
                <a href="<?= APP_URL ?>/donor-dashboard.php#alerts" class="nav-bell" aria-label="Alerts">
                    🔔
                    <?php if ($alertCount > 0): ?>
                        <span class="bell-badge"><?= $alertCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- User menu -->
                <div class="nav-user" id="userMenu">
                    <button class="user-trigger" onclick="toggleUserMenu()">
                        <span class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></span>
                        <span class="user-name"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? '')[0]) ?></span>
                        <span class="caret">▾</span>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong>
                            <span class="bt-pill"><?= htmlspecialchars($_SESSION['blood_type'] ?? '') ?></span>
                        </div>
                        <a href="<?= APP_URL ?>/donor-dashboard.php">My Dashboard</a>
                        <a href="<?= APP_URL ?>/auth.php?action=logout" class="logout-link">Sign Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= APP_URL ?>/auth.php" class="btn btn-sm btn-outline">Sign In</a>
                <a href="<?= APP_URL ?>/auth.php?tab=register" class="btn btn-sm btn-red">Become a Donor</a>
            <?php endif; ?>

            <!-- Mobile hamburger -->
            <button class="hamburger" onclick="toggleMobileNav()" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <!-- Mobile nav -->
    <div class="mobile-nav" id="mobileNav">
        <a href="<?= APP_URL ?>/index.php">Home</a>
        <a href="<?= APP_URL ?>/requests.php">Live Requests</a>
        <a href="<?= APP_URL ?>/donor-search.php">Find Donors</a>
        <a href="<?= APP_URL ?>/about.php">About</a>
        <a href="<?= APP_URL ?>/contact.php">Contact</a>
        <?php if (isLoggedIn()): ?>
            <a href="<?= APP_URL ?>/donor-dashboard.php">My Dashboard</a>
            <a href="<?= APP_URL ?>/auth.php?action=logout">Sign Out</a>
        <?php else: ?>
            <a href="<?= APP_URL ?>/auth.php">Sign In</a>
            <a href="<?= APP_URL ?>/auth.php?tab=register">Become a Donor</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Flash messages -->
<?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $f): ?>
        <div class="flash flash-<?= $f['type'] ?>" role="alert">
            <?= htmlspecialchars($f['msg']) ?>
            <button onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
