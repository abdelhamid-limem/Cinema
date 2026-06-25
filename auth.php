<?php
// auth.php — session helpers

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: index.php');
        exit();
    }
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isModerator(): bool {
    return in_array($_SESSION['role'] ?? '', ['admin', 'moderator'], true);
}

function csrf(): string {
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}

function verifyCsrf(): bool {
    return hash_equals($_SESSION['token'] ?? '', $_POST['token'] ?? '');
}

function refreshCsrf(): void {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
