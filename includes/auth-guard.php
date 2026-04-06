<?php
// ============================================================
//  RaktSetu — Auth Guard
//  Include at top of any protected page
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';

// ── Auto-login via remember-me cookie ───────────────────────
if (!isset($_SESSION['user_id']) && isset($_COOKIE[COOKIE_NAME])) {
    $token = $_COOKIE[COOKIE_NAME];
    $stmt  = getDB()->prepare(
        "SELECT id, name, email, role, blood_type, city, is_verified, is_eligible
         FROM users WHERE remember_token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_id']    = $u['id'];
        $_SESSION['user_name']  = $u['name'];
        $_SESSION['user_email'] = $u['email'];
        $_SESSION['user_role']  = $u['role'];
        $_SESSION['blood_type'] = $u['blood_type'];
        $_SESSION['city']       = $u['city'];
        $_SESSION['verified']   = $u['is_verified'];
        $_SESSION['eligible']   = $u['is_eligible'];
    }
}

// ── Guard functions ──────────────────────────────────────────

/**
 * Require any logged-in user.
 * Redirects to login page if not authenticated.
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/auth.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Require a specific role (or array of roles).
 * @param string|array $roles
 */
function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'], $roles)) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Returns true if a user is currently logged in.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Returns the logged-in user's role, or null.
 */
function userRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}
