<?php
// Auth guard for admin pages
require_once __DIR__ . '/config.php';

function requireLogin() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $isAdminDir = strpos($script, '/admin/') !== false;

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $loginUrl = $isAdminDir ? 'login.php' : 'login.php';
        header("Location: $loginUrl");
        exit;
    }

    // Restrict admin area to admins only
    if ($isAdminDir && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
        header('Location: ../index.php');
        exit;
    }
}

function getCurrentUser() {
    return $_SESSION['username'] ?? 'user';
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}
